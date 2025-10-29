/**
 * ============================================
 * TOR PROXY UI
 * ============================================
 * User interface for Tor anonymization
 */

class TorProxyUI {
    constructor() {
        this.torProxy = null;
        this.mixNetwork = null;
        this.updateInterval = null;
        
        this.init();
    }

    async init() {
        console.log('🧅 Initializing Tor Proxy UI...');
        
        // Initialize Tor proxy
        this.torProxy = new TorProxy();
        
        // Set up event handlers
        this.torProxy.onStatusChanged = (status) => this.updateStatusUI(status);
        this.torProxy.onCircuitChanged = () => this.onCircuitChanged();
        this.torProxy.onIPChanged = (ip) => this.updateIPDisplay(ip);
        
        // Initialize mix network
        this.mixNetwork = new MixNetwork();
        await this.mixNetwork.init();
        
        // Set up UI controls
        this.setupControls();
        
        // Start status updates
        this.startStatusUpdates();
        
        console.log('✅ Tor Proxy UI initialized');
    }

    setupControls() {
        // Add Tor toggle to UI
        this.injectTorControls();
        
        // Event listeners
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-toggle-tor]')) {
                this.toggleTor();
            }
            
            if (e.target.matches('[data-change-circuit]')) {
                this.changeCircuit();
            }
            
            if (e.target.matches('[data-test-tor]')) {
                this.testConnection();
            }
            
            if (e.target.matches('[data-tor-info]')) {
                this.showConnectionInfo();
            }
        });
    }

    injectTorControls() {
        // Check if already exists
        if (document.querySelector('[data-tor-controls]')) return;
        
        const controls = document.createElement('div');
        controls.className = 'tor-controls';
        controls.setAttribute('data-tor-controls', '');
        controls.innerHTML = `
            <div class="tor-status-indicator" data-tor-status="disconnected">
                <div class="tor-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="tor-info">
                    <strong>Tor Network</strong>
                    <span class="tor-status-text">Disconnected</span>
                </div>
                <button class="btn btn-sm btn-primary" data-toggle-tor>
                    <i class="fas fa-power-off"></i>
                    Connect
                </button>
            </div>
            
            <div class="tor-details" style="display: none;">
                <div class="tor-ip-info">
                    <i class="fas fa-globe"></i>
                    <span>Current IP: <code data-current-ip>Loading...</code></span>
                </div>
                <div class="tor-actions">
                    <button class="btn btn-sm btn-secondary" data-change-circuit>
                        <i class="fas fa-sync"></i>
                        Change Circuit
                    </button>
                    <button class="btn btn-sm btn-secondary" data-test-tor>
                        <i class="fas fa-vial"></i>
                        Test Connection
                    </button>
                    <button class="btn btn-sm btn-info" data-tor-info>
                        <i class="fas fa-info-circle"></i>
                        Info
                    </button>
                </div>
            </div>
        `;
        
        // Insert in navbar or create floating widget
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.appendChild(controls);
        } else {
            controls.classList.add('floating-widget');
            document.body.appendChild(controls);
        }
    }

    async toggleTor() {
        const status = this.torProxy.getStatus();
        
        try {
            if (status.connected) {
                this.torProxy.disable();
                this.showNotification('Tor disconnected', 'info');
            } else {
                await this.torProxy.enable();
                this.showNotification('Tor connected - Your IP is now anonymous', 'success');
            }
        } catch (error) {
            this.showNotification('Failed to connect to Tor: ' + error.message, 'error');
            this.showTorSetupModal();
        }
    }

    async changeCircuit() {
        try {
            await this.torProxy.changeCircuit();
            this.showNotification('Circuit changed - New exit node assigned', 'success');
        } catch (error) {
            this.showNotification('Failed to change circuit', 'error');
        }
    }

    async testConnection() {
        this.showNotification('Testing Tor connection...', 'info');
        
        const working = await this.torProxy.testConnection();
        
        if (working) {
            this.showNotification('Tor is working correctly!', 'success');
        } else {
            this.showNotification('Tor test failed - Check your connection', 'warning');
        }
    }

    async showConnectionInfo() {
        const info = await this.torProxy.getConnectionInfo();
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-shield-alt"></i>
                            Tor Connection Info
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="connection-info">
                            <div class="info-row">
                                <strong>Status:</strong>
                                <span class="badge bg-${info.enabled ? 'success' : 'secondary'}">
                                    ${info.status}
                                </span>
                            </div>
                            <div class="info-row">
                                <strong>Current IP:</strong>
                                <code>${info.ip}</code>
                            </div>
                            <div class="info-row">
                                <strong>Exit Node:</strong>
                                <code>${info.exitNode}</code>
                            </div>
                            <div class="info-row">
                                <strong>Country:</strong>
                                <span>${info.country}</span>
                            </div>
                            <div class="info-row">
                                <strong>Active Circuits:</strong>
                                <span>${info.circuits}</span>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i>
                            Tor circuits change automatically every 10 minutes for enhanced anonymity.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    }

    showTorSetupModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-download"></i>
                            Setup Tor for Anonymous Browsing
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <h6>📥 Option 1: Tor Browser (Recommended)</h6>
                        <ol>
                            <li>Download Tor Browser from <a href="https://www.torproject.org/download/" target="_blank">torproject.org</a></li>
                            <li>Install and run Tor Browser</li>
                            <li>Tor SOCKS proxy will automatically start at <code>localhost:9050</code></li>
                            <li>Reload this page and click "Connect"</li>
                        </ol>
                        
                        <hr>
                        
                        <h6>📥 Option 2: Tor Service (Advanced)</h6>
                        <div class="accordion" id="torSetupAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#windowsSetup">
                                        <i class="fab fa-windows"></i> Windows
                                    </button>
                                </h2>
                                <div id="windowsSetup" class="accordion-collapse collapse show" data-bs-parent="#torSetupAccordion">
                                    <div class="accordion-body">
                                        <pre>1. Download Tor Expert Bundle
