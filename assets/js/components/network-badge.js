/**
 * ============================================
 * NETWORK BADGE CONTROLLER
 * ============================================
 * Manages network badge UI and network switching
 * 
 * Requires:
 * - web3Connector (Web3Connector instance)
 * - Web3Utils
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

class NetworkBadge {
    constructor() {
        this.currentChainId = null;
        this.currentNetwork = null;
        this.isSupported = false;
        this.tooltipOpen = false;
        
        this.elements = {};
        
        // Supported networks
        this.supportedChains = window.__SPHERA_GOVERNANCE__?.supportedChains || ['0x89', '0x13882'];
        
        this.init();
    }
    
    /**
     * Initialize network badge
     */
    init() {
        // console.log('Initializing NetworkBadge...');
        
        // Cache DOM elements
        this.cacheElements();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Check initial network
        this.checkNetwork();
        
        // console.log('NetworkBadge initialized');
    }
    
    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.elements = {
            container: document.getElementById('network-badge-container'),
            badge: document.getElementById('network-badge'),
            icon: document.getElementById('network-icon'),
            name: document.getElementById('network-name'),
            chainId: document.getElementById('network-chain-id'),
            
            tooltip: document.getElementById('network-tooltip'),
            nameFull: document.getElementById('network-name-full'),
            chainIdFull: document.getElementById('network-chain-id-full'),
            statusIcon: document.getElementById('network-status-icon'),
            rpcStatus: document.getElementById('network-rpc-status'),
            
            supportedBadge: document.getElementById('network-supported-badge'),
            unsupportedBadge: document.getElementById('network-unsupported-badge'),
            switchSection: document.getElementById('network-switch-section'),
            
            switchPolygonBtn: document.getElementById('switch-to-polygon-btn'),
            switchAmoyBtn: document.getElementById('switch-to-amoy-btn'),
            explorerLink: document.getElementById('network-explorer-link')
        };
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Badge click (toggle tooltip)
        if (this.elements.badge) {
            this.elements.badge.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleTooltip();
            });
        }
        
        // Switch network buttons
        if (this.elements.switchPolygonBtn) {
            this.elements.switchPolygonBtn.addEventListener('click', () => this.switchToPolygon());
        }
        
        if (this.elements.switchAmoyBtn) {
            this.elements.switchAmoyBtn.addEventListener('click', () => this.switchToAmoy());
        }
        
        // Close tooltip when clicking outside
        document.addEventListener('click', (e) => {
            if (this.tooltipOpen && !this.elements.container.contains(e.target)) {
                this.closeTooltip();
            }
        });
        
        // Listen to Web3 events
        document.addEventListener('web3:chainChanged', (e) => {
            this.handleChainChanged(e.detail);
        });
        
        document.addEventListener('web3:accountConnected', (e) => {
            this.handleAccountConnected(e.detail);
        });
        
        document.addEventListener('web3:accountDisconnected', () => {
            this.handleAccountDisconnected();
        });
    }
    
    /**
     * Check current network
     */
    async checkNetwork() {
        try {
            if (!window.web3Connector || !window.web3Connector.isSmartWalletAvailable()) {
                this.setDisconnectedState();
                return;
            }
            
            const chainId = await window.web3Connector.getCurrentChainId();
            
            if (chainId) {
                this.updateNetwork(chainId);
            } else {
                this.setDisconnectedState();
            }
            
        } catch (error) {
            console.error('Error checking network:', error);
            this.setDisconnectedState();
        }
    }
    
    /**
     * Update network display
     */
    updateNetwork(chainId) {
        this.currentChainId = chainId;
        this.currentNetwork = Web3Utils.getNetworkName(chainId);
        this.isSupported = this.supportedChains.includes(chainId);
        
        // Update badge
        this.updateBadgeDisplay();
        
        // Update tooltip
        this.updateTooltipDisplay();
        
        // console.log('Network updated:', {
            chainId,
            network: this.currentNetwork,
            supported: this.isSupported
        });
    }
    
    /**
     * Update badge display
     */
    updateBadgeDisplay() {
        if (!this.elements.badge || !this.elements.icon || !this.elements.name) return;
        
        // Remove all network classes
        const networkClasses = ['network-polygon', 'network-amoy', 'network-ethereum', 'network-bsc', 'network-unsupported', 'network-disconnected'];
        this.elements.badge.classList.remove(...networkClasses);
        
        const iconClasses = ['network-icon-polygon', 'network-icon-amoy', 'network-icon-ethereum', 'network-icon-bsc', 'network-icon-unsupported', 'network-icon-disconnected'];
        this.elements.icon.classList.remove(...iconClasses);
        
        // Set network-specific classes
        let badgeClass, iconClass;
        
        if (!this.currentChainId) {
            badgeClass = 'network-disconnected';
            iconClass = 'network-icon-disconnected';
            this.elements.name.textContent = 'Not Connected';
        } else if (!this.isSupported) {
            badgeClass = 'network-unsupported';
            iconClass = 'network-icon-unsupported';
            this.elements.name.textContent = this.currentNetwork || 'Unsupported';
        } else {
            switch(this.currentChainId) {
                case '0x89':
                    badgeClass = 'network-polygon';
                    iconClass = 'network-icon-polygon';
                    break;
                case '0x13882':
                    badgeClass = 'network-amoy';
                    iconClass = 'network-icon-amoy';
                    break;
                case '0x1':
                    badgeClass = 'network-ethereum';
                    iconClass = 'network-icon-ethereum';
                    break;
                case '0x38':
                    badgeClass = 'network-bsc';
                    iconClass = 'network-icon-bsc';
                    break;
                default:
                    badgeClass = 'network-unsupported';
                    iconClass = 'network-icon-unsupported';
            }
            this.elements.name.textContent = this.currentNetwork;
        }
        
        this.elements.badge.classList.add(badgeClass);
        this.elements.icon.classList.add(iconClass);
        
        // Update chain ID (optional display)
        if (this.elements.chainId && this.currentChainId) {
            this.elements.chainId.textContent = `(${this.currentChainId})`;
        }
    }
    
    /**
     * Update tooltip display
     */
    updateTooltipDisplay() {
        if (!this.elements.tooltip) return;
        
        // Network name
        if (this.elements.nameFull) {
            this.elements.nameFull.textContent = this.currentNetwork || 'Not Connected';
        }
        
        // Chain ID
        if (this.elements.chainIdFull) {
            this.elements.chainIdFull.textContent = this.currentChainId || '—';
        }
        
        // RPC Status (placeholder - could be enhanced with actual RPC check)
        if (this.elements.rpcStatus) {
            if (this.currentChainId) {
                this.elements.rpcStatus.innerHTML = `
                    <span class="inline-flex items-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1"></span>
                        <span class="rpc-online">Online</span>
                    </span>
                `;
            } else {
                this.elements.rpcStatus.innerHTML = `
                    <span class="inline-flex items-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1"></span>
                        Unknown
                    </span>
                `;
            }
        }
        
        // Status icon
        if (this.elements.statusIcon) {
            this.elements.statusIcon.className = 'w-2 h-2 rounded-full';
            if (this.isSupported) {
                this.elements.statusIcon.classList.add('bg-green-500');
            } else if (this.currentChainId) {
                this.elements.statusIcon.classList.add('bg-red-500');
            } else {
                this.elements.statusIcon.classList.add('bg-gray-400');
            }
        }
        
        // Supported/Unsupported badges
        if (this.elements.supportedBadge && this.elements.unsupportedBadge) {
            if (this.isSupported) {
                this.elements.supportedBadge.classList.remove('hidden');
                this.elements.unsupportedBadge.classList.add('hidden');
            } else if (this.currentChainId) {
                this.elements.supportedBadge.classList.add('hidden');
                this.elements.unsupportedBadge.classList.remove('hidden');
            } else {
                this.elements.supportedBadge.classList.add('hidden');
                this.elements.unsupportedBadge.classList.add('hidden');
            }
        }
        
        // Switch network section
        if (this.elements.switchSection) {
            if (!this.isSupported && this.currentChainId) {
                this.elements.switchSection.classList.remove('hidden');
            } else {
                this.elements.switchSection.classList.add('hidden');
            }
        }
        
        // Explorer link
        if (this.elements.explorerLink && this.currentChainId) {
            const explorerUrl = Web3Utils.getExplorerUrl('', this.currentChainId).replace('/address/', '');
            this.elements.explorerLink.href = explorerUrl;
        }
    }
    
    /**
     * Set disconnected state
     */
    setDisconnectedState() {
        this.currentChainId = null;
        this.currentNetwork = null;
        this.isSupported = false;
        
        this.updateBadgeDisplay();
        this.updateTooltipDisplay();
    }
    
    /**
     * Handle chain changed event
     */
    handleChainChanged(data) {
        this.updateNetwork(data.newChainId);
    }
    
    /**
     * Handle account connected event
     */
    handleAccountConnected(data) {
        this.updateNetwork(data.chainId);
    }
    
    /**
     * Handle account disconnected event
     */
    handleAccountDisconnected() {
        this.setDisconnectedState();
    }
    
    /**
     * Switch to Polygon network
     */
    async switchToPolygon() {
        try {
            this.closeTooltip();
            
            if (window.web3Connector) {
                await window.web3Connector.switchToPolygon();
                Web3Utils.showToast('Switching to Polygon...', 'info');
            }
        } catch (error) {
            console.error('Error switching to Polygon:', error);
            const errorMsg = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMsg, 'error');
        }
    }
    
    /**
     * Switch to Amoy testnet
     */
    async switchToAmoy() {
        try {
            this.closeTooltip();
            
            if (window.web3Connector) {
                await window.web3Connector.switchToAmoy();
                Web3Utils.showToast('Switching to Amoy Testnet...', 'info');
            }
        } catch (error) {
            console.error('Error switching to Amoy:', error);
            const errorMsg = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMsg, 'error');
        }
    }
    
    /**
     * Toggle tooltip
     */
    toggleTooltip() {
        if (this.tooltipOpen) {
            this.closeTooltip();
        } else {
            this.openTooltip();
        }
    }
    
    /**
     * Open tooltip
     */
    openTooltip() {
        if (this.elements.tooltip) {
            this.elements.tooltip.classList.remove('hidden');
            this.tooltipOpen = true;
        }
    }
    
    /**
     * Close tooltip
     */
    closeTooltip() {
        if (this.elements.tooltip) {
            this.elements.tooltip.classList.add('hidden');
            this.tooltipOpen = false;
        }
    }
    
    /**
     * Get badge HTML for inline use (e.g., in wallet dropdown)
     */
    getInlineBadge() {
        if (!this.currentChainId) {
            return '<span class="text-xs text-gray-500">Not Connected</span>';
        }
        
        const color = Web3Utils.getNetworkColor(this.currentChainId);
        const name = this.currentNetwork;
        
        return `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-${color}-100 text-${color}-800 dark:bg-${color}-900 dark:text-${color}-200">
                <span class="w-1.5 h-1.5 rounded-full bg-${color}-500 mr-1"></span>
                ${name}
            </span>
        `;
    }
}

// Export as singleton
window.NetworkBadge = NetworkBadge;
window.networkBadge = new NetworkBadge();

// console.log('NetworkBadge module loaded');
