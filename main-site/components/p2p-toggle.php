<!-- ============================================
     P2P MODE TOGGLE - Switch entre modo centralizado y P2P
     Versión compacta para móvil - misma altura que botón usuario
     ============================================ -->

<div id="p2p-toggle-container" class="flex items-center justify-center gap-2 md:gap-2 px-3 md:px-3 py-1.5 md:py-2 bg-white/5 rounded-lg border border-white/10 h-[34px] md:h-[40px]">
    <!-- Status indicator (solo desktop) -->
    <div id="p2p-status-indicator" class="hidden md:flex items-center gap-2">
        <div id="p2p-status-dot" class="w-2 h-2 rounded-full bg-gray-400 transition-colors duration-300"></div>
        <span id="p2p-status-text" class="text-xs text-white/60 font-medium whitespace-nowrap">Centralizado</span>
    </div>

    <!-- Toggle switch (centrado) -->
    <label class="relative inline-flex items-center cursor-pointer">
        <input 
            type="checkbox" 
            id="p2p-mode-toggle" 
            class="sr-only peer"
            onchange="toggleP2PMode(this.checked)"
        >
        <!-- Switch con altura proporcional -->
        <div class="w-9 h-5 md:w-11 md:h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 md:after:h-5 md:after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
    </label>

    <!-- P2P icon (centrado verticalmente) -->
    <svg id="p2p-icon" class="w-4 h-4 md:w-5 md:h-5 text-white/40 transition-colors duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
    </svg>

    <!-- Tooltip (opcional, visible al hacer tap/hover) -->
    <div id="p2p-tooltip" class="hidden absolute top-full mt-2 right-0 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg w-64 z-50">
        <div class="font-semibold mb-1">Modo P2P</div>
        <div class="text-gray-300">
            <span id="p2p-tooltip-content">
                Activa el modo descentralizado para posts en tiempo real via Gun.js
            </span>
        </div>
        <div class="absolute -top-1 right-4 w-2 h-2 bg-gray-900 transform rotate-45"></div>
    </div>
</div>

<style>
/* Animations */
@keyframes pulse-p2p {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.pulse-p2p {
    animation: pulse-p2p 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Status dot colors */
.status-offline { background-color: #6B7280; } /* Gray */
.status-connecting { background-color: #F59E0B; } /* Yellow */
.status-online { background-color: #10B981; } /* Green */
</style>

<script>
// ============================================
// P2P MODE TOGGLE LOGIC
// ============================================

// Estado inicial del toggle basado en localStorage
document.addEventListener('DOMContentLoaded', () => {
    const p2pMode = localStorage.getItem('p2pMode') === 'true';
    const toggle = document.getElementById('p2p-mode-toggle');
    
    if (toggle) {
        toggle.checked = p2pMode;
        updateP2PStatus(p2pMode);
    }

    // Mostrar tooltip al hover (desktop) y tap (móvil)
    const container = document.getElementById('p2p-toggle-container');
    const tooltip = document.getElementById('p2p-tooltip');
    
    if (container && tooltip) {
        // Desktop: hover
        container.addEventListener('mouseenter', () => {
            tooltip.classList.remove('hidden');
        });
        container.addEventListener('mouseleave', () => {
            tooltip.classList.add('hidden');
        });
        
        // Móvil: tap (solo en el icono P2P)
        const p2pIcon = document.getElementById('p2p-icon');
        if (p2pIcon) {
            p2pIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                tooltip.classList.toggle('hidden');
                
                // Auto-cerrar después de 3 segundos
                if (!tooltip.classList.contains('hidden')) {
                    setTimeout(() => {
                        tooltip.classList.add('hidden');
                    }, 3000);
                }
            });
        }
        
        // Cerrar tooltip al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
                tooltip.classList.add('hidden');
            }
        });
    }
});

/**
 * Toggle P2P mode
 */
