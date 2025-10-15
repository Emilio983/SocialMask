/**
 * ============================================
 * P2P CLIENT - Sistema P2P PHP-Based
 * ============================================
 * Sistema P2P descentralizado usando PHP + MySQL + Pinata
 * Sin dependencia de Socket.io/Node.js
 */

class P2PClient {
    constructor() {
        this.isConnected = false;
        this.userId = null;
        this.publicKey = null;
        this.privateKey = null;
        this.callbacks = new Map();
        this.pollInterval = null;
        this.pollDelay = 2000; // Poll cada 2 segundos
        
        // Configuration
        this.config = {
            apiBase: window.location.origin + '/api/p2p'
        };
    }

    /**
     * Inicializar cliente P2P
     */
    async init(userId) {
        try {
            console.log('🔧 Inicializando P2P Client (PHP-Based)...');
            this.userId = userId;
            
            // Generar o cargar par de llaves E2E
            await this.initCrypto();
            
            // Guardar llave pública en servidor
            await this.savePublicKey();
            
            // Iniciar polling para nuevos mensajes
            this.startPolling();
            
            this.isConnected = true;
            console.log('✅ P2P Client inicializado correctamente');
            this.emit('connected', { userId: this.userId });
            
            return true;
        } catch (error) {
            console.error('❌ Error inicializando P2P Client:', error);
            this.isConnected = false;
            return false;
        }
    }

