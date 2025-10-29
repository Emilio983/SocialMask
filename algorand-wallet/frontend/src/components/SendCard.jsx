import React, { useState } from 'react';
import { Send, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { sendAlgoPayment, sendAssetTransfer, optInToAsset, isValidAddress, getAssetInfo } from '../lib/algorand';

export default function SendCard({ address, mnemonic, onTransactionComplete }) {
  const [activeTab, setActiveTab] = useState('algo');
  const [loading, setLoading] = useState(false);
  const [optInLoading, setOptInLoading] = useState(false);

  // ALGO transfer
  const [algoRecipient, setAlgoRecipient] = useState('');
  const [algoAmount, setAlgoAmount] = useState('');
  const [algoNote, setAlgoNote] = useState('');

  // ASA transfer
  const [asaRecipient, setAsaRecipient] = useState('');
  const [asaAmount, setAsaAmount] = useState('');
  const [assetId, setAssetId] = useState('');
  const [asaNote, setAsaNote] = useState('');

  const handleSendAlgo = async (e) => {
    e.preventDefault();
    
    // Validate mnemonic exists
    if (!mnemonic || typeof mnemonic !== 'string') {
      toast.error('Wallet not properly unlocked. Please refresh and try again.');
      return;
    }
    
    // Validate recipient
    if (!algoRecipient || !algoRecipient.trim()) {
      toast.error('Please enter recipient address');
      return;
    }
    
    if (!isValidAddress(algoRecipient.trim())) {
      toast.error('Invalid recipient address (must be 58 characters)');
      return;
    }

    // Validate amount
    const amount = parseFloat(algoAmount);
    if (isNaN(amount) || amount <= 0) {
      toast.error('Invalid amount (must be greater than 0)');
      return;
    }
    
    if (amount < 0.001) {
      toast.error('Amount too small (minimum 0.001 ALGO)');
      return;
    }

    setLoading(true);
    try {
      toast.info('Signing transaction...');
      const result = await sendAlgoPayment(
        address, 
        algoRecipient.trim(), 
        amount, 
        mnemonic, 
        algoNote.trim()
      );
      
      toast.success(
        <>
          Transaction sent! 
          <a 
            href={`https://algoexplorer.io/tx/${result.txId}`}
            target="_blank"
            rel="noopener noreferrer"
            className="underline ml-1"
          >
            View TxID
          </a>
        </>, 
        { duration: 8000 }
      );
      
      // Reset form
      setAlgoRecipient('');
      setAlgoAmount('');
      setAlgoNote('');
      
      if (onTransactionComplete) {
        onTransactionComplete(result);
      }
    } catch (error) {
      console.error('Send ALGO error:', error);
      const errorMessage = error?.message || 'Unknown error occurred';
      toast.error(`Failed to send: ${errorMessage}`, { duration: 6000 });
    } finally {
      setLoading(false);
    }
  };

  const handleOptIn = async () => {
    if (!mnemonic || typeof mnemonic !== 'string') {
      toast.error('Wallet not properly unlocked. Please refresh and try again.');
      return;
    }
    
    if (!assetId || assetId.trim() === '') {
      toast.error('Please enter Asset ID');
      return;
    }
    
    const assetIdNum = parseInt(assetId);
    if (isNaN(assetIdNum) || assetIdNum <= 0) {
      toast.error('Invalid Asset ID (must be a positive number)');
      return;
    }

    setOptInLoading(true);
    try {
      toast.info('Getting asset information...');
      
      // Get asset info first
      let assetInfo;
      let assetName = `Asset ${assetId}`;
      try {
        assetInfo = await getAssetInfo(assetId);
        assetName = assetInfo.params.name || assetName;
      } catch (err) {
        console.warn('Could not fetch asset info:', err);
        // Continue anyway - asset might exist but info fetch failed
      }
      
      toast.info(`Opting in to ${assetName}...`);
      const result = await optInToAsset(address, assetId, mnemonic);
      
      toast.success(
        <>
          Opted in to {assetName}! 
          <a 
            href={`https://algoexplorer.io/tx/${result.txId}`}
            target="_blank"
            rel="noopener noreferrer"
            className="underline ml-1"
          >
            View TxID
          </a>
        </>, 
        { duration: 8000 }
      );
      
      if (onTransactionComplete) {
        onTransactionComplete(result);
      }
    } catch (error) {
      console.error('Opt-in error:', error);
      const errorMessage = error?.message || 'Unknown error occurred';
      toast.error(`Failed to opt-in: ${errorMessage}`, { duration: 6000 });
    } finally {
      setOptInLoading(false);
    }
  };

  const handleSendAsset = async (e) => {
    e.preventDefault();
    
    // Validate mnemonic
    if (!mnemonic || typeof mnemonic !== 'string') {
      toast.error('Wallet not properly unlocked. Please refresh and try again.');
      return;
    }
    
    // Validate recipient
    if (!asaRecipient || !asaRecipient.trim()) {
      toast.error('Please enter recipient address');
      return;
    }
    
    if (!isValidAddress(asaRecipient.trim())) {
      toast.error('Invalid recipient address (must be 58 characters)');
      return;
    }

    // Validate asset ID
    if (!assetId || assetId.trim() === '') {
      toast.error('Please enter Asset ID');
      return;
    }
    
    const assetIdNum = parseInt(assetId);
    if (isNaN(assetIdNum) || assetIdNum <= 0) {
      toast.error('Invalid Asset ID (must be a positive number)');
      return;
    }

    // Validate amount
    const amount = parseInt(asaAmount);
    if (isNaN(amount) || amount <= 0) {
      toast.error('Invalid amount (must be greater than 0)');
      return;
    }

    setLoading(true);
    try {
      toast.info('Signing asset transfer...');
      const result = await sendAssetTransfer(
        address, 
        asaRecipient.trim(), 
        assetId, 
        amount, 
        mnemonic, 
        asaNote.trim()
      );
      
      toast.success(
        <>
          Asset sent! 
          <a 
            href={`https://algoexplorer.io/tx/${result.txId}`}
            target="_blank"
            rel="noopener noreferrer"
            className="underline ml-1"
          >
            View TxID
          </a>
        </>, 
        { duration: 8000 }
      );
      
      // Reset form
      setAsaRecipient('');
      setAsaAmount('');
      setAssetId('');
      setAsaNote('');
      
      if (onTransactionComplete) {
        onTransactionComplete(result);
      }
    } catch (error) {
      console.error('Send ASA error:', error);
      const errorMessage = error?.message || 'Unknown error occurred';
      toast.error(`Failed to send: ${errorMessage}`, { duration: 6000 });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
      <h2 className="text-xl font-semibold text-white mb-4">Send</h2>

      {/* Tabs */}
      <div className="flex gap-2 mb-4 border-b border-gray-700">
        <button
          onClick={() => setActiveTab('algo')}
          className={`px-4 py-2 font-medium transition ${
            activeTab === 'algo'
              ? 'text-primary border-b-2 border-primary'
              : 'text-gray-400 hover:text-gray-300'
          }`}
        >
          Send ALGO
        </button>
        <button
          onClick={() => setActiveTab('asa')}
          className={`px-4 py-2 font-medium transition ${
            activeTab === 'asa'
              ? 'text-primary border-b-2 border-primary'
              : 'text-gray-400 hover:text-gray-300'
          }`}
        >
          Send ASA
        </button>
      </div>

      {/* ALGO Transfer Form */}
      {activeTab === 'algo' && (
        <form onSubmit={handleSendAlgo} className="space-y-4">
          <div>
            <label className="block text-sm text-gray-400 mb-1">Recipient Address</label>
            <input
              type="text"
              value={algoRecipient}
              onChange={(e) => setAlgoRecipient(e.target.value)}
              placeholder="Enter Algorand address"
              className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none font-mono text-sm"
              required
            />
          </div>

          <div>
            <label className="block text-sm text-gray-400 mb-1">Amount (ALGO)</label>
            <input
              type="number"
              step="0.000001"
              value={algoAmount}
              onChange={(e) => setAlgoAmount(e.target.value)}
              placeholder="0.00"
              className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none"
              required
            />
            <p className="text-xs text-gray-500 mt-1">Min: 0.001 ALGO + network fee (~0.001 ALGO)</p>
          </div>

          <div>
            <label className="block text-sm text-gray-400 mb-1">Note (Optional)</label>
            <input
              type="text"
              value={algoNote}
              onChange={(e) => setAlgoNote(e.target.value)}
              placeholder="Transaction note"
              className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none"
              maxLength={1000}
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            {loading ? (
              <>
                <Loader2 size={20} className="animate-spin" />
                Sending...
              </>
            ) : (
              <>
                <Send size={20} />
                Sign & Send ALGO
              </>
            )}
          </button>
        </form>
      )}

      {/* ASA Transfer Form */}
      {activeTab === 'asa' && (
        <div className="space-y-4">
          {/* Opt-in Section */}
          <div className="bg-gray-700/30 rounded p-4 mb-4">
            <p className="text-gray-300 text-sm mb-3">
              Before receiving an ASA, you must opt-in to it:
            </p>
            <div className="flex gap-2">
              <input
                type="number"
                value={assetId}
                onChange={(e) => setAssetId(e.target.value)}
                placeholder="Asset ID"
                className="flex-1 bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none"
              />
              <button
                type="button"
                onClick={handleOptIn}
                disabled={optInLoading}
                className="bg-secondary hover:bg-green-600 text-white px-4 py-2 rounded transition disabled:opacity-50 flex items-center gap-2"
              >
                {optInLoading ? <Loader2 size={18} className="animate-spin" /> : 'Opt-In'}
              </button>
            </div>
            <p className="text-xs text-gray-500 mt-2">Fee: 0.001 ALGO</p>
          </div>

          <form onSubmit={handleSendAsset} className="space-y-4">
            <div>
              <label className="block text-sm text-gray-400 mb-1">Asset ID</label>
              <input
                type="number"
                value={assetId}
                onChange={(e) => setAssetId(e.target.value)}
                placeholder="Enter Asset ID"
                className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none"
                required
              />
            </div>

            <div>
              <label className="block text-sm text-gray-400 mb-1">Recipient Address</label>
              <input
                type="text"
                value={asaRecipient}
                onChange={(e) => setAsaRecipient(e.target.value)}
                placeholder="Enter Algorand address"
                className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none font-mono text-sm"
                required
              />
            </div>

            <div>
              <label className="block text-sm text-gray-400 mb-1">Amount (base units)</label>
              <input
                type="number"
                value={asaAmount}
                onChange={(e) => setAsaAmount(e.target.value)}
                placeholder="0"
                className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none"
                required
              />
              <p className="text-xs text-gray-500 mt-1">Network fee: ~0.001 ALGO</p>
            </div>

            <div>
              <label className="block text-sm text-gray-400 mb-1">Note (Optional)</label>
              <input
                type="text"
                value={asaNote}
                onChange={(e) => setAsaNote(e.target.value)}
                placeholder="Transaction note"
                className="w-full bg-gray-700 text-white px-3 py-2 rounded border border-gray-600 focus:border-primary focus:outline-none"
                maxLength={1000}
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <Loader2 size={20} className="animate-spin" />
                  Sending...
                </>
              ) : (
                <>
                  <Send size={20} />
                  Sign & Send ASA
                </>
              )}
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
