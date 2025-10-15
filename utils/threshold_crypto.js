/**
 * Threshold Cryptography System
 * Implementa Shamir's Secret Sharing para dividir claves privadas
 * 
 * Configuración: 3 de 5 fragmentos requeridos
 * - Fragment 1: Cliente (IndexedDB)
 * - Fragment 2: Servidor (Base de datos encriptada)
 * - Fragment 3: Dispositivo secundario
 * - Fragment 4: Backup offline
 * - Fragment 5: HSM/Cold storage
 */

const secrets = require('secrets.js-grempe');
const crypto = require('crypto');

class ThresholdCrypto {
    constructor() {
        // Configuración: requiere 3 de 5 shares para reconstruir
        this.threshold = 3;
        this.totalShares = 5;
    }

    /**
     * Divide una clave privada en múltiples fragmentos
     * @param {string} privateKey - Clave privada en formato hex (sin 0x)
     * @returns {Array<string>} Array de fragmentos (shares)
     */
    splitPrivateKey(privateKey) {
        // Remover 0x si existe
        const cleanKey = privateKey.replace(/^0x/, '');
        
        // Validar longitud de clave (64 caracteres hex = 32 bytes)
        if (cleanKey.length !== 64) {
            throw new Error('Invalid private key length');
        }

        // Generar shares usando Shamir's Secret Sharing
        const shares = secrets.share(cleanKey, this.totalShares, this.threshold);
        
        return shares;
    }

    /**
     * Reconstruye una clave privada desde los fragmentos
     * @param {Array<string>} shares - Mínimo 3 fragmentos
     * @returns {string} Clave privada reconstruida (con 0x)
     */
    combineShares(shares) {
        if (shares.length < this.threshold) {
            throw new Error(`Need at least ${this.threshold} shares to reconstruct`);
        }

        // Reconstruir la clave
        const reconstructed = secrets.combine(shares.slice(0, this.threshold));
        
        // Añadir 0x prefix
        return '0x' + reconstructed;
    }

    /**
     * Encripta un fragmento con AES-256-GCM
     * @param {string} share - Fragmento a encriptar
     * @param {string} password - Contraseña para encriptación
     * @returns {Object} {encrypted, iv, authTag}
     */
    encryptShare(share, password) {
        // Generar key de 256 bits desde password
        const key = crypto.scryptSync(password, 'salt', 32);
        
        // IV aleatorio
        const iv = crypto.randomBytes(16);
        
        // Crear cipher
        const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
        
        // Encriptar
        let encrypted = cipher.update(share, 'utf8', 'hex');
        encrypted += cipher.final('hex');
        
        // Auth tag para integridad
        const authTag = cipher.getAuthTag();
        
        return {
            encrypted,
            iv: iv.toString('hex'),
            authTag: authTag.toString('hex')
        };
    }

    /**
     * Desencripta un fragmento
     * @param {Object} encryptedData - {encrypted, iv, authTag}
     * @param {string} password - Contraseña para desencriptación
     * @returns {string} Fragmento desencriptado
     */
    decryptShare(encryptedData, password) {
        const { encrypted, iv, authTag } = encryptedData;
        
        // Generar key
        const key = crypto.scryptSync(password, 'salt', 32);
        
        // Crear decipher
        const decipher = crypto.createDecipheriv(
            'aes-256-gcm',
            key,
            Buffer.from(iv, 'hex')
        );
        
        // Set auth tag
        decipher.setAuthTag(Buffer.from(authTag, 'hex'));
        
        // Desencriptar
        let decrypted = decipher.update(encrypted, 'hex', 'utf8');
        decrypted += decipher.final('utf8');
        
        return decrypted;
    }

    /**
     * Genera metadata para los fragmentos
     * @param {number} userId - ID del usuario
     * @param {Array<string>} shares - Fragmentos generados
     * @returns {Array<Object>} Metadata de cada fragmento
     */
    generateShareMetadata(userId, shares) {
        return shares.map((share, index) => ({
            userId,
            shareIndex: index + 1,
            shareHash: crypto.createHash('sha256').update(share).digest('hex'),
            location: this.getShareLocation(index + 1),
            createdAt: new Date().toISOString()
        }));
    }

    /**
     * Determina dónde se debe almacenar cada fragmento
     * @param {number} shareIndex - Índice del fragmento (1-5)
     * @returns {string} Ubicación sugerida
     */
    getShareLocation(shareIndex) {
        const locations = {
            1: 'client_indexeddb',
            2: 'server_database',
            3: 'secondary_device',
            4: 'offline_backup',
            5: 'hsm_cold_storage'
        };
        return locations[shareIndex] || 'unknown';
    }

    /**
     * Valida que un conjunto de fragmentos sea suficiente
     * @param {Array<string>} shares - Fragmentos disponibles
     * @returns {boolean} True si son suficientes
     */
    validateShareCount(shares) {
        return Array.isArray(shares) && shares.length >= this.threshold;
    }

    /**
     * Genera un ID único para el set de fragmentos
     * @param {number} userId - ID del usuario
     * @returns {string} ID único del set
     */
    generateShareSetId(userId) {
        const timestamp = Date.now();
        const random = crypto.randomBytes(8).toString('hex');
        return `shares_${userId}_${timestamp}_${random}`;
    }
}

module.exports = ThresholdCrypto;
