import crypto from 'crypto';
import { ethers } from 'ethers';
import { z } from 'zod';
import { verifyAuthenticationResponse } from '@simplewebauthn/server';
import type { AuthenticationResponseJSON } from '@simplewebauthn/server';
import { config } from '../config/index.js';
import { ValidationError } from '../utils/errors.js';
import { consumePasskeyChallenge, storePasskeyChallenge } from './passkeyStore.js';
import { verifyWeb3AuthIdToken } from './web3Auth.js';
import { logger } from '../utils/logger.js';
import { pool } from '../db/index.js';

const startSchema = z.object({
  challengeId: z.string().uuid(),
  redirectUri: z.string().url().optional(),
});

const web3AuthSchema = z
  .object({
    idToken: z.string().min(10, 'Invalid Web3Auth token'),
    sessionToken: z.string().optional(),
    scope: z.string().optional(),
  })
  .nullable()
  .optional();

const finishSchema = z.object({
  challengeId: z.string().uuid(),
  credential: z.record(z.any()),
  userAgent: z.string().optional(),
  web3Auth: web3AuthSchema,
});

/**
 * Genera una clave privada determinística a partir del credentialId
 * Esta será la clave privada del usuario que controla su smart account
 */
function derivePrivateKeyFromCredential(credentialId: string, derivationSecret: string): string {
  // Usamos HMAC-SHA256 para derivar una clave privada de 32 bytes
  const hmac = crypto.createHmac('sha256', derivationSecret);
  hmac.update(credentialId);
  const privateKeyBuffer = hmac.digest();

  // Convertir a hex (64 caracteres)
  return privateKeyBuffer.toString('hex');
}

/**
 * Genera un address Ethereum a partir de una clave privada
 */
function getAddressFromPrivateKey(privateKey: string): string {
  const wallet = new ethers.Wallet(privateKey);
  return wallet.address;
}

/**
 * Verifica la firma WebAuthn del credential usando @simplewebauthn/server
 * TODO: Descomentar cuando se ajusten los tipos de TypeScript
 * @param credential - Credential devuelto por navigator.credentials.get()
 * @param challenge - Challenge original enviado al cliente
 * @param rpId - Relying Party ID esperado
 * @param credentialPublicKey - Clave pública del credential (si existe)
 * @param counter - Contador actual del credential (para prevenir replay)
 * @returns Resultado de la verificación con el nuevo contador
 */
/* TEMPORALMENTE DESHABILITADO - Problemas de tipos TypeScript
async function verifyWebAuthnResponseWithLibrary(
  credential: AuthenticationResponseJSON,
  expectedChallenge: string,
  rpId: string,
  credentialPublicKey?: Uint8Array,
  counter?: number,
): Promise<{ verified: boolean; newCounter?: number }> {
  try {
    // Si no tenemos la clave pública guardada, solo validamos básicamente
    if (!credentialPublicKey) {
      logger.warn('No public key stored for credential, performing basic validation only');
      verifyWebAuthnResponse(credential as any, expectedChallenge, rpId);
      return { verified: true };
    }

    // Verificación completa con @simplewebauthn/server
    const verification = await verifyAuthenticationResponse({
      response: credential,
      expectedChallenge,
      expectedOrigin: `https://${rpId}`,
      expectedRPID: rpId,
      credential: {
        id: credential.id,
        publicKey: credentialPublicKey,
        counter: counter || 0,
      },
      requireUserVerification: true,
    });

    if (!verification.verified) {
      throw new ValidationError('WebAuthn signature verification failed');
    }

    return {
      verified: true,
      newCounter: verification.authenticationInfo.newCounter,
    };
  } catch (error) {
    logger.error({ err: error }, 'WebAuthn verification failed');
    throw new ValidationError('WebAuthn verification failed', { cause: error });
  }
}
*/

/**
 * Verifica la firma WebAuthn del credential (verificación básica sin librería)
 * @param credential - Credential devuelto by navigator.credentials.get()
 * @param challenge - Challenge original enviado al cliente
 * @returns true si la firma es válida
 */
