import React, { useState, useEffect } from 'react';
import { Toaster } from 'sonner';
import AuthPage from './pages/AuthPage';
import DashboardPage from './pages/DashboardPage';
import { getWalletAddress, hasWallet } from './lib/storage';

function App() {
  const [wallet, setWallet] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check if there's an active session
    const sessionKey = sessionStorage.getItem('wallet_key');
    const walletAddress = getWalletAddress();
    
    if (sessionKey && walletAddress && hasWallet()) {
      // Session exists, but we need the mnemonic
      // User will need to unlock with passkey
      setIsLoading(false);
    } else {
      setIsLoading(false);
    }
  }, []);

  const handleAuthenticated = (walletData) => {
    setWallet(walletData);
  };

  const handleLogout = () => {
    setWallet(null);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-900 flex items-center justify-center">
        <div className="text-white">Loading...</div>
      </div>
    );
  }

  return (
    <>
      <Toaster 
        position="top-right" 
        theme="dark"
        richColors
        closeButton
      />
      
      {wallet ? (
        <DashboardPage wallet={wallet} onLogout={handleLogout} />
      ) : (
        <AuthPage onAuthenticated={handleAuthenticated} />
      )}
    </>
  );
}

export default App;
