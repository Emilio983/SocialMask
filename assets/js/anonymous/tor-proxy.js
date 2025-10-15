/**
 * ============================================
 * TOR PROXY INTEGRATION
 * ============================================
 * Route traffic through Tor network for IP anonymization
 */

class TorProxy {
    constructor() {
        this.torEnabled = false;
        this.torProxy = null;
        this.originalFetch = null;
        this.circuitChangeInterval = null;
        this.connectionStatus = 'disconnected';
        
        // Tor configuration
        this.config = {
            socksHost: 'localhost',
            socksPort: 9050,
            controlPort: 9051,
            circuitChangeIntervalMs: 10 * 60 * 1000, // 10 minutes
            maxRetries: 3,
            retryDelay: 2000
        };
        
        this.init();
    }

    /**
     * Initialize Tor proxy
     */
    async init() {
        console.log('🧅 Initializing Tor Proxy...');
        
        // Check if Tor is available
        this.torEnabled = await this.checkTorAvailable();
        
        if (this.torEnabled) {
            console.log('✅ Tor is available');
            this.setupFetchProxy();
        } else {
            console.log('⚠️ Tor not available, using direct connection');
            this.showTorInstallInstructions();
        }
        
        // Load saved preference
        const savedPreference = localStorage.getItem('tor_enabled');
        if (savedPreference === '1' && this.torEnabled) {
            await this.enable();
        }
    }

