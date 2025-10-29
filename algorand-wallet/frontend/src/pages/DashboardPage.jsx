import React, { useState, useEffect } from 'react';
import { LogOut, Download, Trash2, AlertTriangle, CheckCircle } from 'lucide-react';
import { toast } from 'sonner';
import ReceiveCard from '../components/ReceiveCard';
import BalanceCard from '../components/BalanceCard';
import SendCard from '../components/SendCard';
import ActivityCard from '../components/ActivityCard';
import { getAccountBalance, getAccountAssets } from '../lib/algorand';
import { clearWalletData, exportWalletBackup, isSessionExpired, updateSessionStart } from '../lib/storage';

export default function DashboardPage({ wallet, onLogout }) {
  const [balance, setBalance] = useState(0);
  const [assets, setAssets] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showResetConfirm, setShowResetConfirm] = useState(false);

  const loadBalances = async () => {
    setLoading(true);
    try {
      const bal = await getAccountBalance(wallet.address);
      setBalance(bal);

      const assetList = await getAccountAssets(wallet.address);
      setAssets(assetList);
    } catch (error) {
      console.error('Error loading balances:', error);
      toast.error('Failed to load balances');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadBalances();
    
    // Check session timeout
    const interval = setInterval(() => {
      if (isSessionExpired(15)) {
        toast.error('Session expired. Please unlock your wallet again.');
        handleLogout();
      }
    }, 60000); // Check every minute

    return () => clearInterval(interval);
  }, [wallet.address]);

  const handleTransactionComplete = (result) => {
    toast.success(
      <div>
        <p className="font-semibold">Transaction Confirmed!</p>
        <a
          href={`https://algoexplorer.io/tx/${result.txId}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-blue-300 text-sm underline"
        >
          View on Explorer
        </a>
      </div>,
      { duration: 5000 }
    );
    
    // Refresh balances after transaction
    setTimeout(() => {
      loadBalances();
    }, 2000);
  };

  const handleExportBackup = () => {
    try {
      const backup = exportWalletBackup();
      const blob = new Blob([backup], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `algorand-wallet-backup-${Date.now()}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
      toast.success('Backup exported successfully');
    } catch (error) {
      console.error('Export error:', error);
      toast.error('Failed to export backup');
    }
  };

  const handleReset = () => {
    clearWalletData();
    sessionStorage.clear();
    toast.success('Wallet data cleared');
    onLogout();
  };

  const handleLogout = () => {
    sessionStorage.clear();
    onLogout();
  };

  return (
    <div className="min-h-screen bg-gray-900">
      {/* Header */}
      <header className="bg-gray-800 border-b border-gray-700">
        <div className="container mx-auto px-4 py-4">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-2xl font-bold text-white">Algorand Wallet</h1>
              <div className="flex items-center gap-2 mt-1">
                <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <p className="text-sm text-gray-400">Mainnet • Live Funds</p>
              </div>
            </div>
            <div className="flex gap-2">
              <button
                onClick={handleExportBackup}
                className="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition flex items-center gap-2"
                title="Export encrypted backup"
              >
                <Download size={18} />
                <span className="hidden sm:inline">Backup</span>
              </button>
              <button
                onClick={() => setShowResetConfirm(true)}
                className="bg-red-900/30 hover:bg-red-900/50 text-red-300 px-4 py-2 rounded transition flex items-center gap-2"
                title="Reset wallet (clear data)"
              >
                <Trash2 size={18} />
                <span className="hidden sm:inline">Reset</span>
              </button>
              <button
                onClick={handleLogout}
                className="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition flex items-center gap-2"
              >
                <LogOut size={18} />
                <span className="hidden sm:inline">Lock</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Warning Banner */}
      <div className="bg-yellow-900/20 border-b border-yellow-800">
        <div className="container mx-auto px-4 py-3">
          <div className="flex items-center gap-2 text-yellow-200 text-sm">
            <AlertTriangle size={16} />
            <p>
              <strong>Mainnet Demo:</strong> Real funds • Use small amounts • Network fees ~0.001 ALGO • 
              Always verify addresses before sending
            </p>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Left Column */}
          <div className="space-y-6">
            <ReceiveCard address={wallet.address} />
            <BalanceCard 
              balance={balance} 
              assets={assets} 
              loading={loading}
              onRefresh={loadBalances}
            />
          </div>

          {/* Right Column */}
          <div className="space-y-6">
            <SendCard 
              address={wallet.address} 
              mnemonic={wallet.mnemonic}
              onTransactionComplete={handleTransactionComplete}
            />
            <ActivityCard address={wallet.address} />
          </div>
        </div>

        {/* Binance Integration Guide */}
        <div className="mt-8 bg-gray-800 rounded-lg p-6 border border-gray-700">
          <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <CheckCircle className="text-green-500" size={20} />
            Binance Integration Guide
          </h3>
          <div className="grid md:grid-cols-2 gap-6 text-sm">
            <div>
              <h4 className="font-semibold text-white mb-2">📥 Deposit from Binance</h4>
              <ol className="text-gray-300 space-y-1 list-decimal list-inside">
                <li>Copy your wallet address above</li>
                <li>Go to Binance → Wallet → Withdraw</li>
                <li>Select ALGO (Algorand)</li>
                <li>Network: <strong>ALGO</strong></li>
                <li>Paste address and amount</li>
                <li>Confirm withdrawal</li>
                <li>Wait for confirmations (~4 blocks)</li>
              </ol>
            </div>
            <div>
              <h4 className="font-semibold text-white mb-2">📤 Withdraw to Binance</h4>
              <ol className="text-gray-300 space-y-1 list-decimal list-inside">
                <li>Get your Binance ALGO deposit address</li>
                <li>Go to Binance → Wallet → Deposit</li>
                <li>Select ALGO → Copy deposit address</li>
                <li>Use "Send ALGO" form above</li>
                <li>Paste Binance address as recipient</li>
                <li>Enter amount (min 0.1 ALGO recommended)</li>
                <li>Confirm and wait for TX confirmation</li>
              </ol>
            </div>
          </div>
          <div className="mt-4 bg-blue-900/20 border border-blue-800 rounded p-3">
            <p className="text-blue-200 text-xs">
              <strong>Note:</strong> Algorand has fast finality (~4 seconds per block). 
              Binance typically requires 15+ confirmations. Always verify network and address before sending.
            </p>
          </div>
        </div>
      </main>

      {/* Reset Confirmation Modal */}
      {showResetConfirm && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className="bg-gray-800 rounded-lg p-6 max-w-md w-full border border-red-700">
            <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2">
              <AlertTriangle className="text-red-500" />
              Reset Wallet?
            </h3>
            <p className="text-gray-300 mb-4">
              This will clear all wallet data from this device. Make sure you have backed up 
              your mnemonic phrase before proceeding.
            </p>
            <p className="text-red-300 text-sm mb-6 font-semibold">
              ⚠️ This action cannot be undone!
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowResetConfirm(false)}
                className="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-2 rounded transition"
              >
                Cancel
              </button>
              <button
                onClick={handleReset}
                className="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded transition"
              >
                Reset Wallet
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
