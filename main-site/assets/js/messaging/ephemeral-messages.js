/**
 * ============================================
 * EPHEMERAL MESSAGES SYSTEM
 * ============================================
 * Auto-destructing messages with timers
 */

class EphemeralMessages {
    constructor(e2eMessaging) {
        this.e2eMessaging = e2eMessaging;
        this.timers = new Map(); // messageId -> timeoutId
        this.destructionCallbacks = [];
        
        // Available timers (in seconds)
        this.TIMERS = {
            OFF: 0,
            FIVE_SECONDS: 5,
            THIRTY_SECONDS: 30,
            ONE_MINUTE: 60,
            FIVE_MINUTES: 300,
            ONE_HOUR: 3600,
            ONE_DAY: 86400
        };
        
        // Screenshot detection
        this.screenshotAttempts = [];
        this.initScreenshotDetection();
    }

    /**
     * Set ephemeral timer for conversation
     */
    setConversationTimer(contactId, timerSeconds) {
        localStorage.setItem(`ephemeral_timer_${contactId}`, timerSeconds);
        
        // Notify UI
        this.onTimerChanged(contactId, timerSeconds);
        
        console.log(`✅ Ephemeral timer set to ${timerSeconds}s for contact ${contactId}`);
    }

    /**
     * Get ephemeral timer for conversation
     */
    getConversationTimer(contactId) {
        const timer = localStorage.getItem(`ephemeral_timer_${contactId}`);
        return timer ? parseInt(timer) : this.TIMERS.OFF;
    }

    /**
     * Send ephemeral message
     */
    async sendEphemeral(recipientId, plaintext, timerSeconds = null) {
        // Use conversation timer if not specified
        const timer = timerSeconds !== null ? timerSeconds : this.getConversationTimer(recipientId);
        
        if (timer === this.TIMERS.OFF) {
            throw new Error('Ephemeral timer not set');
        }
        
        // Send via E2E messaging
        const messageId = await this.e2eMessaging.sendMessage(recipientId, plaintext, {
            ephemeralTimer: timer
        });
        
        // Start destruction timer locally
        this.scheduleDestruction(messageId, timer);
        
        return messageId;
    }

    /**
     * Schedule message destruction
     */
    scheduleDestruction(messageId, timerSeconds) {
        // Clear existing timer if any
        this.cancelDestruction(messageId);
        
        // Set new timer
        const timeoutId = setTimeout(() => {
            this.destroyMessage(messageId);
        }, timerSeconds * 1000);
        
        this.timers.set(messageId, {
            timeoutId,
            expiresAt: Date.now() + (timerSeconds * 1000)
        });
        
        console.log(`⏰ Destruction scheduled for message ${messageId} in ${timerSeconds}s`);
    }

    /**
     * Cancel scheduled destruction
     */
    cancelDestruction(messageId) {
        const timer = this.timers.get(messageId);
        if (timer) {
            clearTimeout(timer.timeoutId);
            this.timers.delete(messageId);
        }
    }