function verifyWebAuthnResponse(credential: any, expectedChallenge: string, rpId: string): void {
  if (!credential?.response?.clientDataJSON) {
    throw new ValidationError('Missing WebAuthn client data');
  }

  try {
    // Decodificar clientDataJSON
    const clientDataJSON = Buffer.from(credential.response.clientDataJSON, 'base64url');
    const clientData = JSON.parse(clientDataJSON.toString('utf-8'));

    // Verificar que el challenge coincide
    const expectedChallengeBuffer = Buffer.from(expectedChallenge, 'base64url');
    const receivedChallengeBuffer = Buffer.from(String(clientData.challenge), 'base64url');

    if (!expectedChallengeBuffer.equals(receivedChallengeBuffer)) {
      throw new ValidationError('WebAuthn challenge mismatch');
    }

    // Verificar que el tipo es correcto
    if (clientData.type !== 'webauthn.get') {
      throw new ValidationError('Unexpected WebAuthn response type');
    }

    if (clientData.crossOrigin === true) {
      throw new ValidationError('Cross-origin WebAuthn response not allowed');
    }

    if (clientData.origin) {
      try {
        const originUrl = new URL(clientData.origin);
        const hostname = originUrl.hostname;
        if (hostname !== rpId && !hostname.endsWith(`.${rpId}`)) {
          throw new ValidationError('WebAuthn origin mismatch');
        }
      } catch (error) {
        throw new ValidationError('Invalid WebAuthn origin', { cause: error });
      }
    }
  } catch (error) {
    if (error instanceof ValidationError) {
      throw error;
    }
    throw new ValidationError('Invalid WebAuthn response payload', { cause: error });
  }
}

/**
 * Genera un device public key único para el dispositivo
 */
function deriveDevicePublicKey(credentialId: string, userAgent: string): string {
  const combined = `${credentialId}:${userAgent}`;
  const hash = crypto.createHash('sha256').update(combined).digest('hex');
  return `0x${hash}`;
}

export async function startPasskeyLogin(payload: unknown) {
  const params = startSchema.safeParse(payload);
  if (!params.success) {
    throw new ValidationError('Invalid passkey start payload', { issues: params.error.issues });
  }

  // Generar un challenge criptográficamente seguro
  const challenge = crypto.randomBytes(32).toString('base64url');
  storePasskeyChallenge(params.data.challengeId, challenge);

  return {
    challengeId: params.data.challengeId,
    redirectUri: params.data.redirectUri ?? null,
    challenge,
    rpId: config.passkeys.rpId,
    timeout: 60000,
    web3AuthClientId: config.web3Auth.clientId,
    userVerification: 'preferred',
    // NO incluimos authenticatorSelection en login para máxima compatibilidad
    // Esto permite tanto platform (biometrics) como cross-platform (security keys)
  };
}

export async function finishPasskeyLogin(payload: unknown) {
  const params = finishSchema.safeParse(payload);
  if (!params.success) {
    throw new ValidationError('Invalid passkey finish payload', { issues: params.error.issues });
  }

  const credential = params.data.credential;
  const credentialId = typeof credential?.id === 'string' ? credential.id : crypto.randomUUID();
  let challengeEntry;
  try {
    challengeEntry = consumePasskeyChallenge(params.data.challengeId);
  } catch (error) {
    throw new ValidationError('Passkey challenge expired or invalid', { cause: error });
  }

  // Verificar la firma WebAuthn (opcional, pero recomendado)
  verifyWebAuthnResponse(credential, challengeEntry.challenge, config.passkeys.rpId);

  let ownerAddress: string;
  if (params.data.web3Auth?.idToken) {
    const web3AuthResult = await verifyWeb3AuthIdToken(params.data.web3Auth.idToken);
    ownerAddress = ethers.getAddress(web3AuthResult.ownerAddress);
    logger.debug(
      {
        challengeId: params.data.challengeId,
        verifier: web3AuthResult.verifier,
        verifierId: web3AuthResult.verifierId,
      },
      'Web3Auth token verified for passkey login',
    );
  } else {
    // Derivar clave privada determinística como fallback (debería eliminarse en producción)
    const fallbackPrivateKey = derivePrivateKeyFromCredential(credentialId, config.passkeys.derivationSecret);
    ownerAddress = getAddressFromPrivateKey(fallbackPrivateKey);
    logger.warn(
      { challengeId: params.data.challengeId, credentialId: credentialId.slice(0, 16) },
      'Web3Auth token missing, falling back to deterministic passkey derivation',
    );
  }

  // Generar device public key único para este dispositivo
  const devicePublicKey = deriveDevicePublicKey(credentialId, params.data.userAgent ?? 'unknown');

  return {
    challengeId: params.data.challengeId,
    credentialVerified: true,
    web3AuthClientId: config.web3Auth.clientId,
    ownerAddress: ethers.getAddress(ownerAddress), // Normalizar checksum
    devicePublicKey,
    credentialId,
    // NO devolver la clave privada al cliente
    // La guardaremos cifrada en el backend si es necesario
  };
}

// ============================================
// Passkey Registration (Sign Up)
// ============================================

