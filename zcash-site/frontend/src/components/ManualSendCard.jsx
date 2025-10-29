import { useState } from 'react';
import { API_BASE_URL } from '../utils/config';

export default function ManualSendCard({ userId, walletAddress }) {
  const [txid, setTxid] = useState('');
  const [toAddress, setToAddress] = useState('');
  const [amount, setAmount] = useState('');
  const [utxos, setUtxos] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const fetchUTXOs = async () => {
    if (!txid.trim()) {
      setError('Please enter the transaction ID from Binance');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`${API_BASE_URL}/wallet/get-utxos-from-txid/${txid}/${walletAddress}`);
      const data = await response.json();

      if (data.success && data.utxos && data.utxos.length > 0) {
        setUtxos(data.utxos);
        setSuccess(`Found ${data.utxos.length} UTXO(s) with total ${(data.utxos.reduce((sum, u) => sum + u.value, 0) / 100000000).toFixed(8)} ZEC`);
      } else {
        setError('No UTXOs found. Make sure the transaction is confirmed and sent to your address.');
      }
    } catch (err) {
      setError('Failed to fetch UTXOs: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  const sendTransaction = async () => {
    if (!utxos || utxos.length === 0) {
      setError('Please fetch UTXOs first');
      return;
    }

    if (!toAddress.trim()) {
      setError('Please enter destination address');
      return;
    }

    const amountZEC = parseFloat(amount);
    if (isNaN(amountZEC) || amountZEC <= 0) {
      setError('Please enter a valid amount');
      return;
    }

    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const response = await fetch(`${API_BASE_URL}/wallet/send-manual`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId,
          toAddress,
          amountZEC: amount,
          utxos
        }),
      });

      const data = await response.json();

      if (data.success) {
        setSuccess(`Transaction sent! TXID: ${data.txid}`);
        setUtxos(null);
        setTxid('');
        setAmount('');
        setToAddress('');
      } else {
        setError(data.error || 'Transaction failed');
      }
    } catch (err) {
      setError('Failed to send: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto">
      <div className="border-2 border-black p-8 bg-white shadow-lg">
        <h2 className="text-xl font-semibold mb-6 text-center">
          Manual Send (Paste Binance TXID)
        </h2>

        <div className="space-y-6">
          {/* Step 1: Get TXID from Binance */}
          <div className="p-4 border-2 border-blue-600 bg-blue-50">
            <p className="text-sm font-bold text-blue-800 mb-2">📝 Step 1: Get Transaction ID from Binance</p>
            <p className="text-xs text-blue-700">
              Go to Binance → Wallet → Transaction History → Find your ZEC deposit → Copy the TXID
            </p>
          </div>

          {/* TXID Input */}
          <div>
            <label className="block text-sm font-medium mb-2">
              Transaction ID (TXID) from Binance
            </label>
            <input
              type="text"
              value={txid}
              onChange={(e) => setTxid(e.target.value)}
              placeholder="Paste your Binance transaction ID here..."
              className="w-full px-3 py-2 border-2 border-black focus:outline-none focus:ring-2 focus:ring-black"
              disabled={loading}
            />
            <button
              onClick={fetchUTXOs}
              disabled={loading || !txid.trim()}
              className="mt-2 w-full py-2 px-4 border-2 border-black bg-black text-white font-medium
                         hover:bg-gray-900 transition-colors disabled:opacity-50"
            >
              {loading ? 'Fetching...' : 'Fetch My Funds'}
            </button>
          </div>

          {/* Show UTXOs if found */}
          {utxos && utxos.length > 0 && (
            <div className="p-4 border-2 border-green-600 bg-green-50">
              <p className="text-sm font-bold text-green-800 mb-2">✅ Funds Found!</p>
              <p className="text-xs text-green-700">
                Total available: {(utxos.reduce((sum, u) => sum + u.value, 0) / 100000000).toFixed(8)} ZEC
              </p>
            </div>
          )}

          {/* Send form - only show if UTXOs are loaded */}
          {utxos && utxos.length > 0 && (
            <>
              <div>
                <label className="block text-sm font-medium mb-2">
                  Destination Address (Binance)
                </label>
                <input
                  type="text"
                  value={toAddress}
                  onChange={(e) => setToAddress(e.target.value)}
                  placeholder="tex1... address from Binance"
                  className="w-full px-3 py-2 border-2 border-black focus:outline-none focus:ring-2 focus:ring-black"
                  disabled={loading}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">
                  Amount (ZEC)
                </label>
                <input
                  type="number"
                  step="0.00000001"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="0.00000000"
                  className="w-full px-3 py-2 border-2 border-black focus:outline-none focus:ring-2 focus:ring-black"
                  disabled={loading}
                />
              </div>

              <button
                onClick={sendTransaction}
                disabled={loading}
                className="w-full py-3 px-4 border-2 border-black bg-black text-white font-medium
                           hover:bg-gray-900 transition-colors disabled:opacity-50"
              >
                {loading ? 'Sending...' : 'Send ZEC'}
              </button>
            </>
          )}

          {/* Error */}
          {error && (
            <div className="p-4 border-2 border-red-600 bg-red-50">
              <p className="text-sm text-red-800">{error}</p>
            </div>
          )}

          {/* Success */}
          {success && (
            <div className="p-4 border-2 border-green-600 bg-green-50">
              <p className="text-sm text-green-800">{success}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
