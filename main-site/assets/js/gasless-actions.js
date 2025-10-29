/**
 * thesocialmask - Gasless Actions UI
 * Sistema de acciones 1-clic sin gas (propinas, pagos, votos, etc.)
 */

// Tipos de acciones disponibles
const ACTION_TYPES = {
  TIP: { value: 'TIP', label: '💰 Propina', limit: 1000, icon: '💰' },
  PAYMENT: { value: 'PAYMENT', label: '💳 Pago', limit: 10000, icon: '💳' },
  UNLOCK: { value: 'UNLOCK', label: '🔓 Desbloquear', limit: 5000, icon: '🔓' },
  VOTE: { value: 'VOTE', label: '🗳️ Votar', limit: 100, icon: '🗳️' },
  DONATION: { value: 'DONATION', label: '❤️ Donación', limit: 50000, icon: '❤️' },
  BOUNTY_CLAIM: { value: 'BOUNTY_CLAIM', label: '🎁 Reclamar', limit: 100000, icon: '🎁' }
};

// Estado global
let actionsHistory = [];
let currentBalance = 0;

/**
 * Ejecutar una acción gasless
 */
async function executeAction(recipient, actionType, amount, metadata = {}) {
  const button = document.querySelector(`[data-action-btn="${actionType}"]`);
  if (button) button.disabled = true;

  try {
    // Validar límite de la acción
    const actionConfig = ACTION_TYPES[actionType];
    if (!actionConfig) {
      throw new Error(`Tipo de acción inválido: ${actionType}`);
    }

    const amountNum = parseFloat(amount);
    if (isNaN(amountNum) || amountNum <= 0) {
      throw new Error('Cantidad inválida');
    }

    if (amountNum > actionConfig.limit) {
      throw new Error(`Límite excedido. Máximo: ${actionConfig.limit} SPHE`);
    }

    // Obtener userId y smartAccountAddress desde sessionStorage
    const userId = parseInt(sessionStorage.getItem('user_id'));
    const smartAccountAddress = sessionStorage.getItem('smart_account_address');

    if (!userId || !smartAccountAddress) {
      throw new Error('Sesión no iniciada. Refresca la página.');
    }

    // Mostrar loading
    showActionLoading(actionType, true);

    // Llamar al backend vía PHP proxy
    const response = await fetch('/api/wallet/gasless_action.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        actionType,
        amount: amount.toString(),
        recipientId: recipient,
        metadata: metadata
      })
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || result.error || 'Error desconocido');
    }

    // Mostrar éxito
    showActionSuccess(actionType, result.data);

    // Actualizar historial
    refreshActionsHistory();

    // Actualizar balance
    refreshBalance();

    return result.data;

  } catch (error) {
    console.error('Error ejecutando acción:', error);
    showActionError(actionType, error.message);
    throw error;
  } finally {
    if (button) button.disabled = false;
    showActionLoading(actionType, false);
  }
}

/**
 * Abrir modal de acción
 */
