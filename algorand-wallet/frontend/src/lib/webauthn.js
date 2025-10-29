/**
 * WebAuthn / Passkey utilities
 * Handles registration and authentication of passkeys
 */

const rpName = 'Algorand Passkey Wallet';
const rpID = typeof window !== 'undefined' ? window.location.hostname : 'localhost';

/**
 * Check if WebAuthn is supported
 */
export function isWebAuthnSupported() {
  return !!(
    window.PublicKeyCredential &&
    navigator.credentials &&
    navigator.credentials.create &&
    navigator.credentials.get
  );
}

/**
 * Convert string to ArrayBuffer
 */
function str2ab(str) {
  const encoder = new TextEncoder();
  return encoder.encode(str);
}

/**
 * Convert ArrayBuffer to base64
 */
function arrayBufferToBase64(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary);
}

/**
 * Convert base64 to ArrayBuffer
 */
function base64ToArrayBuffer(base64) {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

/**
 * Register a new passkey
 */
export async function registerPasskey(username) {
  if (!isWebAuthnSupported()) {
    throw new Error('WebAuthn is not supported in this browser');
  }

  // Generate a random user ID
  const userId = crypto.getRandomValues(new Uint8Array(32));

  const publicKeyCredentialCreationOptions = {
    challenge: crypto.getRandomValues(new Uint8Array(32)),
    rp: {
      name: rpName,
      id: rpID,
    },
    user: {
      id: userId,
      name: username,
      displayName: username,
    },
    pubKeyCredParams: [
      { alg: -7, type: 'public-key' }, // ES256
      { alg: -257, type: 'public-key' }, // RS256
    ],
    authenticatorSelection: {
      authenticatorAttachment: 'platform',
      userVerification: 'required',
      requireResidentKey: true,
      residentKey: 'required',
    },
    timeout: 60000,
    attestation: 'none',
  };

  try {
    const credential = await navigator.credentials.create({
      publicKey: publicKeyCredentialCreationOptions,
    });

    if (!credential) {
      throw new Error('Failed to create credential');
    }

    // Store credential info
    const credentialData = {
      id: credential.id,
      rawId: arrayBufferToBase64(credential.rawId),
      type: credential.type,
      username,
      userId: arrayBufferToBase64(userId),
      createdAt: new Date().toISOString(),
    };

    return credentialData;
  } catch (error) {
    console.error('Passkey registration error:', error);
    throw error;
  }
}

/**
 * Authenticate with passkey
 */
export async function authenticatePasskey() {
  if (!isWebAuthnSupported()) {
    throw new Error('WebAuthn is not supported in this browser');
  }

  const publicKeyCredentialRequestOptions = {
    challenge: crypto.getRandomValues(new Uint8Array(32)),
    rpId: rpID,
    timeout: 60000,
    userVerification: 'required',
  };

  try {
    const credential = await navigator.credentials.get({
      publicKey: publicKeyCredentialRequestOptions,
    });

    if (!credential) {
      throw new Error('Authentication failed');
    }

    return {
      id: credential.id,
      rawId: arrayBufferToBase64(credential.rawId),
      type: credential.type,
    };
  } catch (error) {
    console.error('Passkey authentication error:', error);
    throw error;
  }
}

/**
 * Check if user has stored credentials
 */
export async function hasStoredCredentials() {
  try {
    const credentials = localStorage.getItem('passkeyCredential');
    return !!credentials;
  } catch {
    return false;
  }
}

/**
 * Store credential info
 */
export function storeCredentialInfo(credentialData) {
  localStorage.setItem('passkeyCredential', JSON.stringify(credentialData));
}

/**
 * Get stored credential info
 */
export function getStoredCredentialInfo() {
  const data = localStorage.getItem('passkeyCredential');
  return data ? JSON.parse(data) : null;
}

/**
 * Clear stored credentials
 */
export function clearStoredCredentials() {
  localStorage.removeItem('passkeyCredential');
}
