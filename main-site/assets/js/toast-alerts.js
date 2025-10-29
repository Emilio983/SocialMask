/**
 * Sistema de Alertas Mejorado para The Social Mask
 * Reemplaza los alert() nativos de JavaScript con toasts estilizados
 */

(function() {
    // Crear contenedor de toasts si no existe
    function ensureToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    // Función principal para mostrar alertas
    window.showAlert = function(message, type = 'info', duration = 5000) {
        const container = ensureToastContainer();

        // Crear elemento de toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} toast-enter`;

        // Icono según el tipo
        let icon = '';
        switch(type) {
            case 'success':
                icon = `<svg class="toast-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>`;
                break;
            case 'error':
                icon = `<svg class="toast-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>`;
                break;
            case 'warning':
                icon = `<svg class="toast-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>`;
                break;
            default: // info
                icon = `<svg class="toast-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>`;
        }

        // Botón de cerrar
        const closeBtn = `<button class="toast-close" onclick="this.parentElement.remove()">
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>`;

        toast.innerHTML = `
            ${icon}
            <div class="toast-content">
                <p class="toast-message">${escapeHtml(message)}</p>
            </div>
            ${closeBtn}
        `;

        container.appendChild(toast);

        // Animación de entrada
        setTimeout(() => {
            toast.classList.remove('toast-enter');
            toast.classList.add('toast-visible');
        }, 10);

        // Auto-cerrar después de la duración especificada
        if (duration > 0) {
            setTimeout(() => {
                closeToast(toast);
            }, duration);
        }

        return toast;
    };

    // Cerrar toast con animación
    function closeToast(toast) {
        toast.classList.remove('toast-visible');
        toast.classList.add('toast-exit');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

    // Escape HTML para prevenir XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Sobrescribir alert() nativo para usar el sistema de toasts
    window.alert = function(message) {
        // Detectar tipo de mensaje por palabras clave
        let type = 'info';
        const lowerMsg = String(message).toLowerCase();

        if (lowerMsg.includes('error') || lowerMsg.includes('failed') || lowerMsg.includes('invalid')) {
            type = 'error';
        } else if (lowerMsg.includes('success') || lowerMsg.includes('exitoso') || lowerMsg.includes('completado')) {
            type = 'success';
        } else if (lowerMsg.includes('warning') || lowerMsg.includes('advertencia') || lowerMsg.includes('cuidado')) {
            type = 'warning';
        }

        showAlert(message, type);
    };

    // Función confirm mejorada (requiere confirmación del usuario)
    window.confirmAction = function(message, onConfirm, onCancel) {
        const container = ensureToastContainer();

        const modal = document.createElement('div');
        modal.className = 'toast-modal';
        modal.innerHTML = `
            <div class="toast-modal-backdrop" onclick="this.parentElement.remove(); ${onCancel ? 'onCancel()' : ''}"></div>
            <div class="toast-modal-content">
                <h3 class="toast-modal-title">Confirmar Acción</h3>
                <p class="toast-modal-message">${escapeHtml(message)}</p>
                <div class="toast-modal-buttons">
                    <button class="toast-btn toast-btn-cancel" onclick="this.closest('.toast-modal').remove(); ${onCancel ? 'onCancel()' : ''}">
                        Cancelar
                    </button>
                    <button class="toast-btn toast-btn-confirm" onclick="this.closest('.toast-modal').remove(); ${onConfirm ? 'onConfirm()' : ''}">
                        Confirmar
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    };

    // Agregar estilos CSS
    const styles = document.createElement('style');
    styles.textContent = `
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .toast {
            background: #161B22;
            border: 1px solid #30363D;
            border-radius: 12px;
            padding: 16px;
            min-width: 320px;
            max-width: 480px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            pointer-events: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toast-enter {
            opacity: 0;
            transform: translateX(100px);
        }

        .toast-visible {
            opacity: 1;
            transform: translateX(0);
        }

        .toast-exit {
            opacity: 0;
            transform: translateX(100px);
        }

        .toast-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .toast-success {
            border-left: 4px solid #28A745;
        }

        .toast-success .toast-icon {
            color: #28A745;
        }

        .toast-error {
            border-left: 4px solid #DC3545;
        }

        .toast-error .toast-icon {
            color: #DC3545;
        }

        .toast-warning {
            border-left: 4px solid #FFC107;
        }

        .toast-warning .toast-icon {
            color: #FFC107;
        }

        .toast-info {
            border-left: 4px solid #3B82F6;
        }

        .toast-info .toast-icon {
            color: #3B82F6;
        }

        .toast-content {
            flex: 1;
            min-width: 0;
        }

        .toast-message {
            color: #C9D1D9;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .toast-close {
            background: none;
            border: none;
            color: #8B949E;
            cursor: pointer;
            padding: 4px;
            flex-shrink: 0;
            transition: color 0.2s;
        }

        .toast-close:hover {
            color: #C9D1D9;
        }

        .toast-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }

        .toast-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }

        .toast-modal-content {
            position: relative;
            background: #161B22;
            border: 1px solid #30363D;
            border-radius: 16px;
            padding: 24px;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            animation: slideUp 0.3s ease-out;
        }

        .toast-modal-title {
            color: #C9D1D9;
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 12px 0;
        }

        .toast-modal-message {
            color: #8B949E;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }

        .toast-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .toast-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .toast-btn-cancel {
            background: #21262D;
            color: #C9D1D9;
        }

        .toast-btn-cancel:hover {
            background: #30363D;
        }

        .toast-btn-confirm {
            background: #3B82F6;
            color: white;
        }

        .toast-btn-confirm:hover {
            background: #2563EB;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 640px) {
            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
            }

            .toast {
                min-width: 0;
                width: 100%;
            }

            .toast-modal-content {
                width: 95%;
                padding: 20px;
            }
        }
    `;
    document.head.appendChild(styles);
})();
