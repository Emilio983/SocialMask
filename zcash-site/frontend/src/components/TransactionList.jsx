import { useState, useEffect } from 'react';
import { API_BASE_URL } from '../utils/config';

export default function TransactionList({ userId }) {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadTransactions() {
      try {
        const response = await fetch(`${API_BASE_URL}/tx/user/${userId}`);
        if (response.ok) {
          const data = await response.json();
          setTransactions(data.transactions || []);
        }
      } catch (error) {
        console.error('Failed to load transactions:', error);
      } finally {
        setLoading(false);
      }
    }

    loadTransactions();
  }, [userId]);

  const formatAmount = (zats) => {
    return (zats / 100000000).toFixed(8);
  };

  const formatDate = (timestamp) => {
    return new Date(timestamp).toLocaleString();
  };

  if (loading) {
    return (
      <div className="text-center py-12">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-black"></div>
        <p className="mt-4 text-sm text-gray-600">Loading transactions...</p>
      </div>
    );
  }

  if (transactions.length === 0) {
    return (
      <div className="border-2 border-black p-12 text-center bg-white">
        <p className="text-gray-600">No transactions yet</p>
        <p className="text-sm text-gray-500 mt-2">
          Your transaction history will appear here
        </p>
      </div>
    );
  }

  return (
    <div className="border-2 border-black bg-white">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="border-b-2 border-black">
            <tr>
              <th className="text-left p-4 font-semibold text-sm">Date</th>
              <th className="text-left p-4 font-semibold text-sm">Type</th>
              <th className="text-left p-4 font-semibold text-sm">Amount</th>
              <th className="text-left p-4 font-semibold text-sm">Status</th>
              <th className="text-left p-4 font-semibold text-sm">TxID</th>
            </tr>
          </thead>
          <tbody>
            {transactions.map((tx, index) => (
              <tr
                key={tx.id}
                className={index !== transactions.length - 1 ? 'border-b border-gray-200' : ''}
              >
                <td className="p-4 text-sm">{formatDate(tx.created_at)}</td>
                <td className="p-4">
                  <span
                    className={`inline-block px-2 py-1 text-xs font-medium border ${
                      tx.direction === 'incoming'
                        ? 'border-black bg-white'
                        : 'border-gray-400 bg-gray-50'
                    }`}
                  >
                    {tx.direction === 'incoming' ? 'Received' : 'Sent'}
                  </span>
                </td>
                <td className="p-4 text-sm font-mono">
                  {tx.direction === 'incoming' ? '+' : '-'}
                  {formatAmount(tx.amount_zats)} ZEC
                </td>
                <td className="p-4">
                  <span
                    className={`inline-block px-2 py-1 text-xs font-medium border ${
                      tx.status === 'confirmed'
                        ? 'border-black bg-white'
                        : tx.status === 'pending'
                        ? 'border-gray-400 bg-gray-50'
                        : 'border-gray-400 bg-gray-100'
                    }`}
                  >
                    {tx.status}
                  </span>
                </td>
                <td className="p-4 text-xs font-mono text-gray-600">
                  {tx.txid.substring(0, 16)}...
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