    /**
     * Inicializar criptografía E2E
     */
    async initCrypto() {
        try {
            // Buscar llaves existentes en localStorage
            const storedKeys = localStorage.getItem(`p2p_keys_${this.userId}`);
            
            if (storedKeys) {
                const keys = JSON.parse(storedKeys);
                this.publicKey = keys.publicKey;
                this.privateKey = await this.importPrivateKey(keys.privateKeyJwk);
                console.log('🔑 Llaves E2E cargadas desde localStorage');
            } else {
                // Generar nuevo par de llaves
                const keyPair = await window.crypto.subtle.generateKey(
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
                const publicKeyBuffer = await window.crypto.subtle.exportKey('spki', keyPair.publicKey);
                this.publicKey = this.arrayBufferToBase64(publicKeyBuffer);
                
                // Exportar llave privada para almacenar
                const privateKeyJwk = await window.crypto.subtle.exportKey('jwk', keyPair.privateKey);
                
                // Guardar en localStorage
                localStorage.setItem(`p2p_keys_${this.userId}`, JSON.stringify({
                    publicKey: this.publicKey,
                    privateKeyJwk: privateKeyJwk
                }));
                
                console.log('🔑 Nuevo par de llaves E2E generado');
            }
        } catch (error) {
            console.error('Error inicializando crypto:', error);
            throw error;
        }
    }

    /**
     * Conectar a servidor de señalización (Socket.IO)
     */
    async connectSignaling() {
        return new Promise((resolve, reject) => {
            try {
                // Socket.IO se cargará desde CDN en el HTML
                if (typeof io === 'undefined') {
                    console.warn('Socket.IO no está cargado, usando fallback a HTTP');
                    resolve();
                    return;
                }
                
                this.socket = io(this.config.socketUrl, {
                    auth: {
                        userId: this.userId,
                        publicKey: this.publicKey
                    }
                });
                
                this.socket.on('connect', () => {
                    console.log('✅ Conectado a servidor de señalización');
                    this.setupSocketListeners();
                    resolve();
                });
                
                this.socket.on('connect_error', (error) => {
                    console.error('Error conectando a servidor:', error);
                    // Continuar en modo HTTP fallback
                    resolve();
                });
                
            } catch (error) {
                console.error('Error en connectSignaling:', error);
                // Continuar en modo HTTP fallback
                resolve();
            }
        });
    }

    /**
     * Configurar listeners de Socket.IO
     */
    setupSocketListeners() {
        if (!this.socket) return;
        
        // Alguien quiere conectarse conmigo
        this.socket.on('peer-signal', async (data) => {
            console.log('📡 Señal de peer recibida:', data.from);
            await this.handlePeerSignal(data);
        });
        
        // Nuevo peer disponible
        this.socket.on('peer-online', (peerId) => {
            console.log('👤 Peer online:', peerId);
            this.emit('peer:online', peerId);
        });
        
        // Peer desconectado
        this.socket.on('peer-offline', (peerId) => {
            console.log('👋 Peer offline:', peerId);
            this.closePeerConnection(peerId);
            this.emit('peer:offline', peerId);
        });
        
        // Mensaje P2P recibido (fallback HTTP)
        this.socket.on('p2p-message', async (data) => {
            await this.handleMessage(data);
        });
    }

    /**
     * Crear conexión P2P con otro peer
     */
    async connectToPeer(targetUserId) {
        try {
            if (this.peers.has(targetUserId)) {
                console.log('Ya existe conexión con:', targetUserId);
                return this.peers.get(targetUserId);
            }
            
            console.log('🤝 Iniciando conexión P2P con:', targetUserId);
            
            // Si no hay SimplePeer, usar HTTP fallback
            if (typeof SimplePeer === 'undefined') {
                console.log('SimplePeer no disponible, usando HTTP fallback');
                return this.createHTTPFallback(targetUserId);
            }
            
            const peer = new SimplePeer({
                initiator: true,
                trickle: false,
                config: { iceServers: this.config.iceServers }
            });
            
            peer.on('signal', (signal) => {
                // Enviar señal al otro peer vía servidor
                if (this.socket) {
                    this.socket.emit('peer-signal', {
                        to: targetUserId,
                        from: this.userId,
                        signal: signal
                    });
                }
            });
            
            peer.on('connect', () => {
                console.log('✅ Conexión P2P establecida con:', targetUserId);
                this.emit('peer:connected', targetUserId);
            });
            
            peer.on('data', async (data) => {
                const message = JSON.parse(data.toString());
                await this.handleMessage(message);
            });
            
            peer.on('error', (error) => {
                console.error('Error en conexión P2P:', error);
                this.closePeerConnection(targetUserId);
            });
            
            this.peers.set(targetUserId, peer);
            return peer;
            
        } catch (error) {
            console.error('Error conectando a peer:', error);
            return this.createHTTPFallback(targetUserId);
        }
    }

    /**
     * Crear conexión HTTP fallback
     */
    createHTTPFallback(targetUserId) {
        const fallback = {
            send: async (data) => {
                // Enviar vía HTTP API
                return await this.sendViaAPI(targetUserId, data);
            },
            destroy: () => {
                this.peers.delete(targetUserId);
            }
        };
        
        this.peers.set(targetUserId, fallback);
        return fallback;
    }

    /**
     * Manejar señal de peer
     */
    async handlePeerSignal(data) {
        try {
            let peer = this.peers.get(data.from);
            
            if (!peer && typeof SimplePeer !== 'undefined') {
                // Crear nuevo peer (no iniciador)
                peer = new SimplePeer({
                    initiator: false,
                    trickle: false,
                    config: { iceServers: this.config.iceServers }
                });
                
                peer.on('signal', (signal) => {
                    if (this.socket) {
                        this.socket.emit('peer-signal', {
                            to: data.from,
                            from: this.userId,
                            signal: signal
                        });
                    }
                });
                
                peer.on('connect', () => {
                    console.log('✅ Conexión P2P establecida con:', data.from);
                    this.emit('peer:connected', data.from);
                });
                
                peer.on('data', async (msgData) => {
                    const message = JSON.parse(msgData.toString());
                    await this.handleMessage(message);
                });
                
                peer.on('error', (error) => {
                    console.error('Error en peer:', error);
                    this.closePeerConnection(data.from);
                });
                
                this.peers.set(data.from, peer);
            }
            
            if (peer && peer.signal) {
                peer.signal(data.signal);
            }
            
        } catch (error) {
            console.error('Error manejando señal de peer:', error);
        }
    }

    /**
     * Enviar mensaje P2P
     */
    async send(recipients, data, metadata = {}) {
        try {
            // Generar AES key para este mensaje
            const aesKey = await this.generateAESKey();
            
            // Encriptar data con AES
            const { ciphertext, iv } = await this.encryptWithAES(aesKey, JSON.stringify(data));
            
            // Envolver AES key para cada recipiente
            const wrappedKeys = {};
            for (const recipientId of recipients) {
                const recipientPublicKey = await this.getPublicKey(recipientId);
                if (recipientPublicKey) {
                    wrappedKeys[recipientId] = await this.wrapKey(aesKey, recipientPublicKey);
                }
            }
            
            // Crear paquete P2P
            const p2pPackage = {
                ciphertext: this.arrayBufferToBase64(ciphertext),
                iv: this.arrayBufferToBase64(iv),
                senderPub: this.publicKey,
                senderId: this.userId,
                recipients: recipients,
                wrappedKeys: wrappedKeys,
                ts: Date.now(),
                meta: metadata
            };
            
            // Guardar en backend (MySQL)
            const result = await this.storeMetadata(p2pPackage);
            
            // Enviar a peers conectados via WebRTC
            for (const recipientId of recipients) {
                const peer = this.peers.get(recipientId);
                if (peer && peer.send) {
                    try {
                        peer.send(JSON.stringify({
                            type: 'p2p-message',
                            cid: result.cid,
                            package: p2pPackage
                        }));
                    } catch (e) {
                        // Si falla WebRTC, enviar vía API
                        await this.sendViaAPI(recipientId, p2pPackage);
                    }
                } else {
                    // No hay conexión P2P, enviar vía API
                    await this.sendViaAPI(recipientId, p2pPackage);
                }
            }
            
            return result;
            
        } catch (error) {
            console.error('Error enviando mensaje P2P:', error);
            throw error;
        }
    }

    /**
     * Recibir mensajes
     */
    async receive(cid) {
        try {
            // Obtener metadata desde backend
            const response = await fetch(`/api/p2p/metadata/cid/${cid}`);
            const result = await response.json();
            
            if (!result.success) {
                throw new Error('Metadata no encontrada');
            }
            
            const metadata = result.metadata;
            
            // Desencriptar
            const decrypted = await this.decryptMessage(metadata);
            
            return decrypted;
            
        } catch (error) {
            console.error('Error recibiendo mensaje:', error);
            throw error;
        }
    }

    /**
     * Desencriptar mensaje
     */
    async decryptMessage(metadata) {
        try {
            // Obtener wrapped key para este usuario
            const wrappedKey = metadata.wrappedKeys[this.userId];
            if (!wrappedKey) {
                throw new Error('No hay wrapped key para este usuario');
            }
            
            // Desenvolver AES key
            const aesKey = await this.unwrapKey(wrappedKey);
            
            // Desencriptar ciphertext
            const ciphertext = this.base64ToArrayBuffer(metadata.ciphertext);
            const iv = this.base64ToArrayBuffer(metadata.iv);
            
            const decrypted = await this.decryptWithAES(aesKey, ciphertext, iv);
            
            return JSON.parse(decrypted);
            
        } catch (error) {
            console.error('Error desencriptando mensaje:', error);
            throw error;
        }
    }

    /**
     * Almacenar metadata en backend
     */
    async storeMetadata(p2pPackage) {
        try {
            const response = await fetch('/api/p2p/metadata/store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(p2pPackage)
            });
            
            return await response.json();
        } catch (error) {
            console.error('Error guardando metadata:', error);
            throw error;
        }
    }

