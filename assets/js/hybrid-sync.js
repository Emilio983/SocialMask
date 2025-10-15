/**
 * ============================================
 * HYBRID SYNC - MySQL + Gun.js P2P
 * ============================================
 * Sistema híbrido que sincroniza datos entre MySQL (centralizado) y Gun.js (P2P)
 */

const hybridSync = {
    enabled: false,
    syncInterval: null,
    lastSync: null,
    
    /**
     * Inicializar sistema híbrido
     */
    async init() {
        try {
            console.log('🔄 Inicializando Hybrid Sync...');
            
            const p2pMode = localStorage.getItem('p2pMode') === 'true';
            this.enabled = true;
            
            // Si P2P está activo, activar sincronización automática
            if (p2pMode && gunClient && gunClient.isConnected) {
                this.startAutoSync();
            }
            
            // Escuchar cambios en modo P2P
            window.addEventListener('p2pModeChanged', (event) => {
                if (event.detail.enabled && gunClient && gunClient.isConnected) {
                    this.startAutoSync();
                } else {
                    this.stopAutoSync();
                }
            });
            
            console.log('✅ Hybrid Sync inicializado');
            return true;
        } catch (error) {
            console.error('❌ Error inicializando Hybrid Sync:', error);
            return false;
        }
    },
    
    /**
     * Iniciar sincronización automática
     */
    startAutoSync() {
        console.log('⚡ Iniciando auto-sincronización...');
        
        // Sincronizar cada 30 segundos
        this.syncInterval = setInterval(() => {
            this.syncData();
        }, 30000);
        
        // Primera sincronización inmediata
        this.syncData();
    },
    
    /**
     * Detener sincronización automática
     */
    stopAutoSync() {
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
            this.syncInterval = null;
            console.log('⏸️ Auto-sincronización detenida');
        }
    },
    
    /**
     * Sincronizar datos entre MySQL y Gun.js
     */
    async syncData() {
        try {
            console.log('🔄 Sincronizando datos...');
            
            const p2pMode = localStorage.getItem('p2pMode') === 'true';
            
            if (p2pMode && gunClient && gunClient.isConnected) {
                // Modo P2P: priorizar Gun.js
                await this.syncFromGunToMySQL();
            } else {
                // Modo centralizado: priorizar MySQL
                await this.syncFromMySQLToGun();
            }
            
            this.lastSync = new Date();
            console.log('✅ Sincronización completada');
        } catch (error) {
            console.error('❌ Error sincronizando:', error);
        }
    },
    
    /**
     * Sincronizar de Gun.js a MySQL
     */
    async syncFromGunToMySQL() {
        try {
            // Obtener posts de Gun.js
            const p2pPosts = await gunClient.getPosts(50);
            
            if (p2pPosts.length === 0) {
                console.log('ℹ️ No hay posts P2P para sincronizar');
                return;
            }
            
            console.log(`📥 Sincronizando ${p2pPosts.length} posts de Gun.js a MySQL...`);
            
            // Enviar a backend para guardar en MySQL
            for (const post of p2pPosts) {
                try {
                    const response = await fetch('/backend-node/api/p2p/metadata/store', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            cid: post.id,
                            iv: 'auto-generated',
                            senderPub: post.author || 'anonymous',
                            senderId: parseInt(post.author) || 0,
                            recipients: [0], // Público
                            wrappedKeys: {},
                            ts: post.timestamp,
                            meta: {
                                content: post.content,
                                likes: post.likes,
                                source: 'gun.js'
                            }
                        })
                    });
                    
                    if (response.ok) {
                        console.log('✅ Post sincronizado:', post.id);
                    }
                } catch (error) {
                    console.error('Error sincronizando post:', error);
                }
            }
        } catch (error) {
            console.error('Error en syncFromGunToMySQL:', error);
        }
    },
    
    /**
     * Sincronizar de MySQL a Gun.js
     */
    async syncFromMySQLToGun() {
        try {
            if (!gunClient || !gunClient.isConnected) {
                return;
            }
            
            console.log('📤 Sincronizando de MySQL a Gun.js...');
            
            // Obtener posts recientes de MySQL via backend
            const response = await fetch('/backend-node/api/p2p/metadata/stats');
            
            if (!response.ok) {
                return;
            }
            
            const data = await response.json();
            console.log('ℹ️ Estadísticas P2P:', data.stats);
            
        } catch (error) {
            console.error('Error en syncFromMySQLToGun:', error);
        }
    },
    
    /**
     * Publicar contenido en modo híbrido
     */
    async publishContent(content, author) {
        try {
            const p2pMode = localStorage.getItem('p2pMode') === 'true';
            
            if (p2pMode && gunClient && gunClient.isConnected) {
                // Publicar en Gun.js primero
                const result = await gunClient.publishPost({ content, author });
                
                if (result.success) {
                    // Sincronizar a MySQL
                    await this.syncFromGunToMySQL();
                    return result;
                }
            } else {
                // Publicar directamente en MySQL
                console.log('📝 Publicando en modo centralizado...');
                // Aquí iría la lógica de publicación en MySQL
            }
        } catch (error) {
            console.error('Error publicando contenido:', error);
            return { success: false, error: error.message };
        }
    },
    
    /**
     * Obtener estadísticas de sincronización
     */
    getStats() {
        return {
            enabled: this.enabled,
            lastSync: this.lastSync,
            autoSync: this.syncInterval !== null,
            p2pMode: localStorage.getItem('p2pMode') === 'true',
            gunConnected: gunClient && gunClient.isConnected
        };
    }
};

// Auto-exportar
if (typeof window !== 'undefined') {
    window.hybridSync = hybridSync;
}
