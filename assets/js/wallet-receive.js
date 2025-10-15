/**
 * SocialMask - Wallet Receive System
 * Sistema de recepción de fondos (USDT y SPHE)
 */

(function () {
  'use strict';

  // ============================================
  // ELEMENTOS DEL DOM
  // ============================================
  const addressEl = document.getElementById('deposit-address');
  const smartAccountEl = document.getElementById('smart-account-address');
  const expiresEl = document.getElementById('address-expiry');
  const statusEl = document.getElementById('receive-status');
  const copyBtn = document.getElementById('copy-address-btn');
  const qrContainer = document.getElementById('qr-code-container');

  // ============================================
  // ESTADO
  // ============================================
  let currentAddress = null;
  let qrCodeInstance = null;
  let isLoading = false;

  // ============================================
  // FUNCIONES DE UI
  // ============================================
  function setStatus(message, type = 'info') {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.dataset.type = type;
    
    // Auto-ocultar mensajes de éxito después de 5 segundos
    if (type === 'success') {
      setTimeout(() => {
        setStatus('Dirección lista para recibir fondos', 'info');
      }, 5000);
    }
  }

  function setButtonLoading(button, loading) {
    if (!button) return;
    
    if (loading) {
      button.disabled = true;
      button.style.opacity = '0.5';
      button.style.cursor = 'not-allowed';
    } else {
      button.disabled = false;
      button.style.opacity = '1';
      button.style.cursor = 'pointer';
    }
  }

  function formatExpiry(value) {
    if (!value) return 'Activa';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return 'Activa';
    }
    
    const now = new Date();
    const diffMs = date - now;
    
    if (diffMs < 0) {
      return 'Expirada';
    }
    
    const diffMins = Math.floor(diffMs / 1000 / 60);
    if (diffMins < 60) {
      return `Expira en ${diffMins} min`;
    }
    
    const diffHours = Math.floor(diffMins / 60);
    return `Expira en ${diffHours}h`;
  }

  // ============================================
  // GENERAR QR CODE
  // ============================================
  function generateQRCode(address) {
    if (!qrContainer || !address) return;
    
    // Limpiar QR anterior
    qrContainer.innerHTML = '';
    
    try {
      // Verificar si QRCode existe
      if (typeof QRCode === 'undefined') {
        console.warn('QRCode library not loaded');
        qrContainer.innerHTML = '<p class="text-gray-400 text-sm">Código QR no disponible</p>';
        return;
      }
      
      // Crear nuevo QR
      qrCodeInstance = new QRCode(qrContainer, {
        text: address,
        width: 200,
        height: 200,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
      });
      
      console.log('✓ QR Code generado');
    } catch (error) {
      console.error('Error generating QR:', error);
      qrContainer.innerHTML = '<p class="text-gray-400 text-sm">Error al generar QR</p>';
    }
  }

  // ============================================
  // OBTENER DIRECCIÓN DE DEPÓSITO
  // ============================================
  async function fetchAddress() {
    if (isLoading) {
      console.log('Ya hay una petición en curso');
      return;
    }
    
    isLoading = true;
    setButtonLoading(copyBtn, true);
    
    try {
      setStatus('Cargando dirección...', 'info');
      
      const response = await fetch('/api/wallet/request_address.php', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error('Response error:', errorText);
        throw new Error(`HTTP ${response.status}: No se pudo obtener la dirección`);
      }

      const json = await response.json();
      
      if (!json.success) {
        throw new Error(json.message || 'Error al obtener dirección');
      }

      const data = json.data ?? {};
      currentAddress = data.deposit_address || data.smart_account_address;
      
      if (!currentAddress) {
        throw new Error('No se recibió dirección válida');
      }

      // Actualizar UI
      if (addressEl) {
        addressEl.textContent = currentAddress;
      }
      
      if (smartAccountEl && data.smart_account_address) {
        smartAccountEl.textContent = data.smart_account_address;
      }
      
      // Direcciones permanentes no expiran
      if (expiresEl) {
        expiresEl.textContent = 'Permanente';
      }
      
      // Generar QR Code
      generateQRCode(currentAddress);
      
      setStatus('Dirección lista para recibir USDT y SPHE en Polygon', 'success');
      console.log('✓ Dirección obtenida:', currentAddress);
      
    } catch (error) {
      console.error('Error fetching address:', error);
      setStatus((error.message || 'Error al cargar la dirección'), 'error');
      
      // En caso de error, mostrar mensaje en QR
      if (qrContainer) {
        qrContainer.innerHTML = `
          <div class="text-center p-4">
            <p class="text-gray-400 text-sm">No disponible</p>
          </div>
        `;
      }
    } finally {
      isLoading = false;
      setButtonLoading(copyBtn, false);
    }
  }

  // ============================================
  // COPIAR DIRECCIÓN
  // ============================================
  async function copyAddress() {
    if (!currentAddress || !addressEl) {
      setStatus('No hay dirección para copiar', 'error');
      return;
    }

    const value = currentAddress.trim();
    
    if (!value || value === '0x...') {
      setStatus('Espera a que cargue la dirección', 'error');
      return;
    }

    try {
      await navigator.clipboard.writeText(value);
      setStatus('Dirección copiada al portapapeles', 'success');
      
      // Feedback visual en el botón
      if (copyBtn) {
        const originalHTML = copyBtn.innerHTML;
        copyBtn.innerHTML = `
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          Copiado
        `;
        copyBtn.classList.add('bg-green-500');
        copyBtn.classList.remove('bg-brand-accent');
        
        setTimeout(() => {
          copyBtn.innerHTML = originalHTML;
          copyBtn.classList.remove('bg-green-500');
          copyBtn.classList.add('bg-brand-accent');
        }, 2000);
      }
      
      console.log('✓ Dirección copiada:', value);
    } catch (error) {
      console.error('Error copying:', error);
      
      // Fallback: seleccionar texto
      try {
        if (addressEl) {
          const range = document.createRange();
          range.selectNode(addressEl);
          window.getSelection().removeAllRanges();
          window.getSelection().addRange(range);
          document.execCommand('copy');
          window.getSelection().removeAllRanges();
          setStatus('Dirección copiada', 'success');
        }
      } catch (fallbackError) {
        setStatus('No se pudo copiar. Copia manualmente la dirección', 'error');
      }
    }
  }

  // ============================================
  // EVENT LISTENERS
  // ============================================
  if (copyBtn) {
    copyBtn.addEventListener('click', copyAddress);
  }

  // Click en la dirección también copia
  if (addressEl) {
    addressEl.style.cursor = 'pointer';
    addressEl.addEventListener('click', copyAddress);
  }

  // ============================================
  // INICIALIZACIÓN
  // ============================================
  console.log('🚀 Wallet Receive System initialized');
  
  // Cargar dirección al iniciar
  fetchAddress();
  
  // Exportar funciones globales si se necesitan
  window.WalletReceive = {
    copy: copyAddress,
    getCurrentAddress: () => currentAddress
  };
  
})();
