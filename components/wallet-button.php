<!-- 
============================================
WALLET CONNECT BUTTON COMPONENT
============================================
Button component for MetaMask wallet connection
Displays connection status, wallet address, and balance

Usage:
<?php include __DIR__ . '/../components/wallet-button.php'; ?>

@author GitHub Copilot
@version 1.0.0
@date 2025-10-08
-->

<div id="wallet-connect-container" class="relative">
    <!-- Not Installed State -->
    <div id="wallet-not-installed" class="hidden">
        <a 
            href="https://metamask.io/download/" 
            target="_blank"
            class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-lg transition-colors duration-200 shadow-lg"
        >
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M22.05 7.54a4.2 4.2 0 0 0-3.54-2H18V3.5a1.5 1.5 0 0 0-3 0V5.5h-6V3.5a1.5 1.5 0 0 0-3 0V5.5H4.5A4.2 4.2 0 0 0 .95 7.54 4 4 0 0 0 0 10.5v8A3.5 3.5 0 0 0 3.5 22h17a3.5 3.5 0 0 0 3.5-3.5v-8a4 4 0 0 0-.95-2.96z"/>
            </svg>
            Install MetaMask
        </a>
    </div>
    
    <!-- Not Connected State -->
    <div id="wallet-not-connected" class="hidden">
        <button 
            id="connect-wallet-btn"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-medium rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105"
        >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Connect Wallet
        </button>
    </div>
    
    <!-- Connecting State -->
    <div id="wallet-connecting" class="hidden">
        <button 
            disabled
            class="inline-flex items-center px-4 py-2 bg-gray-400 text-white font-medium rounded-lg cursor-not-allowed opacity-75"
        >
            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Connecting...
        </button>
    </div>
    
    <!-- Connected State -->
    <div id="wallet-connected" class="hidden relative">
        <button 
            id="wallet-dropdown-btn"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-medium rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl"
        >
            <!-- Wallet Icon -->
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21 18v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1h-9a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h9zm-9-2h10V8H12v8zm4-2.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
            </svg>
            
            <!-- Address -->
            <span id="wallet-address-display" class="font-mono text-sm">0x0000...0000</span>
            
            <!-- Balance Badge -->
            <span id="wallet-balance-badge" class="ml-2 px-2 py-0.5 bg-white/20 rounded text-xs font-semibold">
                0 GOV
            </span>
            
            <!-- Dropdown Icon -->
            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        
        <!-- Dropdown Menu -->
        <div 
            id="wallet-dropdown-menu" 
            class="hidden absolute right-0 mt-2 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50"
        >
            <!-- Header with full address -->
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Connected Wallet</span>
                    <div id="network-badge-dropdown"></div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span id="wallet-full-address" class="font-mono text-sm text-gray-900 dark:text-white">0x0000...0000</span>
                    <button 
                        id="copy-address-btn"
                        class="ml-2 p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                        title="Copy address"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Balance Info -->
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <div class="space-y-2">
                    <!-- GOV Token Balance -->
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">GOV Balance:</span>
                        <span id="wallet-gov-balance" class="text-sm font-semibold text-gray-900 dark:text-white">0.00</span>
                    </div>
                    <!-- Voting Power -->
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Voting Power:</span>
                        <span id="wallet-voting-power" class="text-sm font-semibold text-purple-600 dark:text-purple-400">0.00</span>
                    </div>
                    <!-- Native Balance (MATIC) -->
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            <span id="native-token-symbol">MATIC</span> Balance:
                        </span>
                        <span id="wallet-native-balance" class="text-sm font-semibold text-gray-900 dark:text-white">0.00</span>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="px-2 py-2">
                <!-- View on Explorer -->
                <a 
                    id="view-explorer-btn"
                    href="#"
                    target="_blank"
                    class="flex items-center w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    View on Explorer
                </a>
                
                <!-- Copy Address -->
                <button 
                    id="copy-address-btn-2"
                    class="flex items-center w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Copy Address
                </button>
                
                <!-- Change Account -->
                <button 
                    id="change-account-btn"
                    class="flex items-center w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Change Account
                </button>
                
                <!-- Refresh Balances -->
                <button 
                    id="refresh-balances-btn"
                    class="flex items-center w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh Balances
                </button>
            </div>
            
            <!-- Disconnect -->
            <div class="px-2 pb-2 pt-1 border-t border-gray-200 dark:border-gray-700">
                <button 
                    id="disconnect-wallet-btn"
                    class="flex items-center w-full px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors font-medium"
                >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Disconnect Wallet
                </button>
            </div>
            
            <!-- Powered by MetaMask -->
            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900 rounded-b-lg">
                <div class="flex items-center justify-center text-xs text-gray-500 dark:text-gray-400">
                    <span>Powered by</span>
                    <svg class="w-4 h-4 mx-1" viewBox="0 0 318.6 318.6" fill="currentColor">
                        <path d="M274.1 35.5l-99.5 73.9L193 65.8z" fill="#e2761b" stroke="#e2761b"/>
                        <path d="M44.4 35.5l98.7 74.6-17.5-44.3zm193.9 171.3l-26.5 40.6 56.7 15.6 16.3-55.3zm-204.4.9L50.1 263l56.7-15.6-26.5-40.6z" fill="#e4761b" stroke="#e4761b"/>
                    </svg>
                    <span class="font-semibold">MetaMask</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Wallet button animations */
@keyframes pulse-green {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
    }
}

#wallet-connected button {
    animation: pulse-green 2s infinite;
}

/* Dropdown animation */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#wallet-dropdown-menu:not(.hidden) {
    animation: slideDown 0.2s ease-out;
}

/* Copy button feedback */
@keyframes copySuccess {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.copy-success {
    animation: copySuccess 0.3s ease;
}
</style>

<script>
// Wallet button functionality will be handled by wallet-button.js
// This is just the HTML/CSS structure
</script>
