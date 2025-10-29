import { useState } from 'react';
import { API_BASE_URL } from '../utils/config';

export default function ExportKeyCard({ userId }) {
  const [showKey, setShowKey] = useState(false);
  const [keyData, setKeyData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [confirmed, setConfirmed] = useState(false);

  const exportKey = async () => {
    if (!confirmed) {
      setError('You must check the confirmation box');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`${API_BASE_URL}/wallet/export-key`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId,
          confirmation: 'I_UNDERSTAND_THIS_IS_MY_PRIVATE_KEY'
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to export key');
      }

      const data = await response.json();
      setKeyData(data);
      setShowKey(true);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const copyKey = async () => {
    try {
      await navigator.clipboard.writeText(keyData.privateKeyWIF);
      alert('Private key copied to clipboard!');
    } catch (error) {
      console.error('Failed to copy:', error);
    }
  };

  return (
    <div className="max-w-2xl mx-auto">
      <div className="border-2 border-black p-8 bg-white shadow-lg">
        <h2 className="text-xl font-semibold mb-6 text-center">
          🔑 Export Private Key
        </h2>

        {!showKey ? (
          <div className="space-y-6">
            <div className="p-4 border-2 border-red-600 bg-red-50">
              <p className="text-sm font-bold text-red-800 mb-2">⚠️ SECURITY WARNING</p>
              <p className="text-xs text-red-700">
                This will export your private key in WIF format. Anyone with this key can spend your funds.
                Only export this key if you need to import your wallet into another application like Ywallet or Zecwallet.
              </p>
            </div>

            <div className="space-y-3">
              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={confirmed}
                  onChange={(e) => setConfirmed(e.target.checked)}
                  className="mt-1"
                />
                <span className="text-sm">
                  I understand that this private key gives full control over my funds,
                  and I will keep it secure and never share it with anyone.
                </span>
              </label>
            </div>

            {error && (
              <div className="p-4 border-2 border-black bg-gray-50">
                <p className="text-sm text-red-600">{error}</p>
              </div>
            )}

            <button
              onClick={exportKey}
              disabled={loading || !confirmed}
              className="w-full py-3 px-4 border-2 border-black bg-black text-white font-medium
                         hover:bg-gray-900 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading ? 'Exporting...' : 'Export Private Key'}
            </button>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="p-4 border-2 border-green-600 bg-green-50">
              <p className="text-sm font-bold text-green-800 mb-2">✅ Private Key Exported</p>
              <p className="text-xs text-green-700">
                Use this key to import your wallet into Ywallet, Zecwallet, or any compatible Zcash wallet.
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">
                Your Address
              </label>
              <div className="border-2 border-black p-3 bg-gray-50 break-all text-xs font-mono">
                {keyData.address}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">
                Private Key (WIF Format)
              </label>
              <div className="border-2 border-red-600 p-3 bg-red-50 break-all text-xs font-mono">
                {keyData.privateKeyWIF}
              </div>
              <button
                onClick={copyKey}
                className="mt-2 w-full py-2 px-4 border-2 border-black bg-white text-black font-medium
                           hover:bg-gray-50 transition-colors"
              >
                Copy Private Key
              </button>
            </div>

            <div className="p-4 border-2 border-black bg-gray-50">
              <p className="text-sm font-semibold mb-3">How to use this key:</p>
              <ol className="text-xs space-y-2 list-decimal list-inside">
                {keyData.instructions.map((instruction, index) => (
                  <li key={index}>{instruction}</li>
                ))}
              </ol>
            </div>

            <div className="p-4 border-2 border-yellow-600 bg-yellow-50">
              <p className="text-xs text-yellow-800 font-bold">
                🔒 Keep this key safe! Delete it after importing to another wallet.
                Never share it via email, chat, or screenshot.
              </p>
            </div>

            <button
              onClick={() => {
                setShowKey(false);
                setKeyData(null);
                setConfirmed(false);
              }}
              className="w-full py-2 px-4 border-2 border-black bg-gray-200 text-black font-medium
                         hover:bg-gray-300 transition-colors"
            >
              Hide Key
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
