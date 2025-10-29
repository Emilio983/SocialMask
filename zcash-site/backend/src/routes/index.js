import express from 'express';
import crypto from 'crypto';
import { txLimiter } from '../middleware/security.js';
import { pool } from '../models/db.js';
import { getLatestBlock, submitTransaction, getTransaction, getAddressBalance } from '../services/bridge-client.js';
import { generateWalletAddresses, validateTAddress } from '../services/address-generator.js';
import { exportPrivateKey, getBalance as getSimpleBalance } from '../services/zcash-simple.js';
import { buildTransaction as buildZcashTx, broadcastTransaction, getAddressFromUserId } from '../services/zcash-tx-builder.js';
import { sendZcash } from '../services/zcash-tx-real.js';

const router = express.Router();

// POST /api/auth/email-login - Login with email and password
router.post('/auth/email-login', async (req, res, next) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({ error: 'Missing email or password' });
    }

    // Hash the provided password
    const passwordHash = crypto.createHash('sha256').update(password).digest('hex');

    // Find user
    const result = await pool.query(
      'SELECT id FROM users WHERE email = $1 AND password_hash = $2',
      [email, passwordHash]
    );

    if (result.rows.length === 0) {
      return res.status(401).json({ success: false, error: 'Invalid credentials' });
    }

    const userId = result.rows[0].id;

    // Get wallet address
    const walletResult = await pool.query(
      'SELECT ua FROM wallets WHERE user_id = $1',
      [userId]
    );

    // Generate t-address
    const addresses = generateWalletAddresses(userId);

    res.json({
      success: true,
      userId,
      walletAddress: addresses.tAddr,
      ua: walletResult.rows[0]?.ua
    });

  } catch (error) {
    console.error('[email-login] Error:', error);
    res.status(500).json({ success: false, error: 'Login failed' });
  }
});

// GET /api/blocks/head - Get current blockchain head
router.get('/blocks/head', async (req, res, next) => {
  try {
    const blockInfo = await getLatestBlock();
    res.json(blockInfo);
  } catch (error) {
    console.error('Error fetching latest block:', error);
    next(error);
  }
});

// POST /api/tx/submit - Submit a signed transaction
router.post('/tx/submit', txLimiter, async (req, res, next) => {
  try {
    const { rawTxHex, userId, metadata } = req.body;

    if (!rawTxHex || !userId) {
      return res.status(400).json({ error: 'Missing required fields: rawTxHex, userId' });
    }

    // Validate hex format
    if (!/^[0-9a-fA-F]+$/.test(rawTxHex)) {
      return res.status(400).json({ error: 'Invalid rawTxHex format' });
    }

    // Submit to bridge (which forwards to lightwalletd)
    const txResult = await submitTransaction(rawTxHex);

    if (!txResult.success) {
      return res.status(400).json({
        success: false,
        error: txResult.error || 'Transaction rejected by network',
      });
    }

    // Log transaction to database
    const result = await pool.query(
      `INSERT INTO tx_log (user_id, txid, direction, amount_zats, to_addr, memo, status)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id, txid, status, created_at`,
      [
        userId,
        txResult.txid,
        metadata?.direction || 'outgoing',
        metadata?.amount_zats || 0,
        metadata?.to_addr || null,
        metadata?.memo || null,
        'pending',
      ]
    );

    res.json({
      success: true,
      txid: txResult.txid,
      status: 'pending',
      ...result.rows[0],
    });
  } catch (error) {
    next(error);
  }
});

// GET /api/tx/:txid - Get transaction status
router.get('/tx/:txid', async (req, res, next) => {
  try {
    const { txid } = req.params;

    const result = await pool.query(
      'SELECT * FROM tx_log WHERE txid = $1',
      [txid]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Transaction not found' });
    }

    res.json(result.rows[0]);
  } catch (error) {
    next(error);
  }
});

