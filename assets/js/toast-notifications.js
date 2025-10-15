/**
 * ============================================
 * TOAST NOTIFICATIONS SYSTEM
 * Sistema global de notificaciones bonitas
 * ============================================
 */

class ToastNotificationSystem {
    constructor() {
        this.container = null;
        this.init();
    }

    /**
     * Inicializar el contenedor de notificaciones
     */
    init() {
        // Crear contenedor si no existe
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'fixed top-24 right-4 z-[9999] space-y-3 pointer-events-none';
            this.container.style.maxWidth = '420px';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    /**
     * Mostrar notificación
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - Tipo: success, error, warning, info
     * @param {number} duration - Duración en ms (0 = no auto-close)
     */
    show(message, type = 'info', duration = 5000) {
        const toast = this.createToast(message, type);
        this.container.appendChild(toast);

        // Animar entrada
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        });

        // Auto-cerrar
        if (duration > 0) {
            setTimeout(() => {
                this.close(toast);
            }, duration);
        }

        return toast;
    }

    /**
     * Crear elemento de toast
     */
    createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast-notification pointer-events-auto';

        // Colores según tipo
        const styles = {
            success: {
                bg: 'bg-green-500',
                icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                text: 'text-green-50'
            },
            error: {
                bg: 'bg-red-500',
                icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                text: 'text-red-50'
            },
            warning: {
                bg: 'bg-yellow-500',
                icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                text: 'text-yellow-50'
            },
            info: {
                bg: 'bg-blue-500',
                icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                text: 'text-blue-50'
            }
        };

        const style = styles[type] || styles.info;

        toast.innerHTML = `
            <div class="${style.bg} ${style.text} rounded-lg shadow-2xl p-4 flex items-start gap-3 min-w-[300px] max-w-[420px] backdrop-blur-sm">
                <div class="flex-shrink-0 pt-0.5">
                    ${style.icon}
                </div>
                <div class="flex-1 mr-2">
                    <p class="text-sm font-medium leading-relaxed">${this.escapeHtml(message)}</p>
                </div>
                <button onclick="window.Toast.close(this.closest('.toast-notification'))" class="flex-shrink-0 hover:opacity-80 transition-opacity">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;

        // Estilos iniciales para animación
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(400px)';
        toast.style.transition = 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';

        return toast;
    }

    /**
     * Cerrar notificación
     */
    close(toast) {
        if (!toast) return;

        toast.style.opacity = '0';
        toast.style.transform = 'translateX(400px)';

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Métodos de conveniencia
     */
    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 7000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }

    /**
     * Confirmar acción (reemplazo de confirm())
     */
    async confirm(message, title = '¿Confirmar acción?', options = {}) {
        return new Promise((resolve) => {
            const modal = this.createConfirmModal(message, title, options, resolve);
            document.body.appendChild(modal);

            // Animar entrada
            requestAnimationFrame(() => {
                modal.querySelector('.modal-content').style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'scale(1)';
            });
        });
    }

    /**
     * Crear modal de confirmación
     */
    createConfirmModal(message, title, options, resolve) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-[10000] flex items-center justify-center p-4';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';
        modal.style.backdropFilter = 'blur(4px)';

        const confirmText = options.confirmText || 'Confirmar';
        const cancelText = options.cancelText || 'Cancelar';
        const type = options.type || 'warning'; // success, error, warning, info

        const iconColors = {
            success: 'text-green-500',
            error: 'text-red-500',
            warning: 'text-yellow-500',
            info: 'text-blue-500'
        };

        const buttonColors = {
            success: 'bg-green-600 hover:bg-green-700',
            error: 'bg-red-600 hover:bg-red-700',
            warning: 'bg-yellow-600 hover:bg-yellow-700',
            info: 'bg-blue-600 hover:bg-blue-700'
        };

        modal.innerHTML = `
            <div class="modal-content bg-gray-900 rounded-xl shadow-2xl max-w-md w-full border border-gray-700 overflow-hidden"
                 style="opacity: 0; transform: scale(0.9); transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);">
                <!-- Header -->
                <div class="p-6 border-b border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="${iconColors[type]}">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-white">${this.escapeHtml(title)}</h3>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-6">
                    <p class="text-gray-300 leading-relaxed whitespace-pre-wrap">${this.escapeHtml(message)}</p>
                </div>

                <!-- Footer -->
                <div class="p-6 bg-gray-800 flex gap-3 justify-end">
                    <button class="cancel-btn px-6 py-2.5 rounded-lg font-semibold text-gray-300 bg-gray-700 hover:bg-gray-600 transition-colors">
                        ${this.escapeHtml(cancelText)}
                    </button>
                    <button class="confirm-btn px-6 py-2.5 rounded-lg font-semibold text-white ${buttonColors[type]} transition-colors">
                        ${this.escapeHtml(confirmText)}
                    </button>
                </div>
            </div>
        `;

        // Event listeners
        const closeModal = (confirmed) => {
            modal.querySelector('.modal-content').style.opacity = '0';
            modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
            setTimeout(() => {
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
                resolve(confirmed);
            }, 300);
        };

        modal.querySelector('.confirm-btn').addEventListener('click', () => closeModal(true));
        modal.querySelector('.cancel-btn').addEventListener('click', () => closeModal(false));

        // Cerrar con ESC
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeModal(false);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);

        // Cerrar al hacer click fuera
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(false);
            }
        });

        return modal;
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Limpiar todas las notificaciones
     */
    clearAll() {
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
}

// Instancia global
window.Toast = new ToastNotificationSystem();

// Alias para compatibilidad
window.showToast = (message, type, duration) => window.Toast.show(message, type, duration);
window.showSuccess = (message, duration) => window.Toast.success(message, duration);
window.showError = (message, duration) => window.Toast.error(message, duration);
window.showWarning = (message, duration) => window.Toast.warning(message, duration);
window.showInfo = (message, duration) => window.Toast.info(message, duration);
window.confirmAction = (message, title, options) => window.Toast.confirm(message, title, options);

console.log('✅ Toast Notification System loaded');
