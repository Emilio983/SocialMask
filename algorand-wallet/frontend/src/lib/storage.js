/**
 * Local storage utilities for wallet data
 * All sensitive data is encrypted before storage
 */

const STORAGE_KEYS = {
  ENCRYPTED_WALLET: 'algo_encrypted_wallet',
  WALLET_ADDRESS: 'algo_wallet_address',
  SESSION_START: 'algo_session_start',
  PASSKEY_CREDENTIAL: 'passkeyCredential',
};

/**
 * Store encrypted wallet data
 */
export function storeEncryptedWallet(encryptedData, address) {
  localStorage.setItem(STORAGE_KEYS.ENCRYPTED_WALLET, JSON.stringify(encryptedData));
  localStorage.setItem(STORAGE_KEYS.WALLET_ADDRESS, address);
  localStorage.setItem(STORAGE_KEYS.SESSION_START, Date.now().toString());
}

/**
 * Get encrypted wallet data
 */
export function getEncryptedWallet() {
  const data = localStorage.getItem(STORAGE_KEYS.ENCRYPTED_WALLET);
  return data ? JSON.parse(data) : null;
}

/**
 * Get wallet address
 */
export function getWalletAddress() {
  return localStorage.getItem(STORAGE_KEYS.WALLET_ADDRESS);
}

/**
 * Check if wallet exists
 */
export function hasWallet() {
  return !!getEncryptedWallet();
}

/**
 * Clear all wallet data
 */
export function clearWalletData() {
  localStorage.removeItem(STORAGE_KEYS.ENCRYPTED_WALLET);
  localStorage.removeItem(STORAGE_KEYS.WALLET_ADDRESS);
  localStorage.removeItem(STORAGE_KEYS.SESSION_START);
}

/**
 * Get session start time
 */
export function getSessionStart() {
  const start = localStorage.getItem(STORAGE_KEYS.SESSION_START);
  return start ? parseInt(start) : null;
}

/**
 * Update session start time
 */
export function updateSessionStart() {
  localStorage.setItem(STORAGE_KEYS.SESSION_START, Date.now().toString());
}

/**
 * Check if session is expired
 */
export function isSessionExpired(timeoutMinutes = 15) {
  const start = getSessionStart();
  if (!start) return true;
  
  const elapsed = Date.now() - start;
  const timeoutMs = timeoutMinutes * 60 * 1000;
  
  return elapsed > timeoutMs;
}

/**
 * Export wallet backup (encrypted)
 */
export function exportWalletBackup() {
  const encryptedWallet = getEncryptedWallet();
  const address = getWalletAddress();
  
  if (!encryptedWallet || !address) {
    throw new Error('No wallet to export');
  }
  
  const backup = {
    version: '1.0',
    address,
    encrypted: encryptedWallet,
    exportedAt: new Date().toISOString(),
  };
  
  return JSON.stringify(backup, null, 2);
}

/**
 * Import wallet backup
 */
export function importWalletBackup(backupJson) {
  try {
    const backup = JSON.parse(backupJson);
    
    if (!backup.version || !backup.address || !backup.encrypted) {
      throw new Error('Invalid backup format');
    }
    
    return {
      address: backup.address,
      encrypted: backup.encrypted,
    };
  } catch (error) {
    console.error('Error importing backup:', error);
    throw new Error('Invalid backup file');
  }
}
