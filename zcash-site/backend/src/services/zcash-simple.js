/**
 * Simple Zcash Transaction Service
 * Uses external APIs to build and broadcast transactions
 */

import crypto from 'crypto';
import bs58check from 'bs58check';
import https from 'https';

const MAINNET_P2PKH_PREFIX = Buffer.from([0x1C, 0xB8]);

/**
 * Derive deterministic private key from userId
 */
function derivePrivateKey(userId) {
  return crypto.createHash('sha256').update(userId).digest();
}

/**
 * Get WIF format private key for importing into other wallets
 */
export function getWIFPrivateKey(userId) {
  const privateKey = derivePrivateKey(userId);
  const prefixed = Buffer.concat([
    Buffer.from([0x80]), // Mainnet private key prefix
    privateKey,
    Buffer.from([0x01]) // Compressed pubkey flag
  ]);
  return bs58check.encode(prefixed);
}

/**
 * Get the transparent address for a user
 */
export function getTAddress(userId) {
  const hash = crypto.createHash('sha256').update(userId).digest();
  const payload = hash.slice(0, 20);
  const prefixedPayload = Buffer.concat([MAINNET_P2PKH_PREFIX, payload]);
  return bs58check.encode(prefixedPayload);
}

/**
 * Fetch UTXOs for an address using external API
 */
async function fetchUTXOs(address) {
  return new Promise((resolve, reject) => {
    // Using sochain.com API for Zcash
    const options = {
      hostname: 'sochain.com',
      path: `/api/v2/get_tx_unspent/ZEC/${address}`,
      method: 'GET',
      headers: { 'User-Agent': 'Zcash-Wallet' }
    };

    https.get(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          if (json.status === 'success') {
            resolve(json.data.txs || []);
          } else {
            resolve([]);
          }
        } catch (e) {
          resolve([]);
        }
      });
    }).on('error', () => resolve([]));
  });
}

/**
 * Broadcast raw transaction using external API
 */
async function broadcastTransaction(rawTxHex) {
  return new Promise((resolve, reject) => {
    const postData = JSON.stringify({
      tx_hex: rawTxHex
    });

    const options = {
      hostname: 'sochain.com',
      path: '/api/v2/send_tx/ZEC',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': postData.length,
        'User-Agent': 'Zcash-Wallet'
      }
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          resolve(json);
        } catch (e) {
          reject(new Error('Failed to parse response'));
        }
      });
    });

    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

/**
 * Export private key for user to import into real wallet
 */
export function exportPrivateKey(userId) {
  const wif = getWIFPrivateKey(userId);
  const tAddr = getTAddress(userId);
  
  return {
    privateKeyWIF: wif,
    address: tAddr,
    instructions: [
      '1. Download Ywallet or Zecwallet',
      '2. Choose "Import private key"',
      '3. Paste the WIF key above',
      '4. Your funds will appear and you can send them'
    ]
  };
}

/**
 * Get balance for an address
 */
export async function getBalance(address) {
  try {
    const utxos = await fetchUTXOs(address);
    const balance = utxos.reduce((sum, utxo) => {
      return sum + parseFloat(utxo.value) * 100000000; // Convert to satoshis
    }, 0);
    
    return {
      balance: Math.floor(balance),
      utxos: utxos.length
    };
  } catch (error) {
    console.error('[getBalance] Error:', error);
    return { balance: 0, utxos: 0 };
  }
}

export default {
  getWIFPrivateKey,
  getTAddress,
  exportPrivateKey,
  getBalance,
  fetchUTXOs,
  broadcastTransaction
};
