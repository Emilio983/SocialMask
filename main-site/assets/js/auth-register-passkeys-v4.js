/**
 * The Social Mask - Passkey Registration Flow
 * Sistema de registro con WebAuthn/Passkeys + Auto-creación de Smart Account (ERC-4337)
 */

// Elementos del DOM
const usernameInput = document.getElementById('username');
const registerBtn = document.getElementById('register-btn');
const btnText = document.getElementById('btn-text');
const statusDisplay = document.getElementById('status-display');
const statusIcon = document.getElementById('status-icon');
const statusText = document.getElementById('status-text');

// Estado de la aplicación
let isRegistering = false;

/**
 * Muestra un mensaje de estado al usuario
 */
function showStatus(message, type = 'info') {
    statusDisplay.classList.remove('hidden', 'status-success', 'status-error', 'status-info');
    statusDisplay.classList.add(`status-${type}`);
    statusText.textContent = message;

    // Animación del icono
    if (type === 'info') {
        statusIcon.className = 'w-4 h-4 rounded-full bg-brand-accent animate-pulse';
    } else if (type === 'success') {
        statusIcon.className = 'w-4 h-4 rounded-full bg-brand-success';
    } else {
        statusIcon.className = 'w-4 h-4 rounded-full bg-brand-error';
    }
}

/**
 * Valida el nombre de usuario
 */
function validateUsername(username) {
    if (!username || username.length < 3) {
        return 'El usuario debe tener al menos 3 caracteres';
    }
    if (username.length > 20) {
        return 'El usuario no puede tener más de 20 caracteres';
    }
    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        return 'El usuario solo puede contener letras, números y guiones bajos';
    }
    return null;
}

/**
 * Verifica si el navegador soporta passkeys/WebAuthn
 * Compatible con: Chrome 67+, Safari 13+, Firefox 60+, Edge 18+
 */
async function checkPasskeySupport() {
    // Verificar si PublicKeyCredential existe
    if (!window.PublicKeyCredential) {
        console.warn('PublicKeyCredential API not available');
        return false;
    }

    try {
        // Verificar disponibilidad de plataforma (platform authenticator)
        // Esto puede fallar pero el navegador aún puede soportar passkeys
        if (typeof PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
            try {
                const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                console.log('Platform authenticator available:', available);
            } catch (error) {
                console.debug('Platform authenticator check failed:', error);
            }
        }
        
        // Chrome/Safari/Edge/Firefox modernos soportan passkeys
        // Permitimos que el usuario intente incluso si la verificación falla
        return true;
    } catch (error) {
        console.warn('Error checking authenticator availability:', error);
        // Si falla la verificación, asumimos que está disponible
        return true;
    }
}

/**
 * Convierte base64url a ArrayBuffer
 */
function base64urlToBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

/**
 * Convierte ArrayBuffer a base64url
 */
function bufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

/**
 * Genera un UUID v4
 */
function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

/**
 * Paso 1: Solicitar opciones de registro al backend
 */
async function startRegistration(username) {
    // Generar un challengeId único
    const challengeId = generateUUID();

    const response = await fetch('/api/auth/passkey_register_start.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ username, challengeId })
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Error al iniciar registro');
    }

    // Agregar challengeId a la respuesta para usarlo en finish
    data.challengeId = challengeId;

    return data;
}

/**
 * Paso 2: Crear el passkey con WebAuthn
 * Compatible con diferentes configuraciones de navegador/dispositivo
 */
async function createPasskey(options) {
    // Verificar que tenemos los datos necesarios
    if (!options || !options.publicKey) {
        throw new Error('Opciones de passkey inválidas');
    }

    // Convertir los datos base64url a ArrayBuffer
    const publicKey = {
        ...options.publicKey,
        challenge: base64urlToBuffer(options.publicKey.challenge),
        user: {
            ...options.publicKey.user,
            id: base64urlToBuffer(options.publicKey.user.id)
        }
    };

    try {
        // Intentar crear la credencial
        console.log('Creating passkey with options:', publicKey);
        const credential = await navigator.credentials.create({ publicKey });
        
        if (!credential) {
            throw new Error('No se pudo crear el passkey');
        }
        
        return credential;
    } catch (error) {
        console.error('Error creating passkey:', error);
        
        // Si falla con platform authenticator, intentar sin restricción
        if (error.name === 'NotAllowedError') {
            throw new Error('Creación de passkey cancelada. Por favor intenta de nuevo.');
        }
        
        if (error.name === 'NotSupportedError' || error.name === 'SecurityError') {
            console.warn('Platform authenticator failed, trying cross-platform:', error);

            // Eliminar restricción de plataforma para permitir security keys USB/NFC
            const fallbackPublicKey = {
                ...publicKey,
                authenticatorSelection: {
                    ...publicKey.authenticatorSelection,
                    authenticatorAttachment: undefined, // Permitir cualquier tipo
                    requireResidentKey: false, // Hacer menos restrictivo
                    residentKey: 'preferred',
                }
            };

            try {
                const credential = await navigator.credentials.create({ publicKey: fallbackPublicKey });
                if (!credential) {
                    throw new Error('No se pudo crear el passkey');
                }
                return credential;
            } catch (fallbackError) {
                console.error('Fallback creation also failed:', fallbackError);
                throw new Error('Tu dispositivo o navegador no soporta la creación de passkeys. Por favor usa Chrome, Safari o Edge actualizado.');
            }
        }
        
        throw error;
    }
}

