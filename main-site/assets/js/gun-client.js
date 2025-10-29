/**
 * ============================================
 * GUN.JS CLIENT - P2P Database
 * ============================================
 * Cliente para manejar la base de datos P2P Gun.js
 */

const gunClient = {
    gun: null,
    user: null,
    isConnected: false,
    peers: [],
    
    /**
     * Inicializar Gun.js
     */
    async init() {
        try {
            console.log('🔧 Inicializando Gun.js...');
            
            // Configurar peers
            const localPeer = window.location.origin + '/gun';
            
            // Inicializar Gun con peers públicos
            this.gun = Gun({
                peers: [
                    localPeer,
                    'https://gun-manhattan.herokuapp.com/gun',
                    'https://gun-us.herokuapp.com/gun'
                ],
                localStorage: true,
                radisk: true,
                multicast: false
            });
            
            this.isConnected = true;
            console.log('✅ Gun.js inicializado correctamente');
            
            // Verificar conexión con peers
            this.checkPeers();
            
            return true;
        } catch (error) {
            console.error('❌ Error inicializando Gun.js:', error);
            this.isConnected = false;
            return false;
        }
    },
    
    /**
     * Verificar conexión con peers
     */
    checkPeers() {
        if (!this.gun) return;
        
        this.gun.on('hi', (peer) => {
            console.log('🤝 Conectado a peer:', peer);
            this.peers.push(peer);
        });
        
        this.gun.on('bye', (peer) => {
            console.log('👋 Desconectado de peer:', peer);
            this.peers = this.peers.filter(p => p !== peer);
        });
    },
    
    /**
     * Obtener nodo de Gun
     */
    getNode(path) {
        if (!this.gun) {
            console.error('Gun.js no está inicializado');
            return null;
        }
        return this.gun.get(path);
    },
    
    /**
     * Guardar datos en Gun
     */
    async put(path, data) {
        return new Promise((resolve, reject) => {
            if (!this.gun) {
                reject(new Error('Gun.js no está inicializado'));
                return;
            }
            
            this.gun.get(path).put(data, (ack) => {
                if (ack.err) {
                    console.error('Error guardando en Gun:', ack.err);
                    reject(ack.err);
                } else {
                    console.log('✅ Datos guardados en Gun:', path);
                    resolve(ack);
                }
            });
        });
    },
    
    /**
     * Obtener datos de Gun
     */
    async get(path) {
        return new Promise((resolve) => {
            if (!this.gun) {
                resolve(null);
                return;
            }
            
            this.gun.get(path).once((data) => {
                resolve(data);
            });
        });
    },
    
    /**
     * Escuchar cambios en tiempo real
     */
    on(path, callback) {
        if (!this.gun) {
            console.error('Gun.js no está inicializado');
            return;
        }
        
        this.gun.get(path).on(callback);
    },
    
    /**
     * Publicar post P2P
     */
    async publishPost(postData) {
        try {
            const timestamp = Date.now();
            const postId = `post_${timestamp}_${Math.random().toString(36).substr(2, 9)}`;
            
            const post = {
                id: postId,
                content: postData.content,
                author: postData.author,
                timestamp: timestamp,
                likes: 0,
                comments: []
            };
            
            // Guardar en Gun.js
            await this.put(`posts/${postId}`, post);
            
            // Añadir a índice de posts
            const node = this.gun.get('posts/index');
            node.set(this.gun.get(`posts/${postId}`));
            
            console.log('✅ Post publicado en P2P:', postId);
            return { success: true, postId, post };
        } catch (error) {
            console.error('❌ Error publicando post P2P:', error);
            return { success: false, error: error.message };
        }
    },
    
    /**
     * Obtener posts P2P
     */
    async getPosts(limit = 50) {
        return new Promise((resolve) => {
            if (!this.gun) {
                resolve([]);
                return;
            }
            
            const posts = [];
            
            this.gun.get('posts/index').map().once((post, id) => {
                if (post && !post._) {
                    posts.push({ id, ...post });
                }
                
                if (posts.length >= limit) {
                    resolve(posts.sort((a, b) => b.timestamp - a.timestamp));
                }
            });
            
            // Timeout de 3 segundos
            setTimeout(() => {
                resolve(posts.sort((a, b) => b.timestamp - a.timestamp));
            }, 3000);
        });
    },
    
    /**
     * Dar like a un post
     */
    async likePost(postId) {
        try {
            const post = await this.get(`posts/${postId}`);
            if (!post) {
                throw new Error('Post no encontrado');
            }
            
            await this.put(`posts/${postId}/likes`, (post.likes || 0) + 1);
            
            console.log('✅ Like añadido al post:', postId);
            return { success: true };
        } catch (error) {
            console.error('❌ Error dando like:', error);
            return { success: false, error: error.message };
        }
    },
    
    /**
     * Comentar en un post
     */
    async commentPost(postId, comment) {
        try {
            const timestamp = Date.now();
            const commentId = `comment_${timestamp}_${Math.random().toString(36).substr(2, 9)}`;
            
            const commentData = {
                id: commentId,
                content: comment.content,
                author: comment.author,
                timestamp: timestamp
            };
            
            // Guardar comentario
            await this.put(`posts/${postId}/comments/${commentId}`, commentData);
            
            // Añadir a índice de comentarios
            const node = this.gun.get(`posts/${postId}/comments/index`);
            node.set(this.gun.get(`posts/${postId}/comments/${commentId}`));
            
            console.log('✅ Comentario añadido:', commentId);
            return { success: true, commentId, comment: commentData };
        } catch (error) {
            console.error('❌ Error comentando:', error);
            return { success: false, error: error.message };
        }
    },
    
    /**
     * Desconectar de Gun.js
     */
    disconnect() {
        if (this.gun) {
            // Gun.js no tiene método disconnect directo
            // Simplemente marcamos como desconectado
            this.isConnected = false;
            console.log('🔌 Desconectado de Gun.js');
        }
    },
    
    /**
     * Obtener estadísticas
     */
    getStats() {
        return {
            connected: this.isConnected,
            peers: this.peers.length,
            peersConnected: this.peers
        };
    }
};

// Auto-exportar
if (typeof window !== 'undefined') {
    window.gunClient = gunClient;
}
