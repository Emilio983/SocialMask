import { API_BASE_URL } from '../utils/config';
import { bufferToBase64, base64ToBuffer } from '../utils/encoding';

// Check if WebAuthn is supported
export function isWebAuthnSupported() {
  return window.PublicKeyCredential !== undefined &&
         navigator.credentials !== undefined;
}

// Register new user with Passkey
export async function register() {
  if (!isWebAuthnSupported()) {
    throw new Error('WebAuthn is not supported in this browser');
  }

  // 1. Create user on backend
  const userResponse = await fetch(`${API_BASE_URL}/users`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
  });

  if (!userResponse.ok) {
    throw new Error('Failed to create user');
  }

  const { id: userId } = await userResponse.json();

  // 2. Create WebAuthn credential
  const challenge = crypto.getRandomValues(new Uint8Array(32));

  const publicKeyCredentialCreationOptions = {
    challenge,
    rp: {
      name: 'Zcash Wallet',
      id: window.location.hostname,
    },
    user: {
      id: new TextEncoder().encode(userId),
      name: `user-${userId.substring(0, 8)}`,
      displayName: `Zcash User ${userId.substring(0, 8)}`,
    },
    pubKeyCredParams: [
      { alg: -7, type: 'public-key' },  // ES256
      { alg: -257, type: 'public-key' }, // RS256
    ],
    authenticatorSelection: {
      authenticatorAttachment: 'platform',
      requireResidentKey: true,
      residentKey: 'required',
      userVerification: 'required',
    },
    timeout: 60000,
    attestation: 'none',
  };

  const credential = await navigator.credentials.create({
    publicKey: publicKeyCredentialCreationOptions,
  });

  if (!credential) {
    throw new Error('Failed to create credential');
  }

  // 3. Register credential with backend
  const credentialResponse = await fetch(`${API_BASE_URL}/credentials`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      userId,
      credentialId: bufferToBase64(credential.rawId),
      publicKey: bufferToBase64(credential.response.getPublicKey()),
      deviceName: navigator.userAgent,
    }),
  });

  if (!credentialResponse.ok) {
    throw new Error('Failed to register credential');
  }

  return {
    userId,
    credentialId: bufferToBase64(credential.rawId),
  };
}

// Login with existing Passkey
export async function login() {
  if (!isWebAuthnSupported()) {
    throw new Error('WebAuthn is not supported in this browser');
  }

  const challenge = crypto.getRandomValues(new Uint8Array(32));

  const publicKeyCredentialRequestOptions = {
    challenge,
    timeout: 60000,
    userVerification: 'required',
    rpId: window.location.hostname,
  };

  const assertion = await navigator.credentials.get({
    publicKey: publicKeyCredentialRequestOptions,
  });

  if (!assertion) {
    throw new Error('Authentication failed');
  }

  const credentialId = bufferToBase64(assertion.rawId);

  // Get credential from backend to retrieve userId
  const credentialResponse = await fetch(
    `${API_BASE_URL}/credentials/${credentialId}`
  );

  if (!credentialResponse.ok) {
    throw new Error('Credential not found');
  }

  const credential = await credentialResponse.json();

  return {
    userId: credential.user_id,
    credentialId,
  };
}

// Logout
export async function logout() {
  // Clear session data
  // In this implementation, we just clear IndexedDB
  // No backend session to clear
  return Promise.resolve();
}
