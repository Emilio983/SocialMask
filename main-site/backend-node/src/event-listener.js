/**
 * Event Listener para Donaciones - FASE 3.4
 * 
 * Escucha eventos del smart contract Donations y actualiza la base de datos
 * automáticamente cuando se confirman donaciones on-chain.
 * 
 * @author thesocialmask Team
 * @date October 8, 2025
 */

import { ethers } from 'ethers';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

// Cargar variables de entorno
dotenv.config();

// ============================================
// CONFIGURACIÓN
// ============================================

// URLs de RPC (WebSocket para eventos en tiempo real)
const POLYGON_WSS = process.env.POLYGON_RPC_WSS_URL || 'wss://polygon-mainnet.g.alchemy.com/v2/YOUR_KEY';
const AMOY_WSS = process.env.AMOY_RPC_WSS_URL || 'wss://polygon-amoy.g.alchemy.com/v2/YOUR_KEY';

// Usar red correcta según configuración
const NETWORK = process.env.NETWORK || 'polygon';
const RPC_WSS_URL = NETWORK === 'amoy' ? AMOY_WSS : POLYGON_WSS;

// Dirección del contrato Donations
const DONATIONS_ADDRESS = process.env.DONATION_CONTRACT_ADDRESS || '0x0000000000000000000000000000000000000000';

// Verificar configuración
if (DONATIONS_ADDRESS === '0x0000000000000000000000000000000000000000') {
    console.error('❌ ERROR: DONATION_CONTRACT_ADDRESS not configured in .env');
    console.error('   Please deploy the contract first (FASE 3.1) and update .env');
    process.exit(1);
}

// ABI del contrato (solo los eventos que necesitamos)
const DONATIONS_ABI = [
    "event DonationSent(uint256 indexed donationId, address indexed donor, address indexed recipient, address token, uint256 amount, uint256 fee, uint256 netAmount, uint256 timestamp, bool isAnonymous)"
];

// Configuración de la base de datos
const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'thesocialmask',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    enableKeepAlive: true,
    keepAliveInitialDelay: 0
};

// ============================================
// INICIALIZACIÓN
// ============================================

console.log('🎧 Donations Event Listener - FASE 3.4');
console.log('=' .repeat(60));
console.log('Network:', NETWORK);
console.log('Contract:', DONATIONS_ADDRESS);
console.log('Database:', DB_CONFIG.database);
console.log('=' .repeat(60));

// Crear provider WebSocket
let provider;
let contract;
let dbPool;
let reconnectAttempts = 0;
const MAX_RECONNECT_ATTEMPTS = 5;

// ============================================
// FUNCIONES DE BASE DE DATOS
// ============================================

/**
 * Conectar a la base de datos
 */
async function connectDatabase() {
    try {
        dbPool = mysql.createPool(DB_CONFIG);
        
        // Test connection
        const conn = await dbPool.getConnection();
        await conn.ping();
        conn.release();
        
        console.log('✅ Database connected successfully');
        return true;
    } catch (error) {
        console.error('❌ Database connection error:', error.message);
        return false;
    }
}

/**
 * Actualizar donación en la base de datos
 * @param {Object} donationData - Datos de la donación
 */
