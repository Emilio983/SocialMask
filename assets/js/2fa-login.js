/**
 * 2FA Login Integration
 * Handles device authorization with 6-digit codes
 */

class TwoFactorLogin {
    constructor() {
        this.modal = null;
        this.userId = null;
        this.requestId = null;
        this.pollInterval = null;
        this.init();
    }

    init() {
        this.createModal();
    }

    createModal() {
        const modalHTML = `
            <div id="twofa-modal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center p-4">
                <div class="bg-brand-bg-secondary border-2 border-brand-border rounded-2xl max-w-md w-full p-8 relative">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-brand-accent bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-shield-alt text-3xl text-brand-accent"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-brand-text-primary mb-2">
                            Verificación Requerida
                        </h2>
                        <p class="text-brand-text-secondary text-sm">
                            Este es un dispositivo nuevo. Ingresa el código de 6 dígitos desde tu dispositivo autorizado.
                        </p>
                    </div>

                    <!-- 6-Digit Code Input -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-brand-text-primary mb-3 text-center">
                            Código de Seguridad
                        </label>
                        <div class="flex justify-center gap-2 mb-4" id="code-inputs">
                            <input type="text" maxlength="1" class="code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="0">
                            <input type="text" maxlength="1" class="code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="1">
                            <input type="text" maxlength="1" class="code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="2">
                            <input type="text" maxlength="1" class="code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="3">
                            <input type="text" maxlength="1" class="code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="4">
                            <input type="text" maxlength="1" class="code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="5">
                        </div>

                        <!-- Error Message -->
                        <div id="code-error" class="hidden text-brand-error text-sm text-center mb-3">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <span id="code-error-text">Código incorrecto</span>
                        </div>
                    </div>

                    <!-- Status Message -->
                    <div id="approval-status" class="hidden mb-4 p-3 rounded-lg border border-yellow-800 bg-yellow-900 bg-opacity-20">
                        <div class="flex items-center justify-center text-yellow-500 text-sm">
                            <i class="fas fa-clock mr-2 animate-pulse"></i>
                            <span>Esperando aprobación desde tu dispositivo autorizado...</span>
                        </div>
                    </div>

                    <!-- Verify Button -->
                    <button id="verify-2fa-btn" class="w-full bg-brand-accent hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed mb-3">
                        <i class="fas fa-check mr-2"></i>
                        Verificar Código
                    </button>

                    <!-- Help Text -->
                    <div class="text-center">
                        <p class="text-brand-text-secondary text-xs mb-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            El código está en la página <a href="/devices" class="text-brand-accent hover:underline" target="_blank">"Dispositivos"</a> de tu cuenta
                        </p>
                        <button id="cancel-2fa-btn" class="text-brand-text-secondary hover:text-brand-text-primary text-sm transition-colors">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('twofa-modal');
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Code input auto-focus and auto-submit
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;

                // Only allow numbers
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }

                // Auto-move to next input
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                // Auto-submit when all filled
                const code = this.getCode();
                if (code.length === 6) {
                    this.verifyCode(code);
                }
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = e.clipboardData.getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, 6);

                digits.split('').forEach((digit, i) => {
                    if (inputs[i]) {
                        inputs[i].value = digit;
                    }
                });

                if (digits.length === 6) {
                    this.verifyCode(digits);
                } else if (digits.length > 0) {
                    inputs[Math.min(digits.length, inputs.length - 1)].focus();
                }
            });
        });

        // Verify button
        document.getElementById('verify-2fa-btn').addEventListener('click', () => {
            const code = this.getCode();
            if (code.length === 6) {
                this.verifyCode(code);
            } else {
                this.showError('Ingresa los 6 dígitos del código');
            }
        });

        // Cancel button
        document.getElementById('cancel-2fa-btn').addEventListener('click', () => {
            this.close();
            window.location.href = '/login';
        });
    }

    getCode() {
        const inputs = document.querySelectorAll('.code-input');
        return Array.from(inputs).map(input => input.value).join('');
    }

    showError(message) {
        const errorEl = document.getElementById('code-error');
        const errorText = document.getElementById('code-error-text');
        errorText.textContent = message;
        errorEl.classList.remove('hidden');

        // Shake animation
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach(input => {
            input.classList.add('border-brand-error');
            setTimeout(() => input.classList.remove('border-brand-error'), 500);
        });
    }

    hideError() {
        document.getElementById('code-error').classList.add('hidden');
    }

    async verifyCode(code) {
        this.hideError();
        const btn = document.getElementById('verify-2fa-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';

        try {
            const response = await fetch('/api/2fa/verify-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: this.userId,
                    code: code
                })
            });

            const data = await response.json();

            if (data.success) {
                // Code verified! Now wait for approval or proceed
                this.showApprovalStatus();
                this.startPollingApproval();
            } else {
                if (data.locked_out) {
                    this.showError('Demasiados intentos. Espera 5 minutos.');
                    setTimeout(() => this.close(), 3000);
                } else {
                    this.showError(data.error || 'Código incorrecto');
                    this.clearCode();
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check mr-2"></i>Verificar Código';
            }
        } catch (error) {
            console.error('Error verifying code:', error);
            this.showError('Error de conexión');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check mr-2"></i>Verificar Código';
        }
    }

    clearCode() {
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach(input => input.value = '');
        inputs[0].focus();
    }

    showApprovalStatus() {
        document.getElementById('approval-status').classList.remove('hidden');
        document.getElementById('verify-2fa-btn').classList.add('hidden');
    }

    async startPollingApproval() {
        // Poll every 2 seconds for approval
        this.pollInterval = setInterval(async () => {
            try {
                const response = await fetch(`/api/2fa/check-request-status.php?request_id=${this.requestId}`);
                const data = await response.json();

                if (data.status === 'approved') {
                    clearInterval(this.pollInterval);
                    this.onApproved();
                } else if (data.status === 'rejected') {
                    clearInterval(this.pollInterval);
                    this.onRejected();
                } else if (data.status === 'expired') {
                    clearInterval(this.pollInterval);
                    this.showError('Solicitud expirada. Intenta nuevamente.');
                    setTimeout(() => this.close(), 2000);
                }
            } catch (error) {
                console.error('Error checking approval status:', error);
            }
        }, 2000);
    }

    onApproved() {
        document.getElementById('approval-status').innerHTML = `
            <div class="flex items-center justify-center text-brand-success text-sm">
                <i class="fas fa-check-circle mr-2"></i>
                <span>¡Aprobado! Redirigiendo...</span>
            </div>
        `;
        document.getElementById('approval-status').classList.remove('border-yellow-800', 'bg-yellow-900');
        document.getElementById('approval-status').classList.add('border-green-800', 'bg-green-900');

        setTimeout(() => {
            window.location.href = '/dashboard';
        }, 1500);
    }

    onRejected() {
        this.showError('Inicio de sesión rechazado desde tu dispositivo autorizado');
        setTimeout(() => {
            this.close();
            window.location.href = '/login';
        }, 3000);
    }

    show(userId, requestId) {
        this.userId = userId;
        this.requestId = requestId;
        this.modal.classList.remove('hidden');

        // Focus first input
        setTimeout(() => {
            document.querySelector('.code-input').focus();
        }, 100);
    }

    close() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        this.modal.classList.add('hidden');
        this.clearCode();
        this.hideError();
    }
}

// Initialize globally
window.TwoFactorLogin = new TwoFactorLogin();
