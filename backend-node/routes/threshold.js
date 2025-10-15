/**
 * API Endpoint para gestión de fragmentos de claves (Threshold Crypto)
 * Maneja almacenamiento y recuperación segura de shares
 */

const express = require('express');
const router = express.Router();
const ThresholdCrypto = require('../../utils/threshold_crypto');

const thresholdCrypto = new ThresholdCrypto();

// TODO: Implementar conexión a base de datos MySQL
// const { pool } = require('../config/database');

/**
 * POST /api/threshold/create
 * Crea y almacena fragmentos de una clave privada
 */
router.post('/create', async (req, res) => {
    try {
        const { userId, privateKey, password } = req.body;

        // Validaciones
        if (!userId || !privateKey || !password) {
            return res.status(400).json({
                success: false,
                message: 'Missing required fields'
            });
        }

        // Dividir la clave en fragmentos
        const shares = thresholdCrypto.splitPrivateKey(privateKey);
        
        // Generar ID único para este set de fragmentos
        const shareSetId = thresholdCrypto.generateShareSetId(userId);
        
        // Encriptar el fragmento que se guardará en servidor (share #2)
        const serverShare = shares[1];
        const encryptedServerShare = thresholdCrypto.encryptShare(serverShare, password);
        
        // Generar metadata
        const metadata = thresholdCrypto.generateShareMetadata(userId, shares);

        // TODO: Guardar en base de datos cuando esté configurado
        console.log('Share set created:', shareSetId);

        // Retornar fragmentos para el cliente (shares #1, #3, #4, #5)
        // El cliente decidirá dónde almacenar cada uno
        res.json({
            success: true,
            shareSetId,
            shares: {
                client: shares[0],          // Para IndexedDB
                secondary: shares[2],       // Para dispositivo secundario
                backup: shares[3],          // Para backup offline
                cold: shares[4]             // Para cold storage
            },
            serverShare: encryptedServerShare, // Temporal hasta configurar DB
            metadata: {
                threshold: thresholdCrypto.threshold,
                totalShares: thresholdCrypto.totalShares,
                locations: metadata.map(m => ({
                    index: m.shareIndex,
                    location: m.location
                }))
            }
        });

    } catch (error) {
        console.error('Threshold create error:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to create key shares',
            error: error.message
        });
    }
});

/**
 * POST /api/threshold/reconstruct
 * Reconstruye una clave privada desde los fragmentos
 */
router.post('/reconstruct', async (req, res) => {
    try {
        const { clientShares } = req.body;

        // Validaciones
        if (!Array.isArray(clientShares)) {
            return res.status(400).json({
                success: false,
                message: 'clientShares must be an array'
            });
        }

        // Validar que tengamos suficientes fragmentos
        if (!thresholdCrypto.validateShareCount(clientShares)) {
            return res.status(400).json({
                success: false,
                message: `Need at least ${thresholdCrypto.threshold} shares`
            });
        }

        // Reconstruir la clave privada
        const privateKey = thresholdCrypto.combineShares(clientShares);

        // IMPORTANTE: La clave privada NUNCA se guarda en logs
        // Solo se retorna al cliente para uso inmediato
        res.json({
            success: true,
            privateKey
        });

    } catch (error) {
        console.error('Threshold reconstruct error:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to reconstruct key',
            error: error.message
        });
    }
});

/**
 * POST /api/threshold/decrypt-share
 * Desencripta un fragmento con password
 */
router.post('/decrypt-share', async (req, res) => {
    try {
        const { encryptedShare, password } = req.body;

        if (!encryptedShare || !password) {
            return res.status(400).json({
                success: false,
                message: 'Missing required fields'
            });
        }

        const decrypted = thresholdCrypto.decryptShare(encryptedShare, password);

        res.json({
            success: true,
            share: decrypted
        });

    } catch (error) {
        console.error('Decrypt share error:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to decrypt share',
            error: error.message
        });
    }
});

module.exports = router;
