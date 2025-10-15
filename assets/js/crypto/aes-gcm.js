/**
 * ============================================
 * AES-GCM ENCRYPTION MODULE
 * ============================================
 * Client-side encryption using Web Crypto API
 * AES-GCM 256-bit for files and messages
 */

class AESGCMCrypto {
    constructor() {
        this.algorithm = 'AES-GCM';
        this.keyLength = 256;
        this.ivLength = 12; // 96 bits for GCM
    }

    /**
     * Generate a random AES-256 key
     * @returns {Promise<CryptoKey>}
     */
    async generateKey() {
        return await crypto.subtle.generateKey(
            {
                name: this.algorithm,
                length: this.keyLength
            },
            true, // extractable
            ['encrypt', 'decrypt']
        );
    }

    /**
     * Export key to raw format (for wrapping)
     * @param {CryptoKey} key
     * @returns {Promise<ArrayBuffer>}
     */
    async exportKey(key) {
        return await crypto.subtle.exportKey('raw', key);
    }

    /**
     * Import key from raw format
     * @param {ArrayBuffer} keyData
     * @returns {Promise<CryptoKey>}
     */
    async importKey(keyData) {
        return await crypto.subtle.importKey(
            'raw',
            keyData,
            {
                name: this.algorithm,
                length: this.keyLength
            },
            true,
            ['encrypt', 'decrypt']
        );
    }

    /**
     * Encrypt data (file/message)
     * @param {ArrayBuffer|Blob} data - Data to encrypt
     * @param {CryptoKey} key - AES key
     * @returns {Promise<{ciphertext: ArrayBuffer, iv: Uint8Array, tag: string}>}
     */
    async encrypt(data, key) {
        // Generate random IV
        const iv = crypto.getRandomValues(new Uint8Array(this.ivLength));

        // Convert Blob to ArrayBuffer if needed
        let dataBuffer = data;
        if (data instanceof Blob) {
            dataBuffer = await data.arrayBuffer();
        }

        // Encrypt
        const ciphertext = await crypto.subtle.encrypt(
            {
                name: this.algorithm,
                iv: iv
            },
            key,
            dataBuffer
        );

        // Extract authentication tag (last 16 bytes)
        const ciphertextArray = new Uint8Array(ciphertext);
        const tagStart = ciphertextArray.length - 16;
        const tag = this.arrayBufferToBase64(ciphertextArray.slice(tagStart));

        return {
            ciphertext,
            iv,
            tag
        };
    }

    /**
     * Decrypt data
     * @param {ArrayBuffer} ciphertext - Encrypted data
     * @param {CryptoKey} key - AES key
     * @param {Uint8Array} iv - Initialization vector
     * @returns {Promise<ArrayBuffer>}
     */
    async decrypt(ciphertext, key, iv) {
        try {
            const plaintext = await crypto.subtle.decrypt(
                {
                    name: this.algorithm,
                    iv: iv
                },
                key,
                ciphertext
            );
            return plaintext;
        } catch (error) {
            console.error('Decryption failed:', error);
            throw new Error('Failed to decrypt data. Key or IV may be incorrect.');
        }
    }

    /**
     * Encrypt a file/blob for upload
     * @param {Blob} file
     * @param {CryptoKey} [key] - Optional key, generates new if not provided
     * @returns {Promise<{encryptedBlob: Blob, key: ArrayBuffer, iv: Uint8Array, metadata: Object}>}
     */
    async encryptFile(file, key = null) {
        // Generate key if not provided
        if (!key) {
            key = await this.generateKey();
        }

        // Encrypt file
        const { ciphertext, iv, tag } = await this.encrypt(file, key);

        // Export key for wrapping
        const keyData = await this.exportKey(key);

        // Create encrypted blob
        const encryptedBlob = new Blob([ciphertext], { type: 'application/octet-stream' });

        return {
            encryptedBlob,
            key: keyData,
            iv,
            metadata: {
                originalSize: file.size,
                originalType: file.type,
                encryptedSize: encryptedBlob.size,
                tag,
                algorithm: this.algorithm,
                ivBase64: this.arrayBufferToBase64(iv)
            }
        };
    }

    /**
     * Decrypt a file/blob after download
     * @param {Blob} encryptedBlob
     * @param {ArrayBuffer} keyData
     * @param {Uint8Array} iv
     * @param {string} originalType
     * @returns {Promise<Blob>}
     */
    async decryptFile(encryptedBlob, keyData, iv, originalType = 'application/octet-stream') {
        // Import key
        const key = await this.importKey(keyData);

        // Decrypt
        const ciphertext = await encryptedBlob.arrayBuffer();
        const plaintext = await this.decrypt(ciphertext, key, iv);

        // Return decrypted blob
        return new Blob([plaintext], { type: originalType });
    }

    /**
     * Utility: ArrayBuffer to Base64
     * @param {ArrayBuffer|Uint8Array} buffer
     * @returns {string}
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    /**
     * Utility: Base64 to ArrayBuffer
     * @param {string} base64
     * @returns {Uint8Array}
     */
    base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }
}

// Make available globally
window.AESGCMCrypto = AESGCMCrypto;
