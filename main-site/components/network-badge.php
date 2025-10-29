<!-- 
============================================
NETWORK BADGE COMPONENT
============================================
Displays current blockchain network with color coding

Usage:
<?php include __DIR__ . '/../components/network-badge.php'; ?>

@author GitHub Copilot
@version 1.0.0
@date 2025-10-08
-->

<div id="network-badge-container" class="inline-flex items-center">
    <!-- Network Badge -->
    <div 
        id="network-badge"
        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-all duration-200 hover:shadow-lg"
        title="Click to view network details"
    >
        <!-- Network Icon -->
        <span id="network-icon" class="w-2 h-2 rounded-full mr-2 animate-pulse"></span>
        
        <!-- Network Name -->
        <span id="network-name">Not Connected</span>
        
        <!-- Chain ID (optional) -->
        <span id="network-chain-id" class="hidden ml-1 opacity-75"></span>
    </div>
    
    <!-- Network Tooltip/Dropdown -->
    <div 
        id="network-tooltip"
        class="hidden absolute mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 p-4"
    >
        <div class="space-y-3">
            <!-- Network Info -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Network</span>
                    <span id="network-status-icon" class="w-2 h-2 rounded-full bg-gray-400"></span>
                </div>
                <div class="text-sm font-medium text-gray-900 dark:text-white" id="network-name-full">
                    Not Connected
                </div>
            </div>
            
            <!-- Chain ID -->
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Chain ID:</span>
                <span class="text-sm font-mono text-gray-900 dark:text-white ml-2" id="network-chain-id-full">—</span>
            </div>
            
            <!-- RPC Status -->
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400">RPC Status:</span>
                <span class="text-sm text-gray-900 dark:text-white ml-2" id="network-rpc-status">
                    <span class="inline-flex items-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1"></span>
                        Unknown
                    </span>
                </span>
            </div>
            
            <!-- Supported Badge -->
            <div id="network-supported-badge" class="hidden">
                <div class="flex items-center px-3 py-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs text-green-700 dark:text-green-300 font-medium">Supported Network</span>
                </div>
            </div>
            
            <!-- Unsupported Badge -->
            <div id="network-unsupported-badge" class="hidden">
                <div class="flex items-center px-3 py-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs text-red-700 dark:text-red-300 font-medium">Unsupported Network</span>
                </div>
            </div>
            
            <!-- Switch Network Button (only shown when unsupported) -->
            <div id="network-switch-section" class="hidden pt-2 border-t border-gray-200 dark:border-gray-700">
                <button 
                    id="switch-to-polygon-btn"
                    class="w-full flex items-center justify-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Switch to Polygon
                </button>
                <button 
                    id="switch-to-amoy-btn"
                    class="w-full flex items-center justify-center px-4 py-2 mt-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Switch to Amoy Testnet
                </button>
            </div>
            
            <!-- Explorer Link -->
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                <a 
                    id="network-explorer-link"
                    href="#"
                    target="_blank"
                    class="flex items-center justify-center w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    View Block Explorer
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Network badge color classes */
.network-polygon {
    background: linear-gradient(135deg, #8247E5 0%, #9B51E0 100%);
    color: white;
}

.network-amoy {
    background: linear-gradient(135deg, #F97316 0%, #FB923C 100%);
    color: white;
}

.network-ethereum {
    background: linear-gradient(135deg, #627EEA 0%, #8CA7F5 100%);
    color: white;
}

.network-bsc {
    background: linear-gradient(135deg, #F3BA2F 0%, #F5C94B 100%);
    color: #1E1E1E;
}

.network-unsupported {
    background: linear-gradient(135deg, #EF4444 0%, #F87171 100%);
    color: white;
}

.network-disconnected {
    background: linear-gradient(135deg, #6B7280 0%, #9CA3AF 100%);
    color: white;
}

/* Network icon colors */
.network-icon-polygon { background-color: #c084fc; }
.network-icon-amoy { background-color: #fdba74; }
.network-icon-ethereum { background-color: #93c5fd; }
.network-icon-bsc { background-color: #fde047; }
.network-icon-unsupported { background-color: #fca5a5; }
.network-icon-disconnected { background-color: #d1d5db; }

/* Pulse animation for network status */
@keyframes networkPulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

#network-icon {
    animation: networkPulse 2s ease-in-out infinite;
}

/* Tooltip animation */
@keyframes tooltipSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#network-tooltip:not(.hidden) {
    animation: tooltipSlide 0.2s ease-out;
}

/* Badge hover effect */
#network-badge:hover {
    transform: scale(1.05);
}

/* RPC status colors */
.rpc-online { color: #10b981; }
.rpc-offline { color: #ef4444; }
.rpc-slow { color: #f59e0b; }
</style>

<script>
// Network badge functionality will be handled by network-badge.js
// This is just the HTML/CSS structure
</script>
