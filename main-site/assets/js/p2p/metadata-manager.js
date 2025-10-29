/**
 * ============================================
 * P2P METADATA MANAGER - Distributed System
 * ============================================
 * Sistema de metadatos distribuido sin OrbitDB (deprecado)
 * Usa MySQL como store principal + WebSocket para sync P2P
 * 
 * Esquema de metadatos:
 * {
 *   cid: string,              // IPFS Content ID
 *   iv: string,               // Initialization Vector (base64)
 *   senderPub: string,        // Sender's X25519 public key (base64)
 *   senderId: number,         // Sender user ID
 *   recipients: array,        // Array of recipient objects
 *   wrappedKeys: object,      // Wrapped keys per recipient
 *   timestamp: number,        // Unix timestamp
 *   metadata: object,         // Additional metadata
 *   signature: string         // Signature for verification
 * }
 */

class P2PMetadataManager {
    constructor() {
        this.localStore = new Map(); // Local cache
        this.ws = null;
        this.userId = null;
        this.reconnectInterval = null;
        this.syncEnabled = true;
    }

    /**
     * Inicializar el manager
     */
    async init(userId) {
        this.userId = userId;
        await this.initLocalCache();
        this.connectWebSocket();
        this.startSyncLoop();
        console.log('✅ P2P Metadata Manager initialized');
    }

    /**
     * Inicializar cache local desde backend
     */
    async initLocalCache() {
        try {
            // Obtener metadatos del usuario desde backend
            const response = await fetch(`http://localhost:3088/p2p/metadata/recipient/${this.userId}?limit=100`);
            const result = await response.json();

            if (result.success && result.metadata) {
                result.metadata.forEach(meta => {
                    this.localStore.set(meta.cid, meta);
                });
                console.log(`📦 Loaded ${result.metadata.length} metadata entries to local cache`);
            }
        } catch (error) {
            console.error('Error loading local cache:', error);
        }
    }

    /**
     * Conectar WebSocket para sync P2P
     */
    connectWebSocket() {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${wsProtocol}//${window.location.host}/ws/signaling`;

        this.ws = new WebSocket(wsUrl);

        this.ws.onopen = () => {
            console.log('🔗 P2P sync connected');
            this.ws.send(JSON.stringify({
                type: 'register',
                userId: this.userId
            }));
        };

        this.ws.onmessage = (event) => {
            try {
                const message = JSON.parse(event.data);
                this.handleSyncMessage(message);
            } catch (error) {
                console.error('Error handling sync message:', error);
            }
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };

        this.ws.onclose = () => {
            console.log('WebSocket closed, reconnecting...');
            setTimeout(() => this.connectWebSocket(), 3000);
        };
    }

    /**
     * Manejar mensajes de sync
     */
    handleSyncMessage(message) {
        switch (message.type) {
            case 'metadata_update':
                this.handleMetadataUpdate(message.data);
                break;
            case 'metadata_delete':
                this.handleMetadataDelete(message.data);
                break;
            case 'sync_request':
                this.handleSyncRequest(message.from);
                break;
        }
    }

    /**
     * Almacenar metadata con política de acceso
     */
    async storeMetadata(data) {
        try {
            // Validar que el usuario actual es el sender
            if (data.senderId !== this.userId) {
                throw new Error('Only sender can store metadata');
            }

            // Validar estructura de datos
            this.validateMetadataStructure(data);

            // Crear signature para verificación
            const signature = await this.signMetadata(data);
            data.signature = signature;

            // Almacenar en backend
            const response = await fetch('http://localhost:3088/p2p/metadata/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                // Actualizar cache local
                this.localStore.set(data.cid, {
                    ...data,
                    id: result.id,
                    createdAt: Date.now()
                });

                // Broadcast a peers (solo a recipients)
                this.broadcastMetadata('metadata_update', data);

                console.log(`✅ Metadata stored: ${data.cid}`);
                return result;
            } else {
                throw new Error(result.message);
            }

        } catch (error) {
            console.error('Error storing metadata:', error);
            throw error;
        }
    }