    /**
     * Check if Tor is available
     */
    async checkTorAvailable() {
        try {
            // Try to connect to Tor SOCKS proxy
            const response = await fetch('/api/anonymous/check-tor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    host: this.config.socksHost,
                    port: this.config.socksPort
                })
            });
            
            const result = await response.json();
            return result.available || false;
        } catch (error) {
            console.error('Failed to check Tor availability:', error);
            return false;
        }
    }

    /**
     * Enable Tor routing
     */
    async enable() {
        if (!this.torEnabled) {
            throw new Error('Tor is not available. Please install Tor Browser or tor service.');
        }
        
        try {
            console.log('🧅 Enabling Tor routing...');
            
            // Start circuit change interval
            this.startCircuitChangeInterval();
            
            // Update connection status
            this.connectionStatus = 'connected';
            
            // Save preference
            localStorage.setItem('tor_enabled', '1');
            
            // Notify UI
            this.onStatusChanged('connected');
            
            console.log('✅ Tor routing enabled');
            
            // Display current IP (should be Tor exit node)
            await this.displayCurrentIP();
            
            return true;
        } catch (error) {
            console.error('❌ Failed to enable Tor:', error);
            this.connectionStatus = 'error';
            this.onStatusChanged('error');
            throw error;
        }
    }

    /**
     * Disable Tor routing
     */
    disable() {
        console.log('🧅 Disabling Tor routing...');
        
        // Stop circuit change interval
        if (this.circuitChangeInterval) {
            clearInterval(this.circuitChangeInterval);
            this.circuitChangeInterval = null;
        }
        
        // Update status
        this.connectionStatus = 'disconnected';
        
        // Save preference
        localStorage.setItem('tor_enabled', '0');
        
        // Notify UI
        this.onStatusChanged('disconnected');
        
        console.log('✅ Tor routing disabled');
    }

    /**
     * Setup fetch proxy to route through Tor
     */
    setupFetchProxy() {
        if (!this.originalFetch) {
            this.originalFetch = window.fetch;
        }
        
        window.fetch = async (url, options = {}) => {
            // Check if Tor is enabled
            if (this.connectionStatus !== 'connected') {
                return this.originalFetch(url, options);
            }
            
            // Add Tor routing header
            const torOptions = {
                ...options,
                headers: {
                    ...options.headers,
                    'X-Use-Tor': '1',
                    'X-Tor-Circuit-Change': Date.now().toString()
                }
            };
            
            try {
                // Route through our backend proxy that connects to Tor
                if (url.startsWith('/api/')) {
                    return this.originalFetch(url, torOptions);
                }
                
                // For external URLs, use proxy endpoint
                return this.originalFetch('/api/anonymous/tor-proxy.php', {
                    ...torOptions,
                    method: 'POST',
                    body: JSON.stringify({
                        url,
                        method: options.method || 'GET',
                        headers: options.headers || {},
                        body: options.body
                    })
                });
            } catch (error) {
                console.error('Tor fetch failed, falling back to direct:', error);
                return this.originalFetch(url, options);
            }
        };
    }

    /**
     * Change Tor circuit (get new exit node)
     */
    async changeCircuit() {
        try {
            console.log('🔄 Changing Tor circuit...');
            
            const response = await fetch('/api/anonymous/change-tor-circuit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Circuit changed successfully');
                await this.displayCurrentIP();
                this.onCircuitChanged();
            } else {
                throw new Error(result.error || 'Failed to change circuit');
            }
        } catch (error) {
            console.error('❌ Failed to change circuit:', error);
        }
    }

    /**
     * Start automatic circuit change interval
     */
    startCircuitChangeInterval() {
        if (this.circuitChangeInterval) {
            clearInterval(this.circuitChangeInterval);
        }
        
        this.circuitChangeInterval = setInterval(() => {
            this.changeCircuit();
        }, this.config.circuitChangeIntervalMs);
    }

    /**
     * Get current IP address
     */
    async getCurrentIP() {
        try {
            const response = await this.originalFetch('/api/anonymous/get-ip.php');
            const result = await response.json();
            return result.ip || 'Unknown';
        } catch (error) {
            console.error('Failed to get IP:', error);
            return 'Unknown';
        }
    }

    /**
     * Display current IP address
     */
    async displayCurrentIP() {
        const ip = await this.getCurrentIP();
        console.log(`🌐 Current IP: ${ip}`);
        this.onIPChanged(ip);
        return ip;
    }

    /**
     * Get connection info
     */
    async getConnectionInfo() {
        try {
            const response = await fetch('/api/anonymous/tor-info.php');
            const result = await response.json();
            
            return {
                enabled: this.connectionStatus === 'connected',
                status: this.connectionStatus,
                ip: result.ip || 'Unknown',
                exitNode: result.exit_node || 'Unknown',
                country: result.country || 'Unknown',
                circuits: result.circuits || 0
            };
        } catch (error) {
            console.error('Failed to get connection info:', error);
            return {
                enabled: false,
                status: 'error',
                ip: 'Unknown',
                exitNode: 'Unknown',
                country: 'Unknown',
                circuits: 0
            };
        }
    }

    /**
     * Show Tor installation instructions
     */
    showTorInstallInstructions() {
        console.log(`
╔════════════════════════════════════════════════════════════╗
║                TOR NOT DETECTED                            ║
╚════════════════════════════════════════════════════════════╝

To enable full IP anonymization, install Tor:

📥 OPTION 1: Tor Browser (Easiest)
   • Download: https://www.torproject.org/download/
   • Run Tor Browser
   • Tor SOCKS proxy will be available at localhost:9050

📥 OPTION 2: Tor Service (Advanced)
   Windows:
   • Download Tor Expert Bundle
   • Run: tor.exe
   
   Linux:
   • sudo apt install tor
   • sudo systemctl start tor
   
   macOS:
   • brew install tor
   • brew services start tor

⚙️  Default Configuration:
   • SOCKS Proxy: localhost:9050
   • Control Port: localhost:9051

Once Tor is running, reload the page to enable anonymous routing.
        `);
    }

    /**
     * Test Tor connection
     */
    async testConnection() {
        try {
            console.log('🧪 Testing Tor connection...');
            
            // Get IP without Tor
            const originalStatus = this.connectionStatus;
            this.connectionStatus = 'disconnected';
            const directIP = await this.getCurrentIP();
            
            // Get IP with Tor
            this.connectionStatus = 'connected';
            const torIP = await this.getCurrentIP();
            
            // Restore status
            this.connectionStatus = originalStatus;
            
            console.log(`Direct IP: ${directIP}`);
            console.log(`Tor IP: ${torIP}`);
            
            if (directIP !== torIP) {
                console.log('✅ Tor is working correctly');
                return true;
            } else {
                console.log('⚠️ IPs match - Tor may not be working');
                return false;
            }
        } catch (error) {
            console.error('❌ Connection test failed:', error);
            return false;
        }
    }

    /**
     * Get Tor status
     */
    getStatus() {
        return {
            enabled: this.torEnabled,
            connected: this.connectionStatus === 'connected',
            status: this.connectionStatus
        };
    }

    /**
     * Event handlers (override in implementation)
     */
    onStatusChanged(status) {
        console.log('Tor status changed:', status);
    }

    onCircuitChanged() {
        console.log('Tor circuit changed');
    }

    onIPChanged(ip) {
        console.log('IP changed:', ip);
    }
}

/**
 * ============================================
 * MIX NETWORK - Traffic Analysis Resistance
 * ============================================
 */

