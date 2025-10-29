/**
 * Wallet Send System
 * Sistema de envío de SPHE y USDT
 */

(function () {
  const form = document.getElementById('withdrawForm');
  const tokenSelect = document.getElementById('withdrawToken');
  const addressInput = document.getElementById('withdrawAddress');
  const amountInput = document.getElementById('withdrawAmount');
  const availableSpan = document.getElementById('withdrawAvailable');
  const submitButton = form?.querySelector('button[type="submit"]');

  let balances = {
    sphe: 0,
    usdt: 0,
  };

  let isProcessing = false;

  // ============================================
  // CARGAR BALANCES
  // ============================================
  async function loadBalances() {
    try {
      const response = await fetch('/api/wallet/balances.php', {
        method: 'GET',
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Error al cargar balances');
      }

      const data = await response.json();

      if (data.success && data.balances) {
        balances.sphe = parseFloat(data.balances.sphe.formatted) || 0;
        balances.usdt = parseFloat(data.balances.usdt.formatted) || 0;

        updateAvailableBalance();
        console.log('✓ Balances cargados:', balances);
      }
    } catch (error) {
      console.error('Error loading balances:', error);
      showMessage('No se pudieron cargar los balances', 'error');
    }
  }

  // ============================================
  // ACTUALIZAR BALANCE DISPONIBLE
  // ============================================
  function updateAvailableBalance() {
    if (!availableSpan || !tokenSelect) return;

    const token = tokenSelect.value;
    const balance = token === 'SPHE' ? balances.sphe : balances.usdt;

    const fmtNumber = new Intl.NumberFormat('es-MX', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 6,
    });

    availableSpan.textContent = `${fmtNumber.format(balance)} ${token}`;
  }

  // ============================================
  // USAR MÁXIMO
  // ============================================
  window.setMaxWithdraw = function () {
    if (!tokenSelect || !amountInput) return;

    const token = tokenSelect.value;
    const balance = token === 'SPHE' ? balances.sphe : balances.usdt;

    amountInput.value = balance.toFixed(6);
  };

  // ============================================
  // VALIDAR DIRECCIÓN ETHEREUM
  // ============================================
  function isValidAddress(address) {
    return /^0x[a-fA-F0-9]{40}$/.test(address);
  }

  // ============================================
  // ENVIAR FONDOS
  // ============================================
  async function handleSubmit(e) {
    e.preventDefault();

    if (isProcessing) {
      showMessage('Ya hay una transacción en proceso', 'warning');
      return;
    }

    // Validar inputs
    const token = tokenSelect.value;
    const address = addressInput.value.trim();
    const amount = parseFloat(amountInput.value);

    if (!address || !isValidAddress(address)) {
      showMessage('Dirección inválida. Debe ser una dirección de Polygon válida.', 'error');
      addressInput.focus();
      return;
    }

    if (!amount || amount <= 0) {
      showMessage('Cantidad inválida', 'error');
      amountInput.focus();
      return;
    }

    const maxBalance = token === 'SPHE' ? balances.sphe : balances.usdt;

    if (amount > maxBalance) {
      showMessage(`Saldo insuficiente. Disponible: ${maxBalance.toFixed(2)} ${token}`, 'error');
      return;
    }

    // Confirmar
    const confirmed = confirm(
      `¿Enviar ${amount.toFixed(2)} ${token} a:\n${address}?\n\nEsta acción no se puede deshacer.`
    );

    if (!confirmed) return;

    // Procesar
    isProcessing = true;
    setButtonState(true);
    showMessage('Procesando transacción...', 'info');

    try {
      const response = await fetch('/api/wallet/send.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          token: token,
          to: address,
          amount: amount.toString(),
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Error al enviar fondos');
      }

      // Éxito
      showMessage(`✓ ${amount.toFixed(2)} ${token} enviados correctamente`, 'success');

      // Limpiar form
      form.reset();

      // Recargar balances
      setTimeout(() => {
        loadBalances();
        if (window.reloadWalletBalances) {
          window.reloadWalletBalances();
        }
      }, 2000);

      // Mostrar TX hash si existe
      if (data.tx_hash) {
        setTimeout(() => {
          const viewTx = confirm(
            '¿Quieres ver la transacción en PolygonScan?'
          );
          if (viewTx) {
            window.open(
              `https://polygonscan.com/tx/${data.tx_hash}`,
              '_blank'
            );
          }
        }, 1000);
      }
    } catch (error) {
      console.error('Error sending funds:', error);
      showMessage(error.message || 'Error al enviar fondos. Intenta nuevamente.', 'error');
    } finally {
      isProcessing = false;
      setButtonState(false);
    }
  }

  // ============================================
  // UI HELPERS
  // ============================================
  function setButtonState(processing) {
    if (!submitButton) return;

    if (processing) {
      submitButton.disabled = true;
      submitButton.innerHTML = `
        <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Procesando...
      `;
    } else {
      submitButton.disabled = false;
      submitButton.innerHTML = `
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
        </svg>
        Enviar Fondos
      `;
    }
  }

  function showMessage(message, type = 'info') {
    // Usar sistema de toast si existe
    if (typeof showToast === 'function') {
      showToast(message, type);
    } else {
      // Fallback a alert
      alert(message);
    }
  }

  // ============================================
  // EVENT LISTENERS
  // ============================================
  if (form) {
    form.addEventListener('submit', handleSubmit);
  }

  if (tokenSelect) {
    tokenSelect.addEventListener('change', updateAvailableBalance);
  }

  // ============================================
  // INICIALIZACIÓN
  // ============================================
  console.log('🚀 Wallet Send System initialized');
  loadBalances();
})();