// GET /api/tx/user/:userId - Get user transaction history
router.get('/tx/user/:userId', async (req, res, next) => {
  try {
    const { userId } = req.params;
    const { limit = 50, offset = 0 } = req.query;

    const result = await pool.query(
      `SELECT * FROM tx_log
       WHERE user_id = $1
       ORDER BY created_at DESC
       LIMIT $2 OFFSET $3`,
      [userId, limit, offset]
    );

    res.json({
      transactions: result.rows,
      total: result.rowCount,
    });
  } catch (error) {
    next(error);
  }
});

// POST /api/users - Create new user
router.post('/users', async (req, res, next) => {
  try {
    const result = await pool.query(
      'INSERT INTO users DEFAULT VALUES RETURNING id, created_at'
    );

    res.status(201).json(result.rows[0]);
  } catch (error) {
    next(error);
  }
});

// POST /api/credentials - Register device credential
router.post('/credentials', async (req, res, next) => {
  try {
    const { userId, credentialId, publicKey, deviceName } = req.body;

    if (!userId || !credentialId || !publicKey) {
      return res.status(400).json({
        error: 'Missing required fields: userId, credentialId, publicKey'
      });
    }

    const result = await pool.query(
      `INSERT INTO device_credentials (user_id, credential_id, public_key, device_name)
       VALUES ($1, $2, $3, $4)
       RETURNING id, created_at`,
      [userId, credentialId, publicKey, deviceName || null]
    );

    res.status(201).json(result.rows[0]);
  } catch (error) {
    if (error.code === '23505') { // Unique violation
      return res.status(409).json({ error: 'Credential already registered' });
    }
    next(error);
  }
});

// GET /api/credentials/:credentialId - Get credential by ID
router.get('/credentials/:credentialId', async (req, res, next) => {
  try {
    const { credentialId } = req.params;

    const result = await pool.query(
      'SELECT * FROM device_credentials WHERE credential_id = $1',
      [credentialId]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Credential not found' });
    }

    res.json(result.rows[0]);
  } catch (error) {
    next(error);
  }
});

// POST /api/addresses/generate - Generate valid Zcash addresses
router.post('/addresses/generate', async (req, res, next) => {
  try {
    const { userId } = req.body;

    if (!userId) {
      return res.status(400).json({ error: 'Missing required field: userId' });
    }

    const addresses = generateWalletAddresses(userId);

    res.json({
      tAddr: addresses.tAddr,
      ua: addresses.ua,
      isValid: addresses.isValid
    });
  } catch (error) {
    next(error);
  }
});

// POST /api/wallets - Create/update wallet
router.post('/wallets', async (req, res, next) => {
  try {
    const { userId, ua, spendingKeyEnc, ivkEnc, ovkEnc } = req.body;

    if (!userId || !ua) {
      return res.status(400).json({ error: 'Missing required fields: userId, ua' });
    }

    const result = await pool.query(
      `INSERT INTO wallets (user_id, ua, spending_key_enc, ivk_enc, ovk_enc, backup_uploaded)
       VALUES ($1, $2, $3, $4, $5, $6)
       ON CONFLICT (user_id) DO UPDATE
       SET ua = EXCLUDED.ua,
           spending_key_enc = COALESCE(EXCLUDED.spending_key_enc, wallets.spending_key_enc),
           ivk_enc = COALESCE(EXCLUDED.ivk_enc, wallets.ivk_enc),
           ovk_enc = COALESCE(EXCLUDED.ovk_enc, wallets.ovk_enc),
           backup_uploaded = CASE WHEN EXCLUDED.spending_key_enc IS NOT NULL THEN true ELSE wallets.backup_uploaded END
       RETURNING *`,
      [
        userId,
        ua,
        spendingKeyEnc || null,
        ivkEnc || null,
        ovkEnc || null,
        spendingKeyEnc ? true : false,
      ]
    );

    res.json(result.rows[0]);
  } catch (error) {
    next(error);
  }
});

// GET /api/wallets/:userId - Get wallet by user ID
router.get('/wallets/:userId', async (req, res, next) => {
  try {
    const { userId } = req.params;

    const result = await pool.query(
      'SELECT * FROM wallets WHERE user_id = $1',
      [userId]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Wallet not found' });
    }

    res.json(result.rows[0]);
  } catch (error) {
    next(error);
  }
});

