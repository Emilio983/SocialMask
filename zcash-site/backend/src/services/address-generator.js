/**
 * Zcash Address Generator
 * Generates valid Zcash transparent addresses (t-addr) for mainnet
 */

import crypto from 'crypto';
import bs58check from 'bs58check';

// Zcash mainnet address prefixes
// t1... = P2PKH (starts with [1C, B8] in hex)
// t3... = P2SH (starts with [1C, BD] in hex)
const MAINNET_P2PKH_PREFIX = Buffer.from([0x1C, 0xB8]);
const MAINNET_P2SH_PREFIX = Buffer.from([0x1C, 0xBD]);

/**
 * Generate a deterministic Zcash transparent address from a seed
 * @param {string} seed - User ID or other unique identifier
 * @param {boolean} isP2SH - If true, generates P2SH (t3...), otherwise P2PKH (t1...)
 * @returns {string} Valid Zcash mainnet t-address
 */
export function generateTAddress(seed, isP2SH = false) {
  // Create a deterministic hash from the seed
  const hash = crypto.createHash('sha256').update(seed).digest();

  // Take first 20 bytes for the address payload (RIPEMD160 size)
  const payload = hash.slice(0, 20);

  // Choose prefix based on address type
  const prefix = isP2SH ? MAINNET_P2SH_PREFIX : MAINNET_P2PKH_PREFIX;

  // Combine prefix + payload
  const prefixedPayload = Buffer.concat([prefix, payload]);

  // Encode with Base58Check (includes checksum)
  const address = bs58check.encode(prefixedPayload);

  return address;
}

/**
 * Generate a random Zcash transparent address
 * @param {boolean} isP2SH - If true, generates P2SH (t3...), otherwise P2PKH (t1...)
 * @returns {string} Valid Zcash mainnet t-address
 */
export function generateRandomTAddress(isP2SH = false) {
  // Generate random 20 bytes
  const payload = crypto.randomBytes(20);

  // Choose prefix based on address type
  const prefix = isP2SH ? MAINNET_P2SH_PREFIX : MAINNET_P2PKH_PREFIX;

  // Combine prefix + payload
  const prefixedPayload = Buffer.concat([prefix, payload]);

  // Encode with Base58Check (includes checksum)
  const address = bs58check.encode(prefixedPayload);

  return address;
}

/**
 * Validate a Zcash transparent address (supports t1, t3, and tex1 formats)
 * @param {string} address - Address to validate
 * @returns {boolean} True if valid mainnet t-address
 */
export function validateTAddress(address) {
  // Handle tex1... addresses (Transparent Extended - Bech32 format)
  if (address.startsWith('tex1')) {
    // Basic validation for tex1 addresses
    // Length should be around 42-44 characters for Bech32
    if (address.length < 40 || address.length > 90) {
      return false;
    }
    // Check if it only contains valid Bech32 characters (lowercase letters and numbers)
    const bech32Regex = /^tex1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]+$/;
    return bech32Regex.test(address);
  }

  // Handle traditional t1/t3 addresses (Base58Check format)
  try {
    // Decode Base58Check (will throw if checksum is invalid)
    const decoded = bs58check.decode(address);

    // Check payload length (prefix 2 bytes + payload 20 bytes)
    if (decoded.length !== 22) {
      return false;
    }

    // Check if it's a mainnet address (starts with correct prefix)
    const prefix = decoded.slice(0, 2);
    const isP2PKH = prefix[0] === 0x1C && prefix[1] === 0xB8;
    const isP2SH = prefix[0] === 0x1C && prefix[1] === 0xBD;

    return isP2PKH || isP2SH;
  } catch (error) {
    return false;
  }
}

/**
 * Generate a complete wallet address set for a user
 * @param {string} userId - Unique user identifier
 * @returns {Object} Address set with t-addr and UA
 */
export function generateWalletAddresses(userId) {
  // Generate deterministic t-addr from userId
  const tAddr = generateTAddress(userId);

  // For now, UA is still a placeholder (requires full Zcash WASM implementation)
  // Format: u1... for mainnet unified addresses
  const uaHash = crypto.createHash('sha256').update(`ua-${userId}`).digest('hex');
  const ua = 'u1' + uaHash.substring(0, 70);

  return {
    tAddr,
    ua,
    isValid: validateTAddress(tAddr)
  };
}

export default {
  generateTAddress,
  generateRandomTAddress,
  validateTAddress,
  generateWalletAddresses
};
