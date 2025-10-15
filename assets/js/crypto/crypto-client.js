/**
 * ============================================
 * CRYPTO CLIENT - Cliente de Cifrado E2E
 * ============================================
 * Módulo completo para gestionar cifrado cliente
 * - Generación de claves AES-GCM y X25519
 * - Cifrado/descifrado de archivos
 * - Key wrapping con tweetnacl
 * - Upload a /pin-proxy
 * - Storage seguro en IndexedDB
 * - Export/Import de claves
 */

class CryptoClient {
    constructor() {
        this.db = null;
        this.dbName = 'SocialMaskCrypto';
        this.dbVersion = 1;
        this.userId = null;
        this.aesGCM = new AESGCMCrypto();
        this.x25519 = new X25519Wrapper();
        this.p2pMetadata = new P2PMetadataManager();
    }

    /**
     * Inicializar IndexedDB y P2P Metadata Manager
     */
    async init(userId) {
        this.userId = userId;
        
        return new Promise(async (resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = async () => {
                this.db = request.result;
                
                // Inicializar P2P Metadata Manager
                await this.p2pMetadata.init(userId);
                
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Store para claves X25519
                if (!db.objectStoreNames.contains('x25519Keys')) {
                    const keyStore = db.createObjectStore('x25519Keys', { keyPath: 'userId' });
                    keyStore.createIndex('userId', 'userId', { unique: true });
                }

                // Store para archivos temporales (thumbnails, etc)
                if (!db.objectStoreNames.contains('tempFiles')) {
                    const tempStore = db.createObjectStore('tempFiles', { keyPath: 'id', autoIncrement: true });
                    tempStore.createIndex('timestamp', 'timestamp');
                }

                // Store para metadata de archivos cifrados
                if (!db.objectStoreNames.contains('fileMetadata')) {
                    const metaStore = db.createObjectStore('fileMetadata', { keyPath: 'cid' });
                    metaStore.createIndex('userId', 'userId');
                    metaStore.createIndex('timestamp', 'timestamp');
                }
            };
        });
    }

    /**
     * Generar o recuperar par de claves X25519 del usuario
     */
    async getOrGenerateKeyPair() {
        // Intentar cargar desde IndexedDB
        const stored = await this.loadKeyPairFromDB();
        if (stored) {
            console.log('✅ Loaded X25519 keypair from IndexedDB');
            return stored;
        }

        // Generar nuevo par de claves
        console.log('🔑 Generating new X25519 keypair...');
        const keyPair = this.x25519.generateKeyPair();

        // Guardar en IndexedDB
        await this.saveKeyPairToDB(keyPair);

        // Subir clave pública al servidor
        await this.uploadPublicKey(keyPair.publicKey);

        return keyPair;
    }