async function updateDonation(donationData) {
    const conn = await dbPool.getConnection();
    
    try {
        await conn.beginTransaction();
        
        const {
            donationId,
            donor,
            recipient,
            token,
            amount,
            fee,
            netAmount,
            timestamp,
            txHash,
            isAnonymous
        } = donationData;
        
        // 1. Buscar la donación pendiente por tx_hash
        const [existingDonations] = await conn.execute(
            'SELECT id, campaign_id, amount FROM donations WHERE tx_hash = ? LIMIT 1',
            [txHash]
        );
        
        if (existingDonations.length > 0) {
            // Actualizar donación existente
            const donation = existingDonations[0];
            
            await conn.execute(`
                UPDATE donations 
                SET status = 'confirmed',
                    confirmed_at = FROM_UNIXTIME(?),
                    donor_address = ?
                WHERE id = ?
            `, [timestamp, donor, donation.id]);
            
            console.log(`   ✓ Donation #${donation.id} confirmed`);
            
            // 2. Actualizar raised_amount de la campaña (usando el trigger automáticamente)
            // El trigger update_campaign_raised_amount se encargará de esto
            
        } else {
            // Si no existe, crear nueva donación (caso raro, pero posible)
            console.log('   ⚠️  Donation not found in pending, creating new record...');
            
            // Buscar campaña por recipient (esto es una simplificación)
            const [campaigns] = await conn.execute(
                'SELECT id FROM donation_campaigns WHERE user_id = (SELECT id FROM users WHERE wallet_address = ?) AND status = "active" ORDER BY created_at DESC LIMIT 1',
                [recipient]
            );
            
            if (campaigns.length > 0) {
                const campaignId = campaigns[0].id;
                const amountEther = parseFloat(ethers.formatEther(amount));
                
                await conn.execute(`
                    INSERT INTO donations 
                    (campaign_id, donor_address, amount, tx_hash, status, confirmed_at, created_at)
                    VALUES (?, ?, ?, ?, 'confirmed', FROM_UNIXTIME(?), FROM_UNIXTIME(?))
                `, [campaignId, donor, amountEther, txHash, timestamp, timestamp]);
                
                console.log(`   ✓ New donation created for campaign #${campaignId}`);
            }
        }
        
        await conn.commit();
        
    } catch (error) {
        await conn.rollback();
        console.error('   ❌ Database update error:', error.message);
        throw error;
    } finally {
        conn.release();
    }
}

/**
 * Obtener estadísticas de donaciones
 */
async function getStats() {
    try {
        const conn = await dbPool.getConnection();
        
        const [results] = await conn.execute(`
            SELECT 
                COUNT(*) as total_donations,
                COUNT(DISTINCT campaign_id) as active_campaigns,
                SUM(amount) as total_amount,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_donations,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_donations
            FROM donations
        `);
        
        conn.release();
        
        return results[0];
    } catch (error) {
        console.error('Error getting stats:', error.message);
        return null;
    }
}

// ============================================
// EVENT HANDLERS
// ============================================

/**
 * Handler para evento DonationSent
 */
async function handleDonationSent(donationId, donor, recipient, token, amount, fee, netAmount, timestamp, isAnonymous, event) {
    console.log('\n🎁 New Donation Event Detected!');
    console.log('─'.repeat(60));
    console.log('Donation ID:', donationId.toString());
    console.log('Donor:', donor);
    console.log('Recipient:', recipient);
    console.log('Token:', token);
    console.log('Amount:', ethers.formatEther(amount), 'tokens');
    console.log('Fee:', ethers.formatEther(fee), 'tokens');
    console.log('Net Amount:', ethers.formatEther(netAmount), 'tokens');
    console.log('Anonymous:', isAnonymous);
    console.log('TX Hash:', event.log.transactionHash);
    console.log('Block:', event.log.blockNumber);
    console.log('Timestamp:', new Date(Number(timestamp) * 1000).toISOString());
    console.log('─'.repeat(60));
    
    try {
        // Actualizar base de datos
        await updateDonation({
            donationId: donationId.toString(),
            donor,
            recipient,
            token,
            amount,
            fee,
            netAmount,
            timestamp: Number(timestamp),
            txHash: event.log.transactionHash,
            isAnonymous
        });
        
        console.log('✅ Database updated successfully\n');
        
        // Mostrar estadísticas
        const stats = await getStats();
        if (stats) {
            console.log('📊 Current Stats:');
            console.log('   Total Donations:', stats.total_donations);
            console.log('   Confirmed:', stats.confirmed_donations);
            console.log('   Pending:', stats.pending_donations);
            console.log('   Total Amount:', parseFloat(stats.total_amount || 0).toFixed(2), 'SPHE');
            console.log('');
        }
        
    } catch (error) {
        console.error('❌ Error processing donation:', error.message);
    }
}