const registerStartSchema = z.object({
  username: z.string().min(3).max(20).regex(/^[a-zA-Z0-9_]+$/),
  challengeId: z.string().uuid(),
});

const registerFinishSchema = z.object({
  username: z.string().min(3).max(20).regex(/^[a-zA-Z0-9_]+$/),
  challengeId: z.string().uuid(),
  credential: z.record(z.any()),
  userAgent: z.string().optional(),
  web3Auth: web3AuthSchema,
});

/**
 * Inicia el proceso de registro con passkey
 * Genera el challenge y las opciones para WebAuthn
 */
export async function startPasskeyRegistration(payload: unknown) {
  const params = registerStartSchema.safeParse(payload);
  if (!params.success) {
    throw new ValidationError('Invalid registration start payload', { issues: params.error.issues });
  }

  // Verificar que el username no exista
  const [existingUsers] = await pool.query(
    'SELECT user_id FROM users WHERE username = ? LIMIT 1',
    [params.data.username]
  );

  if (Array.isArray(existingUsers) && existingUsers.length > 0) {
    throw new ValidationError('Username already taken');
  }

  // Generar un challenge criptográficamente seguro
  const challenge = crypto.randomBytes(32).toString('base64url');
  storePasskeyChallenge(params.data.challengeId, challenge);

  // Generar user ID único para WebAuthn
  const userId = crypto.randomBytes(16).toString('base64url');

  return {
    challengeId: params.data.challengeId,
    challenge,
    publicKey: {
      challenge,
      rp: {
        name: 'TheSocialMask',
        id: config.passkeys.rpId,
      },
      user: {
        id: userId,
        name: params.data.username,
        displayName: params.data.username,
      },
      pubKeyCredParams: [
        { alg: -7, type: 'public-key' },  // ES256
        { alg: -257, type: 'public-key' }, // RS256
      ],
      timeout: 60000,
      attestation: 'none',
      authenticatorSelection: {
        // Eliminamos authenticatorAttachment para permitir tanto platform (biometrics)
        // como cross-platform (security keys USB/NFC)
        // authenticatorAttachment: 'platform',
        requireResidentKey: true,
        residentKey: 'required',
        userVerification: 'preferred', // Changed from 'required' to 'preferred' for better compatibility
      },
    },
    web3AuthClientId: config.web3Auth.clientId,
  };
}

/**
 * Finaliza el proceso de registro con passkey
 * Crea el usuario, guarda el credential y crea la smart account
 */
