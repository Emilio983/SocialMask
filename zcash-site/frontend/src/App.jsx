import { useState, useEffect } from 'react';
import { AuthProvider, useAuth } from './hooks/useAuth';
import LoginScreen from './components/LoginScreen';
import WalletDashboard from './components/WalletDashboard';
import { initDB, resetLocalData } from './services/indexeddb';

function AppContent() {
  const { user, loading } = useAuth();
  const [dbReady, setDbReady] = useState(false);
  const [dbError, setDbError] = useState(null);
  const [resetting, setResetting] = useState(false);

  useEffect(() => {
    // Initialize IndexedDB on app load
    initDB()
      .then(() => {
        setDbReady(true);
        setDbError(null);
        console.log('IndexedDB ready:', true);
      })
      .catch((error) => {
        console.error('Failed to initialize IndexedDB:', error);
        setDbError(error.message || 'Failed to initialize database');
        setDbReady(false);
      });
  }, []);

  const handleResetData = async () => {
    if (!confirm('Are you sure you want to reset all local data? This will log you out and delete all cached data.')) {
      return;
    }

    setResetting(true);
    try {
      await resetLocalData();
      // Reload the page to reinitialize everything
      window.location.reload();
    } catch (error) {
      console.error('Failed to reset data:', error);
      alert('Failed to reset data. Please close all tabs and try again, or clear your browser data manually.');
      setResetting(false);
    }
  };

  // Show error state with reset button
  if (dbError) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white p-4">
        <div className="max-w-md w-full text-center">
          <div className="mb-4">
            <svg className="mx-auto h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Database Error</h2>
          <p className="text-sm text-gray-600 mb-6">
            {dbError}
          </p>
          <p className="text-sm text-gray-600 mb-6">
            This might be due to a corrupted local database or an incompatible schema.
            You can try resetting your local data to fix this issue.
          </p>
          <button
            onClick={handleResetData}
            disabled={resetting}
            className="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {resetting ? 'Resetting...' : 'Reset Local Data'}
          </button>
          <p className="mt-4 text-xs text-gray-500">
            This will delete all local data and reload the page.
          </p>
        </div>
      </div>
    );
  }

  if (loading || !dbReady) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-black"></div>
          <p className="mt-4 text-sm text-gray-600">Loading wallet...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {!user ? <LoginScreen /> : <WalletDashboard />}
    </div>
  );
}

function App() {
  return (
    <AuthProvider>
      <AppContent />
    </AuthProvider>
  );
}

export default App;
