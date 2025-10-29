// Use relative path for API calls (proxied by nginx)
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';
export const LIGHTWALLETD_URL = import.meta.env.VITE_LIGHTWALLETD_URL || 'http://localhost:9068';
export const NETWORK = import.meta.env.VITE_NETWORK || 'mainnet';
