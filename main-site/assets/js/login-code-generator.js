/**
 * Login Code Generator
 * Allows users to generate a 6-digit code to authorize login from another device
 */

class LoginCodeGenerator {
    constructor() {
        this.modal = null;
        this.code = null;
        this.sessionToken = null;
        this.expiresAt = null;
        this.countdownInterval = null;
        this.init();
    }

    init() {
        this.createModal();
        this.setupEventListeners();
    }

    createModal() {
        const modalHTML = `
            <div id="login-code-modal" class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
                <div class="bg-brand-bg-secondary border-2 border-brand-accent rounded-xl md:rounded-2xl max-w-md w-full p-6 md:p-8 relative animate-fade-in-up shadow-2xl">
                    <!-- Close Button -->
                    <button id="close-code-modal" class="absolute top-4 right-4 text-brand-text-secondary hover:text-brand-text-primary transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>

                    <div class="text-center mb-6">
                        <div class="w-14 h-14 md:w-16 md:h-16 bg-brand-accent/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 md:w-8 md:h-8 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl md:text-2xl font-bold text-brand-text-primary mb-2">
                            Tu Código de Acceso
                        </h2>
                        <p class="text-brand-text-secondary text-xs md:text-sm leading-relaxed">
                            Ingresa este código en el panel de <strong class="text-brand-accent">Dispositivos</strong> desde tu dispositivo principal
                        </p>
                    </div>

                    <!-- Generated Code Display -->
                    <div class="mb-6">
                        <div class="bg-brand-bg-primary border-2 border-brand-accent rounded-xl p-6 mb-4">
                            <div id="code-display" class="text-5xl md:text-6xl font-bold text-center text-brand-accent tracking-widest font-mono">
                                ------
                            </div>
                        </div>

                        <!-- Timer Display -->
                        <div class="flex items-center justify-center text-sm text-brand-text-secondary">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Expira en: <strong id="countdown-timer" class="text-brand-accent">10:00</strong></span>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 mb-6">
                        <h3 class="text-sm font-semibold text-blue-400 mb-2">Pasos para autorizar:</h3>
                        <ol class="text-xs text-brand-text-secondary space-y-1.5 list-decimal list-inside">
                            <li>Abre el panel de <strong class="text-brand-accent">Dispositivos</strong> en tu dispositivo principal</li>
                            <li>Busca la sección "Autorizar nuevo dispositivo"</li>
                            <li>Ingresa este código de 6 dígitos</li>
                            <li>Confirma la autorización</li>
                        </ol>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-3">
                        <button id="copy-code-btn" class="w-full bg-brand-accent hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-all flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Copiar Código
                        </button>

                        <a href="/devices" target="_blank" class="block w-full bg-brand-bg-primary border border-brand-border hover:bg-brand-border text-brand-text-primary font-semibold py-3 rounded-lg transition-all text-center">
                            Ir a Dispositivos
                        </a>
                    </div>

                    <!-- Loading State (hidden by default) -->
                    <div id="code-loading" class="hidden text-center py-8">
                        <div class="w-12 h-12 border-4 border-brand-accent border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                        <p class="text-brand-text-secondary text-sm">Generando código...</p>
                    </div>

                    <!-- Error State (hidden by default) -->
                    <div id="code-error" class="hidden text-center py-6">
                        <div class="w-14 h-14 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p id="error-message" class="text-red-500 text-sm mb-4">Error generando código</p>
                        <button id="retry-generate-btn" class="text-brand-accent hover:underline text-sm font-medium">
                            Intentar nuevamente
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('login-code-modal');
    }

    setupEventListeners() {
        // Generate code button
        const generateBtn = document.getElementById('generate-code-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.promptForUsername());
        }

        // Close modal
        const closeBtn = document.getElementById('close-code-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // Copy code button
        const copyBtn = document.getElementById('copy-code-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => this.copyCode());
        }

        // Retry button
        const retryBtn = document.getElementById('retry-generate-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.promptForUsername());
        }
    }

    async promptForUsername() {
        // Create a simple prompt modal for username
        const username = await this.showUsernamePrompt();
        if (username) {
            this.generateCode(username);
        }
    }

    showUsernamePrompt() {
        return new Promise((resolve) => {
            const promptHTML = `
                <div id="username-prompt-modal" class="fixed inset-0 bg-black/90 backdrop-blur-sm z-[10000] flex items-center justify-center p-4">
                    <div class="bg-brand-bg-secondary border-2 border-brand-accent rounded-xl max-w-sm w-full p-6 animate-fade-in-up">
                        <h3 class="text-xl font-bold text-brand-text-primary mb-4 text-center">Ingresa tu Usuario</h3>
                        <input type="text" id="username-input" placeholder="usuario123"
                               class="bg-brand-bg-primary border border-brand-border w-full px-4 py-3 rounded-lg text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent mb-4"
                               autocomplete="username">
                        <div class="flex gap-3">
                            <button id="confirm-username-btn" class="flex-1 bg-brand-accent hover:bg-blue-600 text-white font-semibold py-3 rounded-lg">
                                Continuar
                            </button>
                            <button id="cancel-username-btn" class="flex-1 bg-brand-bg-primary border border-brand-border hover:bg-brand-border text-brand-text-primary font-semibold py-3 rounded-lg">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', promptHTML);
            const promptModal = document.getElementById('username-prompt-modal');
            const input = document.getElementById('username-input');

            // Focus input
            setTimeout(() => input.focus(), 100);

            // Enter key to submit
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const value = input.value.trim();
                    promptModal.remove();
                    resolve(value || null);
                }
            });

            // Confirm button
            document.getElementById('confirm-username-btn').addEventListener('click', () => {
                const value = input.value.trim();
                promptModal.remove();
                resolve(value || null);
            });

            // Cancel button
            document.getElementById('cancel-username-btn').addEventListener('click', () => {
                promptModal.remove();
                resolve(null);
            });
        });
    }

    async generateCode(username) {
        this.show();
        this.showLoading();

        try {
            const response = await fetch('/api/2fa/generate-login-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username })
            });

            const data = await response.json();

            if (data.success) {
                this.code = data.code;
                this.sessionToken = data.session_token;
                this.expiresAt = new Date(data.expires_at);

                this.hideLoading();
                this.displayCode();
                this.startCountdown();

                if (typeof window.notify !== 'undefined') {
                    window.notify.success('Código generado correctamente. Ingrésalo en el panel de Dispositivos.', 'Código Generado', 5000);
                }
            } else {
                this.showError(data.error || 'No se pudo generar el código');
                if (typeof window.notify !== 'undefined') {
                    window.notify.error(data.error || 'Error al generar código', 'Error', 5000);
                }
            }
        } catch (error) {
            console.error('Error generating code:', error);
            this.showError('Error de conexión con el servidor');
            if (typeof window.notify !== 'undefined') {
                window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
            }
        }
    }

    displayCode() {
        const codeDisplay = document.getElementById('code-display');
        if (codeDisplay && this.code) {
            // Add space in the middle for readability: 123 456
            const formattedCode = this.code.slice(0, 3) + ' ' + this.code.slice(3);
            codeDisplay.textContent = formattedCode;
        }
    }

    startCountdown() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }

        const updateTimer = () => {
            const now = new Date();
            const diff = this.expiresAt - now;

            if (diff <= 0) {
                clearInterval(this.countdownInterval);
                document.getElementById('countdown-timer').textContent = 'Expirado';
                document.getElementById('countdown-timer').classList.add('text-red-500');
                if (typeof window.notify !== 'undefined') {
                    window.notify.warning('El código ha expirado. Genera uno nuevo.', 'Código Expirado', 5000);
                }
                return;
            }

            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            document.getElementById('countdown-timer').textContent =
                `${minutes}:${seconds.toString().padStart(2, '0')}`;

            // Warning when less than 2 minutes
            if (diff < 120000) {
                document.getElementById('countdown-timer').classList.add('text-yellow-500');
            }
        };

        updateTimer();
        this.countdownInterval = setInterval(updateTimer, 1000);
    }

    async copyCode() {
        if (!this.code) return;

        try {
            await navigator.clipboard.writeText(this.code);

            const copyBtn = document.getElementById('copy-code-btn');
            const originalHTML = copyBtn.innerHTML;

            copyBtn.innerHTML = `
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                ¡Copiado!
            `;

            if (typeof window.notify !== 'undefined') {
                window.notify.success('Código copiado al portapapeles', 'Copiado', 2000);
            }

            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
            }, 2000);
        } catch (error) {
            console.error('Error copying code:', error);
            if (typeof window.notify !== 'undefined') {
                window.notify.error('No se pudo copiar el código', 'Error', 3000);
            }
        }
    }

    showLoading() {
        document.getElementById('code-loading').classList.remove('hidden');
        document.getElementById('code-error').classList.add('hidden');
        // Hide the main content temporarily
        this.modal.querySelector('.bg-brand-bg-secondary > div:first-child').style.display = 'none';
    }

    hideLoading() {
        document.getElementById('code-loading').classList.add('hidden');
        this.modal.querySelector('.bg-brand-bg-secondary > div:first-child').style.display = 'block';
    }

    showError(message) {
        this.hideLoading();
        document.getElementById('code-error').classList.remove('hidden');
        document.getElementById('error-message').textContent = message;
        this.modal.querySelector('.bg-brand-bg-secondary > div:first-child').style.display = 'none';
    }

    show() {
        this.modal.classList.remove('hidden');
    }

    close() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        this.modal.classList.add('hidden');
        this.code = null;
        this.sessionToken = null;
        this.expiresAt = null;

        // Reset display
        document.getElementById('code-display').textContent = '------';
        document.getElementById('countdown-timer').textContent = '10:00';
        document.getElementById('countdown-timer').classList.remove('text-yellow-500', 'text-red-500');
        document.getElementById('code-loading').classList.add('hidden');
        document.getElementById('code-error').classList.add('hidden');
        this.modal.querySelector('.bg-brand-bg-secondary > div:first-child').style.display = 'block';
    }
}

// Initialize globally
window.LoginCodeGenerator = new LoginCodeGenerator();
