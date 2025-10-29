/**
 * ============================================
 * MESSAGING UI
 * ============================================
 * User interface for E2E encrypted messaging
 */

class MessagingUI {
    constructor() {
        this.e2eMessaging = null;
        this.ephemeralMessages = null;
        this.currentContactId = null;
        this.messageElements = new Map();
        
        this.init();
    }

    /**
     * Initialize UI
     */
    async init() {
        // Initialize E2E messaging
        const userId = this.getCurrentUserId();
        if (!userId) {
            console.error('❌ User not logged in');
            return;
        }

        this.e2eMessaging = new E2EMessaging();
        await this.e2eMessaging.init(userId);

        // Initialize ephemeral messages
        this.ephemeralMessages = new EphemeralMessages(this.e2eMessaging);

        // Setup event listeners
        this.setupEventListeners();

        // Override E2E messaging callbacks
        this.setupCallbacks();

        console.log('✅ Messaging UI initialized');
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Send message button
        const sendBtn = document.getElementById('sendMessageBtn');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }

        // Message input (Enter to send)
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Typing indicator
            let typingTimeout;
            messageInput.addEventListener('input', () => {
                if (this.currentContactId) {
                    this.e2eMessaging.sendTypingIndicator(this.currentContactId, true);
                    
                    clearTimeout(typingTimeout);
                    typingTimeout = setTimeout(() => {
                        this.e2eMessaging.sendTypingIndicator(this.currentContactId, false);
                    }, 1000);
                }
            });
        }

        // Ephemeral timer selector
        const timerSelector = document.getElementById('ephemeralTimer');
        if (timerSelector) {
            timerSelector.addEventListener('change', (e) => {
                this.setEphemeralTimer(parseInt(e.target.value));
            });
        }

        // Contact selection
        document.addEventListener('click', (e) => {
            const contactCard = e.target.closest('.contact-card');
            if (contactCard) {
                const contactId = contactCard.dataset.contactId;
                this.openConversation(contactId);
            }
        });
    }

    /**
     * Setup E2E messaging callbacks
     */
    setupCallbacks() {
        // Message sent
        this.e2eMessaging.onMessageSent = (messageId, data) => {
            this.addMessageToUI(messageId, {
                sender_id: data.sender_id,
                recipient_id: data.recipient_id,
                plaintext: data.plaintext || '[Encrypted]',
                content_type: data.content_type,
                ephemeral_timer: data.ephemeral_timer,
                status: 'sent',
                timestamp: Date.now(),
                isOwn: true
            });
        };

        // Message received
        this.e2eMessaging.onMessageReceived = (message) => {
            this.addMessageToUI(message.id, {
                ...message,
                isOwn: false
            });

            // Play notification sound
            this.playNotificationSound();

            // Show desktop notification if not focused
            if (document.hidden) {
                this.showDesktopNotification(message);
            }
        };

        // Message destroyed
        this.e2eMessaging.onMessageDestroyed = (messageId) => {
            this.removeMessageFromUI(messageId);
        };

        // Typing indicator
        this.e2eMessaging.onTypingIndicator = (userId, isTyping) => {
            this.updateTypingIndicator(userId, isTyping);
        };
    }

    /**
     * Send message
     */
    async sendMessage() {
        if (!this.currentContactId) {
            console.error('No contact selected');
            return;
        }

        const messageInput = document.getElementById('messageInput');
        const plaintext = messageInput.value.trim();

        if (!plaintext) return;

        try {
            // Clear input
            messageInput.value = '';

            // Check if ephemeral
            const ephemeralTimer = this.ephemeralMessages.getConversationTimer(this.currentContactId);

            if (ephemeralTimer > 0) {
                // Send ephemeral
                await this.ephemeralMessages.sendEphemeral(
                    this.currentContactId,
                    plaintext,
                    ephemeralTimer
                );
            } else {
                // Send normal
                await this.e2eMessaging.sendMessage(
                    this.currentContactId,
                    plaintext
                );
            }

        } catch (error) {
            console.error('❌ Failed to send message:', error);
            this.showError('Failed to send message');
        }
    }

    /**
     * Add message to UI
     */
    addMessageToUI(messageId, data) {
        const messagesContainer = document.getElementById('messagesContainer');
        if (!messagesContainer) return;

        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${data.isOwn ? 'own' : 'other'}`;
        messageDiv.dataset.messageId = messageId;

        // Add ephemeral class if applicable
        if (data.ephemeral_timer > 0) {
            messageDiv.classList.add('ephemeral-message');
        }

        // Build message HTML
        const time = new Date(data.timestamp).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });

        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-text">${this.escapeHtml(data.plaintext)}</div>
                <div class="message-footer">
                    <span class="message-time">${time}</span>
                    ${data.ephemeral_timer > 0 ? `
                        <span class="ephemeral-indicator">
                            <i class="fas fa-clock"></i>
                            <span class="timer-countdown" data-message-id="${messageId}"></span>
                        </span>
                    ` : ''}
                    ${data.isOwn ? `
                        <span class="message-status">
                            ${this.getStatusIcon(data.status)}
                        </span>
                    ` : ''}
                </div>
            </div>
        `;

        // Append to container
        messagesContainer.appendChild(messageDiv);

        // Store reference
        this.messageElements.set(messageId, messageDiv);

        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Start ephemeral countdown
        if (data.ephemeral_timer > 0) {
            this.startCountdown(messageId, data.ephemeral_timer);
        }

        // Mark as read if not own
        if (!data.isOwn) {
            setTimeout(() => {
                this.e2eMessaging.markAsRead(messageId);
            }, 1000);
        }
    }

    /**
     * Remove message from UI
     */
    removeMessageFromUI(messageId) {
        const messageDiv = this.messageElements.get(messageId);
        if (messageDiv) {
            messageDiv.classList.add('message-destroying');
            setTimeout(() => {
                messageDiv.remove();
                this.messageElements.delete(messageId);
            }, 300);
        }
    }

    /**
     * Start countdown for ephemeral message
     */
    startCountdown(messageId, totalSeconds) {
        const countdownSpan = document.querySelector(`.timer-countdown[data-message-id="${messageId}"]`);
        if (!countdownSpan) return;

        const updateCountdown = () => {
            const remaining = this.ephemeralMessages.getRemainingTime(messageId);
            
            if (remaining <= 0) {
                return;
            }

            countdownSpan.textContent = this.ephemeralMessages.formatTimer(remaining);

            // Change color when time is running out
            if (remaining <= 5) {
                countdownSpan.style.color = '#ff4444';
            }

            setTimeout(updateCountdown, 1000);
        };

        updateCountdown();
    }

    /**
     * Open conversation
     */
    async openConversation(contactId) {
        this.currentContactId = contactId;

        // Update UI to show active conversation
        document.querySelectorAll('.contact-card').forEach(card => {
            card.classList.remove('active');
        });

        const activeCard = document.querySelector(`[data-contact-id="${contactId}"]`);
        if (activeCard) {
            activeCard.classList.add('active');
        }

        // Load messages
        await this.loadConversationMessages(contactId);

        // Update ephemeral timer UI
        const timer = this.ephemeralMessages.getConversationTimer(contactId);
        const timerSelector = document.getElementById('ephemeralTimer');
        if (timerSelector) {
            timerSelector.value = timer;
        }
    }

    /**
     * Load conversation messages
     */
    async loadConversationMessages(contactId) {
        try {
            const response = await fetch('/api/messaging/get-conversation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: this.e2eMessaging.userId,
                    contact_id: contactId
                })
            });

            const result = await response.json();

            if (result.success) {
                // Clear existing messages
                const messagesContainer = document.getElementById('messagesContainer');
                if (messagesContainer) {
                    messagesContainer.innerHTML = '';
                }
                this.messageElements.clear();

                // Add messages
                for (const message of result.messages) {
                    // Decrypt if needed
                    let plaintext = message.plaintext;
                    if (!plaintext && message.encrypted_content) {
                        plaintext = await this.e2eMessaging.signalCrypto.decryptMessage(
                            message.sender_id,
                            {
                                type: message.message_type,
                                body: message.encrypted_content
                            }
                        );
                    }

                    this.addMessageToUI(message.id, {
                        ...message,
                        plaintext,
                        isOwn: message.sender_id === this.e2eMessaging.userId
                    });
                }
            }
        } catch (error) {
            console.error('❌ Failed to load messages:', error);
        }
    }

    /**
     * Set ephemeral timer
     */
    setEphemeralTimer(timerSeconds) {
        if (!this.currentContactId) return;

        this.ephemeralMessages.setConversationTimer(this.currentContactId, timerSeconds);

        // Show confirmation
        const label = this.ephemeralMessages.getTimerLabel(timerSeconds);
        this.showToast(`Ephemeral timer set to: ${label}`);
    }

    /**
     * Update typing indicator
     */
    updateTypingIndicator(userId, isTyping) {
        const indicator = document.getElementById(`typing-${userId}`);
        
        if (isTyping) {
            if (!indicator) {
                const messagesContainer = document.getElementById('messagesContainer');
                const div = document.createElement('div');
                div.id = `typing-${userId}`;
                div.className = 'typing-indicator';
                div.innerHTML = `
                    <div class="typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                `;
                messagesContainer.appendChild(div);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } else {
            if (indicator) {
                indicator.remove();
            }
        }
    }

    /**
     * Get status icon
     */
    getStatusIcon(status) {
        switch (status) {
            case 'sending': return '<i class="fas fa-clock text-muted"></i>';
            case 'sent': return '<i class="fas fa-check text-muted"></i>';
            case 'delivered': return '<i class="fas fa-check-double text-muted"></i>';
            case 'read': return '<i class="fas fa-check-double text-primary"></i>';
            case 'failed': return '<i class="fas fa-exclamation-circle text-danger"></i>';
            default: return '';
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Show error
     */
    showError(message) {
        this.showToast(message, 'error');
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.volume = 0.5;
        audio.play().catch(() => {});
    }

    /**
     * Show desktop notification
     */
    showDesktopNotification(message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('New Message', {
                body: message.plaintext,
                icon: '/assets/images/logo.png'
            });
        }
    }

    /**
     * Get current user ID
     */
    getCurrentUserId() {
        return window.currentUser?.id || null;
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.messagingUI = new MessagingUI();
});

// Export
window.MessagingUI = MessagingUI;
