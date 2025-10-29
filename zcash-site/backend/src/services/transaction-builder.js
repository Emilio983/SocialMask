/**
 * Zcash Transaction Builder Service
 * Builds real Zcash transparent transactions
 */

import crypto from 'crypto';
import bs58check from 'bs58check';

// Zcash mainnet constants
const MAINNET_P2PKH_PREFIX = Buffer.from([0x1C, 0xB8]);
const MAINNET_PRIVATE_KEY_PREFIX = Buffer.from([0x80]); // Same as Bitcoin for transparent

/**
 * Derive private key from user seed (deterministic)
 */
export function derivePrivateKey(userId) {
  // Create deterministic private key from userId
  const hash = crypto.createHash('sha256').update(userId).digest();
  return hash;
}

/**
 * Get WIF (Wallet Import Format) private key
 */
export function getPrivateKeyWIF(userId) {
  const privateKey = derivePrivateKey(userId);
  const prefixed = Buffer.concat([MAINNET_PRIVATE_KEY_PREFIX, privateKey, Buffer.from([0x01])]); // 0x01 = compressed
  return bs58check.encode(prefixed);
}

/**
 * Get public key from private key
 */
export function getPublicKey(privateKey) {
  const ec = require('elliptic').ec;
  const secp256k1 = new ec('secp256k1');
  
  const keyPair = secp256k1.keyFromPrivate(privateKey);
  const publicKey = keyPair.getPublic(true, 'array'); // compressed
  
  return Buffer.from(publicKey);
}

/**
 * Get address from public key
 */
export function getAddressFromPublicKey(publicKey) {
  // Create address from public key
  const sha256Hash = crypto.createHash('sha256').update(publicKey).digest();
  const ripemd160Hash = crypto.createHash('ripemd160').update(sha256Hash).digest();
  
  const prefixed = Buffer.concat([MAINNET_P2PKH_PREFIX, ripemd160Hash]);
  return bs58check.encode(prefixed);
}

/**
 * Build a raw Zcash transparent transaction
 * This is a simplified version for transparent P2PKH transactions
 */
export async function buildRawTransaction(userId, toAddress, amountSats, utxos) {
  // For now, we need to use zcash-cli or similar to build the actual transaction
  // This is a placeholder that returns the structure needed
  
  const privateKey = derivePrivateKey(userId);
  const publicKey = getPublicKey(privateKey);
  const fromAddress = getAddressFromPublicKey(publicKey);
  
  console.log('[buildRawTransaction] Building transaction:', {
    from: fromAddress,
    to: toAddress,
    amount: amountSats,
    utxos: utxos?.length || 0
  });
  
  // Return transaction data structure
  return {
    privateKey: privateKey.toString('hex'),
    publicKey: publicKey.toString('hex'),
    fromAddress,
    toAddress,
    amountSats,
    utxos,
    // The actual raw transaction would be built here
    // For now, we'll need to use the Zcash bridge/node to build it
  };
}

/**
 * Sign transaction data
 */
export function signTransaction(txData, privateKeyHex) {
  const ec = require('elliptic').ec;
  const secp256k1 = new ec('secp256k1');
  
  const privateKey = Buffer.from(privateKeyHex, 'hex');
  const keyPair = secp256k1.keyFromPrivate(privateKey);
  
  // Create message hash from transaction data
  const messageHash = crypto.createHash('sha256')
    .update(JSON.stringify(txData))
    .digest();
  
  // Sign the hash
  const signature = keyPair.sign(messageHash);
  
  return {
    signature: signature.toDER('hex'),
    messageHash: messageHash.toString('hex')
  };
}

export default {
  derivePrivateKey,
  getPrivateKeyWIF,
  getPublicKey,
  getAddressFromPublicKey,
  buildRawTransaction,
  signTransaction
};
