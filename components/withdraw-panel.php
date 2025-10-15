<!-- 
  thesocialmask - Withdraw Panel Component
  Panel de retiros SPHE→USDT→externa con límites diarios
-->

<div class="max-w-4xl mx-auto space-y-6">
  
  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-2xl font-bold text-white flex items-center space-x-3">
        <span class="text-3xl">💸</span>
        <span>Retirar Fondos</span>
      </h2>
      <p class="text-gray-400 mt-1">Convierte SPHE a USDT y envía a cualquier wallet</p>
    </div>
  </div>

  <!-- Límite diario -->
  <div id="dailyLimitDisplay">
    <!-- Se llena dinámicamente con JavaScript -->
  </div>

  <!-- Formulario de retiro -->
  <div class="bg-brand-bg-secondary rounded-2xl p-6 border border-brand-border">
    <h3 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
      <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <span>Nuevo Retiro</span>
    </h3>

    <form id="withdrawForm" class="space-y-4">
      
      <!-- Dirección destino -->
      <div>
        <label for="withdrawAddress" class="block text-sm font-medium text-gray-300 mb-2">
          Dirección de destino *
        </label>
        <input 
          type="text" 
          id="withdrawAddress"
          name="withdrawAddress"
          placeholder="0x..."
          required
          class="w-full bg-brand-bg-primary border border-brand-border rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-brand-accent transition-all font-mono text-sm"
        >
        <p class="text-xs text-gray-400 mt-1.5 flex items-center space-x-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <span>Dirección Ethereum donde recibirás los USDT</span>
        </p>
      </div>

      <!-- Cantidad SPHE -->
      <div>
        <label for="withdrawAmount" class="block text-sm font-medium text-gray-300 mb-2">
          Cantidad de SPHE *
        </label>
        <div class="relative">
          <input 
            type="number" 
            id="withdrawAmount"
            name="withdrawAmount"
            placeholder="0.0"
            step="0.01"
            min="0"
            required
            class="w-full bg-brand-bg-primary border border-brand-border rounded-xl px-4 py-3 pr-20 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-brand-accent transition-all"
          >
          <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium">
            SPHE
          </span>
        </div>
        <div class="flex items-center justify-between mt-1.5">
          <p class="text-xs text-gray-400">
            Mínimo: 5 USDT (~equivalente en SPHE)
          </p>
          <button 
            type="button"
            onclick="document.getElementById('withdrawAmount').value = sessionStorage.getItem('sphe_balance') || '0'"
            class="text-xs text-brand-accent hover:text-brand-accent-hover font-medium transition-colors"
          >
            Usar máximo
          </button>
        </div>
      </div>

      <!-- Estimación -->
      <div class="bg-brand-bg-primary rounded-xl p-4 border border-brand-border/50">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-gray-400">Recibirás aproximadamente:</span>
          <span class="text-green-400 font-semibold">~0.00 USDT</span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500">
          <span>Fee de conversión:</span>
          <span>0%</span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500">
          <span>Fee de red:</span>
          <span class="text-brand-accent">Patrocinado ✨</span>
        </div>
      </div>

      <!-- Advertencia -->
      <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4">
        <div class="flex items-start space-x-3">
          <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          <div class="text-sm text-yellow-200">
            <p class="font-medium mb-1">⚠️ Verifica la dirección cuidadosamente</p>
            <p class="text-yellow-200/80">Los retiros son irreversibles. Asegúrate de que la dirección sea correcta y soporte USDT en Polygon.</p>
          </div>
        </div>
      </div>

      <!-- Botón de envío -->
      <button 
        type="submit"
        id="withdrawSubmitBtn"
        class="w-full bg-gradient-to-r from-brand-accent to-purple-600 hover:from-brand-accent-hover hover:to-purple-700 text-white font-semibold py-4 rounded-xl transition-all shadow-lg shadow-brand-accent/20 flex items-center justify-center space-x-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        <span>Retirar Fondos</span>
      </button>

      <p class="text-xs text-center text-gray-500">
        Tiempo estimado: 2-5 minutos • Sin comisiones de red
      </p>
    </form>
  </div>

  <!-- Historial de retiros -->
  <div class="bg-brand-bg-secondary rounded-2xl p-6 border border-brand-border">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white flex items-center space-x-2">
        <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>Historial de Retiros</span>
      </h3>
      <button 
        onclick="window.WithdrawSystem.refresh()"
        class="text-sm text-brand-accent hover:text-brand-accent-hover font-medium transition-colors flex items-center space-x-1"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        <span>Actualizar</span>
      </button>
    </div>

    <div id="withdrawHistoryContainer" class="space-y-3">
      <!-- Se llena dinámicamente con JavaScript -->
    </div>
  </div>

  <!-- Info adicional -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-brand-bg-secondary rounded-xl p-4 border border-brand-border text-center">
      <div class="text-3xl mb-2">⚡</div>
      <p class="text-sm text-gray-400 mb-1">Tiempo promedio</p>
      <p class="text-lg font-semibold text-white">2-5 min</p>
    </div>
    <div class="bg-brand-bg-secondary rounded-xl p-4 border border-brand-border text-center">
      <div class="text-3xl mb-2">✨</div>
      <p class="text-sm text-gray-400 mb-1">Fee de red</p>
      <p class="text-lg font-semibold text-brand-accent">$0.00</p>
    </div>
    <div class="bg-brand-bg-secondary rounded-xl p-4 border border-brand-border text-center">
      <div class="text-3xl mb-2">🔒</div>
      <p class="text-sm text-gray-400 mb-1">Límite diario</p>
      <p class="text-lg font-semibold text-white">$1,000</p>
    </div>
  </div>

</div>

<!-- Incluir script -->
<script src="/assets/js/withdraw-system.js"></script>