    /**
     * Obtener metadata con verificación de acceso
     */
    async getMetadata(cid, verifyAccess = true) {
        // Intentar obtener desde cache local primero
        if (this.localStore.has(cid)) {
            const metadata = this.localStore.get(cid);
            
            if (verifyAccess && !this.hasAccess(metadata)) {
                throw new Error('Access denied: You are not a recipient');
            }

            return metadata;
        }

        // Si no está en cache, obtener desde backend
        try {
            const response = await fetch(`http://localhost:3088/p2p/metadata/cid/${cid}`);
            const result = await response.json();

            if (result.success && result.metadata) {
                const metadata = result.metadata;

                // Verificar firma
                if (!await this.verifySignature(metadata)) {
                    console.warn('⚠️ Invalid signature for metadata:', cid);
                }

                // Verificar acceso
                if (verifyAccess && !this.hasAccess(metadata)) {
                    throw new Error('Access denied: You are not a recipient');
                }

                // Actualizar cache local
                this.localStore.set(cid, metadata);

                return metadata;
            } else {
                throw new Error('Metadata not found');
            }
        } catch (error) {
            console.error('Error getting metadata:', error);
            throw error;
        }
    }

    /**
     * Verificar si el usuario actual tiene acceso
     */
    hasAccess(metadata) {
        // El sender siempre tiene acceso
        if (metadata.senderId === this.userId) {
            return true;
        }

        // Verificar si está en la lista de recipients
        const isRecipient = metadata.recipients.includes(this.userId);
        
        // Verificar si tiene wrapped key
        const hasWrappedKey = metadata.wrappedKeys && 
                             metadata.wrappedKeys[this.userId];

        return isRecipient && hasWrappedKey;
    }

    /**
     * Obtener wrapped key para el usuario actual
     */
    getWrappedKeyForCurrentUser(metadata) {
        if (!this.hasAccess(metadata)) {
            throw new Error('Access denied');
        }

        return metadata.wrappedKeys[this.userId];
    }

    /**
     * Listar metadata accesible para el usuario actual
     */
    async listAccessibleMetadata(limit = 50, offset = 0) {
        try {
            // Obtener desde backend (ya filtra por recipient)
            const response = await fetch(
                `http://localhost:3088/p2p/metadata/recipient/${this.userId}?limit=${limit}&offset=${offset}`
            );
            const result = await response.json();

            if (result.success) {
                // Actualizar cache local
                result.metadata.forEach(meta => {
                    this.localStore.set(meta.cid, meta);
                });

                return result.metadata;
            }

            return [];
        } catch (error) {
            console.error('Error listing metadata:', error);
            return [];
        }
    }

