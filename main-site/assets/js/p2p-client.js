/**
 * ============================================
 * P2P CLIENT SIMPLIFICADO
 * ============================================
 * Sistema P2P descentralizado usando:
 * - PHP + MySQL para mensajería
 * - Pinata IPFS para almacenamiento
 * - Smart Wallets para todo
 */

class P2PClient {
    constructor() {
        this.isConnected = false;
        this.userId = null;
        this.publicKey = null;
        this.privateKey = null;
        this.callbacks = new Map();
        this.pollInterval = null;
        this.lastMessageId = 0;
        
        this.config = {
            apiBase: '/api/p2p',
            pollDelay: 3000 // Poll cada 3 segundos
        };
    }

    /**
     * Inicializar P2P Client
     */
    async init(userId) {
        try {
            console.log('🔧 Inicializando P2P Client...');
            this.userId = userId;
            
            // Generar o cargar llaves de encriptación
            await this.initCrypto();
            
            // Guardar llave pública
            await this.savePublicKey();
            
            // Iniciar polling
            this.startPolling();
            
            this.isConnected = true;
            console.log('✅ P2P Client iniciado');
            this.emit('connected', { userId });
            
            return true;
        } catch (error) {
            console.error('❌ Error inicializando P2P:', error);
            return false;
        }
    }

    /**
     * Inicializar criptografía
     */
    async initCrypto() {
        const stored = localStorage.getItem(`p2p_keys_${this.userId}`);
        
        if (stored) {
            const keys = JSON.parse(stored);
            this.publicKey = keys.publicKey;
            this.privateKey = await crypto.subtle.importKey(
                'jwk',
                keys.privateKey,
                { name: 'RSA-OAEP', hash: 'SHA-256' },
                false,
                ['decrypt']
            );
            console.log('🔑 Llaves cargadas');
        } else {
            // Generar nuevas llaves
            const keyPair = await crypto.subtle.generateKey(
                {
                    name: 'RSA-OAEP',
                    modulusLength: 2048,
                    publicExponent: new Uint8Array([1, 0, 1]),
                    hash: 'SHA-256'
                },
                true,
                ['encrypt', 'decrypt']
            );
            
            this.privateKey = keyPair.privateKey;
            
            // Exportar llave pública
            const publicKeySpki = await crypto.subtle.exportKey('spki', keyPair.publicKey);
            this.publicKey = this.arrayBufferToBase64(publicKeySpki);
            
            // Exportar llave privada
            const privateKeyJwk = await crypto.subtle.exportKey('jwk', keyPair.privateKey);
            
            // Guardar
            localStorage.setItem(`p2p_keys_${this.userId}`, JSON.stringify({
                publicKey: this.publicKey,
                privateKey: privateKeyJwk
            }));
            
            console.log('🔑 Nuevas llaves generadas');
        }
    }