function openActionModal(actionType, recipient = '', metadata = {}) {
  const modal = document.getElementById('actionModal');
  const actionConfig = ACTION_TYPES[actionType];

  if (!modal || !actionConfig) return;

  // Configurar modal
  document.getElementById('actionModalTitle').textContent = actionConfig.label;
  document.getElementById('actionModalIcon').textContent = actionConfig.icon;
  document.getElementById('actionTypeInput').value = actionType;
  document.getElementById('actionRecipientInput').value = recipient;
  document.getElementById('actionAmountInput').value = '';
  document.getElementById('actionAmountInput').placeholder = `Máximo: ${actionConfig.limit} SPHE`;
  document.getElementById('actionMetadataInput').value = JSON.stringify(metadata, null, 2);

  // Mostrar modal
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

/**
 * Cerrar modal de acción
 */
function closeActionModal() {
  const modal = document.getElementById('actionModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

/**
 * Confirmar y ejecutar acción desde modal
 */
async function confirmAction() {
  const actionType = document.getElementById('actionTypeInput').value;
  const recipient = document.getElementById('actionRecipientInput').value;
  const amount = document.getElementById('actionAmountInput').value;
  const metadataStr = document.getElementById('actionMetadataInput').value || '{}';

  try {
    const metadata = JSON.parse(metadataStr);
    await executeAction(recipient, actionType, amount, metadata);
    closeActionModal();
  } catch (error) {
    // El error ya se mostró en executeAction
  }
}

/**
 * Cargar historial de acciones
 */
async function refreshActionsHistory(page = 1, limit = 10) {
  try {
    const userId = parseInt(sessionStorage.getItem('user_id'));
    if (!userId) return;

    const response = await fetch(`/api/wallet/gasless_history.php?page=${page}&limit=${limit}`);
    const result = await response.json();

    if (!result.success) {
      console.error('Error cargando historial:', result.message);
      return;
    }

    actionsHistory = result.data.actions || [];
    renderActionsHistory();

  } catch (error) {
    console.error('Error refrescando historial:', error);
  }
}

/**
 * Renderizar historial de acciones
 */
function renderActionsHistory() {
  const container = document.getElementById('actionsHistoryContainer');
  if (!container) return;

  if (actionsHistory.length === 0) {
    container.innerHTML = `
      <div class="text-center py-12 text-gray-400">
        <p class="text-lg">No hay acciones registradas</p>
        <p class="text-sm mt-2">Realiza tu primera propina, pago o donación</p>
      </div>
    `;
    return;
  }

  container.innerHTML = actionsHistory.map(action => {
    const actionConfig = ACTION_TYPES[action.actionType];
    const statusClass = {
      'pending': 'bg-yellow-500/10 text-yellow-400',
      'executed': 'bg-green-500/10 text-green-400',
      'failed': 'bg-red-500/10 text-red-400',
      'cancelled': 'bg-gray-500/10 text-gray-400'
    }[action.status] || 'bg-gray-500/10 text-gray-400';

    return `
      <div class="bg-brand-bg-secondary rounded-2xl p-4 hover:bg-brand-bg-tertiary transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center space-x-3">
            <span class="text-2xl">${actionConfig?.icon || '📦'}</span>
            <div>
              <p class="text-white font-medium">${actionConfig?.label || action.actionType}</p>
              <p class="text-xs text-gray-400">${action.recipient.substring(0, 10)}...${action.recipient.substring(38)}</p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-brand-accent font-semibold">${action.amount} SPHE</p>
            <span class="inline-block px-2 py-1 rounded-lg text-xs ${statusClass}">
              ${action.status}
            </span>
          </div>
        </div>
        ${action.txHash ? `
          <a href="https://polygonscan.com/tx/${action.txHash}" target="_blank" 
             class="text-xs text-brand-accent hover:underline flex items-center">
            Ver en PolygonScan 
            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
            </svg>
          </a>
        ` : ''}
        <p class="text-xs text-gray-500 mt-2">${new Date(action.createdAt).toLocaleString()}</p>
      </div>
    `;
  }).join('');
}

/**
 * Mostrar loading de acción
 */
function showActionLoading(actionType, show) {
  const loader = document.getElementById(`actionLoader_${actionType}`);
  if (loader) {
    loader.classList.toggle('hidden', !show);
  }
}

/**
 * Mostrar éxito de acción
 */
function showActionSuccess(actionType, data) {
  const actionConfig = ACTION_TYPES[actionType];
  showNotification(
    `${actionConfig.icon} ${actionConfig.label} exitosa`,
    `Se enviaron ${data.amount} SPHE. Ejecutándose en blockchain...`,
    'success'
  );
}

/**
 * Mostrar error de acción
 */
function showActionError(actionType, message) {
  const actionConfig = ACTION_TYPES[actionType];
  showNotification(
    `Error en ${actionConfig.label}`,
    message,
    'error'
  );
}

/**
 * Mostrar notificación toast
 */
function showNotification(title, message, type = 'info') {
  const container = document.getElementById('notificationsContainer') || createNotificationsContainer();
  
  const colors = {
    success: 'bg-green-500/10 border-green-500 text-green-400',
    error: 'bg-red-500/10 border-red-500 text-red-400',
    warning: 'bg-yellow-500/10 border-yellow-500 text-yellow-400',
    info: 'bg-brand-accent/10 border-brand-accent text-brand-accent'
  };

  const notification = document.createElement('div');
  notification.className = `${colors[type]} border rounded-2xl p-4 mb-3 animate-slide-in-right`;
  notification.innerHTML = `
    <div class="flex items-start justify-between">
      <div>
        <p class="font-semibold">${title}</p>
        <p class="text-sm opacity-80 mt-1">${message}</p>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-4 opacity-60 hover:opacity-100">
        ✕
      </button>
    </div>
  `;

  container.appendChild(notification);

  // Auto-remover después de 5 segundos
  setTimeout(() => notification.remove(), 5000);
}

/**
 * Crear contenedor de notificaciones si no existe
 */
function createNotificationsContainer() {
  let container = document.getElementById('notificationsContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notificationsContainer';
    container.className = 'fixed top-4 right-4 z-50 w-96 max-w-full';
    document.body.appendChild(container);
  }
  return container;
}

/**
 * Actualizar balance (placeholder - debe implementarse según tu sistema)
 */
async function refreshBalance() {
  // TODO: Implementar según tu sistema de balance
  console.log('Balance actualizado');
}

/**
 * Inicializar sistema de acciones
 */
function initActionsSystem() {
  // Cargar historial al inicio
  refreshActionsHistory();

  // Event listeners para cerrar modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeActionModal();
  });

  // Actualizar historial cada 30 segundos
  setInterval(() => refreshActionsHistory(), 30000);

  console.log('✅ Sistema de acciones gasless inicializado');
}

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initActionsSystem);
} else {
  initActionsSystem();
}

// Exportar funciones globales
window.GaslessActions = {
  execute: executeAction,
  openModal: openActionModal,
  closeModal: closeActionModal,
  confirm: confirmAction,
  refreshHistory: refreshActionsHistory,
  ACTION_TYPES
};
