/**
 * Frontend Threshold Cryptography Client
 * Maneja fragmentos de claves en el navegador con IndexedDB
 */

class ThresholdCryptoClient {
    constructor() {
        this.dbName = 'ThresholdSharesDB';
        this.dbVersion = 1;
        this.storeName = 'keyShares';
        this.db = null;
    }

    /**
     * Inicializa IndexedDB
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const objectStore = db.createObjectStore(this.storeName, { keyPath: 'id', autoIncrement: true });
                    objectStore.createIndex('userId', 'userId', { unique: false });
                    objectStore.createIndex('shareSetId', 'shareSetId', { unique: false });
                    objectStore.createIndex('shareType', 'shareType', { unique: false });
                }
            };
        });
    }

    /**
     * Guarda un fragmento en IndexedDB
     * @param {Object} shareData - {userId, shareSetId, shareType, share}
     */
    async saveShare(shareData) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const objectStore = transaction.objectStore(this.storeName);
            
            const data = {
                ...shareData,
                createdAt: new Date().toISOString()
            };

            const request = objectStore.add(data);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Obtiene fragmentos de un usuario
     * @param {number} userId - ID del usuario
     * @returns {Array} Array de fragmentos
     */
    async getShares(userId) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const objectStore = transaction.objectStore(this.storeName);
            const index = objectStore.index('userId');
            const request = index.getAll(userId);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Elimina fragmentos de un shareSetId específico
     * @param {string} shareSetId - ID del set de fragmentos
     */
    async deleteShareSet(shareSetId) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const objectStore = transaction.objectStore(this.storeName);
            const index = objectStore.index('shareSetId');
            const request = index.openCursor(IDBKeyRange.only(shareSetId));

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    cursor.delete();
                    cursor.continue();
                } else {
                    resolve();
                }
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Descarga un fragmento como archivo de backup
     * @param {string} share - Fragmento a descargar
     * @param {string} filename - Nombre del archivo
     */
    downloadShareBackup(share, filename = 'backup_share.txt') {
        const blob = new Blob([share], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /**
     * Lee un fragmento desde un archivo de backup
     * @param {File} file - Archivo con el fragmento
     * @returns {Promise<string>} Fragmento leído
     */
    async readShareBackup(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = (e) => reject(e);
            reader.readAsText(file);
        });
    }

    /**
     * Muestra un código QR para transferir un fragmento a otro dispositivo
     * @param {string} share - Fragmento a mostrar
     * @param {string} elementId - ID del elemento donde mostrar el QR
     */
    async displayQRForShare(share, elementId) {
        // Requiere librería qrcode.js
        if (typeof QRCode === 'undefined') {
            console.error('QRCode library not loaded');
            return;
        }

        const element = document.getElementById(elementId);
        if (!element) {
            console.error('Element not found:', elementId);
            return;
        }

        // Limpiar elemento anterior
        element.innerHTML = '';

        // Generar QR
        new QRCode(element, {
            text: share,
            width: 256,
            height: 256,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    /**
     * Exporta todos los fragmentos del usuario (para backup completo)
     * @param {number} userId - ID del usuario
     */
    async exportAllShares(userId) {
        const shares = await this.getShares(userId);
        const exportData = {
            userId,
            exportDate: new Date().toISOString(),
            shares: shares.map(s => ({
                shareSetId: s.shareSetId,
                shareType: s.shareType,
                share: s.share,
                createdAt: s.createdAt
            }))
        };

        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `shares_backup_${userId}_${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /**
     * Importa fragmentos desde un backup
     * @param {File} file - Archivo JSON con los fragmentos
     */
    async importShares(file) {
        const text = await this.readShareBackup(file);
        const data = JSON.parse(text);
        
        for (const share of data.shares) {
            await this.saveShare({
                userId: data.userId,
                shareSetId: share.shareSetId,
                shareType: share.shareType,
                share: share.share
            });
        }

        return data.shares.length;
    }
}

// Instancia global
window.thresholdCrypto = new ThresholdCryptoClient();

// Auto-inicializar
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await window.thresholdCrypto.init();
        console.log('✅ Threshold Crypto Client initialized');
    } catch (error) {
        console.error('❌ Failed to initialize Threshold Crypto:', error);
    }
});