    /**
     * Eliminar metadata (solo sender)
     */
    async deleteMetadata(cid) {
        try {
            const metadata = await this.getMetadata(cid, false);

            // Verificar que el usuario actual es el sender
            if (metadata.senderId !== this.userId) {
                throw new Error('Only sender can delete metadata');
            }

            // Eliminar desde backend
            const response = await fetch(`http://localhost:3088/p2p/metadata/${cid}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userId: this.userId })
            });

            const result = await response.json();

            if (result.success) {
                // Eliminar de cache local
                this.localStore.delete(cid);

                // Broadcast delete a peers
                this.broadcastMetadata('metadata_delete', { cid });

                console.log(`🗑️ Metadata deleted: ${cid}`);
                return true;
            }

            return false;
        } catch (error) {
            console.error('Error deleting metadata:', error);
            throw error;
        }
    }

    /**
     * Broadcast metadata a peers
     */
    broadcastMetadata(type, data) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            return;
        }

        // Si hay recipients específicos, enviar solo a ellos
        if (data.recipients && Array.isArray(data.recipients)) {
            data.recipients.forEach(recipientId => {
                this.ws.send(JSON.stringify({
                    type,
                    to: recipientId,
                    data
                }));
            });
        }
    }

    /**
     * Validar estructura de metadata
     */
    validateMetadataStructure(data) {
        const required = ['cid', 'iv', 'senderPub', 'senderId', 'recipients', 'wrappedKeys', 'ts'];
        
        for (const field of required) {
            if (!data[field]) {
                throw new Error(`Missing required field: ${field}`);
            }
        }

        // Validar recipients
        if (!Array.isArray(data.recipients) || data.recipients.length === 0) {
            throw new Error('Recipients must be a non-empty array');
        }

        // Validar wrappedKeys
        if (typeof data.wrappedKeys !== 'object') {
            throw new Error('wrappedKeys must be an object');
        }

        // Verificar que todos los recipients tienen wrapped key
        for (const recipientId of data.recipients) {
            if (!data.wrappedKeys[recipientId]) {
                throw new Error(`Missing wrapped key for recipient: ${recipientId}`);
            }
        }

        return true;
    }

    /**
     * Firmar metadata para verificación
     */
    async signMetadata(metadata) {
        // Crear string canónico para firmar
        const canonical = JSON.stringify({
            cid: metadata.cid,
            iv: metadata.iv,
            senderPub: metadata.senderPub,
            senderId: metadata.senderId,
            recipients: metadata.recipients.sort(),
            ts: metadata.ts
        });

        // Usar Web Crypto API para firmar
        const encoder = new TextEncoder();
        const data = encoder.encode(canonical);
        
        // Usar SHA-256 como signature (simplificado)
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        return hashHex;
    }

    /**
     * Verificar firma de metadata
     */
    async verifySignature(metadata) {
        if (!metadata.signature) {
            return false;
        }

        const expectedSignature = await this.signMetadata(metadata);
        return expectedSignature === metadata.signature;
    }

    /**
     * Manejar actualización de metadata desde peer
     */
    handleMetadataUpdate(data) {
        // Verificar si el usuario actual es recipient
        if (data.recipients && data.recipients.includes(this.userId)) {
            this.localStore.set(data.cid, data);
            console.log(`📥 Received metadata update: ${data.cid}`);
            
            // Disparar evento para UI
            window.dispatchEvent(new CustomEvent('p2p-metadata-update', { detail: data }));
        }
    }

    /**
     * Manejar eliminación de metadata desde peer
     */
    handleMetadataDelete(data) {
        if (this.localStore.has(data.cid)) {
            this.localStore.delete(data.cid);
            console.log(`🗑️ Metadata deleted by peer: ${data.cid}`);
            
            // Disparar evento para UI
            window.dispatchEvent(new CustomEvent('p2p-metadata-delete', { detail: data }));
        }
    }

    /**
     * Manejar solicitud de sync desde peer
     */
    async handleSyncRequest(fromUserId) {
        // Obtener metadata compartidos con ese usuario
        const sharedMetadata = Array.from(this.localStore.values()).filter(meta => 
            meta.recipients.includes(fromUserId)
        );

        // Enviar metadata
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'sync_response',
                to: fromUserId,
                data: sharedMetadata
            }));
        }
    }

    /**
     * Loop de sincronización periódica
     */
    startSyncLoop() {
        // Sincronizar cada 30 segundos
        setInterval(async () => {
            if (this.syncEnabled) {
                await this.syncWithBackend();
            }
        }, 30000);
    }

    /**
     * Sincronizar con backend
     */
    async syncWithBackend() {
        try {
            const remoteMetadata = await this.listAccessibleMetadata(100);
            
            // Actualizar cache local
            remoteMetadata.forEach(meta => {
                this.localStore.set(meta.cid, meta);
            });

            console.log(`🔄 Synced ${remoteMetadata.length} metadata entries`);
        } catch (error) {
            console.error('Sync error:', error);
        }
    }

    /**
     * Obtener estadísticas
     */
    getStats() {
        return {
            localCacheSize: this.localStore.size,
            wsConnected: this.ws && this.ws.readyState === WebSocket.OPEN,
            syncEnabled: this.syncEnabled,
            userId: this.userId
        };
    }

    /**
     * Limpiar y cerrar conexiones
     */
    close() {
        this.syncEnabled = false;
        if (this.ws) {
            this.ws.close();
        }
        this.localStore.clear();
    }
}

// Hacer disponible globalmente
window.P2PMetadataManager = P2PMetadataManager;