async function toggleP2PMode(enabled) {
    console.log('🔄 Switching P2P mode:', enabled ? 'ON' : 'OFF');
    
    // Guardar en localStorage
    localStorage.setItem('p2pMode', enabled);
    
    // Actualizar UI
    updateP2PStatus(enabled);
    
    // Usar el nuevo P2P Client (reemplazo de Gun.js)
    if (typeof p2pClient !== 'undefined') {
        if (enabled) {
            // Inicializar P2P Client si no está conectado
            if (!p2pClient.isConnected) {
                updateP2PStatus(enabled, 'connecting');
                
                const userId = sessionStorage.getItem('user_id');
                if (!userId) {
                    updateP2PStatus(false, 'error');
                    document.getElementById('p2p-mode-toggle').checked = false;
                    localStorage.setItem('p2pMode', 'false');
                    showP2PNotification('❌ Usuario no autenticado', 'error');
                    return;
                }
                
                const success = await p2pClient.init(parseInt(userId));
                
                if (success) {
                    // Guardar llave pública en backend
                    await fetch('/api/p2p/save-public-key.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ publicKey: p2pClient.publicKey })
                    });
                    
                    updateP2PStatus(enabled, 'connected');
                    showP2PNotification('✅ Modo P2P activado', 'success');
                } else {
                    updateP2PStatus(false, 'error');
                    document.getElementById('p2p-mode-toggle').checked = false;
                    localStorage.setItem('p2pMode', 'false');
                    showP2PNotification('❌ Error al conectar P2P', 'error');
                }
            } else {
                updateP2PStatus(enabled, 'connected');
                showP2PNotification('✅ Modo P2P activado', 'success');
            }
        } else {
            // Desconectar P2P
            if (p2pClient.isConnected) {
                p2pClient.disconnect();
            }
            updateP2PStatus(enabled);
            showP2PNotification('🔒 Modo centralizado activado', 'info');
        }
    }
    
    // Emitir evento personalizado para que otros componentes reaccionen
    window.dispatchEvent(new CustomEvent('p2pModeChanged', { 
        detail: { enabled } 
    }));
}

/**
 * Actualizar UI del status
 */
function updateP2PStatus(enabled, state = null) {
    const statusDot = document.getElementById('p2p-status-dot');
    const statusText = document.getElementById('p2p-status-text');
    const icon = document.getElementById('p2p-icon');
    const tooltipContent = document.getElementById('p2p-tooltip-content');
    
    if (!statusDot || !statusText) return;
    
    // Remover clases previas
    statusDot.classList.remove('status-offline', 'status-connecting', 'status-online', 'pulse-p2p');
    
    if (enabled) {
        if (state === 'connecting') {
            statusDot.classList.add('status-connecting', 'pulse-p2p');
            statusText.textContent = 'Conectando...';
            statusText.classList.add('text-yellow-400');
            statusText.classList.remove('text-white/60', 'text-green-400');
            icon.classList.add('text-yellow-400');
            icon.classList.remove('text-white/40', 'text-green-400');
            if (tooltipContent) {
                tooltipContent.textContent = 'Conectando al relay Gun.js...';
            }
        } else {
            statusDot.classList.add('status-online');
            statusText.textContent = 'P2P';
            statusText.classList.add('text-green-400');
            statusText.classList.remove('text-white/60', 'text-yellow-400');
            icon.classList.add('text-green-400');
            icon.classList.remove('text-white/40', 'text-yellow-400');
            if (tooltipContent) {
                tooltipContent.textContent = 'Modo P2P activo - Posts en tiempo real';
            }
        }
    } else {
        statusDot.classList.add('status-offline');
        statusText.textContent = 'Centralizado';
        statusText.classList.add('text-white/60');
        statusText.classList.remove('text-green-400', 'text-yellow-400');
        icon.classList.add('text-white/40');
        icon.classList.remove('text-green-400', 'text-yellow-400');
        if (tooltipContent) {
            tooltipContent.textContent = 'Modo centralizado - Base de datos MySQL';
        }
    }
}

/**
 * Mostrar notificación temporal
 */
function showP2PNotification(message, type = 'info') {
    // Usar el nuevo sistema de notificaciones si está disponible
    if (typeof window.notify !== 'undefined') {
        const title = type === 'success' ? 'P2P Activado' : type === 'error' ? 'Error P2P' : 'Modo P2P';
        window.notify.show({ type: type === 'info' ? 'p2p' : type, title, message, duration: 4000 });
        return;
    }

    // Fallback a notificación simple
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-0`;

    const colors = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        info: 'bg-blue-500 text-white',
        warning: 'bg-yellow-500 text-gray-900'
    };

    notification.className += ' ' + (colors[type] || colors.info);
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);

    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Listener para cambios en P2P mode (otros componentes pueden escuchar esto)
window.addEventListener('p2pModeChanged', (event) => {
    console.log('📡 P2P Mode changed:', event.detail.enabled);
});
</script>