// GET /api/balance/:address - Get address balance
router.get('/balance/:address', async (req, res, next) => {
  try {
    const { address } = req.params;

    // Validate address using proper checksum validation
    if (!validateTAddress(address)) {
      return res.status(400).json({ error: 'Invalid transparent address' });
    }

    const balanceInfo = await getAddressBalance(address);
    res.json(balanceInfo);
  } catch (error) {
    console.error('Error fetching balance:', error);
    next(error);
  }
});

// GET /api/wallet/get-utxos-from-txid/:txid/:address - Get UTXOs from a specific transaction
router.get('/wallet/get-utxos-from-txid/:txid/:address', async (req, res, next) => {
  try {
    const { txid, address } = req.params;
    
    console.log('[get-utxos-from-txid] Fetching UTXOs:', { txid, address });
    
    // Try multiple APIs to get transaction data
    const apis = [
      `https://api.zcha.in/v2/mainnet/transactions/${txid}`,
      `https://zcashblockexplorer.com/api/tx/${txid}`,
      `https://explorer.zcha.in/api/v1/mainnet/tx/${txid}`
    ];
    
    let txData = null;
    
    for (const apiUrl of apis) {
      try {
        const response = await fetch(apiUrl, { timeout: 10000 });
        if (response.ok) {
          txData = await response.json();
          if (txData) {
            console.log('[get-utxos-from-txid] Got data from:', apiUrl);
            break;
          }
        }
      } catch (e) {
        console.log('[get-utxos-from-txid] Failed:', apiUrl, e.message);
        continue;
      }
    }
    
    if (!txData) {
      return res.status(404).json({ 
        error: 'Transaction not found in any explorer',
        suggestion: 'Please provide UTXO manually using /api/wallet/send-manual'
      });
    }
    
    // Extract UTXOs for this address
    const utxos = [];
    if (txData.outputs || txData.vout) {
      const outputs = txData.outputs || txData.vout;
      outputs.forEach((output, index) => {
        const outputAddress = output.address || output.scriptPubKey?.addresses?.[0];
        if (outputAddress === address) {
          utxos.push({
            txid: txid,
            vout: index,
            value: output.value || output.satoshis || Math.floor(output.valueZat || 0),
            address: address
          });
        }
      });
    }
    
    res.json({
      success: true,
      txid,
      address,
      utxos,
      message: utxos.length > 0 ? `Found ${utxos.length} UTXO(s)` : 'No UTXOs found for this address in this transaction'
    });
    
  } catch (error) {
    console.error('[get-utxos-from-txid] Error:', error);
    res.status(500).json({ error: error.message });
  }
});

// POST /api/wallet/send-manual - Send ZEC using manually provided UTXO
router.post('/wallet/send-manual', txLimiter, async (req, res, next) => {
  try {
    const { userId, toAddress, amountZEC, utxos } = req.body;

    if (!userId || !toAddress || !amountZEC) {
      return res.status(400).json({ error: 'Missing required fields: userId, toAddress, amountZEC' });
    }

    if (!utxos || !Array.isArray(utxos) || utxos.length === 0) {
      return res.status(400).json({ 
        error: 'Missing UTXOs. Please provide: [{ txid: "...", vout: 0, value: 3300000 }]',
        example: {
          utxos: [
            { txid: "your_binance_transaction_id", vout: 0, value: 3300000 }
          ]
        }
      });
    }

    // Convert ZEC to satoshis
    const amountSats = Math.floor(parseFloat(amountZEC) * 100000000);
    
    if (isNaN(amountSats) || amountSats <= 0) {
      return res.status(400).json({ error: 'Invalid amount' });
    }

    console.log('[send-manual] Sending with manual UTXOs:', {
      userId: userId.substring(0, 8) + '...',
      toAddress,
      amountZEC,
      amountSats,
      utxosProvided: utxos.length
    });

    // Import the manual send function
    const { sendZcashManual } = await import('../services/zcash-tx-real.js');
    
    // Send the transaction
    const result = await sendZcashManual(userId, toAddress, amountSats, utxos);
    
    if (result.success) {
      // Log to database
      try {
        await pool.query(
          `INSERT INTO tx_log (user_id, txid, direction, amount_zats, to_addr, status)
           VALUES ($1, $2, $3, $4, $5, $6)`,
          [userId, result.txid, 'outgoing', amountSats, toAddress, 'confirmed']
        );
      } catch (dbError) {
        console.error('[send-manual] DB logging error:', dbError);
      }
      
      res.json({
        success: true,
        txid: result.txid,
        message: 'Transaction successfully broadcast!',
        explorer: `https://explorer.zcha.in/transactions/${result.txid}`
      });
    } else {
      throw new Error('Transaction broadcast failed');
    }
    
  } catch (error) {
    console.error('[send-manual] Error:', error);
    res.status(500).json({ 
      error: error.message,
      details: 'Failed to send transaction'
    });
  }
});