    /**
     * Guardar par de claves en IndexedDB
     */
    async saveKeyPairToDB(keyPair) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['x25519Keys'], 'readwrite');
            const store = transaction.objectStore('x25519Keys');

            const data = {
                userId: this.userId,
                publicKey: this.x25519.toBase64(keyPair.publicKey),
                secretKey: this.x25519.toBase64(keyPair.secretKey),
                timestamp: Date.now()
            };

            const request = store.put(data);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Cargar par de claves desde IndexedDB
     */
    async loadKeyPairFromDB() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['x25519Keys'], 'readonly');
            const store = transaction.objectStore('x25519Keys');
            const request = store.get(this.userId);

            request.onsuccess = () => {
                const result = request.result;
                if (result) {
                    resolve({
                        publicKey: this.x25519.fromBase64(result.publicKey),
                        secretKey: this.x25519.fromBase64(result.secretKey)
                    });
                } else {
                    resolve(null);
                }
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Subir clave pública al servidor
     */
    async uploadPublicKey(publicKey) {
        try {
            const response = await fetch('/api/messaging/upload-x25519-key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: this.userId,
                    public_key: this.x25519.toBase64(publicKey)
                })
            });

            const result = await response.json();
            if (!result.success) {
                console.error('Failed to upload public key:', result.message);
            }
            return result.success;
        } catch (error) {
            console.error('Error uploading public key:', error);
            return false;
        }
    }

    /**
     * Obtener clave pública de un usuario
     */
    async getPublicKey(userId) {
        try {
            const response = await fetch(`/api/messaging/get-x25519-key.php?user_id=${userId}`);
            const result = await response.json();

            if (result.success && result.public_key) {
                return this.x25519.fromBase64(result.public_key);
            }
            return null;
        } catch (error) {
            console.error('Error fetching public key:', error);
            return null;
        }
    }

    /**
     * Generar thumbnail local (sin exponerlo sin cifrar)
     */
    async generateThumbnail(file, maxWidth = 200, maxHeight = 200) {
        return new Promise((resolve, reject) => {
            if (!file.type.startsWith('image/')) {
                resolve(null);
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    // Calcular dimensiones manteniendo aspect ratio
                    if (width > height) {
                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convertir a blob
                    canvas.toBlob((blob) => {
                        resolve(blob);
                    }, 'image/jpeg', 0.7);
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Cifrar y subir archivo
     */
    async encryptAndUploadFile(file, recipientIds, options = {}) {
        try {
            const {
                generateThumbnail = true,
                onProgress = null
            } = options;

            // 1. Generar thumbnail si es imagen (antes de cifrar)
            let thumbnailCid = null;
            if (generateThumbnail && file.type.startsWith('image/')) {
                if (onProgress) onProgress({ step: 'thumbnail', progress: 0 });
                
                const thumbnail = await this.generateThumbnail(file);
                if (thumbnail) {
                    const thumbResult = await this.encryptAndUploadFile(thumbnail, recipientIds, {
                        generateThumbnail: false // No generar thumbnail del thumbnail
                    });
                    thumbnailCid = thumbResult.cid;
                }
            }

            // 2. Generar clave AES aleatoria
            if (onProgress) onProgress({ step: 'encrypt', progress: 10 });
            const aesKey = await this.aesGCM.generateKey();

            // 3. Cifrar archivo con AES-GCM
            const encrypted = await this.aesGCM.encryptFile(file, aesKey);
            
            if (onProgress) onProgress({ step: 'encrypt', progress: 50 });

            // 4. Obtener claves públicas de los recipients
            const recipientPublicKeys = await Promise.all(
                recipientIds.map(async (id) => ({
                    id,
                    publicKey: await this.getPublicKey(id)
                }))
            );

            // Verificar que todos los recipients tienen clave pública
            const missingKeys = recipientPublicKeys.filter(r => !r.publicKey);
            if (missingKeys.length > 0) {
                throw new Error(`Recipients without public key: ${missingKeys.map(r => r.id).join(', ')}`);
            }

            // 5. Envolver clave AES para cada recipient
            if (onProgress) onProgress({ step: 'wrap', progress: 60 });
            const wrappedKeys = this.x25519.wrapKeyForMultiple(encrypted.key, recipientPublicKeys);

            // 6. Subir blob cifrado a Pinata vía /pin-proxy
            if (onProgress) onProgress({ step: 'upload', progress: 70 });
            const uploadResult = await this.uploadEncryptedBlob(encrypted.encryptedBlob, {
                originalType: file.type,
                originalSize: file.size,
                encryptedSize: encrypted.encryptedBlob.size,
                algorithm: 'AES-GCM-256',
                iv: encrypted.metadata.ivBase64,
                tag: encrypted.metadata.tag,
                thumbnailCid
            });

            if (!uploadResult.success) {
                throw new Error('Upload failed: ' + uploadResult.message);
            }

            // 7. Obtener clave pública del sender
            const myKeyPair = await this.getOrGenerateKeyPair();
            const senderPub = this.x25519.toBase64(myKeyPair.publicKey);

            // 8. Almacenar metadata en P2P system con política de acceso
            if (onProgress) onProgress({ step: 'metadata', progress: 90 });
            const metadataResult = await this.p2pMetadata.storeMetadata({
                cid: uploadResult.cid,
                iv: encrypted.metadata.ivBase64,
                senderPub,
                senderId: this.userId,
                recipients: recipientIds,
                wrappedKeys: wrappedKeys.reduce((acc, wk) => {
                    acc[wk.recipientId] = {
                        wrappedKey: wk.wrappedKey,
                        ephemeralPublicKey: wk.ephemeralPublicKey,
                        nonce: wk.nonce
                    };
                    return acc;
                }, {}),
                ts: Date.now(),
                meta: {
                    originalType: file.type,
                    originalSize: file.size,
                    originalName: file.name,
                    thumbnailCid
                }
            });

            if (!metadataResult.success) {
                console.warn('Failed to store metadata in P2P:', metadataResult.message);
            }

            // 9. Indexar en backend PHP (solo metadatos públicos, sin IV ni claves)
            await this.indexFileInBackend({
                cid: uploadResult.cid,
                file_name: file.name,
                file_type: file.type,
                file_size: file.size,
                recipients: recipientIds,
                thumbnail_cid: thumbnailCid
            });

            // 10. Guardar metadata local en IndexedDB
            await this.saveFileMetadataLocal({
                cid: uploadResult.cid,
                userId: this.userId,
                fileName: file.name,
                fileType: file.type,
                fileSize: file.size,
                iv: encrypted.metadata.ivBase64,
                thumbnailCid,
                timestamp: Date.now()
            });

            if (onProgress) onProgress({ step: 'complete', progress: 100 });

            return {
                success: true,
                cid: uploadResult.cid,
                size: uploadResult.size,
                gatewayUrl: uploadResult.gateway_url,
                thumbnailCid,
                metadata: metadataResult
            };

        } catch (error) {
            console.error('Error in encryptAndUploadFile:', error);
            throw error;
        }
    }

    /**
     * Subir blob cifrado a /pin-proxy
     */
    async uploadEncryptedBlob(blob, metadata) {
        const formData = new FormData();
        formData.append('encrypted_file', blob, 'encrypted_file.bin');
        formData.append('metadata', JSON.stringify(metadata));

        try {
            const response = await fetch('/api/pin-proxy.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error uploading to pin-proxy:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }

    /**
     * Indexar archivo en backend PHP (solo metadatos públicos)
     */
    async indexFileInBackend(data) {
        try {
            const response = await fetch('/api/file-index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (result.success) {
                console.log('✅ File indexed in backend:', data.cid);
            } else {
                console.warn('⚠️ Failed to index file:', result.message);
            }

            return result;
        } catch (error) {
            console.error('Error indexing file:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Almacenar metadata en backend P2P
     */
    async storeMetadata(data) {
        try {
            const response = await fetch('http://localhost:3088/p2p/metadata/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            return await response.json();
        } catch (error) {
            console.error('Error storing metadata:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Guardar metadata local en IndexedDB
     */
    async saveFileMetadataLocal(data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['fileMetadata'], 'readwrite');
            const store = transaction.objectStore('fileMetadata');
            const request = store.put(data);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Descargar y descifrar archivo con verificación de acceso P2P
     */
    async downloadAndDecryptFile(cid) {
        try {
            // 1. Obtener metadata desde P2P system con verificación de acceso
            const metadata = await this.p2pMetadata.getMetadata(cid, true);

            // 2. Obtener wrapped key para este usuario (ya verificado por P2P Manager)
            const wrappedKeyData = this.p2pMetadata.getWrappedKeyForCurrentUser(metadata);

            // 3. Descargar blob cifrado desde Pinata
            const blobResponse = await fetch(`https://gateway.pinata.cloud/ipfs/${cid}`);
            const encryptedBlob = await blobResponse.blob();

            // 4. Obtener clave privada del usuario
            const myKeyPair = await this.getOrGenerateKeyPair();

            // 5. Desenvolver clave AES
            const aesKeyBytes = this.x25519.unwrapKey(
                this.x25519.fromBase64(wrappedKeyData.wrappedKey),
                this.x25519.fromBase64(wrappedKeyData.ephemeralPublicKey),
                this.x25519.fromBase64(wrappedKeyData.nonce),
                myKeyPair.secretKey
            );

            if (!aesKeyBytes) {
                throw new Error('Failed to unwrap AES key');
            }

            // 6. Descifrar archivo
            const decryptedBlob = await this.aesGCM.decryptFile(
                encryptedBlob,
                aesKeyBytes.buffer,
                this.aesGCM.base64ToArrayBuffer(metadata.iv),
                metadata.meta.originalType || 'application/octet-stream'
            );

            return {
                success: true,
                blob: decryptedBlob,
                fileName: metadata.meta.originalName || 'download',
                fileType: metadata.meta.originalType,
                thumbnailCid: metadata.meta.thumbnailCid
            };

        } catch (error) {
            console.error('Error downloading/decrypting file:', error);
            throw error;
        }
    }

    /**
     * Exportar claves (cifradas con password)
     */
    async exportKeys(password) {
        try {
            // Obtener keypair
            const keyPair = await this.loadKeyPairFromDB();
            if (!keyPair) {
                throw new Error('No keys to export');
            }

            // Datos a exportar
            const exportData = {
                userId: this.userId,
                publicKey: this.x25519.toBase64(keyPair.publicKey),
                secretKey: this.x25519.toBase64(keyPair.secretKey),
                timestamp: Date.now(),
                version: 1
            };

            // Derivar clave desde password
            const passwordKey = await this.deriveKeyFromPassword(password);

            // Cifrar datos con AES-GCM
            const dataStr = JSON.stringify(exportData);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const encrypted = await this.aesGCM.encryptFile(dataBlob, passwordKey);

            // Crear blob exportable
            const exportBlob = new Blob([
                JSON.stringify({
                    encrypted: this.aesGCM.arrayBufferToBase64(encrypted.ciphertext),
                    iv: encrypted.metadata.ivBase64,
                    tag: encrypted.metadata.tag,
                    version: 1
                })
            ], { type: 'application/json' });

            return exportBlob;

        } catch (error) {
            console.error('Error exporting keys:', error);
            throw error;
        }
    }

    /**
     * Importar claves (descifradas con password)
     */
    async importKeys(file, password) {
        try {
            // Leer archivo
            const fileText = await file.text();
            const importData = JSON.parse(fileText);

            // Derivar clave desde password
            const passwordKey = await this.deriveKeyFromPassword(password);

            // Descifrar datos
            const encryptedData = this.aesGCM.base64ToArrayBuffer(importData.encrypted);
            const iv = this.aesGCM.base64ToArrayBuffer(importData.iv);

            const decryptedBlob = await this.aesGCM.decryptFile(
                new Blob([encryptedData]),
                await this.aesGCM.exportKey(passwordKey),
                iv,
                'application/json'
            );

            const decryptedText = await decryptedBlob.text();
            const keyData = JSON.parse(decryptedText);

            // Validar datos
            if (!keyData.publicKey || !keyData.secretKey || !keyData.userId) {
                throw new Error('Invalid key file format');
            }

            // Importar keypair
            const keyPair = {
                publicKey: this.x25519.fromBase64(keyData.publicKey),
                secretKey: this.x25519.fromBase64(keyData.secretKey)
            };

            // Guardar en IndexedDB
            this.userId = keyData.userId;
            await this.saveKeyPairToDB(keyPair);

            // Subir clave pública al servidor
            await this.uploadPublicKey(keyPair.publicKey);

            return {
                success: true,
                userId: keyData.userId
            };

        } catch (error) {
            console.error('Error importing keys:', error);
            throw error;
        }
    }

    /**
     * Derivar clave desde password usando PBKDF2
     */
    async deriveKeyFromPassword(password) {
        const encoder = new TextEncoder();
        const passwordBuffer = encoder.encode(password);

        // Importar password como material de clave
        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            passwordBuffer,
            { name: 'PBKDF2' },
            false,
            ['deriveBits', 'deriveKey']
        );

        // Derivar clave AES usando PBKDF2
        const salt = encoder.encode('SocialMask-KeyExport-v1'); // Salt fijo para export/import
        const derivedKey = await crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt,
                iterations: 100000,
                hash: 'SHA-256'
            },
            keyMaterial,
            { name: 'AES-GCM', length: 256 },
            true,
            ['encrypt', 'decrypt']
        );

        return derivedKey;
    }

    /**
     * Limpiar archivos temporales antiguos
     */
    async cleanupOldTempFiles(maxAgeMs = 24 * 60 * 60 * 1000) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['tempFiles'], 'readwrite');
            const store = transaction.objectStore('tempFiles');
            const index = store.index('timestamp');
            
            const cutoff = Date.now() - maxAgeMs;
            const range = IDBKeyRange.upperBound(cutoff);
            
            const request = index.openCursor(range);
            let deleted = 0;

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    cursor.delete();
                    deleted++;
                    cursor.continue();
                } else {
                    console.log(`🧹 Cleaned up ${deleted} old temp files`);
                    resolve(deleted);
                }
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Obtener estadísticas de uso
     */
    async getStats() {
        const stats = {
            hasKeyPair: false,
            fileMetadataCount: 0,
            tempFilesCount: 0
        };

        try {
            // Verificar keypair
            const keyPair = await this.loadKeyPairFromDB();
            stats.hasKeyPair = !!keyPair;

            // Contar file metadata
            const metaCount = await new Promise((resolve) => {
                const transaction = this.db.transaction(['fileMetadata'], 'readonly');
                const store = transaction.objectStore('fileMetadata');
                const request = store.count();
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => resolve(0);
            });
            stats.fileMetadataCount = metaCount;

            // Contar temp files
            const tempCount = await new Promise((resolve) => {
                const transaction = this.db.transaction(['tempFiles'], 'readonly');
                const store = transaction.objectStore('tempFiles');
                const request = store.count();
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => resolve(0);
            });
            stats.tempFilesCount = tempCount;

        } catch (error) {
            console.error('Error getting stats:', error);
        }

        return stats;
    }
}

// Hacer disponible globalmente
window.CryptoClient = CryptoClient;
