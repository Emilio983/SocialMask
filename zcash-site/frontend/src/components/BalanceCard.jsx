export default function BalanceCard({ balance, loading, onSync }) {
  const formatBalance = (zats) => {
    const zec = zats / 100000000;
    return zec.toFixed(8);
  };

  return (
    <div className="border-2 border-black p-8 bg-white shadow-lg">
      <div className="flex justify-between items-start mb-6">
        <div>
          <h2 className="text-sm font-medium text-gray-600 mb-2">Total Balance</h2>
          <p className="text-4xl font-bold">
            {formatBalance(balance)} <span className="text-2xl">ZEC</span>
          </p>
        </div>
        <button
          onClick={onSync}
          disabled={loading}
          className="px-4 py-2 border-2 border-black hover:bg-gray-50 transition-colors text-sm font-medium disabled:opacity-50"
          title="Sync with blockchain"
        >
          {loading ? 'Syncing...' : 'Sync'}
        </button>
      </div>

      <div className="pt-4 border-t border-gray-200">
        <div className="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p className="text-gray-600 mb-1">Shielded (Orchard)</p>
            <p className="font-semibold">{formatBalance(balance)} ZEC</p>
          </div>
          <div>
            <p className="text-gray-600 mb-1">Network</p>
            <p className="font-semibold">Mainnet</p>
          </div>
        </div>
      </div>
    </div>
  );
}