    /**
     * Guardar llave pública en servidor
     */
    async savePublicKey() {
        try {
            const response = await fetch(`${this.config.apiBase}/save-public-key.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ publicKey: this.publicKey }),
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error('Error guardando llave pública');
            }
            
            console.log('✅ Llave pública guardada');
        } catch (error) {
            console.error('Error guardando llave pública:', error);
            throw error;
        }
    }

    /**
     * Enviar mensaje P2P
     */
    async send(recipientId, message, metadata = {}) {
        try {
            // Obtener llave pública del destinatario
            const response = await fetch(`${this.config.apiBase}/public-key.php?userId=${recipientId}`, {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (!data.success || !data.publicKey) {
                throw new Error('Destinatario no encontrado');
            }
            
            // Encriptar mensaje
            const encrypted = await this.encryptMessage(message, data.publicKey);
            
            // Enviar al servidor
            const sendResponse = await fetch(`${this.config.apiBase}/send.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    recipientId: recipientId,
                    encryptedData: encrypted,
                    metadata: metadata
                })
            });
            
            const result = await sendResponse.json();
            
            if (result.success) {
                console.log('✅ Mensaje enviado');
                this.emit('messageSent', { recipientId, message });
            }
            
            return result.success;
        } catch (error) {
            console.error('Error enviando mensaje:', error);
            return false;
        }
    }

    /**
     * Encriptar mensaje
     */
    async encryptMessage(message, recipientPublicKeyB64) {
        // Importar llave pública del destinatario
        const publicKeyBuffer = this.base64ToArrayBuffer(recipientPublicKeyB64);
        const recipientPublicKey = await crypto.subtle.importKey(
            'spki',
            publicKeyBuffer,
            { name: 'RSA-OAEP', hash: 'SHA-256' },
            false,
            ['encrypt']
        );
        
        // Encriptar
        const encoder = new TextEncoder();
        const messageBuffer = encoder.encode(JSON.stringify(message));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'RSA-OAEP' },
            recipientPublicKey,
            messageBuffer
        );
        
        return this.arrayBufferToBase64(encrypted);
    }

    /**
     * Desencriptar mensaje
     */
    async decryptMessage(encryptedB64) {
        try {
            const encryptedBuffer = this.base64ToArrayBuffer(encryptedB64);
            const decrypted = await crypto.subtle.decrypt(
                { name: 'RSA-OAEP' },
                this.privateKey,
                encryptedBuffer
            );
            
            const decoder = new TextDecoder();
            const message = decoder.decode(decrypted);
            return JSON.parse(message);
        } catch (error) {
            console.error('Error desencriptando:', error);
            return null;
        }
    }

    /**
     * Polling para recibir mensajes
     */
    startPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        this.pollInterval = setInterval(async () => {
            await this.checkNewMessages();
        }, this.config.pollDelay);
        
        // Primera verificación inmediata
        this.checkNewMessages();
    }

    /**
     * Verificar nuevos mensajes
     */
    async checkNewMessages() {
        try {
            const response = await fetch(
                `${this.config.apiBase}/receive.php?lastId=${this.lastMessageId}`,
                { credentials: 'include' }
            );
            
            const data = await response.json();
            
            if (data.success && data.messages && data.messages.length > 0) {
                for (const msg of data.messages) {
                    // Actualizar último ID
                    if (msg.id > this.lastMessageId) {
                        this.lastMessageId = msg.id;
                    }
                    
                    // Desencriptar
                    const decrypted = await this.decryptMessage(msg.encrypted_data);
                    
                    if (decrypted) {
                        this.emit('message', {
                            from: msg.sender_id,
                            data: decrypted,
                            metadata: msg.metadata ? JSON.parse(msg.metadata) : {},
                            timestamp: msg.created_at
                        });
                    }
                }
            }
        } catch (error) {
            console.error('Error verificando mensajes:', error);
        }
    }

    /**
     * Subir archivo a IPFS (Pinata)
     */
    async uploadToIPFS(file, metadata = {}) {
        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('metadata', JSON.stringify(metadata));
            
            const response = await fetch('/api/ipfs/upload.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('✅ Archivo subido a IPFS:', data.ipfsHash);
                return {
                    ipfsHash: data.ipfsHash,
                    url: `https://gateway.pinata.cloud/ipfs/${data.ipfsHash}`
                };
            }
            
            throw new Error(data.message || 'Error subiendo a IPFS');
        } catch (error) {
            console.error('Error subiendo a IPFS:', error);
            throw error;
        }
    }

    /**
     * Crear post P2P
     */
    async createPost(content, images = []) {
        try {
            // Subir imágenes a IPFS
            const imageHashes = [];
            for (const img of images) {
                const result = await this.uploadToIPFS(img, { type: 'post-image' });
                imageHashes.push(result.ipfsHash);
            }
            
            // Crear metadata del post
            const postData = {
                type: 'post',
                content: content,
                images: imageHashes,
                timestamp: Date.now(),
                author: this.userId
            };
            
            // Guardar en servidor
            const response = await fetch(`${this.config.apiBase}/store-metadata.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    type: 'post',
                    metadata: postData
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Post creado');
                this.emit('postCreated', postData);
                return postData;
            }
            
            throw new Error(result.message || 'Error creando post');
        } catch (error) {
            console.error('Error creando post:', error);
            throw error;
        }
    }

    /**
     * Obtener posts P2P
     */
    async getPosts(limit = 20, offset = 0) {
        try {
            const response = await fetch(
                `${this.config.apiBase}/get-metadata.php?type=post&limit=${limit}&offset=${offset}`,
                { credentials: 'include' }
            );
            
            const data = await response.json();
            
            if (data.success) {
                return data.items || [];
            }
            
            return [];
        } catch (error) {
            console.error('Error obteniendo posts:', error);
            return [];
        }
    }

    /**
     * Registrar callback para evento
     */
    on(event, callback) {
        if (!this.callbacks.has(event)) {
            this.callbacks.set(event, []);
        }
        this.callbacks.get(event).push(callback);
    }

    /**
     * Emitir evento
     */
    emit(event, data) {
        const callbacks = this.callbacks.get(event);
        if (callbacks) {
            callbacks.forEach(cb => {
                try {
                    cb(data);
                } catch (error) {
                    console.error(`Error en callback de ${event}:`, error);
                }
            });
        }
    }

    /**
     * Desconectar
     */
    disconnect() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        this.isConnected = false;
        console.log('👋 P2P desconectado');
    }

    // ============================================
    // UTILIDADES
    // ============================================

    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    base64ToArrayBuffer(base64) {
        const binary = window.atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }
}

// ============================================
// INSTANCIA GLOBAL
// ============================================

window.P2PClient = P2PClient;
window.p2pClient = new P2PClient();

// Auto-inicializar si está en modo P2P
document.addEventListener('DOMContentLoaded', async () => {
    const userId = sessionStorage.getItem('user_id');
    const p2pMode = localStorage.getItem('p2pMode') === 'true';
    
    if (userId && p2pMode) {
        console.log('🚀 Auto-inicializando P2P...');
        try {
            await window.p2pClient.init(parseInt(userId));
        } catch (error) {
            console.error('Error auto-inicializando P2P:', error);
        }
    }
});

console.log('📦 P2PClient cargado y disponible globalmente');
