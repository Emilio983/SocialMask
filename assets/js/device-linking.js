/**
 * ============================================
 * DEVICE LINKING SYSTEM - FRONTEND
 * ============================================
 * Sistema de vinculación de dispositivos con código temporal
 */

class DeviceLinkingManager {
    constructor() {
        this.currentCode = null;
        this.currentToken = null;
        this.expiresAt = null;
        this.timerInterval = null;
        this.refreshInterval = null;
    }

    /**
     * Generar nuevo código de vinculación
     */
    async generateCode() {
        try {
            showLoading('Generando código seguro...');
            
            const response = await fetch('/api/devices/generate-link-code.php', {
                method: 'POST',
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Error generando código');
            }
            
            if (data.success) {
                this.currentCode = data.code;
                this.currentToken = data.session_token;
                this.expiresAt = new Date(data.expires_at);
                
                this.displayCode(data.code);
                this.startTimer();
                this.startAutoRefresh();
                
                showToast('✅ Código generado. Válido por 5 minutos', 'success');
                
                // Log de seguridad
                console.log('🔐 Código de vinculación generado');
                
                return data;
            }
        } catch (error) {
            console.error('Error generando código:', error);
            showToast('❌ ' + error.message, 'error');
            hideLoading();
        }
    }

    /**
     * Mostrar código en UI
     */
    displayCode(code) {
        const codeContainer = document.getElementById('code-display');
        if (!codeContainer) return;
        
        // Limpiar
        codeContainer.innerHTML = '';
        
        // Crear dígitos
        const digits = code.split('');
        digits.forEach((digit, index) => {
            const digitEl = document.createElement('div');
            digitEl.className = 'code-digit';
            digitEl.textContent = digit;
            digitEl.style.animationDelay = `${index * 0.1}s`;
            codeContainer.appendChild(digitEl);
            
            // Separador cada 4 dígitos
            if (index === 3) {
                const separator = document.createElement('div');
                separator.className = 'code-separator';
                separator.textContent = '-';
                codeContainer.appendChild(separator);
            }
        });
        
        // Mostrar sección de código
        document.getElementById('code-section').classList.remove('hidden');
        document.getElementById('generate-section').classList.add('hidden');
        
        hideLoading();
    }

    /**
     * Iniciar temporizador de expiración
     */
    startTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
        
        this.updateTimer();
        
        this.timerInterval = setInterval(() => {
            this.updateTimer();
        }, 1000);
    }

    /**
     * Actualizar temporizador visual
     */
    updateTimer() {
        const now = new Date();
        const timeLeft = Math.max(0, this.expiresAt - now);
        
        if (timeLeft === 0) {
            this.codeExpired();
            return;
        }
        
        const minutes = Math.floor(timeLeft / 60000);
        const seconds = Math.floor((timeLeft % 60000) / 1000);
        
        const timerEl = document.getElementById('timer');
        if (timerEl) {
            timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Cambiar color según tiempo restante
            if (minutes < 1) {
                timerEl.classList.add('text-red-500');
                timerEl.classList.remove('text-blue-500');
            }
        }
        
        // Actualizar barra de progreso
        const progressBar = document.getElementById('timer-progress');
        if (progressBar) {
            const totalTime = 5 * 60 * 1000; // 5 minutos
            const percentage = (timeLeft / totalTime) * 100;
            progressBar.style.width = `${percentage}%`;
            
            if (percentage < 20) {
                progressBar.classList.add('bg-red-500');
                progressBar.classList.remove('bg-blue-500');
            }
        }
    }

    /**
     * Código expirado
     */
    codeExpired() {
        clearInterval(this.timerInterval);
        clearInterval(this.refreshInterval);
        
        showToast('⏰ El código ha expirado', 'warning');
        
        // Ocultar código
        document.getElementById('code-section').classList.add('hidden');
        document.getElementById('generate-section').classList.remove('hidden');
        
        this.currentCode = null;
        this.currentToken = null;
        this.expiresAt = null;
    }

