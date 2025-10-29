import React, { useState } from 'react';
import { Fingerprint, Loader2, Shield, AlertTriangle, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { registerPasskey, authenticatePasskey, isWebAuthnSupported, hasStoredCredentials, storeCredentialInfo } from '../lib/webauthn';
import { generateAccount } from '../lib/algorand';
import { encryptMnemonic, generateRandomPassword } from '../lib/crypto';
import { storeEncryptedWallet, getEncryptedWallet, clearWalletData } from '../lib/storage';

export default function AuthPage({ onAuthenticated }) {
  const [loading, setLoading] = useState(false);
  const [username, setUsername] = useState('');
  const [mode, setMode] = useState('create'); // 'create' or 'unlock'
  const [showResetConfirm, setShowResetConfirm] = useState(false);

  const hasExistingWallet = getEncryptedWallet() !== null;

  const handleCreateWallet = async (e) => {
    e.preventDefault();

    if (!isWebAuthnSupported()) {
      toast.error('WebAuthn is not supported in this browser');
      return;
    }

    if (!username.trim()) {
      toast.error('Please enter a username');
      return;
    }

    setLoading(true);
    try {
      // Register passkey
      toast.info('Creating passkey...');
      const credential = await registerPasskey(username);
      storeCredentialInfo(credential);

      // Generate Algorand account
      toast.info('Generating wallet...');
      const account = generateAccount();

      // Derive encryption password deterministically from credential ID
      const { derivePasswordFromCredential } = await import('../lib/crypto');
      const encryptionPassword = await derivePasswordFromCredential(credential.id);

      // Encrypt mnemonic
      const encryptedData = await encryptMnemonic(account.mnemonic, encryptionPassword);

      // Store encrypted wallet
      storeEncryptedWallet(encryptedData, account.address);

      // Store encryption password in session for current session
      sessionStorage.setItem('wallet_key', encryptionPassword);

      toast.success('Wallet created successfully!');
      
      onAuthenticated({
        address: account.address,
        mnemonic: account.mnemonic,
      });
    } catch (error) {
      console.error('Create wallet error:', error);
      toast.error(`Failed to create wallet: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleUnlockWallet = async () => {
    if (!isWebAuthnSupported()) {
      toast.error('WebAuthn is not supported in this browser');
      return;
    }

    setLoading(true);
    try {
      // Authenticate with passkey
      toast.info('Authenticating with passkey...');
      const authResult = await authenticatePasskey();

      // Get encrypted wallet
      const encryptedWallet = getEncryptedWallet();
      if (!encryptedWallet) {
        toast.error('No wallet found. Please create a new wallet.');
        setLoading(false);
        return;
      }

      // Try to decrypt with derived password (new system)
      toast.info('Decrypting wallet...');
      const { derivePasswordFromCredential, decryptMnemonic } = await import('../lib/crypto');
      const derivedPassword = await derivePasswordFromCredential(authResult.id);
      
      let mnemonic = null;
      let needsMigration = false;
      
      try {
        // Try with derived password first
        mnemonic = await decryptMnemonic(encryptedWallet, derivedPassword);
      } catch (decryptError) {
        // Derived password failed, try with session storage (old system)
        console.log('Derived password failed, trying legacy session password...');
        const legacyPassword = sessionStorage.getItem('wallet_key');
        
        if (legacyPassword) {
          try {
            mnemonic = await decryptMnemonic(encryptedWallet, legacyPassword);
            needsMigration = true;
            toast.info('Wallet opened with legacy session. Migration recommended.');
          } catch (legacyError) {
            throw new Error('Cannot decrypt wallet with either method');
          }
        } else {
          throw new Error('Wallet was created with old version. Please create a new wallet or restore from backup.');
        }
      }

      if (!mnemonic) {
        throw new Error('Failed to decrypt wallet');
      }

      // Restore account
      const { restoreAccountFromMnemonic } = await import('../lib/algorand');
      const account = restoreAccountFromMnemonic(mnemonic);

      // If needs migration, re-encrypt with derived password
      if (needsMigration) {
        toast.info('Migrating wallet to new system...');
        const { encryptMnemonic } = await import('../lib/crypto');
        const newEncryptedData = await encryptMnemonic(mnemonic, derivedPassword);
        storeEncryptedWallet(newEncryptedData, account.address);
        toast.success('Wallet migrated to new secure system!');
      }

      // Store password in session for current session
      sessionStorage.setItem('wallet_key', derivedPassword);

      // Update session start time
      const { updateSessionStart } = await import('../lib/storage');
      updateSessionStart();

      toast.success('Wallet unlocked successfully!');
      
      onAuthenticated({
        address: account.address,
        mnemonic: mnemonic,
      });
    } catch (error) {
      console.error('Unlock wallet error:', error);
      
      // Provide helpful error messages
      if (error.message.includes('decrypt') || error.message.includes('old version')) {
        toast.error(
          <div className="text-sm">
            <p className="font-semibold mb-1">Cannot unlock this wallet</p>
            <p>This wallet was created before the security update.</p>
            <p className="mt-1">Options:</p>
            <p>• Create a new wallet</p>
            <p>• Restore from backup if you have one</p>
          </div>,
          { duration: 10000 }
        );
      } else if (error.name === 'NotAllowedError') {
        toast.error('Authentication cancelled or failed.');
      } else {
        toast.error(`Failed to unlock: ${error.message}`, { duration: 6000 });
      }
    } finally {
      setLoading(false);
    }
  };

  const handleResetWallet = () => {
    if (!showResetConfirm) {
      setShowResetConfirm(true);
      setTimeout(() => setShowResetConfirm(false), 5000);
      toast.warning('Click again to confirm wallet reset', { duration: 5000 });
      return;
    }

    try {
      clearWalletData();
      sessionStorage.clear();
      toast.success('Wallet data cleared. You can create a new wallet now.');
      setShowResetConfirm(false);
      setMode('create');
      window.location.reload();
    } catch (error) {
      console.error('Reset error:', error);
      toast.error('Failed to reset wallet');
    }
  };

  return (
    <div className="min-h-screen bg-gray-900 flex items-center justify-center p-4">
      <div className="max-w-md w-full">
        {/* Warning Banner */}
        <div className="bg-yellow-900/30 border border-yellow-700 rounded-lg p-4 mb-6">
          <div className="flex items-start gap-3">
            <AlertTriangle className="text-yellow-400 flex-shrink-0 mt-0.5" size={20} />
            <div>
              <p className="text-yellow-200 font-semibold text-sm">⚠️ Mainnet Live Demo</p>
              <p className="text-yellow-300 text-xs mt-1">
                This wallet operates on Algorand Mainnet with real funds. Use small amounts only for testing.
                Network fees apply (~0.001 ALGO per transaction).
              </p>
            </div>
          </div>
        </div>

        {/* Main Card */}
        <div className="bg-gray-800 rounded-lg shadow-xl p-8 border border-gray-700">
          <div className="text-center mb-6">
            <div className="bg-primary/10 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <Shield className="text-primary" size={32} />
            </div>
            <h1 className="text-2xl font-bold text-white mb-2">
              Algorand Passkey Wallet
            </h1>
            <p className="text-gray-400 text-sm">
              Self-custody wallet secured with biometric authentication
            </p>
          </div>

          {/* Mode Selection */}
          {hasExistingWallet && (
            <div className="flex gap-2 mb-6">
              <button
                onClick={() => setMode('create')}
                className={`flex-1 py-2 px-4 rounded transition ${
                  mode === 'create'
                    ? 'bg-primary text-white'
                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                }`}
              >
                Create New
              </button>
              <button
                onClick={() => setMode('unlock')}
                className={`flex-1 py-2 px-4 rounded transition ${
                  mode === 'unlock'
                    ? 'bg-primary text-white'
                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                }`}
              >
                Unlock Existing
              </button>
            </div>
          )}

          {mode === 'create' ? (
            <form onSubmit={handleCreateWallet} className="space-y-4">
              <div>
                <label className="block text-sm text-gray-400 mb-2">Username</label>
                <input
                  type="text"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Enter a username"
                  className="w-full bg-gray-700 text-white px-4 py-3 rounded border border-gray-600 focus:border-primary focus:outline-none"
                  required
                  disabled={loading}
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? (
                  <>
                    <Loader2 size={20} className="animate-spin" />
                    Creating Wallet...
                  </>
                ) : (
                  <>
                    <Fingerprint size={20} />
                    Create Wallet with Passkey
                  </>
                )}
              </button>
            </form>
          ) : (
            <div className="space-y-4">
              <p className="text-gray-300 text-sm text-center mb-4">
                Use your passkey to unlock your existing wallet
              </p>

              <button
                onClick={handleUnlockWallet}
                disabled={loading}
                className="w-full bg-secondary hover:bg-green-600 text-white font-semibold py-3 rounded transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? (
                  <>
                    <Loader2 size={20} className="animate-spin" />
                    Unlocking...
                  </>
                ) : (
                  <>
                    <Fingerprint size={20} />
                    Unlock with Passkey
                  </>
                )}
              </button>

              {/* Reset Wallet Button */}
              <div className="pt-4 border-t border-gray-700">
                <p className="text-gray-400 text-xs text-center mb-3">
                  Having trouble unlocking? Reset and start fresh.
                </p>
                <button
                  onClick={handleResetWallet}
                  disabled={loading}
                  className={`w-full py-2 px-4 rounded transition text-sm font-medium flex items-center justify-center gap-2 ${
                    showResetConfirm
                      ? 'bg-red-600 hover:bg-red-700 text-white'
                      : 'bg-gray-700 hover:bg-gray-600 text-gray-300'
                  }`}
                >
                  <Trash2 size={16} />
                  {showResetConfirm ? 'Click again to confirm' : 'Reset Wallet Data'}
                </button>
                {showResetConfirm && (
                  <p className="text-red-400 text-xs text-center mt-2">
                    ⚠️ This will delete your stored wallet. Make sure you have a backup!
                  </p>
                )}
              </div>
            </div>
          )}

          {/* Info */}
          <div className="mt-6 pt-6 border-t border-gray-700">
            <p className="text-gray-400 text-xs text-center">
              Your private keys are encrypted and stored locally.
              <br />
              Passkeys use biometric authentication (Face ID, Touch ID, Windows Hello).
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="text-center mt-6">
          <p className="text-gray-500 text-xs">
            Powered by Algorand Mainnet • AlgoNode API
          </p>
        </div>
      </div>
    </div>
  );
}
