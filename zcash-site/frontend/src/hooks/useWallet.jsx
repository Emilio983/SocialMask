import { useState, useEffect, useCallback } from 'react';
import { getWallet, saveWallet, getSyncState, saveSyncState } from '../services/indexeddb';
import { API_BASE_URL } from '../utils/config';

export function useWallet(userId) {
  const [wallet, setWallet] = useState(null);
  const [balance, setBalance] = useState(0);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);

  // Initialize wallet
  useEffect(() => {
    if (!userId) return;

    async function initWallet() {
      setLoading(true);
      try {
        // Check if wallet exists locally
        let localWallet = await getWallet(userId);

        if (!localWallet) {
          // Generate valid Zcash addresses from backend
          const addressResponse = await fetch(`${API_BASE_URL}/addresses/generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId }),
          });

          if (!addressResponse.ok) {
            throw new Error('Failed to generate addresses');
          }

          const addresses = await addressResponse.json();

          // Verify addresses are valid
          if (!addresses.isValid) {
            throw new Error('Generated invalid addresses');
          }

          // Generate spending key (stub for MVP - in production use WASM)
          const spendingKey = 'stub-spending-key-' + userId;

          localWallet = {
            userId,
            spendingKey, // In production, this would be encrypted
            ua: addresses.ua,
            tAddr: addresses.tAddr, // Real valid transparent address
            createdAt: new Date().toISOString(),
          };

          await saveWallet(localWallet);

          // Register wallet with backend
          await fetch(`${API_BASE_URL}/wallets`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              userId,
              ua: addresses.ua,
              // In production, would upload encrypted spending key backup
            }),
          });
        }

        setWallet(localWallet);

        console.log('[useWallet] Wallet initialized:', {
          ua: localWallet.ua,
          tAddr: localWallet.tAddr,
          userId: localWallet.userId
        });

        // Load last known balance
        const syncState = await getSyncState(userId);
        if (syncState) {
          setBalance(syncState.balance || 0);
        }

        // Auto-sync balance on wallet load if t-addr exists
        if (localWallet.tAddr) {
          console.log('[useWallet] Auto-syncing balance for:', localWallet.tAddr);
          setTimeout(() => {
            syncBalance(localWallet.tAddr);
          }, 1000);
        }
      } catch (error) {
        console.error('Failed to initialize wallet:', error);
      } finally {
        setLoading(false);
      }
    }

    initWallet();
  }, [userId]);

  // Helper to sync balance from blockchain
  const syncBalance = useCallback(async (tAddr) => {
    try {
      console.log('[syncBalance] Fetching balance for:', tAddr);
      const response = await fetch(`${API_BASE_URL}/balance/${tAddr}`);

      if (response.ok) {
        const data = await response.json();
        const balanceZats = parseInt(data.balance) || 0;

        console.log('[syncBalance] Balance fetched:', balanceZats, 'zats');

        // Save sync state
        const newSyncState = {
          userId,
          balance: balanceZats,
          lastSyncedAt: new Date().toISOString(),
          utxos: data.utxos || []
        };

        await saveSyncState(newSyncState);
        setBalance(balanceZats);
      } else {
        console.error('[syncBalance] Failed to fetch balance:', response.statusText);
      }
    } catch (error) {
      console.error('[syncBalance] Error:', error);
    }
  }, [userId]);

  // Sync wallet with blockchain (called by user clicking Sync button)
  const sync = useCallback(async () => {
    if (!wallet || syncing) return;

    setSyncing(true);
    try {
      if (wallet.tAddr) {
        await syncBalance(wallet.tAddr);
      }
    } catch (error) {
      console.error('Sync failed:', error);
    } finally {
      setSyncing(false);
    }
  }, [wallet, syncing, syncBalance]);

  // Auto-sync balance every 30 seconds
  useEffect(() => {
    if (!wallet || !wallet.tAddr) return;

    console.log('[useWallet] Starting auto-sync interval (every 30 seconds)');

    const interval = setInterval(() => {
      if (!syncing) {
        console.log('[useWallet] Auto-syncing balance...');
        syncBalance(wallet.tAddr);
      }
    }, 30000); // 30 seconds

    return () => {
      console.log('[useWallet] Clearing auto-sync interval');
      clearInterval(interval);
    };
  }, [wallet, syncing, syncBalance]);

  return {
    wallet,
    balance,
    loading,
    syncing,
    sync,
  };
}
