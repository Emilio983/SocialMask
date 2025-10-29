/**
 * Algorand SDK utilities for Mainnet operations
 */

import algosdk from 'algosdk';

// Mainnet configuration
const ALGOD_SERVER = import.meta.env.VITE_ALGOD_BASE_URL || 'https://mainnet-api.algonode.cloud';
const ALGOD_TOKEN = import.meta.env.VITE_ALGOD_TOKEN || '';
const INDEXER_SERVER = import.meta.env.VITE_INDEXER_BASE_URL || 'https://mainnet-idx.algonode.cloud';
const INDEXER_TOKEN = import.meta.env.VITE_INDEXER_TOKEN || '';

/**
 * Get Algod client for Mainnet
 */
export function getAlgodClient() {
  return new algosdk.Algodv2(ALGOD_TOKEN, ALGOD_SERVER, '');
}

/**
 * Get Indexer client for Mainnet
 */
export function getIndexerClient() {
  return new algosdk.Indexer(INDEXER_TOKEN, INDEXER_SERVER, '');
}

/**
 * Generate a new Algorand account
 */
export function generateAccount() {
  const account = algosdk.generateAccount();
  return {
    address: account.addr,
    mnemonic: algosdk.secretKeyToMnemonic(account.sk),
  };
}

/**
 * Restore account from mnemonic
 */
export function restoreAccountFromMnemonic(mnemonic) {
  try {
    const account = algosdk.mnemonicToSecretKey(mnemonic);
    return {
      address: account.addr,
      secretKey: account.sk,
    };
  } catch (error) {
    console.error('Error restoring account:', error);
    throw new Error('Invalid mnemonic');
  }
}

/**
 * Get account information
 */
export async function getAccountInfo(address) {
  const algodClient = getAlgodClient();
  try {
    const accountInfo = await algodClient.accountInformation(address).do();
    return accountInfo;
  } catch (error) {
    console.error('Error getting account info:', error);
    throw error;
  }
}

/**
 * Get account balance in ALGO
 */
export async function getAccountBalance(address) {
  const accountInfo = await getAccountInfo(address);
  return accountInfo.amount / 1_000_000; // Convert microAlgos to ALGO
}

/**
 * Get account assets (ASAs)
 */
export async function getAccountAssets(address) {
  const accountInfo = await getAccountInfo(address);
  return accountInfo.assets || [];
}

/**
 * Get suggested transaction parameters
 */
export async function getSuggestedParams() {
  const algodClient = getAlgodClient();
  try {
    const params = await algodClient.getTransactionParams().do();
    return params;
  } catch (error) {
    console.error('Error getting suggested params:', error);
    throw error;
  }
}

/**
 * Send ALGO payment transaction
 */
export async function sendAlgoPayment(fromAddress, toAddress, amount, mnemonic, note = '') {
  try {
    // Validate inputs
    if (!mnemonic || typeof mnemonic !== 'string') {
      throw new Error('Invalid mnemonic');
    }
    
    if (!isValidAddress(fromAddress)) {
      throw new Error('Invalid sender address');
    }
    
    if (!isValidAddress(toAddress)) {
      throw new Error('Invalid recipient address');
    }
    
    if (isNaN(amount) || amount <= 0) {
      throw new Error('Invalid amount');
    }

    const algodClient = getAlgodClient();
    
    // Restore account
    let account;
    try {
      account = algosdk.mnemonicToSecretKey(mnemonic);
    } catch (err) {
      throw new Error('Failed to restore account from mnemonic');
    }

    // Get suggested params
    let params;
    try {
      params = await getSuggestedParams();
    } catch (err) {
      throw new Error('Failed to get network parameters. Check your internet connection.');
    }

    // Create payment transaction
    const amountInMicroAlgos = Math.floor(amount * 1_000_000);
    const noteBytes = note ? new TextEncoder().encode(note) : undefined;

    let txn;
    try {
      txn = algosdk.makePaymentTxnWithSuggestedParamsFromObject({
        from: fromAddress,
        to: toAddress,
        amount: amountInMicroAlgos,
        note: noteBytes,
        suggestedParams: params,
      });
    } catch (err) {
      throw new Error('Failed to create transaction: ' + err.message);
    }

    // Sign transaction
    let signedTxn;
    try {
      signedTxn = txn.signTxn(account.sk);
    } catch (err) {
      throw new Error('Failed to sign transaction');
    }

    // Send transaction
    let txId;
    try {
      const result = await algodClient.sendRawTransaction(signedTxn).do();
      txId = result.txId;
    } catch (err) {
      if (err.message.includes('overspend')) {
        throw new Error('Insufficient funds (including 0.001 ALGO fee)');
      }
      throw new Error('Failed to send transaction: ' + (err.message || 'Unknown error'));
    }
    
    // Wait for confirmation
    let confirmation;
    try {
      confirmation = await algosdk.waitForConfirmation(algodClient, txId, 4);
    } catch (err) {
      // Transaction was sent but confirmation failed - this is OK
      console.warn('Transaction sent but confirmation timeout:', err);
      confirmation = { confirmedRound: 'pending' };
    }
    
    return {
      txId,
      confirmation,
    };
  } catch (error) {
    console.error('Send ALGO error:', error);
    throw error;
  }
}

