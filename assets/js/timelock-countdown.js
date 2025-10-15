/**
 * ============================================
 * TIMELOCK COUNTDOWN TIMER
 * ============================================
 * Real-time countdown for timelock execution
 * Auto-updates every second
 */

class TimelockCountdown {
    constructor() {
        this.timers = new Map();
        this.updateInterval = null;
    }

    /**
     * Initialize countdown for an operation
     * @param {string} operationHash - The operation hash
     * @param {string} executionEta - ISO date string of execution time
     * @param {string} containerId - DOM element ID to render countdown
     */
    init(operationHash, executionEta, containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container ${containerId} not found`);
            return;
        }

        const timer = {
            operationHash,
            executionEta: new Date(executionEta),
            container,
            isReady: false
        };

        this.timers.set(operationHash, timer);
        this.render(timer);

        // Start update interval if not already running
        if (!this.updateInterval) {
            this.updateInterval = setInterval(() => this.updateAll(), 1000);
        }
    }

    /**
     * Calculate time remaining
     */
    getTimeRemaining(eta) {
        const now = new Date();
        const diff = eta - now;

        if (diff <= 0) {
            return {
                isReady: true,
                days: 0,
                hours: 0,
                minutes: 0,
                seconds: 0,
                totalSeconds: 0,
                formatted: 'Ready to execute'
            };
        }

        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        return {
            isReady: false,
            days: days,
            hours: hours % 24,
            minutes: minutes % 60,
            seconds: seconds % 60,
            totalSeconds: seconds,
            formatted: `${days}d ${hours % 24}h ${minutes % 60}m ${seconds % 60}s`
        };
    }

    /**
     * Render countdown in container
     */
    render(timer) {
        const remaining = this.getTimeRemaining(timer.executionEta);
        
        if (remaining.isReady) {
            timer.container.innerHTML = `
                <div class="timelock-ready">
                    <i class="fas fa-check-circle"></i>
                    <span class="status-text">Ready to Execute</span>
                </div>
            `;
            timer.container.classList.add('ready');
            timer.container.classList.remove('pending');
            timer.isReady = true;
            
            // Trigger ready event
            this.triggerReadyEvent(timer.operationHash);
        } else {
            const progressPercent = this.calculateProgress(timer.executionEta);
            
            timer.container.innerHTML = `
                <div class="timelock-countdown">
                    <div class="countdown-header">
                        <i class="fas fa-clock"></i>
                        <span class="countdown-label">Time until execution</span>
                    </div>
                    <div class="countdown-display">
                        ${remaining.days > 0 ? `<div class="time-unit">
                            <span class="time-value">${remaining.days}</span>
                            <span class="time-label">days</span>
                        </div>` : ''}
                        <div class="time-unit">
                            <span class="time-value">${String(remaining.hours).padStart(2, '0')}</span>
                            <span class="time-label">hours</span>
                        </div>
                        <div class="time-separator">:</div>
                        <div class="time-unit">
                            <span class="time-value">${String(remaining.minutes).padStart(2, '0')}</span>
                            <span class="time-label">minutes</span>
                        </div>
                        <div class="time-separator">:</div>
                        <div class="time-unit">
                            <span class="time-value">${String(remaining.seconds).padStart(2, '0')}</span>
                            <span class="time-label">seconds</span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progressPercent}%"></div>
                    </div>
                    <div class="countdown-eta">
                        ETA: ${timer.executionEta.toLocaleString()}
                    </div>
                </div>
            `;
            timer.container.classList.add('pending');
            timer.container.classList.remove('ready');
        }
    }

    /**
     * Calculate progress percentage
     */
    calculateProgress(eta) {
        const now = new Date();
        const diff = eta - now;
        
        // Assume 48 hour timelock (172800 seconds)
        const totalTime = 172800 * 1000;
        const elapsed = totalTime - diff;
        
        return Math.max(0, Math.min(100, (elapsed / totalTime) * 100));
    }

    /**
     * Update all active timers
     */
    updateAll() {
        this.timers.forEach(timer => {
            this.render(timer);
        });
    }

    /**
     * Stop countdown for an operation
     */
    stop(operationHash) {
        this.timers.delete(operationHash);
        
        // Stop interval if no more timers
        if (this.timers.size === 0 && this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    /**
     * Stop all countdowns
     */
    stopAll() {
        this.timers.clear();
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    /**
     * Trigger ready event
     */
    triggerReadyEvent(operationHash) {
        const event = new CustomEvent('timelockReady', {
            detail: { operationHash }
        });
        window.dispatchEvent(event);
    }

    /**
     * Check if operation is ready
     */
    isReady(operationHash) {
        const timer = this.timers.get(operationHash);
        return timer ? timer.isReady : false;
    }

    /**
     * Get all ready operations
     */
    getReadyOperations() {
        const ready = [];
        this.timers.forEach((timer, hash) => {
            if (timer.isReady) {
                ready.push(hash);
            }
        });
        return ready;
    }
}

// Export as global
window.TimelockCountdown = TimelockCountdown;

// Auto-initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    window.timelockCountdown = new TimelockCountdown();
    
    // Listen for ready events
    window.addEventListener('timelockReady', (e) => {
        console.log('Timelock ready for execution:', e.detail.operationHash);
        
        // Enable execute button
        const executeBtn = document.querySelector(`[data-operation="${e.detail.operationHash}"] .btn-execute`);
        if (executeBtn) {
            executeBtn.disabled = false;
            executeBtn.classList.add('ready');
        }
    });
});