// POST /api/wallet/send-real - Send ZEC using real transaction building
router.post('/wallet/send-real', txLimiter, async (req, res, next) => {
  try {
    const { userId, toAddress, amountZEC, memo } = req.body;

    if (!userId || !toAddress || !amountZEC) {
      return res.status(400).json({ error: 'Missing required fields: userId, toAddress, amountZEC' });
    }

    // Convert ZEC to satoshis
    const amountSats = Math.floor(parseFloat(amountZEC) * 100000000);
    
    if (isNaN(amountSats) || amountSats <= 0) {
      return res.status(400).json({ error: 'Invalid amount' });
    }

    if (amountSats < 1000) {
      return res.status(400).json({ error: 'Amount too small (minimum 0.00001 ZEC)' });
    }

    console.log('[send-real] Sending ZEC:', {
      userId: userId.substring(0, 8) + '...',
      toAddress,
      amountZEC,
      amountSats
    });

    // Send the transaction using real implementation
    const result = await sendZcash(userId, toAddress, amountSats);
    
    if (result.success) {
      // Log to database
      try {
        await pool.query(
          `INSERT INTO tx_log (user_id, txid, direction, amount_zats, to_addr, memo, status)
           VALUES ($1, $2, $3, $4, $5, $6, $7)`,
          [userId, result.txid, 'outgoing', amountSats, toAddress, memo || null, 'confirmed']
        );
      } catch (dbError) {
        console.error('[send-real] DB logging error:', dbError);
        // Continue even if DB logging fails
      }
      
      res.json({
        success: true,
        txid: result.txid,
        message: 'Transaction successfully broadcast to Zcash network!',
        explorer: `https://explorer.zcha.in/transactions/${result.txid}`
      });
    } else {
      throw new Error('Transaction broadcast failed');
    }
    
  } catch (error) {
    console.error('[send-real] Error:', error);
    res.status(500).json({ 
      error: error.message,
      details: 'Failed to send transaction'
    });
  }
});

// POST /api/wallet/export-key - Export private key for recovery
router.post('/wallet/export-key', async (req, res, next) => {
  try {
    const { userId, confirmation } = req.body;

    if (!userId) {
      return res.status(400).json({ error: 'Missing required field: userId' });
    }

    // Security confirmation
    if (confirmation !== 'I_UNDERSTAND_THIS_IS_MY_PRIVATE_KEY') {
      return res.status(400).json({ 
        error: 'You must confirm you understand this exports your private key',
        requiredConfirmation: 'I_UNDERSTAND_THIS_IS_MY_PRIVATE_KEY'
      });
    }

    const keyData = exportPrivateKey(userId);
    
    res.json({
      success: true,
      ...keyData,
      warning: 'NEVER share this private key with anyone. Anyone with this key can spend your funds.'
    });
  } catch (error) {
    next(error);
  }
});

// GET /api/wallet/balance-simple/:address - Get balance using external API
router.get('/wallet/balance-simple/:address', async (req, res, next) => {
  try {
    const { address } = req.params;
    
    const balanceData = await getSimpleBalance(address);
    
    res.json(balanceData);
  } catch (error) {
    next(error);
  }
});

// All routes under /api
export const setupRoutes = (app) => {
  app.use('/api', router);
};

export default router;
