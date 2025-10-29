/**
 * ============================================
 * X25519 KEY WRAPPING MODULE
 * ============================================
 * Uses TweetNaCl for X25519 ECDH key agreement
 * Wraps AES-GCM keys for secure transmission
 */

class X25519Wrapper {
    constructor() {
        // Check if nacl is loaded
        if (typeof nacl === 'undefined') {
            throw new Error('TweetNaCl not loaded. Include tweetnacl.min.js before this script.');
        }
        this.nonceLength = 24; // For nacl.secretbox
    }

    /**
     * Generate X25519 keypair
     * @returns {{publicKey: Uint8Array, secretKey: Uint8Array}}
     */
    generateKeyPair() {
        return nacl.box.keyPair();
    }

    /**
     * Derive shared secret using ECDH
     * @param {Uint8Array} recipientPublicKey
     * @param {Uint8Array} senderSecretKey
     * @returns {Uint8Array}
     */
    deriveSharedSecret(recipientPublicKey, senderSecretKey) {
        return nacl.box.before(recipientPublicKey, senderSecretKey);
    }

    /**
     * Wrap AES key using X25519
     * @param {ArrayBuffer} aesKeyData - Raw AES-256 key (32 bytes)
     * @param {Uint8Array} recipientPublicKey - Recipient's X25519 public key
     * @returns {{wrappedKey: Uint8Array, ephemeralPublicKey: Uint8Array, nonce: Uint8Array}}
     */
    wrapKey(aesKeyData, recipientPublicKey) {
        // Generate ephemeral keypair
        const ephemeralKeyPair = this.generateKeyPair();

        // Derive shared secret
        const sharedSecret = this.deriveSharedSecret(
            recipientPublicKey,
            ephemeralKeyPair.secretKey
        );

        // Generate nonce
        const nonce = nacl.randomBytes(this.nonceLength);

        // Convert AES key to Uint8Array
        const aesKeyBytes = new Uint8Array(aesKeyData);

        // Wrap key using secretbox
        const wrappedKey = nacl.secretbox(aesKeyBytes, nonce, sharedSecret);

        return {
            wrappedKey,
            ephemeralPublicKey: ephemeralKeyPair.publicKey,
            nonce
        };
    }

    /**
     * Unwrap AES key using X25519
     * @param {Uint8Array} wrappedKey - Wrapped AES key
     * @param {Uint8Array} ephemeralPublicKey - Sender's ephemeral public key
     * @param {Uint8Array} nonce - Nonce used for wrapping
     * @param {Uint8Array} recipientSecretKey - Recipient's X25519 secret key
     * @returns {Uint8Array|null} - Unwrapped AES key or null if failed
     */
    unwrapKey(wrappedKey, ephemeralPublicKey, nonce, recipientSecretKey) {
        // Derive shared secret
        const sharedSecret = this.deriveSharedSecret(
            ephemeralPublicKey,
            recipientSecretKey
        );

        // Unwrap key
        const aesKeyBytes = nacl.secretbox.open(wrappedKey, nonce, sharedSecret);

        if (!aesKeyBytes) {
            console.error('Failed to unwrap key');
            return null;
        }

        return aesKeyBytes;
    }

    /**
     * Wrap key for multiple recipients
     * @param {ArrayBuffer} aesKeyData
     * @param {Array<{id: string, publicKey: Uint8Array}>} recipients
     * @returns {Array<{recipientId: string, wrappedKey: string, ephemeralPublicKey: string, nonce: string}>}
     */
    wrapKeyForMultiple(aesKeyData, recipients) {
        return recipients.map(recipient => {
            const { wrappedKey, ephemeralPublicKey, nonce } = this.wrapKey(
                aesKeyData,
                recipient.publicKey
            );

            return {
                recipientId: recipient.id,
                wrappedKey: this.toBase64(wrappedKey),
                ephemeralPublicKey: this.toBase64(ephemeralPublicKey),
                nonce: this.toBase64(nonce)
            };
        });
    }

    /**
     * Store keypair in localStorage
     * @param {Object} keyPair - {publicKey, secretKey}
     * @param {string} userId
     */
    storeKeyPair(keyPair, userId) {
        localStorage.setItem(`x25519_publicKey_${userId}`, this.toBase64(keyPair.publicKey));
        localStorage.setItem(`x25519_secretKey_${userId}`, this.toBase64(keyPair.secretKey));
    }

    /**
     * Load keypair from localStorage
     * @param {string} userId
     * @returns {{publicKey: Uint8Array, secretKey: Uint8Array}|null}
     */
    loadKeyPair(userId) {
        const publicKeyB64 = localStorage.getItem(`x25519_publicKey_${userId}`);
        const secretKeyB64 = localStorage.getItem(`x25519_secretKey_${userId}`);

        if (!publicKeyB64 || !secretKeyB64) {
            return null;
        }

        return {
            publicKey: this.fromBase64(publicKeyB64),
            secretKey: this.fromBase64(secretKeyB64)
        };
    }

    /**
     * Utility: Uint8Array to Base64
     * @param {Uint8Array} bytes
     * @returns {string}
     */
    toBase64(bytes) {
        return nacl.util.encodeBase64(bytes);
    }

    /**
     * Utility: Base64 to Uint8Array
     * @param {string} base64
     * @returns {Uint8Array}
     */
    fromBase64(base64) {
        return nacl.util.decodeBase64(base64);
    }

    /**
     * Get or generate keypair for current user
     * @param {string} userId
     * @returns {{publicKey: Uint8Array, secretKey: Uint8Array}}
     */
    async getOrGenerateKeyPair(userId) {
        let keyPair = this.loadKeyPair(userId);

        if (!keyPair) {
            // Generate new keypair
            keyPair = this.generateKeyPair();
            this.storeKeyPair(keyPair, userId);

            // Upload public key to server
            await this.uploadPublicKey(userId, keyPair.publicKey);
        }

        return keyPair;
    }

    /**
     * Upload public key to server
     * @param {string} userId
     * @param {Uint8Array} publicKey
     */
    async uploadPublicKey(userId, publicKey) {
        try {
            const response = await fetch('/api/messaging/upload-x25519-key.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    public_key: this.toBase64(publicKey)
                })
            });

            const result = await response.json();
            if (!result.success) {
                console.error('Failed to upload public key:', result.message);
            }
        } catch (error) {
            console.error('Error uploading public key:', error);
        }
    }

    /**
     * Fetch recipient's public key from server
     * @param {string} recipientId
     * @returns {Promise<Uint8Array|null>}
     */
    async fetchPublicKey(recipientId) {
        try {
            const response = await fetch(`/api/messaging/get-x25519-key.php?user_id=${recipientId}`);
            const result = await response.json();

            if (result.success && result.public_key) {
                return this.fromBase64(result.public_key);
            }

            return null;
        } catch (error) {
            console.error('Error fetching public key:', error);
            return null;
        }
    }
}

// Make available globally
window.X25519Wrapper = X25519Wrapper;