    /**
     * Destroy message
     */
    async destroyMessage(messageId) {
        try {
            // Remove from DOM
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (messageElement) {
                messageElement.classList.add('message-destroying');
                
                setTimeout(() => {
                    messageElement.remove();
                }, 300); // Animation duration
            }
            
            // Delete from server
            await fetch('/api/messaging/destroy-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId })
            });
            
            // Clear timer
            this.timers.delete(messageId);
            
            // Notify callbacks
            this.destructionCallbacks.forEach(cb => cb(messageId));
            
            console.log(`💥 Message ${messageId} destroyed`);
        } catch (error) {
            console.error('❌ Failed to destroy message:', error);
        }
    }

    /**
     * Get remaining time for message
     */
    getRemainingTime(messageId) {
        const timer = this.timers.get(messageId);
        if (!timer) return 0;
        
        const remaining = Math.max(0, timer.expiresAt - Date.now());
        return Math.floor(remaining / 1000);
    }

    /**
     * Format timer for display
     */
    formatTimer(seconds) {
        if (seconds === this.TIMERS.OFF) return 'Off';
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
        return `${Math.floor(seconds / 86400)}d`;
    }

    /**
     * Get timer label
     */
    getTimerLabel(seconds) {
        switch (seconds) {
            case this.TIMERS.OFF: return 'Off';
            case this.TIMERS.FIVE_SECONDS: return '5 seconds';
            case this.TIMERS.THIRTY_SECONDS: return '30 seconds';
            case this.TIMERS.ONE_MINUTE: return '1 minute';
            case this.TIMERS.FIVE_MINUTES: return '5 minutes';
            case this.TIMERS.ONE_HOUR: return '1 hour';
            case this.TIMERS.ONE_DAY: return '1 day';
            default: return `${seconds}s`;
        }
    }

    /**
     * Initialize screenshot detection
     */
    initScreenshotDetection() {
        // Detect keyboard shortcuts (limited effectiveness)
        document.addEventListener('keyup', (e) => {
            // Windows/Linux: PrtScn, Ctrl+PrtScn, Alt+PrtScn
            if (e.key === 'PrintScreen') {
                this.handleScreenshotAttempt();
            }
            
            // Mac: Cmd+Shift+3, Cmd+Shift+4
            if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === '3' || e.key === '4')) {
                this.handleScreenshotAttempt();
            }
        });

        // Detect visibility change (user switched apps)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // User may be taking screenshot from another app
                this.handlePotentialScreenshot();
            }
        });

        // Disable right-click on ephemeral messages
        document.addEventListener('contextmenu', (e) => {
            const messageElement = e.target.closest('.ephemeral-message');
            if (messageElement) {
                e.preventDefault();
                this.showScreenshotWarning();
            }
        });

        // Prevent copy on ephemeral messages
        document.addEventListener('copy', (e) => {
            const selection = window.getSelection().toString();
            const messageElement = window.getSelection().anchorNode?.parentElement?.closest('.ephemeral-message');
            
            if (messageElement && selection) {
                e.preventDefault();
                this.showScreenshotWarning('Cannot copy ephemeral messages');
            }
        });
    }

    /**
     * Handle confirmed screenshot attempt
     */
    handleScreenshotAttempt() {
        const timestamp = Date.now();
        this.screenshotAttempts.push(timestamp);
        
        // Show notification
        this.showScreenshotWarning('Screenshot detected! Other party will be notified.');
        
        // Notify server and other party
        this.notifyScreenshotAttempt();
        
        console.warn('⚠️ Screenshot attempt detected');
    }

    /**
     * Handle potential screenshot (less certain)
     */
    handlePotentialScreenshot() {
        // Log but don't notify (too many false positives)
        console.log('ℹ️ Potential screenshot (user switched apps)');
    }

    /**
     * Notify screenshot attempt
     */
    async notifyScreenshotAttempt() {
        // Get current conversation
        const activeContact = this.getCurrentContactId();
        if (!activeContact) return;
        
        try {
            await fetch('/api/messaging/screenshot-attempt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: this.e2eMessaging.userId,
                    contact_id: activeContact,
                    timestamp: Date.now()
                })
            });
        } catch (error) {
            console.error('❌ Failed to notify screenshot attempt:', error);
        }
    }

    /**
     * Show screenshot warning
     */
    showScreenshotWarning(message = 'Screenshots are detected and discouraged') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'screenshot-warning toast';
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-shield-alt"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Get current contact ID from UI
     */
    getCurrentContactId() {
        const activeChat = document.querySelector('.chat-container.active');
        return activeChat ? activeChat.dataset.contactId : null;
    }

    /**
     * Add destruction callback
     */
    onDestruction(callback) {
        this.destructionCallbacks.push(callback);
    }

    /**
     * Event: Timer changed
     */
    onTimerChanged(contactId, timerSeconds) {
        // Implement in UI
        console.log(`Timer changed for ${contactId}: ${timerSeconds}s`);
    }

    /**
     * Cleanup all timers
     */
    cleanup() {
        for (const [messageId, timer] of this.timers.entries()) {
            clearTimeout(timer.timeoutId);
        }
        this.timers.clear();
    }
}

// Export
window.EphemeralMessages = EphemeralMessages;
