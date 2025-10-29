import React from 'react';
import { Loader2 } from 'lucide-react';

export default function BalanceCard({ balance, assets, loading, onRefresh }) {
  return (
    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-semibold text-white">Balances</h2>
        <button
          onClick={onRefresh}
          disabled={loading}
          className="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm transition disabled:opacity-50"
        >
          {loading ? <Loader2 size={16} className="animate-spin" /> : 'Refresh'}
        </button>
      </div>

      <div className="space-y-3">
        {/* ALGO Balance */}
        <div className="bg-gray-700/50 rounded p-4">
          <div className="flex justify-between items-center">
            <div>
              <p className="text-gray-400 text-sm">ALGO</p>
              <p className="text-2xl font-bold text-white">{balance.toFixed(6)}</p>
            </div>
            <div className="text-right">
              <p className="text-gray-400 text-xs">Native Token</p>
            </div>
          </div>
        </div>

        {/* ASAs */}
        {assets.length > 0 && (
          <div>
            <p className="text-gray-400 text-sm mb-2">Assets (ASAs)</p>
            <div className="space-y-2">
              {assets.map((asset) => (
                <div key={asset['asset-id']} className="bg-gray-700/30 rounded p-3">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="text-white font-medium">
                        {asset.name || `Asset ${asset['asset-id']}`}
                      </p>
                      <p className="text-gray-400 text-xs">ID: {asset['asset-id']}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-white font-semibold">
                        {(asset.amount / Math.pow(10, asset.decimals || 0)).toFixed(asset.decimals || 0)}
                      </p>
                      <p className="text-gray-400 text-xs">{asset['unit-name'] || 'units'}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {assets.length === 0 && (
          <p className="text-gray-500 text-sm text-center py-4">
            No ASAs yet. Use "Send ASA" tab to opt-in to assets.
          </p>
        )}
      </div>
    </div>
  );
}