/**
 * Opt-in to an ASA
 */
export async function optInToAsset(address, assetId, mnemonic) {
  try {
    // Validate inputs
    if (!mnemonic || typeof mnemonic !== 'string') {
      throw new Error('Invalid mnemonic');
    }
    
    if (!isValidAddress(address)) {
      throw new Error('Invalid address');
    }
    
    const assetIdNum = parseInt(assetId);
    if (isNaN(assetIdNum) || assetIdNum <= 0) {
      throw new Error('Invalid asset ID');
    }

    const algodClient = getAlgodClient();
    
    // Restore account
    let account;
    try {
      account = algosdk.mnemonicToSecretKey(mnemonic);
    } catch (err) {
      throw new Error('Failed to restore account from mnemonic');
    }

    // Get params
    let params;
    try {
      params = await getSuggestedParams();
    } catch (err) {
      throw new Error('Failed to get network parameters. Check your internet connection.');
    }

    // Create asset opt-in transaction (amount = 0, to self)
    let txn;
    try {
      txn = algosdk.makeAssetTransferTxnWithSuggestedParamsFromObject({
        from: address,
        to: address,
        assetIndex: assetIdNum,
        amount: 0,
        suggestedParams: params,
      });
    } catch (err) {
      throw new Error('Failed to create opt-in transaction: ' + err.message);
    }

    // Sign and send
    let signedTxn;
    try {
      signedTxn = txn.signTxn(account.sk);
    } catch (err) {
      throw new Error('Failed to sign transaction');
    }

    let txId;
    try {
      const result = await algodClient.sendRawTransaction(signedTxn).do();
      txId = result.txId;
    } catch (err) {
      if (err.message.includes('overspend')) {
        throw new Error('Insufficient funds (need 0.001 ALGO for fee + 0.1 ALGO for min balance)');
      }
      if (err.message.includes('already opted in')) {
        throw new Error('Already opted in to this asset');
      }
      throw new Error('Failed to send opt-in: ' + (err.message || 'Unknown error'));
    }
    
    // Wait for confirmation
    let confirmation;
    try {
      confirmation = await algosdk.waitForConfirmation(algodClient, txId, 4);
    } catch (err) {
      console.warn('Opt-in sent but confirmation timeout:', err);
      confirmation = { confirmedRound: 'pending' };
    }
    
    return {
      txId,
      confirmation,
    };
  } catch (error) {
    console.error('Opt-in error:', error);
    throw error;
  }
}

/**
 * Send ASA transfer transaction
 */
