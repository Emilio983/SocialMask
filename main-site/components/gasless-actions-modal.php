<!-- 
  thesocialmask - Gasless Actions Modal Component
  Modal reutilizable para ejecutar acciones sin gas
-->

<!-- Modal de Acción -->
<div id="actionModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/80 backdrop-blur-sm">
  <div class="bg-brand-bg-secondary rounded-2xl p-6 w-full max-w-md mx-4 border border-brand-border animate-scale-in">
    
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center space-x-3">
        <span id="actionModalIcon" class="text-3xl">💰</span>
        <h3 id="actionModalTitle" class="text-xl font-bold text-white">Enviar Propina</h3>
      </div>
      <button onclick="window.GaslessActions.closeModal()" class="text-gray-400 hover:text-white transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <!-- Form -->
    <div class="space-y-4">
      
      <!-- Tipo de acción (oculto) -->
      <input type="hidden" id="actionTypeInput" value="">

      <!-- Destinatario -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          Destinatario
        </label>
        <input 
          type="text" 
          id="actionRecipientInput"
          placeholder="0x..."
          class="w-full bg-brand-bg-primary border border-brand-border rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-brand-accent transition-all"
        >
        <p class="text-xs text-gray-400 mt-1">Dirección Ethereum del destinatario</p>
      </div>

      <!-- Cantidad -->
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
          Cantidad (SPHE)
        </label>
        <input 
          type="number" 
          id="actionAmountInput"
          placeholder="0.0"
          step="0.01"
          min="0"
          class="w-full bg-brand-bg-primary border border-brand-border rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-brand-accent transition-all"
        >
        <p class="text-xs text-gray-400 mt-1">Sin comisiones de gas - Patrocinado por thesocialmask</p>
      </div>

      <!-- Metadata (opcional, colapsable) -->
      <details class="group">
        <summary class="cursor-pointer text-sm text-brand-accent hover:text-brand-accent-hover font-medium">
          Opciones avanzadas
        </summary>
        <div class="mt-3">
          <label class="block text-sm font-medium text-gray-300 mb-2">
            Metadata (JSON)
          </label>
          <textarea 
            id="actionMetadataInput"
            rows="3"
            placeholder='{"message": "Gracias!", "postId": 123}'
            class="w-full bg-brand-bg-primary border border-brand-border rounded-xl px-4 py-3 text-white placeholder-gray-500 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-brand-accent transition-all"
          ></textarea>
        </div>
      </details>
    </div>

    <!-- Footer -->
    <div class="flex items-center space-x-3 mt-6">
      <button 
        onclick="window.GaslessActions.closeModal()"
        class="flex-1 bg-brand-bg-tertiary hover:bg-brand-bg-hover text-white font-medium py-3 rounded-xl transition-colors"
      >
        Cancelar
      </button>
      <button 
        onclick="window.GaslessActions.confirm()"
        class="flex-1 bg-brand-accent hover:bg-brand-accent-hover text-white font-medium py-3 rounded-xl transition-colors shadow-lg shadow-brand-accent/20"
      >
        Confirmar
      </button>
    </div>

  </div>
</div>

<!-- Botones de acción rápida (ejemplo de uso) -->
<div class="gasless-action-buttons hidden">
  <!-- TIP -->
  <button 
    data-action-btn="TIP"
    onclick="window.GaslessActions.openModal('TIP', this.dataset.recipient || '')"
    class="inline-flex items-center space-x-2 bg-brand-accent/10 hover:bg-brand-accent/20 text-brand-accent px-4 py-2 rounded-xl transition-all"
  >
    <span>💰</span>
    <span>Propina</span>
  </button>

  <!-- PAYMENT -->
  <button 
    data-action-btn="PAYMENT"
    onclick="window.GaslessActions.openModal('PAYMENT', this.dataset.recipient || '')"
    class="inline-flex items-center space-x-2 bg-green-500/10 hover:bg-green-500/20 text-green-400 px-4 py-2 rounded-xl transition-all"
  >
    <span>💳</span>
    <span>Pagar</span>
  </button>

  <!-- UNLOCK -->
  <button 
    data-action-btn="UNLOCK"
    onclick="window.GaslessActions.openModal('UNLOCK', this.dataset.recipient || '')"
    class="inline-flex items-center space-x-2 bg-purple-500/10 hover:bg-purple-500/20 text-purple-400 px-4 py-2 rounded-xl transition-all"
  >
    <span>🔓</span>
    <span>Desbloquear</span>
  </button>

  <!-- VOTE -->
  <button 
    data-action-btn="VOTE"
    onclick="window.GaslessActions.openModal('VOTE', this.dataset.recipient || '')"
    class="inline-flex items-center space-x-2 bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 px-4 py-2 rounded-xl transition-all"
  >
    <span>🗳️</span>
    <span>Votar</span>
  </button>

  <!-- DONATION -->
  <button 
    data-action-btn="DONATION"
    onclick="window.GaslessActions.openModal('DONATION', this.dataset.recipient || '')"
    class="inline-flex items-center space-x-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 px-4 py-2 rounded-xl transition-all"
  >
    <span>❤️</span>
    <span>Donar</span>
  </button>

  <!-- BOUNTY_CLAIM -->
  <button 
    data-action-btn="BOUNTY_CLAIM"
    onclick="window.GaslessActions.openModal('BOUNTY_CLAIM', this.dataset.recipient || '')"
    class="inline-flex items-center space-x-2 bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 px-4 py-2 rounded-xl transition-all"
  >
    <span>🎁</span>
    <span>Reclamar</span>
  </button>
</div>

<!-- Historial de acciones -->
<div id="actionsHistoryContainer" class="space-y-3">
  <!-- Se llenará dinámicamente con JavaScript -->
</div>

<!-- Contenedor de notificaciones (se crea automáticamente) -->
<!-- <div id="notificationsContainer" class="fixed top-4 right-4 z-50 w-96 max-w-full"></div> -->

<style>
/* Animaciones personalizadas */
@keyframes scale-in {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes slide-in-right {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.animate-scale-in {
  animation: scale-in 0.2s ease-out;
}

.animate-slide-in-right {
  animation: slide-in-right 0.3s ease-out;
}
</style>
