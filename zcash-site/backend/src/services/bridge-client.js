/**
 * REST Bridge Client - Connects to local bridge instead of direct gRPC
 */

const BRIDGE_URL = process.env.BRIDGE_URL || 'http://localhost:3000';

/**
 * Get latest block
 */
export async function getLatestBlock() {
  const response = await fetch(`${BRIDGE_URL}/blocks/head`);
  if (!response.ok) {
    throw new Error(`Bridge error: ${response.statusText}`);
  }
  return await response.json();
}

/**
 * Submit transaction
 */
export async function submitTransaction(rawTxHex) {
  const response = await fetch(`${BRIDGE_URL}/tx/submit`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ rawTxHex })
  });
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Transaction submission failed');
  }
  
  return await response.json();
}

/**
 * Get transaction
 */
export async function getTransaction(txid) {
  const response = await fetch(`${BRIDGE_URL}/tx/${txid}`);
  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('Transaction not found');
    }
    throw new Error(`Bridge error: ${response.statusText}`);
  }
  return await response.json();
}

/**
 * Get bridge info
 */
export async function getBridgeInfo() {
  const response = await fetch(`${BRIDGE_URL}/info`);
  if (!response.ok) {
    throw new Error(`Bridge error: ${response.statusText}`);
  }
  return await response.json();
}

/**
 * Get address balance and UTXOs from lightwalletd via bridge
 */
export async function getAddressBalance(address) {
  const response = await fetch(`${BRIDGE_URL}/address/balance`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ addresses: [address] })
  });

  if (!response.ok) {
    throw new Error(`Bridge error: ${response.statusText}`);
  }

  const data = await response.json();

  // Bridge returns array of balances, extract the first one
  if (data && data.length > 0) {
    return data[0];
  }

  // If no data returned, return zero balance
  return {
    address,
    balance: 0,
    utxos: []
  };
}

export default {
  getLatestBlock,
  submitTransaction,
  getTransaction,
  getBridgeInfo,
  getAddressBalance
};
