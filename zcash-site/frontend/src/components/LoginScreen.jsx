import { useState } from 'react';
import { useAuth } from '../hooks/useAuth';
import { isWebAuthnSupported } from '../services/webauthn';

export default function LoginScreen() {
  const { register, login } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleRegister = async () => {
    setLoading(true);
    setError(null);
    try {
      await register();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleLogin = async () => {
    setLoading(true);
    setError(null);
    try {
      await login();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  if (!isWebAuthnSupported()) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <div className="max-w-md w-full text-center">
          <div className="border-2 border-black p-8 bg-white">
            <h2 className="text-2xl font-bold mb-4">Unsupported Browser</h2>
            <p className="text-gray-700 mb-4">
              Your browser does not support WebAuthn/Passkeys.
            </p>
            <p className="text-sm text-gray-600">
              Please use a modern browser that supports Passkeys (Chrome 108+, Safari 16+, Edge 108+).
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center px-4 bg-white">
      <div className="max-w-md w-full">
        {/* Header */}
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold mb-2">Zcash Wallet</h1>
          <p className="text-sm text-gray-600">
            Seedless, non-custodial, private
          </p>
        </div>

        {/* Main card */}
        <div className="border-2 border-black p-8 bg-white shadow-lg">
          <h2 className="text-2xl font-semibold mb-6 text-center">
            Welcome
          </h2>

          {error && (
            <div className="mb-6 p-4 border-2 border-black bg-gray-50">
              <p className="text-sm text-gray-800">{error}</p>
            </div>
          )}

          <div className="space-y-4">
            <button
              onClick={handleRegister}
              disabled={loading}
              className="w-full py-3 px-4 border-2 border-black bg-black text-white font-medium
                         hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2
                         disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {loading ? 'Processing...' : 'Create New Wallet'}
            </button>

            <button
              onClick={handleLogin}
              disabled={loading}
              className="w-full py-3 px-4 border-2 border-black bg-white text-black font-medium
                         hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2
                         disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {loading ? 'Processing...' : 'Login with Passkey'}
            </button>
          </div>

          {/* Info section */}
          <div className="mt-8 pt-6 border-t border-gray-200">
            <h3 className="text-sm font-semibold mb-2">What is this?</h3>
            <ul className="text-xs text-gray-600 space-y-1">
              <li>• No seed phrases to remember</li>
              <li>• Non-custodial (you control your keys)</li>
              <li>• Shielded transactions with Orchard</li>
              <li>• Secured by your device's biometrics</li>
            </ul>
          </div>
        </div>

        {/* Footer disclaimer */}
        <div className="mt-6 text-center">
          <p className="text-xs text-gray-500">
            Testnet only. Experimental software. Use at your own risk.
          </p>
        </div>
      </div>
    </div>
  );
}
