import { createContext, useContext, useState, useEffect } from 'react';
import { register, login, logout as authLogout } from '../services/webauthn';
import { getUser, saveUser, isDBReady, initDB } from '../services/indexeddb';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Wait for IndexedDB to be ready before checking for stored user
    const checkStoredUser = async () => {
      try {
        // Ensure DB is initialized
        if (!isDBReady()) {
          await initDB();
        }

        // Check if user is already logged in (stored in IndexedDB)
        const storedUser = await getUser();
        if (storedUser) {
          setUser(storedUser);
        }
      } catch (error) {
        console.error('Error checking stored user:', error);
        // Don't throw - just continue without a stored user
      } finally {
        setLoading(false);
      }
    };

    checkStoredUser();
  }, []);

  const handleRegister = async () => {
    try {
      const newUser = await register();
      await saveUser(newUser);
      setUser(newUser);
      return newUser;
    } catch (error) {
      console.error('Registration failed:', error);
      throw error;
    }
  };

  const handleLogin = async () => {
    try {
      const existingUser = await login();
      await saveUser(existingUser);
      setUser(existingUser);
      return existingUser;
    } catch (error) {
      console.error('Login failed:', error);
      throw error;
    }
  };

  const handleLogout = async () => {
    await authLogout();
    setUser(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        register: handleRegister,
        login: handleLogin,
        logout: handleLogout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}
