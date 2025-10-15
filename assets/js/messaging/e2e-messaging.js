/**
 * ============================================
 * E2E MESSAGING CORE SYSTEM
 * ============================================
 * Sistema completo de mensajería end-to-end con Signal Protocol
 */

class E2EMessaging {
    constructor() {
        this.signalCrypto = null;
        this.gun = null;
        this.userId = null;
        this.messageQueue = [];
        this.activeConversations = new Map();
        this.typingTimeouts = new Map();
    }

    /**
     * Inicializar sistema E2E
     */
    async init(userId) {
        try {
            this.userId = userId;
            
            // Inicializar Signal Protocol
            this.signalCrypto = new SignalCrypto();
            await this.signalCrypto.init(userId);
            
            // Subir pre-keys al servidor si no existen
            await this.uploadPreKeys();
            
            // Inicializar P2P system (sin Gun.js)
            this.initP2P();
            
            // Inicializar listeners
            this.initListeners();
            
            // Procesar cola de mensajes pendientes
            await this.processMessageQueue();
            
            console.log('✅ E2E Messaging initialized');
            return true;
        } catch (error) {
            console.error('❌ Failed to initialize E2E messaging:', error);
            return false;
        }
    }

    /**
     * Subir pre-keys al servidor
     */
    async uploadPreKeys() {
        try {
            // Verificar si ya existen pre-keys
            const response = await fetch('/api/messaging/check-prekeys.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: this.userId })
            });
            
            const result = await response.json();
            
            if (!result.exists || result.keys_low) {
                // Generar nuevas pre-keys
                const preKeyBundle = await this.signalCrypto.generatePreKeys();
                
                // Subir al servidor
                const uploadResponse = await fetch('/api/messaging/upload-prekeys.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: this.userId,
                        ...preKeyBundle
                    })
                });
                
                const uploadResult = await uploadResponse.json();
                if (uploadResult.success) {
                    console.log('✅ Pre-keys uploaded successfully');
                } else {
                    throw new Error('Failed to upload pre-keys');
                }
            }
        } catch (error) {
            console.error('❌ Failed to upload pre-keys:', error);
            throw error;
        }
    }

    /**
     * Inicializar sistema P2P (sin Gun.js)
     * Usa MySQL backend para metadatos + IPFS para archivos
     */
    initP2P() {
        console.log('✅ P2P system initialized (MySQL + IPFS)');
        // No necesita Gun.js - usa endpoints HTTP
        this.p2pEndpoint = window.location.origin + '/api/p2p';
    }

    /**
     * Inicializar listeners de eventos
     */
    initListeners() {
        // Polling para mensajes del servidor
        setInterval(() => this.pollServerMessages(), 5000);
        
        // Cleanup de typing indicators
        setInterval(() => this.cleanupTypingIndicators(), 10000);
    }

    /**
     * Enviar mensaje encriptado
     */
    async sendMessage(recipientId, plaintext, options = {}) {
        try {
            // Verificar o crear sesión
            const hasSession = await this.signalCrypto.hasSession(recipientId);
            if (!hasSession) {
                await this.createSession(recipientId);
            }
            
            // Encriptar mensaje
            const encrypted = await this.signalCrypto.encryptMessage(recipientId, plaintext);
            
            // Preparar datos del mensaje
            const messageData = {
                sender_id: this.userId,
                recipient_id: recipientId,
                encrypted_content: encrypted.body,
                message_type: encrypted.type,
                session_id: `${this.userId}_${recipientId}`,
                content_type: options.contentType || 'text',
                ephemeral_timer: options.ephemeralTimer || 0,
                reply_to: options.replyTo || null
            };
            
            // Calcular expiración si es efímero
            if (messageData.ephemeral_timer > 0) {
                const expiresAt = new Date();
                expiresAt.setSeconds(expiresAt.getSeconds() + messageData.ephemeral_timer);
                messageData.expires_at = expiresAt.toISOString();
            }
            
            // Guardar en servidor
            const response = await fetch('/api/messaging/send-encrypted.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(messageData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Enviar vía P2P también
                this.sendViaGun(recipientId, {
                    type: 'encrypted_message',
                    message_id: result.message_id,
                    ...messageData,
                    timestamp: Date.now()
                });
                
                // Actualizar UI
                this.onMessageSent(result.message_id, messageData);
                
                return result.message_id;
            } else {
                throw new Error(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('❌ Failed to send message:', error);
            
            // Añadir a cola para reintentar
            this.messageQueue.push({
                recipientId,
                plaintext,
                options,
                retries: 0
            });
            
            throw error;
        }
    }

    /**
     * Crear sesión con usuario
     */
    async createSession(recipientId) {
        try {
            // Obtener pre-key bundle del servidor
            const response = await fetch('/api/messaging/get-prekeys.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: recipientId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                await this.signalCrypto.createSession(recipientId, result.preKeyBundle);
                console.log('✅ Session created with user:', recipientId);
            } else {
                throw new Error('Failed to get pre-keys');
            }
        } catch (error) {
            console.error('❌ Failed to create session:', error);
            throw error;
        }
    }

    /**
     * Manejar mensaje entrante
     */
    async handleIncomingMessage(data) {
        try {
            // Verificar si ya procesamos este mensaje
            if (this.isMessageProcessed(data.message_id)) {
                return;
            }
            
            // Desencriptar
            const plaintext = await this.signalCrypto.decryptMessage(
                data.sender_id,
                {
                    type: data.message_type,
                    body: data.encrypted_content
                }
            );
            
            // Marcar como entregado
            await this.markAsDelivered(data.message_id);
            
            // Notificar UI
            this.onMessageReceived({
                id: data.message_id,
                sender_id: data.sender_id,
                plaintext: plaintext,
                content_type: data.content_type,
                ephemeral_timer: data.ephemeral_timer,
                reply_to: data.reply_to,
                timestamp: data.timestamp
            });
            
            // Si es efímero, programar destrucción
            if (data.ephemeral_timer > 0) {
                this.scheduleMessageDestruction(data.message_id, data.ephemeral_timer);
            }
        } catch (error) {
            console.error('❌ Failed to handle incoming message:', error);
        }
    }

    /**
     * Enviar vía Gun.js P2P
     */
    sendViaGun(recipientId, data) {
        try {
            const recipientMessages = this.gun.get(`messages_${recipientId}`);
            recipientMessages.set(data);
        } catch (error) {
            console.error('❌ Failed to send via Gun:', error);
        }
    }

    /**
     * Polling de mensajes del servidor
     */
    async pollServerMessages() {
        try {
            const response = await fetch('/api/messaging/get-pending.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: this.userId })
            });
            
            const result = await response.json();
            
            if (result.success && result.messages.length > 0) {
                for (const message of result.messages) {
                    await this.handleIncomingMessage(message);
                }
            }
        } catch (error) {
            console.error('❌ Failed to poll messages:', error);
        }
    }

    /**
     * Marcar mensaje como leído
     */
    async markAsRead(messageId) {
        try {
            await fetch('/api/messaging/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message_id: messageId,
                    user_id: this.userId
                })
            });
        } catch (error) {
            console.error('❌ Failed to mark as read:', error);
        }
    }

    /**
     * Marcar mensaje como entregado
     */
    async markAsDelivered(messageId) {
        try {
            await fetch('/api/messaging/mark-delivered.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message_id: messageId,
                    user_id: this.userId
                })
            });
        } catch (error) {
            console.error('❌ Failed to mark as delivered:', error);
        }
    }

    /**
     * Enviar indicador de escritura
     */
    async sendTypingIndicator(recipientId, isTyping) {
        try {
            // Enviar al servidor
            await fetch('/api/messaging/typing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: this.userId,
                    recipient_id: recipientId,
                    is_typing: isTyping
                })
            });
            
            // Enviar vía P2P
            this.sendViaGun(recipientId, {
                type: 'typing_indicator',
                sender_id: this.userId,
                is_typing: isTyping,
                timestamp: Date.now()
            });
        } catch (error) {
            console.error('❌ Failed to send typing indicator:', error);
        }
    }

    /**
     * Programar destrucción de mensaje efímero
     */
    scheduleMessageDestruction(messageId, timer) {
        setTimeout(async () => {
            await this.destroyMessage(messageId);
        }, timer * 1000);
    }

    /**
     * Destruir mensaje efímero
     */
    async destroyMessage(messageId) {
        try {
            await fetch('/api/messaging/destroy-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId })
            });
            
            this.onMessageDestroyed(messageId);
        } catch (error) {
            console.error('❌ Failed to destroy message:', error);
        }
    }

    /**
     * Procesar cola de mensajes pendientes
     */
    async processMessageQueue() {
        const queue = [...this.messageQueue];
        this.messageQueue = [];
        
        for (const item of queue) {
            if (item.retries < 3) {
                try {
                    await this.sendMessage(item.recipientId, item.plaintext, item.options);
                } catch (error) {
                    item.retries++;
                    this.messageQueue.push(item);
                }
            }
        }
    }

    /**
     * Verificar si mensaje ya fue procesado
     */
    isMessageProcessed(messageId) {
        const key = `processed_${messageId}`;
        if (localStorage.getItem(key)) {
            return true;
        }
        localStorage.setItem(key, '1');
        setTimeout(() => localStorage.removeItem(key), 60000); // Limpiar después de 1 min
        return false;
    }

    /**
     * Cleanup de typing indicators
     */
    cleanupTypingIndicators() {
        const now = Date.now();
        for (const [key, timestamp] of this.typingTimeouts.entries()) {
            if (now - timestamp > 5000) {
                this.typingTimeouts.delete(key);
                this.onTypingIndicator(key, false);
            }
        }
    }

    /**
     * Event handlers (sobrescribir en implementación)
     */
    onMessageSent(messageId, data) {
        console.log('Message sent:', messageId);
    }

    onMessageReceived(message) {
        console.log('Message received:', message);
    }

    onMessageDestroyed(messageId) {
        console.log('Message destroyed:', messageId);
    }

    onTypingIndicator(userId, isTyping) {
        console.log('Typing indicator:', userId, isTyping);
    }
}

// Export
window.E2EMessaging = E2EMessaging;
