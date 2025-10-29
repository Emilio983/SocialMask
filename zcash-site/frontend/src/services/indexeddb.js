import { openDB } from 'idb';

const DB_NAME = 'zcash-wallet';
const DB_VERSION = 2; // Incremented for migration support

let db = null;
let dbInitPromise = null;
let dbReady = false;

// Initialize IndexedDB with proper error handling and migration support
export async function initDB() {
  // Return existing promise if initialization is in progress
  if (dbInitPromise) {
    return dbInitPromise;
  }

  // Return existing db if already initialized
  if (db && dbReady) {
    return db;
  }

  dbInitPromise = (async () => {
    try {
      db = await openDB(DB_NAME, DB_VERSION, {
        upgrade(db, oldVersion, newVersion, transaction) {
          console.log(`Upgrading IndexedDB from version ${oldVersion} to ${newVersion}`);

          // Store for user data
          if (!db.objectStoreNames.contains('users')) {
            db.createObjectStore('users', { keyPath: 'userId' });
          }

          // Store for device credentials (added in v2)
          if (!db.objectStoreNames.contains('device_credentials')) {
            db.createObjectStore('device_credentials', { keyPath: 'credentialId' });
          }

          // Store for wallet data (spending key, UA, etc.)
          if (!db.objectStoreNames.contains('wallets')) {
            db.createObjectStore('wallets', { keyPath: 'userId' });
          }

          // Store for transaction cache (renamed from 'transactions' to 'tx_cache')
          if (!db.objectStoreNames.contains('tx_cache')) {
            // Migrate old 'transactions' store if it exists
            if (db.objectStoreNames.contains('transactions')) {
              db.deleteObjectStore('transactions');
            }
            const txStore = db.createObjectStore('tx_cache', {
              keyPath: 'id',
              autoIncrement: true,
            });
            txStore.createIndex('userId', 'userId', { unique: false });
            txStore.createIndex('txid', 'txid', { unique: false });
          }

          // Store for sync state
          if (!db.objectStoreNames.contains('sync')) {
            db.createObjectStore('sync', { keyPath: 'userId' });
          }

          // Store for encrypted key backups
          if (!db.objectStoreNames.contains('backups')) {
            db.createObjectStore('backups', { keyPath: 'userId' });
          }
        },
        blocked() {
          console.warn('IndexedDB upgrade blocked by another tab. Please close other tabs.');
        },
        blocking() {
          console.warn('This tab is blocking an IndexedDB upgrade. Database will close.');
          if (db) {
            db.close();
            db = null;
            dbReady = false;
          }
        },
        terminated() {
          console.error('IndexedDB connection was unexpectedly terminated');
          db = null;
          dbReady = false;
        },
      });

      dbReady = true;
      console.log('IndexedDB initialized successfully');
      return db;
    } catch (error) {
      console.error('Failed to initialize IndexedDB:', error);
      dbReady = false;
      db = null;
      throw error;
    } finally {
      dbInitPromise = null;
    }
  })();

  return dbInitPromise;
}

// Check if DB is ready
export function isDBReady() {
  return dbReady && db !== null;
}

// Get DB instance (throws if not initialized)
function getDB() {
  if (!db || !dbReady) {
    throw new Error('IndexedDB not initialized. Call initDB() first.');
  }
  return db;
}

// Reset local data (for corrupted DB recovery)
export async function resetLocalData() {
  try {
    // Close existing connection
    if (db) {
      db.close();
      db = null;
      dbReady = false;
      dbInitPromise = null;
    }

    // Delete the database
    await new Promise((resolve, reject) => {
      const request = indexedDB.deleteDatabase(DB_NAME);
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
      request.onblocked = () => {
        console.warn('Database deletion blocked. Please close all tabs using this site.');
        reject(new Error('Database deletion blocked'));
      };
    });

    // Clear localStorage as well
    localStorage.clear();

    console.log('Local data reset successfully');
    return true;
  } catch (error) {
    console.error('Failed to reset local data:', error);
    throw error;
  }
}

// User operations
export async function saveUser(user) {
  const database = getDB();
  return database.put('users', user);
}

export async function getUser() {
  const database = getDB();
  const users = await database.getAll('users');
  return users[0] || null;
}

export async function clearUser() {
  const database = getDB();
  const tx = database.transaction(['users'], 'readwrite');
  await tx.store.clear();
  await tx.done;
}

// Wallet operations
export async function saveWallet(wallet) {
  const database = getDB();
  return database.put('wallets', wallet);
}

export async function getWallet(userId) {
  const database = getDB();
  return database.get('wallets', userId);
}

// Transaction operations
export async function saveTransaction(tx) {
  const database = getDB();
  return database.add('tx_cache', tx);
}

export async function getTransactions(userId) {
  const database = getDB();
  return database.getAllFromIndex('tx_cache', 'userId', userId);
}

// Sync state operations
export async function saveSyncState(syncState) {
  const database = getDB();
  return database.put('sync', syncState);
}

export async function getSyncState(userId) {
  const database = getDB();
  return database.get('sync', userId);
}

// Backup operations
export async function saveBackup(backup) {
  const database = getDB();
  return database.put('backups', backup);
}

export async function getBackup(userId) {
  const database = getDB();
  return database.get('backups', userId);
}

// Clear all data (for logout/reset)
export async function clearAllData() {
  const database = getDB();
  const storeNames = ['users', 'wallets', 'tx_cache', 'sync', 'backups', 'device_credentials'];
  const tx = database.transaction(storeNames, 'readwrite');

  await Promise.all([
    tx.objectStore('users').clear(),
    tx.objectStore('wallets').clear(),
    tx.objectStore('tx_cache').clear(),
    tx.objectStore('sync').clear(),
    tx.objectStore('backups').clear(),
    tx.objectStore('device_credentials').clear(),
  ]);

  await tx.done;
}
