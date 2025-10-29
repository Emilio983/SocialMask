import React, { useEffect, useState } from 'react';
import { ExternalLink, RefreshCw, Clock } from 'lucide-react';
import { getRecentTransactions, getExplorerTxUrl } from '../lib/algorand';

export default function ActivityCard({ address }) {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(false);

  const loadTransactions = async () => {
    setLoading(true);
    try {
      const txs = await getRecentTransactions(address, 10);
      setTransactions(txs);
    } catch (error) {
      console.error('Error loading transactions:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (address) {
      loadTransactions();
    }
  }, [address]);

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
  };

  const formatAmount = (amount) => {
    return (amount / 1_000_000).toFixed(6);
  };

  const getTxType = (tx) => {
    if (tx['tx-type'] === 'pay') return 'Payment';
    if (tx['tx-type'] === 'axfer') return 'Asset Transfer';
    if (tx['tx-type'] === 'acfg') return 'Asset Config';
    return tx['tx-type'];
  };

  const getTxDirection = (tx) => {
    if (tx['tx-type'] === 'pay') {
      return tx.sender === address ? 'sent' : 'received';
    }
    if (tx['tx-type'] === 'axfer') {
      return tx.sender === address ? 'sent' : 'received';
    }
    return 'other';
  };

  return (
    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-semibold text-white">Recent Activity</h2>
        <button
          onClick={loadTransactions}
          disabled={loading}
          className="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded transition disabled:opacity-50"
          title="Refresh"
        >
          <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
        </button>
      </div>

      <div className="space-y-3">
        {transactions.length === 0 && !loading && (
          <div className="text-center py-8">
            <Clock size={48} className="mx-auto text-gray-600 mb-3" />
            <p className="text-gray-400">No transactions yet</p>
            <p className="text-gray-500 text-sm mt-1">
              Your transaction history will appear here
            </p>
          </div>
        )}

        {loading && transactions.length === 0 && (
          <div className="text-center py-8">
            <RefreshCw size={24} className="animate-spin mx-auto text-gray-500" />
            <p className="text-gray-400 mt-2">Loading transactions...</p>
          </div>
        )}

        {transactions.map((tx) => {
          const direction = getTxDirection(tx);
          const txType = getTxType(tx);
          
          return (
            <div key={tx.id} className="bg-gray-700/30 rounded p-4">
              <div className="flex justify-between items-start mb-2">
                <div>
                  <div className="flex items-center gap-2">
                    <span className={`px-2 py-1 rounded text-xs font-semibold ${
                      direction === 'sent' 
                        ? 'bg-red-900/30 text-red-300' 
                        : direction === 'received'
                        ? 'bg-green-900/30 text-green-300'
                        : 'bg-gray-600 text-gray-300'
                    }`}>
                      {direction === 'sent' ? '↑ Sent' : direction === 'received' ? '↓ Received' : txType}
                    </span>
                    {tx['tx-type'] === 'axfer' && (
                      <span className="text-xs text-gray-400">
                        Asset #{tx['asset-transfer-transaction']?.['asset-id']}
                      </span>
                    )}
                  </div>
                  <p className="text-gray-400 text-xs mt-1">
                    {formatDate(tx['round-time'])}
                  </p>
                </div>
                <a
                  href={getExplorerTxUrl(tx.id)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary hover:text-blue-400 transition"
                  title="View on Explorer"
                >
                  <ExternalLink size={18} />
                </a>
              </div>

              <div className="space-y-1 text-sm">
                {tx['tx-type'] === 'pay' && tx['payment-transaction'] && (
                  <p className="text-white">
                    {formatAmount(tx['payment-transaction'].amount)} ALGO
                  </p>
                )}
                
                <p className="text-gray-400 text-xs font-mono truncate">
                  TxID: {tx.id}
                </p>
                
                {tx.note && (
                  <p className="text-gray-400 text-xs">
                    Note: {atob(tx.note)}
                  </p>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {transactions.length > 0 && (
        <div className="mt-4 text-center">
          <a
            href={`https://algoexplorer.io/address/${address}`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary hover:text-blue-400 text-sm transition"
          >
            View all on Explorer →
          </a>
        </div>
      )}
    </div>
  );
}
