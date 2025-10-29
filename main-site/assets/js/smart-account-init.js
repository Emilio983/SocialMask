/**
 * Inicializa Smart Account automáticamente en background
 * Se ejecuta cuando el usuario carga el dashboard
 */

(function() {
    'use strict';

    // Verificar si ya tiene smart account
    const existingSmartAccount = window.__thesocialmask_DASHBOARD__?.smartAccount;
    
    if (existingSmartAccount && existingSmartAccount !== 'Pendiente') {
        console.log('✅ Smart Account ya existe:', existingSmartAccount);
        return;
    }

    console.log('🔄 Inicializando Smart Account en background...');

    // Esperar 2 segundos después del login para no interferir con carga inicial
    setTimeout(async () => {
        try {
            const response = await fetch('/api/auth/create_smart_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                console.log('✅ Smart Account creado:', data.smartAccount);
                
                // Actualizar UI si existe el elemento
                const smartAccountEl = document.getElementById('smart-account-display');
                if (smartAccountEl && smartAccountEl.textContent === 'Pendiente') {
                    smartAccountEl.textContent = data.smartAccount;
                }

                // Mostrar notificación opcional
                if (data.status === 'created' && typeof showToast === 'function') {
                    showToast('Smart Account creado exitosamente', 'success');
                }

                // Actualizar variable global
                if (window.__thesocialmask_DASHBOARD__) {
                    window.__thesocialmask_DASHBOARD__.smartAccount = data.smartAccount;
                }
            } else {
                console.warn('⚠️ Smart Account:', data.message);
                
                // Si falla, mostrar mensaje discreto
                if (data.message !== 'No pending smart account data') {
                    console.error('Error creando Smart Account:', data.message);
                }
            }
        } catch (error) {
            console.error('❌ Error al inicializar Smart Account:', error);
            // No mostrar error al usuario, se reintentará en próximo login
        }
    }, 2000); // 2 segundos de delay para no afectar carga inicial
})();
