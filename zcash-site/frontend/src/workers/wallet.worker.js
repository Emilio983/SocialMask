// Wallet WebWorker for WASM operations
// This runs in a separate thread to avoid blocking the UI

let wasmModule = null;
let wasmInitialized = false;

// Initialize WASM module
async function initWasm() {
  if (wasmModule && wasmInitialized) {
    return wasmModule;
  }

  try {
    console.log('Worker: Loading WASM module from /wasm/zcash_wallet_wasm.js');

    // Load actual WASM module
    const wasm = await import('/wasm/zcash_wallet_wasm.js');

    // Initialize the WASM module
    await wasm.default();

    wasmModule = wasm;
    wasmInitialized = true;

    console.log('Worker: WASM module loaded successfully');
    console.log('Worker: Available WASM functions:', Object.keys(wasm).filter(k => typeof wasm[k] === 'function'));

    return wasmModule;
  } catch (error) {
    console.error('Worker: Failed to initialize WASM:', error);
    wasmInitialized = false;
    throw new Error(`WASM initialization failed: ${error.message}`);
  }
}

// Message handler
self.onmessage = async (event) => {
  const { id, type, payload } = event.data;

  try {
    const wasm = await initWasm();

    let result;

    switch (type) {
      case 'generate_key':
        result = wasm.generate_spending_key();
        break;

      case 'derive_address':
        result = wasm.derive_unified_address(payload.spendingKey);
        break;

      case 'sync':
        result = wasm.sync_wallet(
          payload.spendingKey,
          payload.startHeight,
          payload.endHeight
        );
        break;

      case 'build_tx':
        // This is the expensive operation (ZK proof generation)
        // Report progress
        self.postMessage({ id, type: 'progress', payload: { step: 'Building transaction' } });

        await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate work

        self.postMessage({ id, type: 'progress', payload: { step: 'Generating proof' } });

        await new Promise(resolve => setTimeout(resolve, 2000)); // Simulate ZK proof

        result = wasm.build_transaction(
          payload.spendingKey,
          payload.toAddress,
          payload.amount,
          payload.memo
        );
        break;

      case 'validate_address':
        result = wasm.validate_address(payload.address);
        break;

      default:
        throw new Error(`Unknown message type: ${type}`);
    }

    // Send result back to main thread
    self.postMessage({ id, type: 'success', payload: result });
  } catch (error) {
    self.postMessage({ id, type: 'error', payload: error.message });
  }
};

// Log that worker is ready
console.log('Wallet worker initialized');
