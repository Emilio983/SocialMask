/**
 * Test Event Listener Connection - FASE 3.4
 * 
 * Verifica que todas las conexiones estén funcionando antes de ejecutar el listener
 */

import { ethers } from 'ethers';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

console.log('🧪 Testing Event Listener Configuration');
console.log('=' .repeat(60));

const NETWORK = process.env.NETWORK || 'polygon';
const DONATIONS_ADDRESS = process.env.DONATION_CONTRACT_ADDRESS;
const POLYGON_WSS = process.env.POLYGON_RPC_WSS_URL;
const AMOY_WSS = process.env.AMOY_RPC_WSS_URL;
const RPC_WSS_URL = NETWORK === 'amoy' ? AMOY_WSS : POLYGON_WSS;

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'thesocialmask'
};

let allTestsPassed = true;

// ============================================
// TEST 1: Environment Variables
// ============================================

console.log('\n1️⃣  Testing Environment Variables...');

if (!DONATIONS_ADDRESS || DONATIONS_ADDRESS === '0x0000000000000000000000000000000000000000') {
    console.log('   ❌ DONATION_CONTRACT_ADDRESS not configured');
    console.log('      Please deploy the contract first (FASE 3.1)');
    allTestsPassed = false;
} else {
    console.log('   ✅ Contract Address:', DONATIONS_ADDRESS);
}

if (!RPC_WSS_URL || RPC_WSS_URL.includes('YOUR_')) {
    console.log('   ❌ WebSocket RPC URL not configured');
    console.log('      Please set', NETWORK === 'amoy' ? 'AMOY_RPC_WSS_URL' : 'POLYGON_RPC_WSS_URL');
    allTestsPassed = false;
} else {
    const urlPreview = RPC_WSS_URL.substring(0, 50) + '...';
    console.log('   ✅ WebSocket URL:', urlPreview);
}

console.log('   ✅ Network:', NETWORK);
console.log('   ✅ Database:', `${DB_CONFIG.user}@${DB_CONFIG.host}/${DB_CONFIG.database}`);

// ============================================
// TEST 2: Database Connection
// ============================================

console.log('\n2️⃣  Testing Database Connection...');

try {
    const pool = mysql.createPool(DB_CONFIG);
    const conn = await pool.getConnection();
    
    await conn.ping();
    console.log('   ✅ Database connection successful');
    
    // Verificar tablas
    const [tables] = await conn.execute("SHOW TABLES LIKE 'donation%'");
    
    if (tables.length === 0) {
        console.log('   ⚠️  Donation tables not found');
        console.log('      Run: mysql < database/migrations/create_donations_tables.sql');
        allTestsPassed = false;
    } else {
        console.log('   ✅ Found donation tables:', tables.length);
        
        // Verificar estructura
        const requiredTables = ['donation_campaigns', 'donations'];
        const foundTables = tables.map(t => Object.values(t)[0]);
        
        for (const table of requiredTables) {
            if (foundTables.includes(table)) {
                console.log(`      ✓ ${table}`);
            } else {
                console.log(`      ✗ ${table} (missing)`);
                allTestsPassed = false;
            }
        }
    }
    
    conn.release();
    await pool.end();
    
} catch (error) {
    console.log('   ❌ Database connection failed:', error.message);
    allTestsPassed = false;
}

// ============================================
// TEST 3: Blockchain Connection
// ============================================

console.log('\n3️⃣  Testing Blockchain Connection...');

if (!RPC_WSS_URL || RPC_WSS_URL.includes('YOUR_')) {
    console.log('   ⚠️  Skipping (WebSocket URL not configured)');
} else {
    try {
        const provider = new ethers.WebSocketProvider(RPC_WSS_URL);
        
        // Test connection
        const network = await provider.getNetwork();
        console.log('   ✅ Connected to network:', network.name);
        console.log('   ✅ Chain ID:', network.chainId.toString());
        
        // Test block number
        const blockNumber = await provider.getBlockNumber();
        console.log('   ✅ Latest block:', blockNumber);
        
        provider.destroy();
        
    } catch (error) {
        console.log('   ❌ Blockchain connection failed:', error.message);
        console.log('      Check your WebSocket URL and API key');
        allTestsPassed = false;
    }
}

// ============================================
// TEST 4: Contract Verification
// ============================================

console.log('\n4️⃣  Testing Contract Access...');

if (!DONATIONS_ADDRESS || !RPC_WSS_URL || RPC_WSS_URL.includes('YOUR_')) {
    console.log('   ⚠️  Skipping (missing configuration)');
} else {
    try {
        const provider = new ethers.WebSocketProvider(RPC_WSS_URL);
        
        // Verificar que el contrato existe
        const code = await provider.getCode(DONATIONS_ADDRESS);
        
        if (code === '0x') {
            console.log('   ❌ No contract found at address:', DONATIONS_ADDRESS);
            console.log('      Please deploy the contract first (FASE 3.1)');
            allTestsPassed = false;
        } else {
            console.log('   ✅ Contract found at:', DONATIONS_ADDRESS);
            console.log('   ✅ Contract code size:', code.length, 'bytes');
        }
        
        provider.destroy();
        
    } catch (error) {
        console.log('   ❌ Contract verification failed:', error.message);
        allTestsPassed = false;
    }
}

// ============================================
// FINAL RESULTS
// ============================================

console.log('\n' + '=' .repeat(60));

if (allTestsPassed) {
    console.log('✅ ALL TESTS PASSED!');
    console.log('');
    console.log('🚀 You can now start the event listener:');
    console.log('   npm run event-listener');
    console.log('');
    console.log('Or in development mode:');
    console.log('   npm run event-listener:dev');
    console.log('');
    process.exit(0);
} else {
    console.log('❌ SOME TESTS FAILED');
    console.log('');
    console.log('📝 Please fix the issues above before starting the listener');
    console.log('');
    console.log('Configuration checklist:');
    console.log('[ ] Deploy Donations contract (FASE 3.1)');
    console.log('[ ] Set DONATION_CONTRACT_ADDRESS in .env');
    console.log('[ ] Get WebSocket URL from Alchemy/Infura');
    console.log('[ ] Set WebSocket URL in .env');
    console.log('[ ] Create donation tables in database');
    console.log('[ ] Verify database credentials');
    console.log('');
    process.exit(1);
}
