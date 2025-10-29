import { useState } from 'react';
import { API_BASE_URL } from '../utils/config';

export default function SendCard({ userId, balance }) {
  const [toAddress, setToAddress] = useState('');
  const [amount, setAmount] = useState('');
  const [memo, setMemo] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const formatBalance = (zats) => {
    return (zats / 100000000).toFixed(8);
  };

  const validateAddress = (addr) => {
    // Basic validation for different address formats
    // u1... = Unified Address
    // t1/t3... = Transparent Address (Base58Check)
    // tex1... = Transparent Extended Address (Bech32)
    // zs1... = Sapling Shielded Address
    return addr.startsWith('u1') || 
           addr.startsWith('t1') || 
           addr.startsWith('t3') ||
           addr.startsWith('tex1') ||
           addr.startsWith('zs1');
  };

  const isTransparentAddress = (addr) => {
    return addr.startsWith('t1') || addr.startsWith('t3') || addr.startsWith('tex1');
  };

  const handleSend = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    // Validations
    if (!toAddress.trim()) {
      setError('Recipient address is required');
      return;
    }

    if (!validateAddress(toAddress)) {
      setError('Invalid Zcash address format');
      return;
    }

    const amountZats = Math.floor(parseFloat(amount) * 100000000);
    if (isNaN(amountZats) || amountZats <= 0) {
      setError('Invalid amount');
      return;
    }

    if (amountZats > balance) {
      setError('Insufficient balance');
      return;
    }

    if (memo.length > 512) {
      setError('Memo too long (max 512 characters)');
      return;
    }

    // Warn about transparent addresses
    if (isTransparentAddress(toAddress)) {
      const confirmed = confirm(
        'Warning: You are sending to a transparent address. ' +
        'This transaction will not be private. Continue?'
      );
      if (!confirmed) return;
    }

    setLoading(true);

    try {
      // Use the real transaction building endpoint
      const response = await fetch(`${API_BASE_URL}/wallet/send-real`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId,
          toAddress,
          amountZEC: amount,
          memo: memo || null,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Transaction failed');
      }

      const result = await response.json();

      if (result.success) {
        if (result.txid) {
          setSuccess(`Transaction sent! TxID: ${result.txid}`);
        } else {
          // Transaction was built but not yet fully implemented
          setSuccess(`Transaction prepared! ${result.note || result.message}`);
        }
        
        // Reset form
        setToAddress('');
        setAmount('');
        setMemo('');
      } else {
        throw new Error(result.error || 'Transaction failed');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-md mx-auto">
      <div className="border-2 border-black p-8 bg-white shadow-lg">
        <h2 className="text-xl font-semibold mb-6 text-center">
          Send ZEC
        </h2>

        <form onSubmit={handleSend} className="space-y-6">
          {/* Recipient address */}
          <div>
            <label htmlFor="to-address" className="block text-sm font-medium mb-2">
              Recipient Address
            </label>
            <input
              id="to-address"
              type="text"
              value={toAddress}
              onChange={(e) => setToAddress(e.target.value)}
              placeholder="u1..., t1..., t3..., or tex1..."
              className="w-full px-3 py-2 border-2 border-black focus:outline-none focus:ring-2 focus:ring-black"
              disabled={loading}
            />
          </div>

          {/* Amount */}
          <div>
            <label htmlFor="amount" className="block text-sm font-medium mb-2">
              Amount (ZEC)
            </label>
            <input
              id="amount"
              type="number"
              step="0.00000001"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="0.00000000"
              className="w-full px-3 py-2 border-2 border-black focus:outline-none focus:ring-2 focus:ring-black"
              disabled={loading}
            />
            <p className="mt-1 text-xs text-gray-600">
              Available: {formatBalance(balance)} ZEC
            </p>
          </div>

          {/* Memo */}
          <div>
            <label htmlFor="memo" className="block text-sm font-medium mb-2">
              Memo (optional)
            </label>
            <textarea
              id="memo"
              value={memo}
              onChange={(e) => setMemo(e.target.value)}
              placeholder="Optional encrypted memo..."
              rows={3}
              maxLength={512}
              className="w-full px-3 py-2 border-2 border-black focus:outline-none focus:ring-2 focus:ring-black resize-none"
              disabled={loading}
            />
            <p className="mt-1 text-xs text-gray-600">
              {memo.length}/512 characters
            </p>
          </div>

          {/* Error message */}
          {error && (
            <div className="p-4 border-2 border-black bg-gray-50">
              <p className="text-sm text-gray-800">{error}</p>
            </div>
          )}

          {/* Success message */}
          {success && (
            <div className="p-4 border-2 border-black bg-white">
              <p className="text-sm text-gray-800">{success}</p>
            </div>
          )}

          {/* Submit button */}
          <button
            type="submit"
            disabled={loading}
            className="w-full py-3 px-4 border-2 border-black bg-black text-white font-medium
                       hover:bg-gray-900 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Building transaction...' : 'Send'}
          </button>
        </form>

        {/* Info */}
        <div className="mt-6 pt-4 border-t border-gray-200">
          <p className="text-xs text-gray-600">
            Transactions are fully shielded when sent to Unified or Sapling addresses.
            Sending to transparent addresses (t1..., t3..., tex1...) is not private.
          </p>
        </div>
      </div>
    </div>
  );
}