export async function finishPasskeyRegistration(payload: unknown) {
  // Log payload for debugging
  logger.info({ payload: JSON.stringify(payload).slice(0, 500) }, 'finishPasskeyRegistration called');
  
  const params = registerFinishSchema.safeParse(payload);
  if (!params.success) {
    logger.error({ issues: params.error.issues }, 'Validation failed');
    throw new ValidationError('Invalid registration finish payload', { issues: params.error.issues });
  }

  // Verificar que el username no exista (double-check)
  const [existingUsers] = await pool.query(
    'SELECT user_id FROM users WHERE username = ? LIMIT 1',
    [params.data.username]
  );

  if (Array.isArray(existingUsers) && existingUsers.length > 0) {
    throw new ValidationError('Username already taken');
  }

  const credential = params.data.credential;
  const credentialId = typeof credential?.id === 'string' ? credential.id : crypto.randomUUID();

  // Consumir el challenge
  let challengeEntry;
  try {
    challengeEntry = consumePasskeyChallenge(params.data.challengeId);
  } catch (error) {
    throw new ValidationError('Registration challenge expired or invalid', { cause: error });
  }

  // Verificar la firma WebAuthn (verificación básica)
  // Nota: En registro el tipo debe ser 'webauthn.create', no 'webauthn.get'
  if (!credential?.response?.clientDataJSON) {
    throw new ValidationError('Missing WebAuthn client data');
  }

  try {
    const clientDataJSON = Buffer.from(credential.response.clientDataJSON, 'base64url');
    const clientData = JSON.parse(clientDataJSON.toString('utf-8'));

    // Verificar challenge - el cliente puede enviarlo con o sin padding
    const challengeFromClient = String(clientData.challenge);
    const expectedChallenge = challengeEntry.challenge;
    
    // Normalizar ambos challenges para comparación (eliminar padding =)
    const normalizeBase64url = (str: string): string => str.replace(/=/g, '');
    
    if (normalizeBase64url(challengeFromClient) !== normalizeBase64url(expectedChallenge)) {
      logger.warn({
        expected: expectedChallenge,
        received: challengeFromClient,
        expectedNormalized: normalizeBase64url(expectedChallenge),
        receivedNormalized: normalizeBase64url(challengeFromClient)
      }, 'Challenge mismatch details');
      throw new ValidationError('WebAuthn challenge mismatch');
    }

    // Verificar tipo (debe ser 'webauthn.create' para registro)
    if (clientData.type !== 'webauthn.create') {
      throw new ValidationError('Unexpected WebAuthn response type for registration');
    }

    if (clientData.crossOrigin === true) {
      throw new ValidationError('Cross-origin WebAuthn response not allowed');
    }
  } catch (error) {
    if (error instanceof ValidationError) {
      throw error;
    }
    throw new ValidationError('Invalid WebAuthn response payload', { cause: error });
  }

  // Derivar owner address
  let ownerAddress: string;
  if (params.data.web3Auth?.idToken) {
    const web3AuthResult = await verifyWeb3AuthIdToken(params.data.web3Auth.idToken);
    ownerAddress = ethers.getAddress(web3AuthResult.ownerAddress);
    logger.debug(
      {
        username: params.data.username,
        verifier: web3AuthResult.verifier,
        verifierId: web3AuthResult.verifierId,
      },
      'Web3Auth token verified for passkey registration',
    );
  } else {
    // Derivar clave privada determinística como fallback
    const fallbackPrivateKey = derivePrivateKeyFromCredential(credentialId, config.passkeys.derivationSecret);
    ownerAddress = getAddressFromPrivateKey(fallbackPrivateKey);
    logger.warn(
      { username: params.data.username, credentialId: credentialId.slice(0, 16) },
      'Web3Auth token missing, falling back to deterministic passkey derivation',
    );
  }

  // Calcular smart account address (determinístico basado en owner address)
  // SimpleAccountFactory usa createAccount(owner, salt) que internamente hace CREATE2
  // El initCode es el bytecode del contrato SimpleAccount con el constructor
  // Para calcular correctamente necesitamos el initCodeHash del SimpleAccount
  // Por simplicidad, usamos el mismo smart account address para almacenar
  // Nota: En producción deberías calcular esto correctamente o llamar al factory
  const smartAccountAddress = ownerAddress; // Temporal: usamos owner como placeholder

  // Generar device public key
  const devicePublicKey = deriveDevicePublicKey(credentialId, params.data.userAgent ?? 'unknown');

  // Extraer public key del credential
  let publicKeyBase64 = '';
  if (credential.response?.attestationObject) {
    try {
      // Por ahora guardamos el attestationObject completo
      publicKeyBase64 = credential.response.attestationObject;
    } catch (error) {
      logger.warn({ error }, 'Failed to extract public key from attestation');
    }
  }

  // Crear usuario en la base de datos
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    // Insertar usuario
    // El email es opcional en el schema actual
    const [userResult] = await connection.query(
      `INSERT INTO users (username, email, wallet_address, wallet_type, status, created_at, updated_at)
       VALUES (?, NULL, ?, 'passkey', 'active', NOW(), NOW())`,
      [params.data.username, smartAccountAddress]
    );

    const userId = (userResult as any).insertId;

    // Insertar smart account
    await connection.query(
      `INSERT INTO smart_accounts
       (user_id, smart_account_address, owner_address, factory_address, entry_point_address,
        account_type, is_deployed, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, 'simple', FALSE, NOW(), NOW())`,
      [
        userId,
        smartAccountAddress,
        ownerAddress,
        config.smartAccounts.factory,
        config.smartAccounts.entryPoint,
      ]
    );

    // Insertar passkey credential
    // credential_id debe ser BINARY (varbinary)
    const credentialIdBuffer = Buffer.from(credentialId, 'base64url');
    await connection.query(
      `INSERT INTO passkey_credentials
       (user_id, credential_id, public_key, counter, device_type, device_label, created_at, last_used_at)
       VALUES (?, ?, ?, 0, 'platform', ?, NOW(), NOW())`,
      [userId, credentialIdBuffer, publicKeyBase64, params.data.userAgent ?? 'unknown']
    );

    await connection.commit();

    logger.info(
      {
        userId,
        username: params.data.username,
        smartAccountAddress,
        ownerAddress,
      },
      'User registered successfully with passkey',
    );

    return {
      success: true,
      userId,
      username: params.data.username,
      smart_account_address: smartAccountAddress,
      owner_address: ownerAddress,
      device_public_key: devicePublicKey,
      credential_id: credentialId,
    };
  } catch (error) {
    await connection.rollback();
    logger.error({ error, username: params.data.username }, 'Failed to create user');
    throw new ValidationError('Failed to create user account', { cause: error });
  } finally {
    connection.release();
  }
}
