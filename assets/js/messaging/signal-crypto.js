/**
 * ============================================
 * SIGNAL PROTOCOL CRYPTO WRAPPER
 * ============================================
 * Wrapper para libsignal-protocol con API simplificada
 */

class SignalCrypto {
    constructor() {
        this.store = null;
        this.initialized = false;
    }

    /**
     * Inicializar Signal Protocol
     */
    async init(userId) {
        try {
            // Crear store para keys
            this.store = new SignalProtocolStore(userId);
            
            // Generar identity key si no existe
            if (!await this.store.getIdentityKeyPair()) {
                const identityKeyPair = await this.generateIdentityKeyPair();
                await this.store.storeIdentityKeyPair(identityKeyPair);
                
                // Generar registration ID
                const registrationId = this.generateRegistrationId();
                await this.store.storeLocalRegistrationId(registrationId);
            }

            // Generar pre-keys si no existen
            await this.generatePreKeys();
            
            this.initialized = true;
            console.log('✅ Signal Protocol initialized for user:', userId);
            
            return true;
        } catch (error) {
            console.error('❌ Failed to initialize Signal Protocol:', error);
            return false;
        }
    }

    /**
     * Generar identity key pair
     */
    async generateIdentityKeyPair() {
        const keyPair = await libsignal.KeyHelper.generateIdentityKeyPair();
        return keyPair;
    }

    /**
     * Generar registration ID
     */
    generateRegistrationId() {
        return libsignal.KeyHelper.generateRegistrationId();
    }

    /**
     * Generar pre-keys
     */
    async generatePreKeys() {
        try {
            const preKeyId = Math.floor(Math.random() * 16777215); // 24-bit number
            
            // Generar signed pre-key
            const identityKeyPair = await this.store.getIdentityKeyPair();
            const signedPreKey = await libsignal.KeyHelper.generateSignedPreKey(
                identityKeyPair,
                preKeyId
            );
            await this.store.storeSignedPreKey(signedPreKey.keyId, signedPreKey.keyPair);

            // Generar one-time pre-keys (100 keys)
            const preKeys = await libsignal.KeyHelper.generatePreKeys(preKeyId, 100);
            for (const preKey of preKeys) {
                await this.store.storePreKey(preKey.keyId, preKey.keyPair);
            }

            console.log('✅ Generated', preKeys.length, 'pre-keys');
            
            return {
                identityKey: identityKeyPair.pubKey,
                signedPreKey: {
                    keyId: signedPreKey.keyId,
                    publicKey: signedPreKey.keyPair.pubKey,
                    signature: signedPreKey.signature
                },
                preKeys: preKeys.map(pk => ({
                    keyId: pk.keyId,
                    publicKey: pk.keyPair.pubKey
                }))
            };
        } catch (error) {
            console.error('❌ Failed to generate pre-keys:', error);
            throw error;
        }
    }

    /**
     * Crear sesión con otro usuario
     */
    async createSession(recipientId, preKeyBundle) {
        try {
            const address = new libsignal.SignalProtocolAddress(recipientId, 1);
            const sessionBuilder = new libsignal.SessionBuilder(this.store, address);
            
            await sessionBuilder.processPreKey({
                registrationId: preKeyBundle.registrationId,
                identityKey: preKeyBundle.identityKey,
                signedPreKey: {
                    keyId: preKeyBundle.signedPreKey.keyId,
                    publicKey: preKeyBundle.signedPreKey.publicKey,
                    signature: preKeyBundle.signedPreKey.signature
                },
                preKey: preKeyBundle.preKey
            });

            console.log('✅ Session created with user:', recipientId);
            return true;
        } catch (error) {
            console.error('❌ Failed to create session:', error);
            return false;
        }
    }

    /**
     * Encriptar mensaje
     */
    async encryptMessage(recipientId, plaintext) {
        try {
            if (!this.initialized) {
                throw new Error('Signal Protocol not initialized');
            }

            const address = new libsignal.SignalProtocolAddress(recipientId, 1);
            const sessionCipher = new libsignal.SessionCipher(this.store, address);
            
            // Convertir texto a bytes
            const plaintextBytes = new TextEncoder().encode(plaintext);
            
            // Encriptar
            const ciphertext = await sessionCipher.encrypt(plaintextBytes);
            
            return {
                type: ciphertext.type,
                body: this.arrayBufferToBase64(ciphertext.body),
                registrationId: await this.store.getLocalRegistrationId()
            };
        } catch (error) {
            console.error('❌ Encryption failed:', error);
            throw error;
        }
    }

