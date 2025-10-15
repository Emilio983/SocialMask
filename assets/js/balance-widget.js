/**
 * thesocialmask - Balance Widget
 * Widget unificado de balance SPHE + USDT + USD equivalente
 */

// Estado global del balance
let balanceData = {
  sphe: '0',
  usdt: '0',
  usdValue: 0,
  lastUpdated: null,
  smartAccountAddress: null
};

// Precio SPHE en USD (se actualizará desde API)
let sphePrice = 0.10; // $0.10 por defecto

/**
 * Inicializar widget de balance
 */
async function initBalanceWidget() {
  await updateWalletBalance();
  
  // Actualizar cada 30 segundos
  setInterval(() => updateWalletBalance(), 30000);
  
  // Actualizar precio SPHE cada 5 minutos
  setInterval(() => updateSphePrice(), 300000);
  
  console.log('✅ Balance widget inicializado');
}

/**
 * Actualizar balance de wallet
 */
async function updateWalletBalance() {
  try {
    const userId = parseInt(sessionStorage.getItem('user_id'));
    const smartAccountAddress = sessionStorage.getItem('smart_account_address');

    if (!userId || !smartAccountAddress) {
      console.warn('Usuario no autenticado');
      return;
    }

    // Llamar al endpoint de balance (PHP proxy → backend Node)
    const response = await fetch(`/api/wallet/balances.php`);
    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'Error obteniendo balance');
    }

    // Actualizar estado (result.balances contiene sphe/usdt con raw + formatted)
    balanceData.sphe = result.balances?.sphe?.formatted || '0';
    balanceData.usdt = result.balances?.usdt?.formatted || '0';
    balanceData.smartAccountAddress = result.smart_account_address || smartAccountAddress;
    balanceData.lastUpdated = new Date();

    // Calcular valor en USD
    const spheAmount = parseFloat(balanceData.sphe);
    const usdtAmount = parseFloat(balanceData.usdt);
    balanceData.usdValue = (spheAmount * sphePrice) + usdtAmount;

    // Guardar en sessionStorage
    sessionStorage.setItem('sphe_balance', balanceData.sphe);
    sessionStorage.setItem('usdt_balance', balanceData.usdt);
    sessionStorage.setItem('usd_value', balanceData.usdValue.toString());

    // Renderizar
    renderBalanceWidget();

  } catch (error) {
    console.error('Error actualizando balance:', error);
  }
}

/**
 * Actualizar precio de SPHE
 */
async function updateSphePrice() {
  try {
    // TODO: Implementar llamada a API de precio real
    // Por ahora usa valor fijo
    sphePrice = 0.10;
  } catch (error) {
    console.error('Error actualizando precio SPHE:', error);
  }
}

/**
 * Renderizar widget de balance
 */
function renderBalanceWidget() {
  const container = document.getElementById('balanceWidget');
  if (!container) return;

  const spheAmount = parseFloat(balanceData.sphe);
  const usdtAmount = parseFloat(balanceData.usdt);
  const totalUsd = balanceData.usdValue;

  container.innerHTML = `
    <!-- Balance principal -->
    <div class="bg-gradient-to-br from-brand-accent to-purple-600 rounded-2xl p-6 text-white relative overflow-hidden">
      <!-- Patrón de fondo -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
      </div>

      <div class="relative z-10">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
            </svg>
            <span class="text-sm font-medium opacity-90">Balance Total</span>
          </div>
          <button 
            onclick="window.updateWalletBalance()"
            class="p-2 hover:bg-white/10 rounded-lg transition-colors"
            title="Actualizar balance"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
          </button>
        </div>

        <!-- Valor total en USD -->
        <div class="mb-6">
          <div class="flex items-baseline space-x-2">
            <span class="text-4xl font-bold">$${totalUsd.toFixed(2)}</span>
            <span class="text-lg opacity-75">USD</span>
          </div>
          ${balanceData.lastUpdated ? `
            <p class="text-xs opacity-75 mt-1">
              Actualizado: ${formatTimeAgo(balanceData.lastUpdated)}
            </p>
          ` : ''}
        </div>

        <!-- Tokens individuales -->
        <div class="grid grid-cols-2 gap-3">
          <!-- SPHE -->
          <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
            <div class="flex items-center space-x-2 mb-1">
              <div class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">
                S
              </div>
              <span class="text-xs font-medium opacity-90">SPHE</span>
            </div>
            <p class="text-lg font-bold">${formatNumber(spheAmount, 2)}</p>
            <p class="text-xs opacity-75">~$${(spheAmount * sphePrice).toFixed(2)}</p>
          </div>

          <!-- USDT -->
          <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
            <div class="flex items-center space-x-2 mb-1">
              <div class="w-6 h-6 rounded-full bg-green-400/30 flex items-center justify-center text-xs font-bold">
                U
              </div>
              <span class="text-xs font-medium opacity-90">USDT</span>
            </div>
            <p class="text-lg font-bold">${formatNumber(usdtAmount, 2)}</p>
            <p class="text-xs opacity-75">~$${usdtAmount.toFixed(2)}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="grid grid-cols-3 gap-2 mt-3">
      <button 
        onclick="window.location.href='/pages/wallet/receive.php'"
        class="bg-brand-bg-secondary hover:bg-brand-bg-tertiary border border-brand-border rounded-xl p-3 transition-all text-center"
      >
        <div class="text-2xl mb-1">📥</div>
        <span class="text-xs text-white">Recibir</span>
      </button>
      <button 
        onclick="window.location.href='/pages/wallet/withdraw.php'"
        class="bg-brand-bg-secondary hover:bg-brand-bg-tertiary border border-brand-border rounded-xl p-3 transition-all text-center"
      >
        <div class="text-2xl mb-1">📤</div>
        <span class="text-xs text-white">Retirar</span>
      </button>
      <button 
        onclick="window.GaslessActions?.openModal('TIP')"
        class="bg-brand-bg-secondary hover:bg-brand-bg-tertiary border border-brand-border rounded-xl p-3 transition-all text-center"
      >
        <div class="text-2xl mb-1">💰</div>
        <span class="text-xs text-white">Propina</span>
      </button>
    </div>
  `;
}

/**
 * Formatear número con decimales
 */
function formatNumber(num, decimals = 2) {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K';
  }
  return num.toFixed(decimals);
}

/**
 * Formatear tiempo relativo (hace X minutos)
 */
function formatTimeAgo(date) {
  const seconds = Math.floor((new Date() - date) / 1000);
  
  if (seconds < 60) return 'justo ahora';
  if (seconds < 3600) return `hace ${Math.floor(seconds / 60)} min`;
  if (seconds < 86400) return `hace ${Math.floor(seconds / 3600)} h`;
  return `hace ${Math.floor(seconds / 86400)} días`;
}

// Auto-inicializar
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initBalanceWidget);
} else {
  initBalanceWidget();
}

// Exportar funciones globales
window.updateWalletBalance = updateWalletBalance;
window.BalanceWidget = {
  init: initBalanceWidget,
  refresh: updateWalletBalance,
  getData: () => balanceData
};