/**
 * Paso 3: Enviar el passkey al backend para finalizar registro
 */
async function finishRegistration(credential, username, challengeId) {
    // Preparar datos para enviar
    const credentialData = {
        id: credential.id,
        rawId: bufferToBase64url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
            attestationObject: bufferToBase64url(credential.response.attestationObject)
        }
    };

    const response = await fetch('/api/auth/passkey_register_finish.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            username,
            challengeId,
            credential: credentialData
        })
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Error al completar registro');
    }

    return data;
}

/**
 * Maneja el proceso completo de registro
 */
async function handleRegistration() {
    // Validaciones previas
    if (isRegistering) return;

    const username = usernameInput.value.trim();
    const validationError = validateUsername(username);
    if (validationError) {
        showStatus(validationError, 'error');
        return;
    }

    // Verificación básica de WebAuthn (muy permisiva)
    // Solo verificamos lo mínimo necesario
    if (!window.PublicKeyCredential) {
        showStatus('Tu navegador no soporta passkeys. Por favor usa un navegador moderno en HTTPS.', 'error');
        return;
    }

    try {
        isRegistering = true;
        registerBtn.disabled = true;
        btnText.textContent = 'Creando cuenta...';
        showStatus('Iniciando registro...', 'info');

        // Paso 1: Obtener opciones del servidor
        showStatus('Preparando passkey...', 'info');
        const options = await startRegistration(username);

        // Verificar que recibimos las opciones correctamente
        if (!options || !options.publicKey) {
            throw new Error('El servidor no devolvió las opciones de passkey correctamente');
        }

        // Paso 2: Crear passkey en el dispositivo
        showStatus('Crea tu passkey (usa huella, Face ID, PIN o security key)', 'info');
        const credential = await createPasskey(options);

        // Paso 3: Finalizar registro en el servidor
        showStatus('Creando tu cuenta y wallet...', 'info');
        const result = await finishRegistration(credential, username, options.challengeId);

        // Éxito
        showStatus('¡Cuenta creada exitosamente! Redirigiendo...', 'success');

        // Mostrar información de la wallet creada
        if (result.user && result.user.smart_account_address) {
            console.log('Smart Account creada:', result.user.smart_account_address);
            console.log('Username:', result.user.username);
            console.log('User ID:', result.user.id);
        }

        // Redirigir al dashboard después de 2 segundos
        setTimeout(() => {
            window.location.href = '/pages/dashboard.php';
        }, 2000);

    } catch (error) {
        console.error('Error en registro:', error);

        let errorMessage = 'Error al crear cuenta';

        // Errores específicos de WebAuthn
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Registro cancelado. Por favor intenta de nuevo.';
        } else if (error.name === 'InvalidStateError') {
            errorMessage = 'Ya existe un passkey para este dispositivo.';
        } else if (error.name === 'NotSupportedError') {
            errorMessage = 'Tu dispositivo no soporta este tipo de passkey. Intenta con otro navegador o dispositivo.';
        } else if (error.message) {
            errorMessage = error.message;
        }

        showStatus(errorMessage, 'error');

    } finally {
        isRegistering = false;
        registerBtn.disabled = false;
        btnText.textContent = 'Crear Cuenta con Passkey';
    }
}

// Event Listeners
registerBtn.addEventListener('click', handleRegistration);

// Permitir registro con Enter
usernameInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        handleRegistration();
    }
});

// Limpiar mensajes de error al escribir
usernameInput.addEventListener('input', () => {
    if (!statusDisplay.classList.contains('hidden')) {
        statusDisplay.classList.add('hidden');
    }
});

// Verificar soporte al cargar (de manera asíncrona)
window.addEventListener('load', async () => {
    const isSupported = await checkPasskeySupport();
    if (!isSupported) {
        console.warn('WebAuthn not fully supported, but registration will still be attempted');
        // No deshabilitamos el botón, dejamos que el usuario intente
    }
});