    /**
     * Desencriptar mensaje
     */
    async decryptMessage(senderId, ciphertext) {
        try {
            if (!this.initialized) {
                throw new Error('Signal Protocol not initialized');
            }

            const address = new libsignal.SignalProtocolAddress(senderId, 1);
            const sessionCipher = new libsignal.SessionCipher(this.store, address);
            
            // Convertir de base64 a bytes
            const ciphertextBytes = this.base64ToArrayBuffer(ciphertext.body);
            
            // Desencriptar según tipo
            let plaintext;
            if (ciphertext.type === 3) {
                // PreKeyWhisperMessage
                plaintext = await sessionCipher.decryptPreKeyWhisperMessage(ciphertextBytes);
            } else {
                // WhisperMessage
                plaintext = await sessionCipher.decryptWhisperMessage(ciphertextBytes);
            }
            
            // Convertir bytes a texto
            return new TextDecoder().decode(plaintext);
        } catch (error) {
            console.error('❌ Decryption failed:', error);
            throw error;
        }
    }

    /**
     * Verificar fingerprint de identidad
     */
    async getFingerprint(userId, theirIdentityKey) {
        try {
            const ourIdentityKey = await this.store.getIdentityKeyPair();
            
            const generator = new libsignal.FingerprintGenerator(5200);
            const fingerprint = generator.createFor(
                this.store.userId,
                ourIdentityKey.pubKey,
                userId,
                theirIdentityKey
            );
            
            return {
                displayable: fingerprint.displayableFingerprint(),
                scannable: fingerprint.scannableFingerprint()
            };
        } catch (error) {
            console.error('❌ Failed to generate fingerprint:', error);
            throw error;
        }
    }

    /**
     * Verificar sesión existente
     */
    async hasSession(userId) {
        try {
            const address = new libsignal.SignalProtocolAddress(userId, 1);
            const sessionRecord = await this.store.loadSession(address.toString());
            return sessionRecord !== undefined;
        } catch (error) {
            return false;
        }
    }

    /**
     * Eliminar sesión
     */
    async deleteSession(userId) {
        try {
            const address = new libsignal.SignalProtocolAddress(userId, 1);
            await this.store.deleteSession(address.toString());
            console.log('✅ Session deleted with user:', userId);
            return true;
        } catch (error) {
            console.error('❌ Failed to delete session:', error);
            return false;
        }
    }

    /**
     * Helpers
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }
}

/**
 * ============================================
 * SIGNAL PROTOCOL STORE
 * ============================================
 * Almacenamiento de keys y sessions usando IndexedDB
 */

class SignalProtocolStore {
    constructor(userId) {
        this.userId = userId;
        this.dbName = `signal_store_${userId}`;
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, 1);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Object stores
                if (!db.objectStoreNames.contains('identityKeys')) {
                    db.createObjectStore('identityKeys');
                }
                if (!db.objectStoreNames.contains('preKeys')) {
                    db.createObjectStore('preKeys');
                }
                if (!db.objectStoreNames.contains('signedPreKeys')) {
                    db.createObjectStore('signedPreKeys');
                }
                if (!db.objectStoreNames.contains('sessions')) {
                    db.createObjectStore('sessions');
                }
            };
        });
    }

    // Identity Key methods
    async getIdentityKeyPair() {
        await this.ensureDB();
        return this.get('identityKeys', 'identityKeyPair');
    }

    async storeIdentityKeyPair(keyPair) {
        await this.ensureDB();
        return this.put('identityKeys', 'identityKeyPair', keyPair);
    }

    async getLocalRegistrationId() {
        await this.ensureDB();
        return this.get('identityKeys', 'registrationId');
    }

    async storeLocalRegistrationId(registrationId) {
        await this.ensureDB();
        return this.put('identityKeys', 'registrationId', registrationId);
    }

    // PreKey methods
    async loadPreKey(keyId) {
        await this.ensureDB();
        return this.get('preKeys', keyId);
    }

    async storePreKey(keyId, keyPair) {
        await this.ensureDB();
        return this.put('preKeys', keyId, keyPair);
    }

    async removePreKey(keyId) {
        await this.ensureDB();
        return this.delete('preKeys', keyId);
    }

    // Signed PreKey methods
    async loadSignedPreKey(keyId) {
        await this.ensureDB();
        return this.get('signedPreKeys', keyId);
    }

    async storeSignedPreKey(keyId, keyPair) {
        await this.ensureDB();
        return this.put('signedPreKeys', keyId, keyPair);
    }

    // Session methods
    async loadSession(identifier) {
        await this.ensureDB();
        return this.get('sessions', identifier);
    }

    async storeSession(identifier, record) {
        await this.ensureDB();
        return this.put('sessions', identifier, record);
    }

    async deleteSession(identifier) {
        await this.ensureDB();
        return this.delete('sessions', identifier);
    }

    // Helper methods
    async ensureDB() {
        if (!this.db) {
            await this.init();
        }
    }

    async get(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async put(storeName, key, value) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(value, key);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve();
        });
    }

    async delete(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve();
        });
    }
}

// Export
window.SignalCrypto = SignalCrypto;
window.SignalProtocolStore = SignalProtocolStore;