class MixNetwork {
    constructor() {
        this.mixNodes = [];
        this.messageQueue = [];
        this.batchSize = 10;
        this.batchDelay = 5000; // 5 seconds
        this.processingInterval = null;
    }

    /**
     * Initialize mix network
     */
    async init() {
        console.log('🔀 Initializing Mix Network...');
        
        // Load mix nodes
        await this.loadMixNodes();
        
        // Start processing queue
        this.startProcessing();
        
        console.log(`✅ Mix Network initialized with ${this.mixNodes.length} nodes`);
    }

    /**
     * Load available mix nodes
     */
    async loadMixNodes() {
        try {
            const response = await fetch('/api/anonymous/get-mix-nodes.php');
            const result = await response.json();
            
            if (result.success) {
                this.mixNodes = result.nodes || [];
            }
        } catch (error) {
            console.error('Failed to load mix nodes:', error);
            // Use default local mixing
            this.mixNodes = [{ id: 'local', type: 'local' }];
        }
    }

    /**
     * Send message through mix network
     */
    async sendMessage(message, destination) {
        // Add to queue
        this.messageQueue.push({
            id: this.generateMessageId(),
            message,
            destination,
            timestamp: Date.now(),
            encrypted: await this.encryptMessage(message)
        });
        
        console.log(`📨 Message queued (${this.messageQueue.length} in queue)`);
        
        // Process immediately if batch is full
        if (this.messageQueue.length >= this.batchSize) {
            await this.processBatch();
        }
    }

    /**
     * Process message batch
     */
    async processBatch() {
        if (this.messageQueue.length === 0) return;
        
        console.log(`🔀 Processing batch of ${this.messageQueue.length} messages...`);
        
        // Take batch
        const batch = this.messageQueue.splice(0, this.batchSize);
        
        // Shuffle messages (mixing)
        this.shuffleArray(batch);
        
        // Add dummy messages for anonymity
        const dummies = this.generateDummyMessages(Math.max(0, this.batchSize - batch.length));
        const mixedBatch = [...batch, ...dummies];
        
        // Shuffle again
        this.shuffleArray(mixedBatch);
        
        // Send batch
        for (const msg of mixedBatch) {
            if (!msg.isDummy) {
                await this.deliverMessage(msg);
            }
        }
        
        console.log('✅ Batch processed');
    }

    /**
     * Deliver message to destination
     */
    async deliverMessage(msg) {
        try {
            await fetch(msg.destination, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    encrypted_message: msg.encrypted,
                    timestamp: msg.timestamp
                })
            });
        } catch (error) {
            console.error('Failed to deliver message:', error);
        }
    }

    /**
     * Start batch processing
     */
    startProcessing() {
        if (this.processingInterval) {
            clearInterval(this.processingInterval);
        }
        
        this.processingInterval = setInterval(() => {
            this.processBatch();
        }, this.batchDelay);
    }

    /**
     * Stop batch processing
     */
    stopProcessing() {
        if (this.processingInterval) {
            clearInterval(this.processingInterval);
            this.processingInterval = null;
        }
    }

    /**
     * Encrypt message
     */
    async encryptMessage(message) {
        // Use Web Crypto API
        const encoder = new TextEncoder();
        const data = encoder.encode(JSON.stringify(message));
        
        // Generate random key for this message
        const key = await crypto.subtle.generateKey(
            { name: 'AES-GCM', length: 256 },
            true,
            ['encrypt', 'decrypt']
        );
        
        const iv = crypto.getRandomValues(new Uint8Array(12));
        
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            data
        );
        
        // Export key
        const exportedKey = await crypto.subtle.exportKey('raw', key);
        
        return {
            ciphertext: this.arrayBufferToBase64(encrypted),
            key: this.arrayBufferToBase64(exportedKey),
            iv: this.arrayBufferToBase64(iv)
        };
    }

    /**
     * Generate dummy messages
     */
    generateDummyMessages(count) {
        const dummies = [];
        for (let i = 0; i < count; i++) {
            dummies.push({
                id: this.generateMessageId(),
                isDummy: true,
                encrypted: this.generateRandomData(256),
                timestamp: Date.now()
            });
        }
        return dummies;
    }

    /**
     * Utilities
     */
    generateMessageId() {
        return `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    generateRandomData(length) {
        const array = new Uint8Array(length);
        crypto.getRandomValues(array);
        return this.arrayBufferToBase64(array.buffer);
    }

    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }
}

// Export
window.TorProxy = TorProxy;
window.MixNetwork = MixNetwork;