    /**
     * Enviar vía API (fallback)
     */
    async sendViaAPI(recipientId, p2pPackage) {
        try {
            await fetch('/api/p2p/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    recipientId: recipientId,
                    package: p2pPackage
                })
            });
        } catch (error) {
            console.error('Error enviando vía API:', error);
        }
    }

    /**
     * Obtener llave pública de un usuario
     */
    async getPublicKey(userId) {
        try {
            const response = await fetch(`/api/p2p/public-key/${userId}`);
            const result = await response.json();
            return result.publicKey;
        } catch (error) {
            console.error('Error obteniendo llave pública:', error);
            return null;
        }
    }

    /**
     * Generar AES key
     */
    async generateAESKey() {
        return await window.crypto.subtle.generateKey(
            { name: 'AES-GCM', length: 256 },
            true,
            ['encrypt', 'decrypt']
        );
    }

    /**
     * Encriptar con AES
     */
    async encryptWithAES(key, plaintext) {
        const iv = window.crypto.getRandomValues(new Uint8Array(12));
        const encoded = new TextEncoder().encode(plaintext);
        
        const ciphertext = await window.crypto.subtle.encrypt(
            { name: 'AES-GCM', iv: iv },
            key,
            encoded
        );
        
        return { ciphertext, iv };
    }

    /**
     * Desencriptar con AES
     */
    async decryptWithAES(key, ciphertext, iv) {
        const decrypted = await window.crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: iv },
            key,
            ciphertext
        );
        
        return new TextDecoder().decode(decrypted);
    }

    /**
     * Envolver AES key con RSA
     */
    async wrapKey(aesKey, publicKeyBase64) {
        try {
            // Importar llave pública RSA
            const publicKeyBuffer = this.base64ToArrayBuffer(publicKeyBase64);
            const publicKey = await window.crypto.subtle.importKey(
                'spki',
                publicKeyBuffer,
                { name: 'RSA-OAEP', hash: 'SHA-256' },
                true,
                ['encrypt']
            );
            
            // Exportar AES key
            const aesKeyBuffer = await window.crypto.subtle.exportKey('raw', aesKey);
            
            // Encriptar AES key con RSA
            const wrapped = await window.crypto.subtle.encrypt(
                { name: 'RSA-OAEP' },
                publicKey,
                aesKeyBuffer
            );
            
            return this.arrayBufferToBase64(wrapped);
            
        } catch (error) {
            console.error('Error envolviendo key:', error);
            throw error;
        }
    }

    /**
     * Desenvolver AES key con RSA
     */
    async unwrapKey(wrappedKeyBase64) {
        try {
            const wrappedKey = this.base64ToArrayBuffer(wrappedKeyBase64);
            
            // Desencriptar con llave privada
            const unwrapped = await window.crypto.subtle.decrypt(
                { name: 'RSA-OAEP' },
                this.privateKey,
                wrappedKey
            );
            
            // Importar AES key
            return await window.crypto.subtle.importKey(
                'raw',
                unwrapped,
                { name: 'AES-GCM' },
                true,
                ['encrypt', 'decrypt']
            );
            
        } catch (error) {
            console.error('Error desenvolviendo key:', error);
            throw error;
        }
    }

    /**
     * Importar llave privada
     */
    async importPrivateKey(jwk) {
        return await window.crypto.subtle.importKey(
            'jwk',
            jwk,
            { name: 'RSA-OAEP', hash: 'SHA-256' },
            true,
            ['decrypt']
        );
    }

    /**
     * Manejar mensaje recibido
     */
    async handleMessage(data) {
        try {
            if (data.type === 'p2p-message') {
                const decrypted = await this.decryptMessage(data.package);
                this.emit('message', {
                    from: data.package.senderId,
                    data: decrypted,
                    metadata: data.package.meta
                });
            }
        } catch (error) {
            console.error('Error manejando mensaje:', error);
        }
    }

    /**
     * Cerrar conexión con peer
     */
    closePeerConnection(peerId) {
        const peer = this.peers.get(peerId);
        if (peer && peer.destroy) {
            peer.destroy();
        }
        this.peers.delete(peerId);
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
            callbacks.forEach(cb => cb(data));
        }
    }

    /**
     * Desconectar
     */
    disconnect() {
        // Cerrar todas las conexiones P2P
        for (const [peerId, peer] of this.peers.entries()) {
            this.closePeerConnection(peerId);
        }
        
        // Desconectar socket
        if (this.socket) {
            this.socket.disconnect();
        }
        
        this.isConnected = false;
        console.log('👋 P2P Client desconectado');
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

// Instancia global
window.p2pClient = new P2PClient();

// Auto-inicializar si hay usuario logueado
document.addEventListener('DOMContentLoaded', async () => {
    const userId = sessionStorage.getItem('user_id');
    const p2pMode = localStorage.getItem('p2pMode') === 'true';
    
    if (userId && p2pMode) {
        console.log('🚀 Auto-inicializando P2P Client...');
        await window.p2pClient.init(parseInt(userId));
    }
});
