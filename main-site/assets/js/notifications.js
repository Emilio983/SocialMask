/**
 * ============================================
 * SISTEMA DE NOTIFICACIONES MODERNO
 * ============================================
 * Sistema elegante de notificaciones toast para The Social Mask
 */

class NotificationSystem {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Crear contenedor de notificaciones si no existe
        if (!document.getElementById('notification-container')) {
            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.className = 'notification-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('notification-container');
        }

        // Inyectar estilos
        this.injectStyles();
    }

    injectStyles() {
        if (document.getElementById('notification-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification-container {
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
                max-width: 420px;
                width: 100%;
            }

            @media (max-width: 640px) {
                .notification-container {
                    top: 70px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }

            .notification {
                pointer-events: all;
                background: linear-gradient(135deg, #161B22 0%, #0D1117 100%);
                border: 1px solid #30363D;
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
                display: flex;
                align-items: start;
                gap: 12px;
                animation: slideInRight 0.3s ease-out;
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
                max-width: 100%;
            }

            .notification:hover {
                transform: translateX(-4px);
                box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
            }

            .notification.removing {
                animation: slideOutRight 0.3s ease-in forwards;
            }

            .notification-icon {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
            }

            .notification-success .notification-icon {
                background: linear-gradient(135deg, #10B981 0%, #059669 100%);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            }

            .notification-error .notification-icon {
                background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            }

            .notification-warning .notification-icon {
                background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
                box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
            }

            .notification-info .notification-icon {
                background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }

            .notification-p2p .notification-icon {
                background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
            }

            .notification-content {
                flex: 1;
                min-width: 0;
            }

            .notification-title {
                font-size: 14px;
                font-weight: 600;
                color: #F0F6FC;
                margin-bottom: 4px;
                line-height: 1.4;
            }

            .notification-message {
                font-size: 13px;
                color: #8B949E;
                line-height: 1.5;
                word-wrap: break-word;
            }

            .notification-close {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                border-radius: 6px;
                background: transparent;
                border: none;
                color: #8B949E;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
                padding: 0;
            }

            .notification-close:hover {
                background: rgba(255, 255, 255, 0.1);
                color: #F0F6FC;
            }

            .notification-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 0 0 12px 12px;
                overflow: hidden;
            }

            .notification-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #3B82F6, #8B5CF6);
                animation: progressBar linear forwards;
                border-radius: 0 0 12px 12px;
            }

            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(120%);
                }
            }

            @keyframes progressBar {
                from { width: 100%; }
                to { width: 0%; }
            }
        `;
        document.head.appendChild(styles);
    }

    show(options) {
        const {
            type = 'info',
            title = '',
            message = '',
            duration = 5000,
            showProgress = true
        } = options;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ',
            p2p: '🔒'
        };

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        notification.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-content">
                ${title ? `<div class="notification-title">${title}</div>` : ''}
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.749.749 0 0 1 1.275.326.749.749 0 0 1-.215.734L9.06 8l3.22 3.22a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215L8 9.06l-3.22 3.22a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06Z"></path>
                </svg>
            </button>
            ${showProgress && duration > 0 ? `
                <div class="notification-progress">
                    <div class="notification-progress-bar" style="animation-duration: ${duration}ms;"></div>
                </div>
            ` : ''}
        `;

        this.container.appendChild(notification);

        // Botón de cerrar
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => this.remove(notification));

        // Auto-remover después del duration
        if (duration > 0) {
            setTimeout(() => this.remove(notification), duration);
        }

        return notification;
    }

    remove(notification) {
        notification.classList.add('removing');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Métodos de conveniencia
    success(message, title = '¡Éxito!', duration = 4000) {
        return this.show({ type: 'success', title, message, duration });
    }

    error(message, title = 'Error', duration = 6000) {
        return this.show({ type: 'error', title, message, duration });
    }

    warning(message, title = 'Advertencia', duration = 5000) {
        return this.show({ type: 'warning', title, message, duration });
    }

    info(message, title = '', duration = 4000) {
        return this.show({ type: 'info', title, message, duration });
    }

    p2p(message, title = 'Modo P2P', duration = 5000) {
        return this.show({ type: 'p2p', title, message, duration });
    }

    // Método para fondos insuficientes
    insufficientFunds(required, available, currency = 'SPHE') {
        return this.show({
            type: 'warning',
            title: 'Fondos Insuficientes',
            message: `Se requieren ${required} ${currency}, pero solo tienes ${available} ${currency} disponibles.`,
            duration: 6000
        });
    }
}

// Instancia global
window.notify = new NotificationSystem();

// Override de alert() nativo (opcional)
window.showNotification = function(message, type = 'info', title = '') {
    return window.notify.show({ type, title, message });
};

console.log('✅ Sistema de notificaciones cargado');
