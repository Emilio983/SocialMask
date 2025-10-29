(() => {
  const state = {
    smartAccount: null,
    balances: null,
    loading: false,
    lastUpdate: null,
  };

  const smartAccountEl = document.getElementById('smart-account-display');
  const spheDisplay = document.getElementById('sphe-balance-display');
  const usdtDisplay = document.getElementById('usdt-balance-display');
  const quickActionButtons = document.querySelectorAll('[data-action]');

  const fmtToken = new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  });

  // Función para mostrar skeleton loading
  function showLoading(element) {
    if (element) {
      element.innerHTML = '<span class="inline-block w-24 h-7 bg-brand-border animate-pulse rounded"></span>';
    }
  }

  // Función para mostrar error
  function showError(element, message = 'Error') {
    if (element) {
      element.innerHTML = `<span class="text-red-400 text-sm">${message}</span>`;
    }
  }

  // Cargar balances al iniciar
  async function loadBalances() {
    if (state.loading) return;
    
    state.loading = true;
    console.log('🔄 Cargando balances...');

    // Mostrar loading state
    showLoading(spheDisplay);
    showLoading(usdtDisplay);

    try {
      const response = await fetch('/api/wallet/balances.php', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      console.log('📊 Respuesta de balances:', data);

      if (data.success) {
        state.balances = data.balances;
        state.smartAccount = data.smart_account_address;
        state.lastUpdate = new Date();
        
        // ✅ Actualizar displays de balances - SIN CENSURAR (mostrar valores reales)
        if (spheDisplay && data.balances && data.balances.sphe) {
          const spheBalance = parseFloat(data.balances.sphe.formatted) || 0;
          spheDisplay.innerHTML = `${fmtToken.format(spheBalance)} <span class="text-sm text-brand-text-secondary">SPHE</span>`;
        } else if (spheDisplay) {
          spheDisplay.innerHTML = `0.00 <span class="text-sm text-brand-text-secondary">SPHE</span>`;
        }

        if (usdtDisplay && data.balances && data.balances.usdt) {
          const usdtBalance = parseFloat(data.balances.usdt.formatted) || 0;
          usdtDisplay.innerHTML = `${fmtToken.format(usdtBalance)} <span class="text-sm text-brand-text-secondary">USDT</span>`;
        } else if (usdtDisplay) {
          usdtDisplay.innerHTML = `0.00 <span class="text-sm text-brand-text-secondary">USDT</span>`;
        }

        // Actualizar smart account si está disponible
        if (smartAccountEl && state.smartAccount) {
          const shortAddress = state.smartAccount.substring(0, 6) + '...' + state.smartAccount.substring(38);
          smartAccountEl.textContent = shortAddress;
          smartAccountEl.title = state.smartAccount; // Tooltip con dirección completa
          
          // Hacer el address clickeable para copiar
          smartAccountEl.style.cursor = 'pointer';
          smartAccountEl.onclick = () => {
            navigator.clipboard.writeText(state.smartAccount).then(() => {
              showToastMessage('✅ Dirección copiada', 'success');
            });
          };
        }

        console.log('✅ Balances actualizados correctamente desde', data.source || 'unknown');
      } else {
        throw new Error(data.message || 'Error desconocido');
      }
    } catch (error) {
      console.error('❌ Error cargando balances:', error);
      
      // Mostrar valores en 0 en caso de error (no skeleton)
      if (spheDisplay) {
        spheDisplay.innerHTML = `0.00 <span class="text-sm text-brand-text-secondary">SPHE</span>`;
      }
      if (usdtDisplay) {
        usdtDisplay.innerHTML = `0.00 <span class="text-sm text-brand-text-secondary">USDT</span>`;
      }

      // Mostrar notificación
      showToastMessage('No se pudieron cargar los balances. Intenta recargar la página.', 'error');
    } finally {
      state.loading = false;
    }
  }

  // Función auxiliar para mostrar toast (si existe el sistema)
  function showToastMessage(message, type = 'info') {
    if (typeof showToast === 'function') {
      showToast(message, type);
    } else {
      console.log(`[${type.toUpperCase()}] ${message}`);
    }
  }

  // Manejar acciones rápidas
  quickActionButtons.forEach(button => {
    button.addEventListener('click', () => {
      const action = button.dataset.action;
      handleQuickAction(action);
    });
  });

  function handleQuickAction(action) {
    console.log('⚡ Acción rápida:', action);

    switch (action) {
      case 'send':
        window.location.href = '/pages/wallet/send';
        break;

      case 'swap':
        window.location.href = '/pages/swap';
        break;

      case 'receive':
        window.location.href = '/pages/wallet/receive';
        break;

      default:
        console.log('Acción no implementada:', action);
    }
  }

  // Auto-refresh de balances cada 30 segundos
  const AUTO_REFRESH_INTERVAL = 30000;
  setInterval(() => {
    if (document.visibilityState === 'visible' && !state.loading) {
      console.log('🔄 Auto-refresh de balances...');
      loadBalances();
    }
  }, AUTO_REFRESH_INTERVAL);

  // Recargar cuando la página se vuelve visible
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && !state.loading) {
      const timeSinceLastUpdate = state.lastUpdate ? Date.now() - state.lastUpdate.getTime() : Infinity;
      // Solo recargar si han pasado más de 10 segundos
      if (timeSinceLastUpdate > 10000) {
        console.log('🔄 Página visible, recargando balances...');
        loadBalances();
      }
    }
  });

  // Exponer función para recargar balances manualmente
  window.reloadBalances = loadBalances;
  window.dashboardState = state;

  // Cargar balances al iniciar
  console.log('🚀 Iniciando dashboard...');
  
  // Esperar a que el DOM esté completamente cargado
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadBalances);
  } else {
    loadBalances();
  }
})();
