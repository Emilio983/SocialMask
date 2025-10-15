/**
 * ============================================
 * FILE SEARCH CLIENT
 * ============================================
 * Cliente de búsqueda que usa el índice PHP
 * y obtiene metadatos completos desde P2P
 */

class FileSearchClient {
    constructor(cryptoClient) {
        this.cryptoClient = cryptoClient;
        this.cache = new Map(); // Cache de resultados
    }

    /**
     * Buscar archivos
     */
    async search(options = {}) {
        const {
            query = '',
            type = '',
            user = null,
            limit = 20,
            offset = 0,
            sort = 'created_at',
            order = 'DESC'
        } = options;

        try {
            // 1. Buscar en índice PHP (solo metadatos públicos)
            const params = new URLSearchParams({
                q: query,
                type,
                limit,
                offset,
                sort,
                order
            });

            if (user) {
                params.append('user', user);
            }

            const response = await fetch(`/api/file-search.php?${params}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            // 2. Para cada archivo, obtener metadatos completos desde P2P (si se necesita)
            // Por ahora retornamos solo los resultados del índice
            // El usuario puede llamar a getFileWithMetadata() para obtener detalles completos

            return {
                success: true,
                files: result.files,
                pagination: result.pagination
            };

        } catch (error) {
            console.error('Search error:', error);
            throw error;
        }
    }

    /**
     * Obtener archivo con metadatos completos
     */
    async getFileWithMetadata(cid) {
        try {
            // 1. Obtener info básica desde índice PHP
            const infoResponse = await fetch(`/api/file-info.php?cid=${cid}`);
            const infoResult = await infoResponse.json();

            if (!infoResult.success) {
                throw new Error(infoResult.message);
            }

            // 2. Obtener metadatos completos desde P2P (incluye IV, wrapped keys)
            const p2pMetadata = await this.cryptoClient.p2pMetadata.getMetadata(cid, true);

            // 3. Combinar ambos
            return {
                success: true,
                file: {
                    ...infoResult.file,
                    // Metadatos P2P (cifrado)
                    iv: p2pMetadata.iv,
                    senderPub: p2pMetadata.senderPub,
                    wrappedKey: p2pMetadata.wrappedKeys[this.cryptoClient.userId],
                    timestamp: p2pMetadata.ts,
                    signature: p2pMetadata.signature
                },
                access_list: infoResult.access_list,
                ipfs_gateway: infoResult.ipfs_gateway
            };

        } catch (error) {
            console.error('Error getting file with metadata:', error);
            throw error;
        }
    }

    /**
     * Buscar por tipo de archivo
     */
    async searchByType(fileType, options = {}) {
        return this.search({
            ...options,
            type: fileType
        });
    }

    /**
     * Buscar archivos de un usuario
     */
    async searchByUser(userId, options = {}) {
        return this.search({
            ...options,
            user: userId
        });
    }

    /**
     * Buscar imágenes
     */
    async searchImages(options = {}) {
        return this.searchByType('image/', options);
    }

    /**
     * Buscar videos
     */
    async searchVideos(options = {}) {
        return this.searchByType('video/', options);
    }

    /**
     * Buscar documentos
     */
    async searchDocuments(options = {}) {
        const types = ['application/pdf', 'application/msword', 'application/vnd'];
        const results = await Promise.all(
            types.map(type => this.searchByType(type, { ...options, limit: 10 }))
        );

        // Combinar resultados
        const allFiles = results.flatMap(r => r.files);
        const uniqueFiles = Array.from(new Map(allFiles.map(f => [f.cid, f])).values());

        return {
            success: true,
            files: uniqueFiles.slice(0, options.limit || 20),
            pagination: {
                total: uniqueFiles.length,
                limit: options.limit || 20,
                offset: options.offset || 0,
                has_more: false
            }
        };
    }

    /**
     * Obtener archivos recientes
     */
    async getRecent(limit = 20) {
        return this.search({
            sort: 'created_at',
            order: 'DESC',
            limit
        });
    }

    /**
     * Obtener archivos enviados por el usuario actual
     */
    async getMySentFiles(limit = 20, offset = 0) {
        return this.searchByUser(this.cryptoClient.userId, { limit, offset });
    }

    /**
     * Buscar con autocompletado
     */
    async autocomplete(query, limit = 5) {
        if (query.length < 2) {
            return { success: true, suggestions: [] };
        }

        const results = await this.search({ query, limit });
        
        return {
            success: true,
            suggestions: results.files.map(f => ({
                cid: f.cid,
                file_name: f.file_name,
                file_type: f.file_type
            }))
        };
    }

    /**
     * Obtener estadísticas de archivos
     */
    async getStats() {
        try {
            // Buscar todos los archivos accesibles (solo primeros 1000 para stats)
            const result = await this.search({ limit: 1000 });

            const files = result.files;
            const total = files.length;

            // Agrupar por tipo
            const byType = files.reduce((acc, file) => {
                const category = this.getFileCategory(file.file_type);
                acc[category] = (acc[category] || 0) + 1;
                return acc;
            }, {});

            // Total size
            const totalSize = files.reduce((sum, f) => sum + f.file_size, 0);

            // Archivos con thumbnail
            const withThumbnails = files.filter(f => f.has_thumbnail).length;

            return {
                success: true,
                stats: {
                    total,
                    by_type: byType,
                    total_size: totalSize,
                    with_thumbnails: withThumbnails,
                    avg_size: total > 0 ? Math.round(totalSize / total) : 0
                }
            };

        } catch (error) {
            console.error('Error getting stats:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }

    /**
     * Obtener categoría de archivo
     */
    getFileCategory(mimeType) {
        if (mimeType.startsWith('image/')) return 'images';
        if (mimeType.startsWith('video/')) return 'videos';
        if (mimeType.startsWith('audio/')) return 'audio';
        if (mimeType.includes('pdf')) return 'pdfs';
        if (mimeType.includes('word') || mimeType.includes('document')) return 'documents';
        if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'spreadsheets';
        if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'archives';
        return 'other';
    }

    /**
     * Limpiar cache
     */
    clearCache() {
        this.cache.clear();
    }
}

// Hacer disponible globalmente
window.FileSearchClient = FileSearchClient;