2. Extract and run tor.exe
3. Tor will start on localhost:9050</pre>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#linuxSetup">
                                        <i class="fab fa-linux"></i> Linux
                                    </button>
                                </h2>
                                <div id="linuxSetup" class="accordion-collapse collapse" data-bs-parent="#torSetupAccordion">
                                    <div class="accordion-body">
                                        <pre>sudo apt install tor
sudo systemctl start tor
sudo systemctl enable tor</pre>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#macSetup">
                                        <i class="fab fa-apple"></i> macOS
                                    </button>
                                </h2>
                                <div id="macSetup" class="accordion-collapse collapse" data-bs-parent="#torSetupAccordion">
                                    <div class="accordion-body">
                                        <pre>brew install tor
brew services start tor</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Close
                        </button>
                        <a href="https://www.torproject.org/download/" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i>
                            Download Tor
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    }

    updateStatusUI(status) {
        const indicator = document.querySelector('[data-tor-status]');
        const details = document.querySelector('.tor-details');
        const statusText = document.querySelector('.tor-status-text');
        const toggleBtn = document.querySelector('[data-toggle-tor]');
        
        if (!indicator) return;
        
        indicator.setAttribute('data-tor-status', status);
        
        if (statusText) {
            statusText.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        }
        
        if (details) {
            details.style.display = status === 'connected' ? 'block' : 'none';
        }
        
        if (toggleBtn) {
            if (status === 'connected') {
                toggleBtn.innerHTML = '<i class="fas fa-power-off"></i> Disconnect';
                toggleBtn.classList.remove('btn-primary');
                toggleBtn.classList.add('btn-danger');
            } else {
                toggleBtn.innerHTML = '<i class="fas fa-power-off"></i> Connect';
                toggleBtn.classList.remove('btn-danger');
                toggleBtn.classList.add('btn-primary');
            }
        }
    }

    updateIPDisplay(ip) {
        const ipDisplay = document.querySelector('[data-current-ip]');
        if (ipDisplay) {
            ipDisplay.textContent = ip;
        }
    }

    onCircuitChanged() {
        this.showNotification('Circuit changed', 'info');
    }

    startStatusUpdates() {
        this.updateInterval = setInterval(async () => {
            const status = this.torProxy.getStatus();
            if (status.connected) {
                await this.torProxy.displayCurrentIP();
            }
        }, 60000); // Update every minute
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification-toast`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.torProxyUI = new TorProxyUI();
    });
} else {
    window.torProxyUI = new TorProxyUI();
}
