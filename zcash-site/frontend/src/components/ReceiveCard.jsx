import { useState, useEffect } from 'react';
import QRCode from 'qrcode';

export default function ReceiveCard({ address, transparentAddress }) {
  const [qrDataUrl, setQrDataUrl] = useState('');
  const [copied, setCopied] = useState(false);
  // Show transparent address by default (for Binance and other exchanges)
  const [showTransparent, setShowTransparent] = useState(true);
  const [activeAddress, setActiveAddress] = useState(transparentAddress || address);

  useEffect(() => {
    const addrToShow = showTransparent && transparentAddress ? transparentAddress : address;
    setActiveAddress(addrToShow);

    if (addrToShow) {
      QRCode.toDataURL(addrToShow, {
        width: 256,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#ffffff',
        },
      }).then(setQrDataUrl);
    }
  }, [address, transparentAddress, showTransparent]);

  const copyAddress = async () => {
    try {
      await navigator.clipboard.writeText(activeAddress);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (error) {
      console.error('Failed to copy:', error);
    }
  };

  return (
    <div className="max-w-md mx-auto">
      <div className="border-2 border-black p-8 bg-white shadow-lg">
        <h2 className="text-xl font-semibold mb-6 text-center">
          Receive ZEC
        </h2>

        {/* Address Type Toggle */}
        {transparentAddress && (
          <div className="mb-6 flex gap-2">
            <button
              onClick={() => setShowTransparent(false)}
              className={`flex-1 py-2 px-4 border-2 border-black font-medium text-sm transition-colors
                ${!showTransparent ? 'bg-black text-white' : 'bg-white text-black hover:bg-gray-50'}`}
            >
              Unified (Private)
            </button>
            <button
              onClick={() => setShowTransparent(true)}
              className={`flex-1 py-2 px-4 border-2 border-black font-medium text-sm transition-colors
                ${showTransparent ? 'bg-black text-white' : 'bg-white text-black hover:bg-gray-50'}`}
            >
              Transparent
            </button>
          </div>
        )}

        {/* QR Code */}
        {qrDataUrl && (
          <div className="mb-6 flex justify-center">
            <div className="border-2 border-black p-4 bg-white">
              <img src={qrDataUrl} alt="Address QR Code" className="w-64 h-64" />
            </div>
          </div>
        )}

        {/* Address */}
        <div className="mb-6">
          <label className="block text-sm font-medium mb-2">
            {showTransparent ? 'Your Transparent Address (t-addr)' : 'Your Unified Address'}
          </label>
          <div className="border-2 border-black p-3 bg-gray-50 break-all text-xs font-mono">
            {activeAddress}
          </div>
        </div>

        {/* Copy button */}
        <button
          onClick={copyAddress}
          className="w-full py-3 px-4 border-2 border-black bg-black text-white font-medium
                     hover:bg-gray-900 transition-colors"
        >
          {copied ? 'Copied!' : 'Copy Address'}
        </button>

        {/* Info */}
        <div className="mt-6 pt-4 border-t border-gray-200">
          {showTransparent ? (
            <div className="text-xs text-gray-600">
              <p className="font-semibold text-yellow-700 mb-2">⚠️ Transparent Address (Public)</p>
              <p>
                This is your transparent address. Transactions are <strong>publicly visible</strong> on the blockchain.
                Use this for exchanges like Binance that don't support shielded addresses yet.
              </p>
            </div>
          ) : (
            <p className="text-xs text-gray-600">
              This is your Unified Address. It supports shielded Orchard transactions.
              Share this address to receive ZEC privately.
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
