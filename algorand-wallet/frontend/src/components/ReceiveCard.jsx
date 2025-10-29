import React from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { Copy, ExternalLink, Check } from 'lucide-react';
import { toast } from 'sonner';

export default function ReceiveCard({ address }) {
  const [copied, setCopied] = React.useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(address);
    setCopied(true);
    toast.success('Address copied to clipboard');
    setTimeout(() => setCopied(false), 2000);
  };

  const explorerUrl = `https://algoexplorer.io/address/${address}`;

  return (
    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
      <h2 className="text-xl font-semibold text-white mb-4">Receive ALGO / ASA</h2>
      
      <div className="bg-white p-4 rounded-lg mb-4 flex justify-center">
        <QRCodeSVG value={address} size={200} />
      </div>

      <div className="mb-4">
        <label className="block text-sm text-gray-400 mb-2">Your Mainnet Address</label>
        <div className="flex items-center gap-2">
          <input
            type="text"
            value={address}
            readOnly
            className="flex-1 bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 text-sm font-mono"
          />
          <button
            onClick={handleCopy}
            className="bg-primary hover:bg-blue-600 text-white p-2 rounded transition"
            title="Copy address"
          >
            {copied ? <Check size={20} /> : <Copy size={20} />}
          </button>
          <a
            href={explorerUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded transition"
            title="View on Explorer"
          >
            <ExternalLink size={20} />
          </a>
        </div>
      </div>

      <div className="bg-yellow-900/30 border border-yellow-700 rounded p-3 text-sm text-yellow-200">
        <p className="font-semibold mb-1">⚠️ Mainnet Address - Real Funds</p>
        <p className="text-xs">
          Send ALGO or ASAs from Binance to this address. Network: <strong>ALGO</strong>
        </p>
      </div>
    </div>
  );
}
