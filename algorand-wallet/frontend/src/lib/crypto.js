/**
 * WebCrypto utilities for encrypting/decrypting mnemonics
 * Uses AES-GCM with password-derived keys
 */

const ALGO_CONFIG = {
  name: 'AES-GCM',
  length: 256,
};

const PBKDF2_CONFIG = {
  name: 'PBKDF2',
  iterations: 100000,
  hash: 'SHA-256',
};

/**
 * Generate a random salt
 */
export function generateSalt() {
  return crypto.getRandomValues(new Uint8Array(16));
}

/**
 * Generate a random IV for AES-GCM
 */
export function generateIV() {
  return crypto.getRandomValues(new Uint8Array(12));
}

/**
 * Derive encryption key from password using PBKDF2
 */
export async function deriveKey(password, salt) {
  const encoder = new TextEncoder();
  const passwordKey = await crypto.subtle.importKey(
    'raw',
    encoder.encode(password),
    'PBKDF2',
    false,
    ['deriveKey']
  );

  return crypto.subtle.deriveKey(
    {
      ...PBKDF2_CONFIG,
      salt,
    },
    passwordKey,
    ALGO_CONFIG,
    false,
    ['encrypt', 'decrypt']
  );
}

/**
 * Encrypt data using AES-GCM
 */
export async function encryptData(plaintext, key, iv) {
  const encoder = new TextEncoder();
  const encrypted = await crypto.subtle.encrypt(
    {
      name: 'AES-GCM',
      iv,
    },
    key,
    encoder.encode(plaintext)
  );

  return new Uint8Array(encrypted);
}

/**
 * Decrypt data using AES-GCM
 */
export async function decryptData(ciphertext, key, iv) {
  const decrypted = await crypto.subtle.decrypt(
    {
      name: 'AES-GCM',
      iv,
    },
    key,
    ciphertext
  );

  const decoder = new TextDecoder();
  return decoder.decode(decrypted);
}

/**
 * Convert Uint8Array to base64
 */
export function arrayToBase64(array) {
  return btoa(String.fromCharCode.apply(null, array));
}

/**
 * Convert base64 to Uint8Array
 */
export function base64ToArray(base64) {
  const binary = atob(base64);
  const array = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    array[i] = binary.charCodeAt(i);
  }
  return array;
}

/**
 * Encrypt mnemonic and return encrypted package
 */
export async function encryptMnemonic(mnemonic, password) {
  const salt = generateSalt();
  const iv = generateIV();
  const key = await deriveKey(password, salt);
  const encrypted = await encryptData(mnemonic, key, iv);

  return {
    encrypted: arrayToBase64(encrypted),
    salt: arrayToBase64(salt),
    iv: arrayToBase64(iv),
  };
}

/**
 * Decrypt mnemonic from encrypted package
 */
export async function decryptMnemonic(encryptedPackage, password) {
  const { encrypted, salt, iv } = encryptedPackage;
  const key = await deriveKey(password, base64ToArray(salt));
  const decrypted = await decryptData(
    base64ToArray(encrypted),
    key,
    base64ToArray(iv)
  );

  return decrypted;
}

/**
 * Generate random password for encryption (derived from passkey)
 */
export function generateRandomPassword() {
  const array = new Uint8Array(32);
  crypto.getRandomValues(array);
  return arrayToBase64(array);
}

/**
 * Derive deterministic password from passkey credential ID
 * This allows re-unlocking the wallet with the same passkey
 */
export async function derivePasswordFromCredential(credentialId) {
  const encoder = new TextEncoder();
  const data = encoder.encode(credentialId);
  
  // Hash the credential ID to get deterministic password
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  const hashArray = new Uint8Array(hashBuffer);
  
  return arrayToBase64(hashArray);
}