    /**
     * Auto-refresh para verificar si el código fue usado
     */
    startAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        this.refreshInterval = setInterval(async () => {
            await this.checkCodeStatus();
        }, 3000); // Cada 3 segundos
    }

    /**
     * Verificar estado del código
     */
    async checkCodeStatus() {
        if (!this.currentToken) return;
        
        try {
            const response = await fetch(`/api/devices/check-code-status.php?token=${this.currentToken}`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.status === 'used') {
                clearInterval(this.refreshInterval);
                clearInterval(this.timerInterval);
                
                showToast('🎉 ¡Dispositivo vinculado exitosamente!', 'success');
                
                // Recargar lista de dispositivos
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        } catch (error) {
            console.error('Error verificando estado:', error);
        }
    }

    /**
     * Cancelar código actual
     */
    async cancelCode() {
        if (!this.currentToken) return;
        
        try {
            const response = await fetch('/api/devices/cancel-link-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ session_token: this.currentToken })
            });
            
            const data = await response.json();
            
            if (data.success) {
                clearInterval(this.timerInterval);
                clearInterval(this.refreshInterval);
                
                document.getElementById('code-section').classList.add('hidden');
                document.getElementById('generate-section').classList.remove('hidden');
                
                this.currentCode = null;
                this.currentToken = null;
                this.expiresAt = null;
                
                showToast('Código cancelado', 'info');
            }
        } catch (error) {
            console.error('Error cancelando código:', error);
        }
    }

    /**
     * Copiar código al portapapeles
     */
    copyCode() {
        if (!this.currentCode) return;
        
        const formattedCode = this.currentCode.slice(0, 4) + '-' + this.currentCode.slice(4);
        
        navigator.clipboard.writeText(formattedCode).then(() => {
            showToast('📋 Código copiado', 'success');
        }).catch(() => {
            showToast('❌ Error copiando código', 'error');
        });
    }
}

// ============================================
// DEVICE FINGERPRINTING
// ============================================

class DeviceFingerprint {
    /**
     * Generar fingerprint único del dispositivo
     */
    static async generate() {
        const components = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            screenResolution: `${screen.width}x${screen.height}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            hardwareConcurrency: navigator.hardwareConcurrency,
            deviceMemory: navigator.deviceMemory || 'unknown',
            colorDepth: screen.colorDepth,
            pixelRatio: window.devicePixelRatio,
            touchSupport: 'ontouchstart' in window,
            webgl: this.getWebGLInfo(),
            canvas: await this.getCanvasFingerprint(),
            fonts: await this.getFontFingerprint()
        };
        
        // Crear hash del fingerprint
        const fingerprint = JSON.stringify(components);
        const hash = await this.sha256(fingerprint);
        
        return hash;
    }

    static getWebGLInfo() {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (!gl) return 'not-supported';
        
        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        if (debugInfo) {
            return {
                vendor: gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL),
                renderer: gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
            };
        }
        
        return 'available';
    }

    static async getCanvasFingerprint() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('Fingerprint', 2, 2);
        return canvas.toDataURL().slice(0, 50);
    }

    static async getFontFingerprint() {
        const baseFonts = ['monospace', 'sans-serif', 'serif'];
        const testFonts = ['Arial', 'Verdana', 'Times New Roman', 'Courier New'];
        const detected = [];
        
        for (const font of testFonts) {
            if (this.detectFont(font, baseFonts)) {
                detected.push(font);
            }
        }
        
        return detected.join(',');
    }

    static detectFont(font, baseFonts) {
        const testString = 'mmmmmmmmmmlli';
        const testSize = '72px';
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        const baselineWidth = {};
        for (const baseFont of baseFonts) {
            ctx.font = testSize + ' ' + baseFont;
            baselineWidth[baseFont] = ctx.measureText(testString).width;
        }
        
        for (const baseFont of baseFonts) {
            ctx.font = testSize + ' ' + font + ',' + baseFont;
            const width = ctx.measureText(testString).width;
            if (width !== baselineWidth[baseFont]) {
                return true;
            }
        }
        
        return false;
    }

    static async sha256(message) {
        const msgBuffer = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        return hashHex;
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function showToast(message, type = 'info') {
    // Usar sistema de toast existente o crear uno simple
    if (typeof toast !== 'undefined') {
        toast[type](message);
    } else {
        console.log(`[${type.toUpperCase()}] ${message}`);
        alert(message);
    }
}

function showLoading(message = 'Cargando...') {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.classList.remove('hidden');
        const loaderText = loader.querySelector('.loader-text');
        if (loaderText) loaderText.textContent = message;
    }
}

function hideLoading() {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.classList.add('hidden');
    }
}

// ============================================
// GLOBAL INSTANCE
// ============================================
window.deviceLinkingManager = new DeviceLinkingManager();
window.DeviceFingerprint = DeviceFingerprint;

console.log('🔐 Device Linking System loaded');
