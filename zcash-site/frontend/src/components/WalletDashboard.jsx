import { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import { useWallet } from '../hooks/useWallet';
import BalanceCard from './BalanceCard';
import ReceiveCard from './ReceiveCard';
import SendCard from './SendCard';
import TransactionList from './TransactionList';
import ExportKeyCard from './ExportKeyCard';
import ManualSendCard from './ManualSendCard';

export default function WalletDashboard() {
  const { user, logout } = useAuth();
  const { wallet, balance, loading, sync } = useWallet(user.userId);
  const [activeTab, setActiveTab] = useState('balance');

  useEffect(() => {
    if (wallet) {
      // Sync wallet on load
      sync();
    }
  }, [wallet, sync]);

  const handleLogout = async () => {
    if (confirm('Are you sure you want to logout?')) {
      await logout();
    }
  };

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <header className="border-b-2 border-black py-4 px-6">
        <div className="max-w-6xl mx-auto flex justify-between items-center">
          <div className="flex items-center gap-3">
            <div>
              <h1 className="text-2xl font-bold">Zcash Wallet</h1>
              <p className="text-xs text-gray-600">
                User: {user.userId.substring(0, 8)}...
              </p>
            </div>
            <span className="px-2 py-1 bg-yellow-400 text-black text-xs font-bold border-2 border-black">
              MAINNET
            </span>
          </div>
          <button
            onClick={handleLogout}
            className="px-4 py-2 border-2 border-black hover:bg-gray-50 transition-colors text-sm font-medium"
          >
            Logout
          </button>
        </div>
      </header>

      {/* Main content */}
      <main className="max-w-6xl mx-auto px-6 py-8">
        {loading && !wallet ? (
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-black"></div>
            <p className="mt-4 text-sm text-gray-600">Loading wallet...</p>
          </div>
        ) : !wallet ? (
          <div className="text-center py-12">
            <p className="text-gray-600">Initializing wallet...</p>
          </div>
        ) : (
          <>
            {/* Balance overview */}
            <BalanceCard balance={balance} loading={loading} onSync={sync} />

            {/* Tab navigation */}
            <div className="mt-8 border-b-2 border-gray-200">
              <nav className="flex space-x-6 overflow-x-auto">
                {['balance', 'receive', 'send', 'manual', 'history', 'export'].map((tab) => (
                  <button
                    key={tab}
                    onClick={() => setActiveTab(tab)}
                    className={`py-2 px-1 font-medium text-sm capitalize transition-all whitespace-nowrap
                      ${activeTab === tab
                        ? 'border-b-2 border-black text-black -mb-[2px]'
                        : 'text-gray-500 hover:text-black'
                      }`}
                  >
                    {tab === 'export' ? '🔑 Key' : tab === 'manual' ? '🚀 Quick Send' : tab}
                  </button>
                ))}
              </nav>
            </div>

            {/* Tab content */}
            <div className="mt-8">
              {activeTab === 'balance' && (
                <div className="text-center py-12">
                  <p className="text-sm text-gray-600">
                    Your balance is displayed above. Use the tabs to receive or send ZEC.
                  </p>
                  <p className="text-xs text-yellow-600 mt-4">
                    ⚠️ Note: To send funds, you need to export your private key and import it into a full Zcash wallet like Ywallet or Zecwallet.
                  </p>
                </div>
              )}

              {activeTab === 'receive' && (
                <ReceiveCard address={wallet.ua} transparentAddress={wallet.tAddr} />
              )}

              {activeTab === 'send' && (
                <SendCard userId={user.userId} balance={balance} />
              )}

              {activeTab === 'manual' && (
                <ManualSendCard userId={user.userId} walletAddress={wallet.tAddr} />
              )}

              {activeTab === 'history' && (
                <TransactionList userId={user.userId} />
              )}

              {activeTab === 'export' && (
                <ExportKeyCard userId={user.userId} />
              )}
            </div>
          </>
        )}
      </main>

      {/* Footer */}
      <footer className="border-t-2 border-gray-200 py-4 px-6 mt-12">
        <div className="max-w-6xl mx-auto text-center text-xs text-gray-500">
          <p>Mainnet • Non-custodial • Open source</p>
          <button
            onClick={() => {
              if (confirm('Reset all local data? This will log you out and delete all cached data.')) {
                import('../services/indexeddb').then(({ resetLocalData }) => {
                  resetLocalData().then(() => window.location.reload());
                });
              }
            }}
            className="mt-3 px-3 py-1 text-xs border border-gray-300 hover:bg-gray-50 transition-colors rounded"
          >
            Reset Local Data
          </button>
        </div>
      </footer>
    </div>
  );
}
