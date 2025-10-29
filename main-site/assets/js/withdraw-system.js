/**
 * thesocialmask - Withdraw System UI
 * Sistema de retiros SPHE→USDT→dirección externa con límites diarios
 */

// Estado global
let withdrawHistory = [];
let dailyLimitUsed = 0;
const DAILY_LIMIT_USD = 1000;

/**
 * Inicializar sistema de retiros
 */
function initWithdrawSystem() {
  refreshWithdrawHistory();
  updateLimitsDisplay();
  
  // Event listener para el formulario
  const form = document.getElementById('withdrawForm');
  if (form) {
    form.addEventListener('submit', handleWithdrawSubmit);
  }

  // Actualizar historial cada 30 segundos
  setInterval(() => refreshWithdrawHistory(), 30000);

  console.log('✅ Sistema de retiros inicializado');
}

/**
 * Manejar envío del formulario de retiro
 */
async function handleWithdrawSubmit(e) {
  e.preventDefault();
  
  const submitBtn = document.getElementById('withdrawSubmitBtn');
  const externalAddress = document.getElementById('withdrawAddress').value.trim();
  const amountSphe = document.getElementById('withdrawAmount').value.trim();

  // Validaciones
  if (!externalAddress || !amountSphe) {
    showWithdrawNotification('Error', 'Completa todos los campos', 'error');
    return;
  }

  if (!isValidEthereumAddress(externalAddress)) {
    showWithdrawNotification('Error', 'Dirección Ethereum inválida', 'error');
    return;
  }

  const amount = parseFloat(amountSphe);
  if (isNaN(amount) || amount <= 0) {
    showWithdrawNotification('Error', 'Cantidad inválida', 'error');
    return;
  }

  // Deshabilitar botón
  submitBtn.disabled = true;
  submitBtn.textContent = 'Procesando...';

  try {
    // Obtener userId desde sessionStorage
    const userId = parseInt(sessionStorage.getItem('user_id'));
    const smartAccountAddress = sessionStorage.getItem('smart_account_address');

    if (!userId || !smartAccountAddress) {
      throw new Error('Sesión no iniciada. Refresca la página.');
    }

    // Llamar al endpoint de retiro vía PHP proxy
    const response = await fetch('/api/wallet/request_withdraw.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        spheAmount: amountSphe.toString(),
        destinationAddress: externalAddress
      })
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || result.error || 'Error desconocido');
    }

    // Mostrar éxito
    showWithdrawNotification(
      '✅ Retiro iniciado',
      `Se están convirtiendo ${result.data.amountSphe} SPHE a ${result.data.amountUsdt} USDT. Tiempo estimado: ${result.data.estimatedTime}`,
      'success'
    );

    // Limpiar formulario
    document.getElementById('withdrawForm').reset();

    // Actualizar historial
    refreshWithdrawHistory();

    // Actualizar límites
    updateLimitsDisplay();

    // Actualizar balance
    if (window.updateWalletBalance) {
      window.updateWalletBalance();
    }

  } catch (error) {
    console.error('Error ejecutando retiro:', error);
    showWithdrawNotification('Error', error.message, 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Retirar Fondos';
  }
}

/**
 * Cargar historial de retiros
 */
async function refreshWithdrawHistory(page = 1, limit = 10) {
  try {
    const userId = parseInt(sessionStorage.getItem('user_id'));
    if (!userId) return;

    // Obtener historial
    const historyResponse = await fetch(`/api/wallet/withdraw_history.php?page=${page}&limit=${limit}`);
    const historyResult = await historyResponse.json();

    if (historyResult.success) {
      withdrawHistory = historyResult.data.withdraws || [];
    }

    // Obtener límites
    const limitsResponse = await fetch('/api/wallet/withdraw_limits.php');
    const limitsResult = await limitsResponse.json();

    if (limitsResult.success) {
      dailyLimitUsed = limitsResult.data.usedTodayUsd || 0;
    }

    renderWithdrawHistory();
    updateLimitsDisplay();

  } catch (error) {
    console.error('Error refrescando historial de retiros:', error);
  }
}

/**
 * Renderizar historial de retiros
 */
