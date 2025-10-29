<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Sistema P2P</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">🔥 Test Sistema P2P</h1>
        
        <!-- Status -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Estado del Sistema</h2>
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <div id="status-dot" class="w-3 h-3 rounded-full bg-red-500"></div>
                    <span id="status-text">Desconectado</span>
                </div>
                <div>
                    <strong>User ID:</strong> <span id="user-id">-</span>
                </div>
                <div>
                    <strong>Public Key:</strong> <span id="public-key" class="text-xs text-gray-400 break-all">-</span>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Acciones</h2>
            <div class="space-y-3">
                <div class="flex gap-3">
                    <input type="number" id="userId" placeholder="Tu User ID" class="flex-1 bg-gray-700 px-4 py-2 rounded">
                    <button onclick="initP2P()" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded">
                        Inicializar
                    </button>
                </div>
                <div class="flex gap-3">
                    <input type="number" id="recipientId" placeholder="ID Destinatario" class="flex-1 bg-gray-700 px-4 py-2 rounded">
                    <input type="text" id="messageText" placeholder="Mensaje" class="flex-1 bg-gray-700 px-4 py-2 rounded">
                    <button onclick="sendMessage()" class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded">
                        Enviar
                    </button>
                </div>
                <button onclick="loadPosts()" class="w-full bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded">
                    Cargar Posts
                </button>
            </div>
        </div>
        
        <!-- Messages Log -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Log</h2>
            <div id="log" class="space-y-2 text-sm font-mono max-h-96 overflow-y-auto"></div>
        </div>
    </div>

    <!-- Load P2P Client -->
    <script src="/assets/js/p2p-client.js?v=<?php echo time(); ?>"></script>
    
    <script>
        let client = null;
        
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const entry = document.createElement('div');
            const colors = {
                info: 'text-blue-400',
                success: 'text-green-400',
                error: 'text-red-400',
                warning: 'text-yellow-400'
            };
            entry.className = colors[type] || colors.info;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logDiv.appendChild(entry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function updateStatus() {
            const dot = document.getElementById('status-dot');
            const text = document.getElementById('status-text');
            const userId = document.getElementById('user-id');
            const publicKey = document.getElementById('public-key');
            
            if (client && client.isConnected) {
                dot.className = 'w-3 h-3 rounded-full bg-green-500';
                text.textContent = 'Conectado';
                userId.textContent = client.userId || '-';
                publicKey.textContent = client.publicKey ? 
                    client.publicKey.substring(0, 100) + '...' : '-';
            } else {
                dot.className = 'w-3 h-3 rounded-full bg-red-500';
                text.textContent = 'Desconectado';
            }
        }

        async function initP2P() {
            try {
                const userIdInput = document.getElementById('userId').value;
                if (!userIdInput) {
                    log('❌ Por favor ingresa tu User ID', 'error');
                    return;
                }
                
                const userId = parseInt(userIdInput);
                log('🔧 Inicializando P2P Client...', 'info');
                
                client = window.p2pClient;
                
                // Registrar eventos
                client.on('connected', (data) => {
                    log('✅ Conectado exitosamente', 'success');
                    updateStatus();
                });
                
                client.on('message', (data) => {
                    log(`📨 Mensaje de ${data.from}: ${JSON.stringify(data.data)}`, 'success');
                });
                
                client.on('messageSent', (data) => {
                    log(`✅ Mensaje enviado a ${data.recipientId}`, 'success');
                });
                
                // Inicializar
                const success = await client.init(userId);
                if (success) {
                    log('✅ P2P Client listo', 'success');
                    updateStatus();
                } else {
                    log('❌ Error inicializando', 'error');
                }
            } catch (error) {
                log('❌ Error: ' + error.message, 'error');
                console.error(error);
            }
        }

        async function sendMessage() {
            try {
                if (!client || !client.isConnected) {
                    log('❌ Primero inicializa el cliente P2P', 'error');
                    return;
                }
                
                const recipientId = parseInt(document.getElementById('recipientId').value);
                const message = document.getElementById('messageText').value;
                
                if (!recipientId || !message) {
                    log('❌ Ingresa ID destinatario y mensaje', 'error');
                    return;
                }
                
                log('📤 Enviando mensaje...', 'info');
                const success = await client.send(recipientId, { text: message });
                
                if (success) {
                    document.getElementById('messageText').value = '';
                }
            } catch (error) {
                log('❌ Error enviando: ' + error.message, 'error');
                console.error(error);
            }
        }

        async function loadPosts() {
            try {
                if (!client || !client.isConnected) {
                    log('❌ Primero inicializa el cliente P2P', 'error');
                    return;
                }
                
                log('📥 Cargando posts...', 'info');
                const posts = await client.getPosts(10);
                log(`✅ ${posts.length} posts cargados`, 'success');
                
                posts.forEach(post => {
                    log(`📝 Post: ${JSON.stringify(post)}`, 'info');
                });
            } catch (error) {
                log('❌ Error cargando posts: ' + error.message, 'error');
                console.error(error);
            }
        }
        
        // Esperar a que todo se cargue
        window.addEventListener('DOMContentLoaded', () => {
            log('✅ Sistema cargado. Ingresa tu User ID y dale a Inicializar', 'success');
        });
    </script>
</body>
</html>
    <script>
        // Cargar p2p-client.js con timestamp para evitar caché
        const script = document.createElement('script');
        script.src = '/assets/js/p2p-client.js?v=' + Date.now();
        script.onload = () => {
            console.log('✅ p2p-client.js cargado');
            if (typeof window.p2pClient !== 'undefined') {
                console.log('✅ window.p2pClient disponible');
            } else {
                console.error('❌ window.p2pClient NO está disponible después de cargar el script');
            }
        };
        script.onerror = () => {
            console.error('❌ Error cargando p2p-client.js');
            log('❌ P2PClient no está cargando. Verifica que /assets/js/p2p-client.js esté disponible', 'error');
        };
        document.head.appendChild(script);
    </script>
    
    <script>
        // Usar instancia global de p2pClient  
        let p2pClient = null;
        
        // Función para esperar a que p2pClient esté disponible
        function waitForP2PClient() {
            return new Promise((resolve, reject) => {
                let attempts = 0;
                const maxAttempts = 50; // 5 segundos máximo
                
                const checkInterval = setInterval(() => {
                    attempts++;
                    
                    if (window.p2pClient) {
                        clearInterval(checkInterval);
                        p2pClient = window.p2pClient;
                        resolve();
                    } else if (attempts >= maxAttempts) {
                        clearInterval(checkInterval);
                        reject(new Error('Timeout esperando P2PClient'));
                    }
                }, 100);
            });
        }
        
        // Inicializar al cargar página
        window.addEventListener('DOMContentLoaded', async () => {
            try {
                await waitForP2PClient();
                log('✅ P2PClient cargado correctamente', 'success');
                log('Tipo de P2PClient: ' + typeof p2pClient, 'info');
                log('Métodos disponibles: init, send, receive, connectToPeer', 'info');
            } catch (error) {
                log('❌ Error: P2PClient no está disponible. ' + error.message, 'error');
                log('Verifica que /assets/js/p2p-client.js esté accesible', 'error');
            }
        });
        
        // Log helper
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const entry = document.createElement('div');
            const colors = {
                info: 'text-blue-400',
                success: 'text-green-400',
                error: 'text-red-400',
                warning: 'text-yellow-400'
            };
            entry.className = colors[type] || colors.info;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logDiv.appendChild(entry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        // Update status UI
        function updateStatusUI(connected = false) {
            const dot = document.getElementById('status-dot');
            const text = document.getElementById('status-text');
            const userId = document.getElementById('user-id');
            const publicKey = document.getElementById('public-key');
            
            if (connected && p2pClient && p2pClient.isConnected) {
                dot.className = 'w-3 h-3 rounded-full bg-green-500';
                text.textContent = 'Conectado';
                userId.textContent = p2pClient.userId || '-';
                publicKey.textContent = p2pClient.publicKey ? 
                    p2pClient.publicKey.substring(0, 50) + '...' : '-';
            } else {
                dot.className = 'w-3 h-3 rounded-full bg-red-500';
                text.textContent = 'Desconectado';
            }
        }

        // Initialize P2P
        async function initP2P() {
            try {
                log('Inicializando cliente P2P...', 'info');
                
                // Verificar que p2pClient existe
                if (!p2pClient) {
                    log('❌ p2pClient no está cargado. Verifica que /assets/js/p2p-client.js esté disponible', 'error');
                    return;
                }
                
                // Simular userId (en producción viene de sesión)
                const userId = parseInt(prompt('Ingresa tu User ID:', '1'));
                if (!userId) {
                    log('User ID requerido', 'error');
                    return;
                }
                
                sessionStorage.setItem('user_id', userId);
                
                const success = await p2pClient.init(userId);
                
                if (success) {
                    log('✅ Cliente P2P inicializado correctamente', 'success');
                    updateStatusUI(true);
                    
                    // Guardar llave pública
                    try {
                        await fetch('/api/p2p/save-public-key.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ publicKey: p2pClient.publicKey })
                        });
                        log('Llave pública guardada en servidor', 'success');
                    } catch (e) {
                        log('⚠️ No se pudo guardar llave pública: ' + e.message, 'warning');
                    }
                    
                    // Escuchar mensajes
                    p2pClient.on('message', (data) => {
                        log(`📨 Mensaje recibido de User #${data.from}: ${JSON.stringify(data.data)}`, 'success');
                    });
                    
                    // Escuchar conexiones de peers
                    p2pClient.on('peer:connected', (peerId) => {
                        log(`🤝 Peer conectado: User #${peerId}`, 'info');
                        document.getElementById('peers-count').textContent = p2pClient.peers.size;
                    });
                    
                    p2pClient.on('peer:offline', (peerId) => {
                        log(`👋 Peer desconectado: User #${peerId}`, 'warning');
                        document.getElementById('peers-count').textContent = p2pClient.peers.size;
                    });
                    
                } else {
                    log('❌ Error inicializando cliente P2P', 'error');
                }
                
            } catch (error) {
                log('❌ Error: ' + error.message, 'error');
                console.error(error);
            }
        }

        // Test send message
        async function testSendMessage() {
            try {
                if (!p2pClient) {
                    log('❌ p2pClient no está cargado', 'error');
                    return;
                }
                
                if (!p2pClient.isConnected) {
                    log('Primero inicializa el cliente P2P', 'warning');
                    return;
                }
                
                const recipientId = parseInt(prompt('ID del destinatario:', '2'));
                if (!recipientId) return;
                
                log(`Enviando mensaje a User #${recipientId}...`, 'info');
                
                const message = {
                    text: 'Hola! Este es un mensaje de prueba P2P 🚀',
                    timestamp: Date.now()
                };
                
                const result = await p2pClient.send([recipientId], message, {
                    test: true,
                    version: '1.0'
                });
                
                log(`✅ Mensaje enviado. CID: ${result.cid}`, 'success');
                
            } catch (error) {
                log('❌ Error enviando mensaje: ' + error.message, 'error');
                console.error(error);
            }
        }

        // Test connect to peer
        async function testConnect() {
            try {
                if (!p2pClient) {
                    log('❌ p2pClient no está cargado', 'error');
                    return;
                }
                
                if (!p2pClient.isConnected) {
                    log('Primero inicializa el cliente P2P', 'warning');
                    return;
                }
                
                const peerId = parseInt(prompt('ID del peer:', '1'));
                if (!peerId) return;
                
                log(`Conectando con User #${peerId}...`, 'info');
                
                await p2pClient.connectToPeer(peerId);
                
                log(`✅ Conexión establecida con User #${peerId}`, 'success');
                document.getElementById('peers-count').textContent = p2pClient.peers.size;
                
            } catch (error) {
                log('❌ Error conectando: ' + error.message, 'error');
                console.error(error);
            }
        }

        // Initial log
        log('Sistema P2P listo. Haz click en "Inicializar P2P" para comenzar.', 'info');
        updateStatusUI(false);
    </script>
</body>
</html>