export async function sendAssetTransfer(fromAddress, toAddress, assetId, amount, mnemonic, note = '') {
  try {
    // Validate inputs
    if (!mnemonic || typeof mnemonic !== 'string') {
      throw new Error('Invalid mnemonic');
    }
    
    if (!isValidAddress(fromAddress)) {
      throw new Error('Invalid sender address');
    }
    
    if (!isValidAddress(toAddress)) {
      throw new Error('Invalid recipient address');
    }
    
    const assetIdNum = parseInt(assetId);
    if (isNaN(assetIdNum) || assetIdNum <= 0) {
      throw new Error('Invalid asset ID');
    }
    
    const amountNum = parseInt(amount);
    if (isNaN(amountNum) || amountNum <= 0) {
      throw new Error('Invalid amount');
    }

    const algodClient = getAlgodClient();
    
    // Restore account
    let account;
    try {
      account = algosdk.mnemonicToSecretKey(mnemonic);
    } catch (err) {
      throw new Error('Failed to restore account from mnemonic');
    }

    // Get params
    let params;
    try {
      params = await getSuggestedParams();
    } catch (err) {
      throw new Error('Failed to get network parameters. Check your internet connection.');
    }

    const noteBytes = note ? new TextEncoder().encode(note) : undefined;

    // Create transaction
    let txn;
    try {
      txn = algosdk.makeAssetTransferTxnWithSuggestedParamsFromObject({
        from: fromAddress,
        to: toAddress,
        assetIndex: assetIdNum,
        amount: amountNum,
        note: noteBytes,
        suggestedParams: params,
      });
    } catch (err) {
      throw new Error('Failed to create transaction: ' + err.message);
    }

    // Sign
    let signedTxn;
    try {
      signedTxn = txn.signTxn(account.sk);
    } catch (err) {
      throw new Error('Failed to sign transaction');
    }

    // Send
    let txId;
    try {
      const result = await algodClient.sendRawTransaction(signedTxn).do();
      txId = result.txId;
    } catch (err) {
      if (err.message.includes('overspend')) {
        throw new Error('Insufficient funds (need 0.001 ALGO for fee)');
      }
      if (err.message.includes('not opted in')) {
        throw new Error('Recipient has not opted in to this asset');
      }
      if (err.message.includes('insufficient balance')) {
        throw new Error('Insufficient asset balance');
      }
      throw new Error('Failed to send asset: ' + (err.message || 'Unknown error'));
    }
    
    // Wait for confirmation
    let confirmation;
    try {
      confirmation = await algosdk.waitForConfirmation(algodClient, txId, 4);
    } catch (err) {
      console.warn('Asset transfer sent but confirmation timeout:', err);
      confirmation = { confirmedRound: 'pending' };
    }
    
    return {
      txId,
      confirmation,
    };
  } catch (error) {
    console.error('Asset transfer error:', error);
    throw error;
  }
}

/**
 * Get asset information
 */
export async function getAssetInfo(assetId) {
  const algodClient = getAlgodClient();
  try {
    const assetInfo = await algodClient.getAssetByID(parseInt(assetId)).do();
    return assetInfo;
  } catch (error) {
    console.error('Error getting asset info:', error);
    throw error;
  }
}

/**
 * Get recent transactions for an address
 */
export async function getRecentTransactions(address, limit = 10) {
  const indexerClient = getIndexerClient();
  try {
    const response = await indexerClient
      .searchForTransactions()
      .address(address)
      .limit(limit)
      .do();
    
    return response.transactions || [];
  } catch (error) {
    console.error('Error getting transactions:', error);
    throw error;
  }
}

/**
 * Validate Algorand address
 */
export function isValidAddress(address) {
  return algosdk.isValidAddress(address);
}

/**
 * Get explorer URL for transaction
 */
export function getExplorerTxUrl(txId) {
  const explorerBase = import.meta.env.VITE_EXPLORER_BASE_URL || 'https://algoexplorer.io';
  return `${explorerBase}/tx/${txId}`;
}

/**
 * Get explorer URL for address
 */
export function getExplorerAddressUrl(address) {
  const explorerBase = import.meta.env.VITE_EXPLORER_BASE_URL || 'https://algoexplorer.io';
  return `${explorerBase}/address/${address}`;
}

/**
 * Format amount with proper decimals
 */
export function formatAmount(amount, decimals = 6) {
  return (amount / Math.pow(10, decimals)).toFixed(decimals);
}
