const { ethers } = require("hardhat");

/**
 * Test de Gelato Relay Integration
 * Verifica que Gelato Relay esté configurado correctamente
 */

// Configuración
const GELATO_RELAY_URL = "https://relay.gelato.digital";
const GELATO_API_KEY = process.env.GELATO_RELAY_API_KEY;

console.log("🧪 TESTING GELATO RELAY\n");
console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

/**
 * Test 1: Verificar variables de entorno
 */
async function testEnvironment() {
    console.log("1️⃣  Testing Environment Variables");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const required = [
        'GELATO_RELAY_API_KEY',
        'GELATO_RELAY_URL',
        'PRIVATE_KEY'
    ];
    
    let allPresent = true;
    
    for (const key of required) {
        const value = process.env[key];
        if (value) {
            console.log(`✅ ${key}: ${value.substring(0, 10)}...`);
        } else {
            console.log(`❌ ${key}: Missing`);
            allPresent = false;
        }
    }
    
    console.log();
    return allPresent;
}

/**
 * Test 2: Verificar conexión con Gelato API
 */
async function testGelatoConnection() {
    console.log("2️⃣  Testing Gelato API Connection");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    if (!GELATO_API_KEY) {
        console.log("❌ GELATO_RELAY_API_KEY not set\n");
        return false;
    }
    
    try {
        const response = await fetch(`${GELATO_RELAY_URL}/relays/v2/balance`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${GELATO_API_KEY}`
            }
        });
        
        if (!response.ok) {
            console.log(`❌ API responded with status ${response.status}`);
            const text = await response.text();
            console.log(`Response: ${text}\n`);
            return false;
        }
        
        const balance = await response.json();
        console.log("✅ Connection successful");
        console.log(`Balance:`, balance);
        console.log();
        
        return true;
        
    } catch (error) {
        console.log(`❌ Connection failed: ${error.message}\n`);
        return false;
    }
}

/**
 * Test 3: Verificar contratos deployados
 */
async function testContracts() {
    console.log("3️⃣  Testing Deployed Contracts");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const fs = require("fs");
    const path = require("path");
    const configPath = path.join(__dirname, "../config/contracts.json");
    
    if (!fs.existsSync(configPath)) {
        console.log("❌ contracts.json not found\n");
        return false;
    }
    
    const config = JSON.parse(fs.readFileSync(configPath, "utf8"));
    const network = hre.network.name;
    
    const contracts = {
        'SPHE Token': config.spheToken?.[network],
        'PayPerView': config.payPerView?.[network]
    };
    
    let allDeployed = true;
    
    for (const [name, address] of Object.entries(contracts)) {
        if (address) {
            console.log(`✅ ${name}: ${address}`);
        } else {
            console.log(`❌ ${name}: Not deployed`);
            allDeployed = false;
        }
    }
    
    console.log();
    return allDeployed;
}

/**
 * Test 4: Simular sponsored call (sin ejecutar)
 */
async function testSponsoredCallStructure() {
    console.log("4️⃣  Testing Sponsored Call Structure");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const fs = require("fs");
    const path = require("path");
    const configPath = path.join(__dirname, "../config/contracts.json");
    
    if (!fs.existsSync(configPath)) {
        console.log("❌ contracts.json not found\n");
        return false;
    }
    
    const config = JSON.parse(fs.readFileSync(configPath, "utf8"));
    const network = hre.network.name;
    const payPerViewAddress = config.payPerView?.[network];
    
    if (!payPerViewAddress) {
        console.log("❌ PayPerView not deployed\n");
        return false;
    }
    
    // Crear estructura de llamada
    const [signer] = await ethers.getSigners();
    const chainId = (await ethers.provider.getNetwork()).chainId;
    
    const PayPerView = await ethers.getContractFactory("PayPerView");
    const iface = PayPerView.interface;
    
    // Encodear función purchaseContent(1)
    const data = iface.encodeFunctionData("purchaseContent", [1]);
    
    const request = {
        chainId: Number(chainId),
        target: payPerViewAddress,
        data: data,
        user: signer.address,
        gasLimit: "500000"
    };
    
    console.log("📋 Request structure:");
    console.log(JSON.stringify(request, null, 2));
    console.log();
    
    // Validar estructura
    let valid = true;
    
    if (!request.chainId || typeof request.chainId !== 'number') {
        console.log("❌ Invalid chainId");
        valid = false;
    } else {
        console.log("✅ chainId valid");
    }
    
    if (!ethers.isAddress(request.target)) {
        console.log("❌ Invalid target address");
        valid = false;
    } else {
        console.log("✅ target address valid");
    }
    
    if (!request.data || !request.data.startsWith('0x')) {
        console.log("❌ Invalid data");
        valid = false;
    } else {
        console.log("✅ data valid");
    }
    
    if (!ethers.isAddress(request.user)) {
        console.log("❌ Invalid user address");
        valid = false;
    } else {
        console.log("✅ user address valid");
    }
    
    console.log();
    return valid;
}

/**
 * Test 5: Estimate gas para purchaseContent
 */
async function testGasEstimation() {
    console.log("5️⃣  Testing Gas Estimation");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const fs = require("fs");
    const path = require("path");
    const configPath = path.join(__dirname, "../config/contracts.json");
    
    if (!fs.existsSync(configPath)) {
        console.log("❌ contracts.json not found\n");
        return false;
    }
    
    const config = JSON.parse(fs.readFileSync(configPath, "utf8"));
    const network = hre.network.name;
    const payPerViewAddress = config.payPerView?.[network];
    
    if (!payPerViewAddress) {
        console.log("❌ PayPerView not deployed\n");
        return false;
    }
    
    try {
        const [signer] = await ethers.getSigners();
        const PayPerView = await ethers.getContractFactory("PayPerView");
        const payPerView = PayPerView.attach(payPerViewAddress);
        
        // Crear contenido de prueba si no existe
        try {
            await payPerView.contents(1);
        } catch {
            console.log("📝 Creando contenido de prueba...");
            const tx = await payPerView.createContent(ethers.parseEther("10"));
            await tx.wait();
            console.log("✅ Contenido creado\n");
        }
        
        // Estimar gas
        const data = payPerView.interface.encodeFunctionData("purchaseContent", [1]);
        
        const gasEstimate = await ethers.provider.estimateGas({
            from: signer.address,
            to: payPerViewAddress,
            data: data
        });
        
        const gasWithBuffer = gasEstimate * 120n / 100n; // +20%
        
        console.log(`✅ Gas estimated: ${gasEstimate.toString()}`);
        console.log(`✅ Gas with buffer: ${gasWithBuffer.toString()}`);
        console.log(`✅ Within Gelato limit: ${gasWithBuffer < 10000000n ? "Yes" : "No"}`);
        console.log();
        
        return true;
        
    } catch (error) {
        console.log(`❌ Gas estimation failed: ${error.message}\n`);
        return false;
    }
}

/**
 * Test 6: Verificar task status endpoint
 */
async function testTaskStatusEndpoint() {
    console.log("6️⃣  Testing Task Status Endpoint");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    // Usar un task ID de ejemplo (no existente, solo para probar endpoint)
    const testTaskId = "0x1234567890abcdef";
    
    try {
        const response = await fetch(`${GELATO_RELAY_URL}/tasks/status/${testTaskId}`);
        
        if (response.status === 404) {
            console.log("✅ Endpoint responsive (task not found as expected)");
            console.log();
            return true;
        }
        
        const data = await response.json();
        console.log("📋 Response:", data);
        console.log();
        return true;
        
    } catch (error) {
        console.log(`❌ Endpoint test failed: ${error.message}\n`);
        return false;
    }
}

/**
 * Main test runner
 */
async function main() {
    const results = {
        environment: await testEnvironment(),
        connection: await testGelatoConnection(),
        contracts: await testContracts(),
        callStructure: await testSponsoredCallStructure(),
        gasEstimation: await testGasEstimation(),
        taskEndpoint: await testTaskStatusEndpoint()
    };
    
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("📊 SUMMARY");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const passed = Object.values(results).filter(r => r).length;
    const total = Object.keys(results).length;
    
    for (const [test, passed] of Object.entries(results)) {
        const icon = passed ? "✅" : "❌";
        console.log(`${icon} ${test}`);
    }
    
    console.log();
    console.log(`Result: ${passed}/${total} tests passed`);
    console.log();
    
    if (passed === total) {
        console.log("✅ All tests passed! Gelato Relay is ready to use.");
        console.log();
        console.log("📋 Next steps:");
        console.log("   1. Deposit funds in Gelato Network");
        console.log("   2. Test gasless transaction in frontend");
        console.log("   3. Deploy to production");
        console.log();
    } else {
        console.log("⚠️  Some tests failed. Please fix the issues before proceeding.");
        console.log();
        console.log("💡 Common issues:");
        console.log("   - GELATO_RELAY_API_KEY not set in .env");
        console.log("   - Contracts not deployed");
        console.log("   - Network mismatch");
        console.log("   - Insufficient Gelato balance");
        console.log();
    }
    
    process.exit(passed === total ? 0 : 1);
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("❌ Error:", error);
        process.exit(1);
    });