// ============================================
// PROVIDER & CONTRACT SETUP
// ============================================

/**
 * Inicializar provider y contrato
 */
async function initializeProvider() {
    try {
        console.log('🔌 Connecting to blockchain...');
        
        provider = new ethers.WebSocketProvider(RPC_WSS_URL);
        
        // Verificar conexión
        await provider.getNetwork();
        
        console.log('✅ Blockchain connected');
        
        // Crear instancia del contrato
        contract = new ethers.Contract(DONATIONS_ADDRESS, DONATIONS_ABI, provider);
        
        console.log('✅ Contract instance created');
        console.log('🎧 Listening for events...\n');
        
        // Registrar listener para DonationSent
        contract.on('DonationSent', handleDonationSent);
        
        reconnectAttempts = 0;
        
        return true;
        
    } catch (error) {
        console.error('❌ Provider initialization error:', error.message);
        return false;
    }
}

/**
 * Limpiar listeners y cerrar conexiones
 */
async function cleanup() {
    console.log('\n🧹 Cleaning up...');
    
    if (contract) {
        contract.removeAllListeners();
    }
    
    if (provider) {
        provider.destroy();
    }
    
    if (dbPool) {
        await dbPool.end();
    }
    
    console.log('✅ Cleanup complete');
}

/**
 * Reconectar después de error
 */
async function reconnect() {
    if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
        console.error(`❌ Max reconnection attempts (${MAX_RECONNECT_ATTEMPTS}) reached`);
        console.error('   Please check your configuration and restart manually');
        process.exit(1);
    }
    
    reconnectAttempts++;
    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000); // Exponential backoff, max 30s
    
    console.log(`🔄 Attempting to reconnect (${reconnectAttempts}/${MAX_RECONNECT_ATTEMPTS})...`);
    console.log(`   Waiting ${delay}ms before retry`);
    
    await new Promise(resolve => setTimeout(resolve, delay));
    
    await cleanup();
    await initialize();
}

// ============================================
// MAIN INITIALIZATION
// ============================================

/**
 * Función principal de inicialización
 */
async function initialize() {
    try {
        // 1. Conectar a base de datos
        const dbConnected = await connectDatabase();
        if (!dbConnected) {
            throw new Error('Database connection failed');
        }
        
        // 2. Inicializar provider
        const providerInitialized = await initializeProvider();
        if (!providerInitialized) {
            throw new Error('Provider initialization failed');
        }
        
        // 3. Setup event handlers
        setupErrorHandlers();
        
        console.log('✅ Event Listener fully initialized and running!');
        console.log('📡 Waiting for donation events...\n');
        
    } catch (error) {
        console.error('❌ Initialization failed:', error.message);
        await reconnect();
    }
}

/**
 * Configurar handlers de error
 */
function setupErrorHandlers() {
    // Provider errors
    provider.on('error', (error) => {
        console.error('\n❌ Provider error:', error.message);
        reconnect();
    });
    
    // Provider close
    provider.on('close', () => {
        console.log('\n⚠️  Provider connection closed');
        reconnect();
    });
    
    // Process errors
    process.on('uncaughtException', (error) => {
        console.error('\n❌ Uncaught exception:', error);
        cleanup().then(() => process.exit(1));
    });
    
    process.on('unhandledRejection', (reason, promise) => {
        console.error('\n❌ Unhandled rejection at:', promise, 'reason:', reason);
        cleanup().then(() => process.exit(1));
    });
    
    // Graceful shutdown
    process.on('SIGINT', async () => {
        console.log('\n⏹️  Received SIGINT, shutting down gracefully...');
        await cleanup();
        process.exit(0);
    });
    
    process.on('SIGTERM', async () => {
        console.log('\n⏹️  Received SIGTERM, shutting down gracefully...');
        await cleanup();
        process.exit(0);
    });
}

// ============================================
// START APPLICATION
// ============================================

console.log('🚀 Starting Donations Event Listener...\n');
initialize();