function renderWithdrawHistory() {
  const container = document.getElementById('withdrawHistoryContainer');
  if (!container) return;

  if (withdrawHistory.length === 0) {
    container.innerHTML = `
      <div class="text-center py-12 text-gray-400">
        <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <p class="text-lg">No hay retiros registrados</p>
        <p class="text-sm mt-2">Realiza tu primer retiro para ver el historial</p>
      </div>
    `;
    return;
  }

  container.innerHTML = withdrawHistory.map(withdraw => {
    const statusConfig = {
      'pending': { 
        class: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
        icon: '⏳',
        label: 'Procesando'
      },
      'executed': { 
        class: 'bg-green-500/10 text-green-400 border-green-500/20',
        icon: '✅',
        label: 'Completado'
      },
      'failed': { 
        class: 'bg-red-500/10 text-red-400 border-red-500/20',
        icon: '❌',
        label: 'Fallido'
      },
      'cancelled': { 
        class: 'bg-gray-500/10 text-gray-400 border-gray-500/20',
        icon: '🚫',
        label: 'Cancelado'
      }
    };

    const status = statusConfig[withdraw.status] || statusConfig['pending'];

    return `
      <div class="bg-brand-bg-secondary rounded-2xl p-5 hover:bg-brand-bg-tertiary transition-all border border-brand-border">
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-accent to-purple-600 flex items-center justify-center text-2xl">
              💸
            </div>
            <div>
              <p class="text-white font-semibold">Retiro a externa</p>
              <p class="text-xs text-gray-400">${new Date(withdraw.createdAt).toLocaleString()}</p>
            </div>
          </div>
          <span class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-lg text-xs font-medium border ${status.class}">
            <span>${status.icon}</span>
            <span>${status.label}</span>
          </span>
        </div>

        <!-- Montos -->
        <div class="grid grid-cols-2 gap-3 mb-3 bg-brand-bg-primary rounded-xl p-3">
          <div>
            <p class="text-xs text-gray-400 mb-1">SPHE enviado</p>
            <p class="text-lg font-bold text-brand-accent">${withdraw.amountSphe}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 mb-1">USDT recibido</p>
            <p class="text-lg font-bold text-green-400">${withdraw.amountUsdt}</p>
          </div>
        </div>

        <!-- Dirección destino -->
        <div class="mb-3">
          <p class="text-xs text-gray-400 mb-1">Dirección destino</p>
          <div class="flex items-center space-x-2">
            <code class="text-xs text-white bg-brand-bg-primary px-3 py-1.5 rounded-lg font-mono flex-1 truncate">
              ${withdraw.externalAddress}
            </code>
            <button 
              onclick="copyToClipboard('${withdraw.externalAddress}')"
              class="text-brand-accent hover:text-brand-accent-hover transition-colors"
              title="Copiar dirección"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Transaction Hash -->
        ${withdraw.txHash ? `
          <a 
            href="https://polygonscan.com/tx/${withdraw.txHash}" 
            target="_blank" 
            class="flex items-center justify-center space-x-2 text-brand-accent hover:text-brand-accent-hover text-sm font-medium transition-colors"
          >
            <span>Ver en PolygonScan</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
            </svg>
          </a>
        ` : ''}

        <!-- Ejecución timestamp -->
        ${withdraw.executedAt ? `
          <p class="text-xs text-gray-500 mt-2">
            Ejecutado: ${new Date(withdraw.executedAt).toLocaleString()}
          </p>
        ` : ''}
      </div>
    `;
  }).join('');
}

/**
 * Actualizar display de límites diarios
 */
function updateLimitsDisplay() {
  const limitContainer = document.getElementById('dailyLimitDisplay');
  if (!limitContainer) return;

  const remaining = Math.max(0, DAILY_LIMIT_USD - dailyLimitUsed);
  const percentage = (dailyLimitUsed / DAILY_LIMIT_USD) * 100;

  let statusClass = 'text-green-400';
  let statusIcon = '✅';
  if (percentage > 80) {
    statusClass = 'text-red-400';
    statusIcon = '⚠️';
  } else if (percentage > 50) {
    statusClass = 'text-yellow-400';
    statusIcon = '⚡';
  }

  limitContainer.innerHTML = `
    <div class="bg-brand-bg-secondary rounded-2xl p-4 border border-brand-border">
      <div class="flex items-center justify-between mb-3">
        <span class="text-sm text-gray-400">Límite diario de retiro</span>
        <span class="${statusClass} text-xl">${statusIcon}</span>
      </div>
      
      <!-- Barra de progreso -->
      <div class="w-full bg-brand-bg-primary rounded-full h-2 mb-3 overflow-hidden">
        <div 
          class="h-full transition-all duration-500 rounded-full ${percentage > 80 ? 'bg-red-500' : percentage > 50 ? 'bg-yellow-500' : 'bg-green-500'}"
          style="width: ${Math.min(percentage, 100)}%"
        ></div>
      </div>

      <div class="flex items-center justify-between text-sm">
        <div>
          <p class="text-gray-400">Usado hoy</p>
          <p class="text-white font-semibold">$${dailyLimitUsed.toFixed(2)} USD</p>
        </div>
        <div class="text-right">
          <p class="text-gray-400">Disponible</p>
          <p class="${statusClass} font-semibold">$${remaining.toFixed(2)} USD</p>
        </div>
      </div>
    </div>
  `;
}

/**
 * Validar dirección Ethereum
 */
function isValidEthereumAddress(address) {
  return /^0x[a-fA-F0-9]{40}$/.test(address);
}

/**
 * Copiar al portapapeles
 */
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    showWithdrawNotification('✅ Copiado', 'Dirección copiada al portapapeles', 'success');
  }).catch(err => {
    console.error('Error copiando:', err);
  });
}

/**
 * Mostrar notificación de retiro
 */
function showWithdrawNotification(title, message, type = 'info') {
  // Reutilizar el sistema de notificaciones de gasless-actions
  if (window.GaslessActions && window.GaslessActions.showNotification) {
    window.GaslessActions.showNotification(title, message, type);
  } else {
    // Fallback simple
    alert(`${title}: ${message}`);
  }
}

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initWithdrawSystem);
} else {
  initWithdrawSystem();
}

// Exportar funciones globales
window.WithdrawSystem = {
  init: initWithdrawSystem,
  refresh: refreshWithdrawHistory,
  updateLimits: updateLimitsDisplay
};
