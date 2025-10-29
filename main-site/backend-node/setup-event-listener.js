#!/usr/bin/env node

/**
 * Setup Script para Event Listener - FASE 3.4
 * 
 * Este script ayuda a configurar el event listener automáticamente
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import readline from 'readline';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

function question(query) {
    return new Promise(resolve => rl.question(query, resolve));
}

console.log('🚀 Event Listener Setup - FASE 3.4');
console.log('=' .repeat(60));

async function setup() {
    try {
        // 1. Verificar si existe .env
        const envPath = path.join(__dirname, '.env');
        const envExamplePath = path.join(__dirname, '.env.example');
        
        let envContent = '';
        
        if (fs.existsSync(envPath)) {
            console.log('✅ Found existing .env file');
            envContent = fs.readFileSync(envPath, 'utf8');
        } else if (fs.existsSync(envExamplePath)) {
            console.log('📝 Creating .env from .env.example');
            envContent = fs.readFileSync(envExamplePath, 'utf8');
        } else {
            console.log('⚠️  No .env file found, creating new one...');
            envContent = `# Backend Node.js Configuration
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=thesocialmask
NETWORK=polygon
DONATION_CONTRACT_ADDRESS=
POLYGON_RPC_WSS_URL=
AMOY_RPC_WSS_URL=
`;
        }
        
        console.log('');
        
        // 2. Preguntar configuración
        console.log('📋 Please provide the following information:\n');
        
        const network = await question('Network (polygon/amoy) [polygon]: ');
        const selectedNetwork = network.trim() || 'polygon';
        
        const contractAddress = await question('Donation Contract Address: ');
        
        let wssUrl = '';
        if (selectedNetwork === 'amoy') {
            wssUrl = await question('Amoy WebSocket RPC URL (wss://...): ');
        } else {
            wssUrl = await question('Polygon WebSocket RPC URL (wss://...): ');
        }
        
        const dbHost = await question('Database Host [localhost]: ');
        const dbName = await question('Database Name [thesocialmask]: ');
        const dbUser = await question('Database User [root]: ');
        const dbPass = await question('Database Password []: ');
        
        console.log('');
        
        // 3. Actualizar .env
        const updates = {
            NETWORK: selectedNetwork,
            DONATION_CONTRACT_ADDRESS: contractAddress.trim(),
            DB_HOST: dbHost.trim() || 'localhost',
            DB_NAME: dbName.trim() || 'thesocialmask',
            DB_USER: dbUser.trim() || 'root',
            DB_PASS: dbPass.trim()
        };
        
        if (selectedNetwork === 'amoy') {
            updates.AMOY_RPC_WSS_URL = wssUrl.trim();
        } else {
            updates.POLYGON_RPC_WSS_URL = wssUrl.trim();
        }
        
        // Actualizar cada variable
        for (const [key, value] of Object.entries(updates)) {
            const regex = new RegExp(`^${key}=.*$`, 'm');
            if (regex.test(envContent)) {
                envContent = envContent.replace(regex, `${key}=${value}`);
            } else {
                envContent += `\n${key}=${value}`;
            }
        }
        
        // Guardar .env
        fs.writeFileSync(envPath, envContent);
        
        console.log('✅ Configuration saved to .env');
        console.log('');
        
        // 4. Verificar configuración
        console.log('📋 Configuration Summary:');
        console.log('─'.repeat(60));
        console.log('Network:', selectedNetwork);
        console.log('Contract:', contractAddress || 'NOT SET');
        console.log('Database:', `${updates.DB_USER}@${updates.DB_HOST}/${updates.DB_NAME}`);
        console.log('WebSocket URL:', wssUrl.substring(0, 50) + '...');
        console.log('─'.repeat(60));
        console.log('');
        
        // 5. Instrucciones finales
        console.log('✅ Setup complete!');
        console.log('');
        console.log('📝 Next steps:');
        console.log('1. Verify .env configuration:');
        console.log('   cat .env');
        console.log('');
        console.log('2. Start the event listener:');
        console.log('   npm run event-listener');
        console.log('');
        console.log('3. Or start in development mode (auto-restart):');
        console.log('   npm run event-listener:dev');
        console.log('');
        console.log('4. For production, use PM2:');
        console.log('   pm2 start src/event-listener.js --name donations-listener');
        console.log('');
        
    } catch (error) {
        console.error('❌ Setup failed:', error.message);
        process.exit(1);
    } finally {
        rl.close();
    }
}

setup();
