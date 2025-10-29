const fs = require('fs');
const path = require('path');

/**
 * Script para actualizar automáticamente las direcciones de contratos en el frontend
 * Uso: node update-frontend-addresses.js <network> <donationsAddress>
 * Ejemplo: node update-frontend-addresses.js polygon 0x1234...
 */

async function main() {
    const network = process.argv[2] || 'polygon';
    const donationsAddress = process.argv[3];

    if (!donationsAddress) {
        console.error("❌ Error: Debes proporcionar la dirección del contrato");
        console.log("Uso: node update-frontend-addresses.js <network> <donationsAddress>");
        process.exit(1);
    }

    console.log("🔧 Updating frontend with contract addresses...\n");
    console.log("Network:", network);
    console.log("Donations Contract:", donationsAddress);

    // 1. Actualizar deployed-addresses.json
    const addressesPath = path.join(__dirname, '..', 'deployed-addresses.json');
    let addresses = {};
    
    if (fs.existsSync(addressesPath)) {
        addresses = JSON.parse(fs.readFileSync(addressesPath, 'utf8'));
    }

    addresses.donations = addresses.donations || {};
    addresses.donations[network] = donationsAddress;

    fs.writeFileSync(addressesPath, JSON.stringify(addresses, null, 2));
    console.log("✅ Updated deployed-addresses.json\n");

    // 2. Actualizar assets/js/donations.js
    const donationsJsPath = path.join(__dirname, '..', '..', 'assets', 'js', 'donations.js');
    
    if (!fs.existsSync(donationsJsPath)) {
        console.log("⚠️  Warning: assets/js/donations.js not found");
        return;
    }

    let donationsJs = fs.readFileSync(donationsJsPath, 'utf8');

    // Obtener SPHE token address del .env o deployed-addresses
    const spheAddress = addresses.spheToken?.[network] || process.env.SPHE_TOKEN_ADDRESS || '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b';

    // Reemplazar direcciones
    const oldDonationsPattern = /const DONATIONS_CONTRACT_ADDRESS = ['"]0x[a-fA-F0-9]{40}['"];/;
    const oldSphePattern = /const SPHE_CONTRACT_ADDRESS = ['"]0x[a-fA-F0-9]{40}['"];/;

    const newDonationsLine = `const DONATIONS_CONTRACT_ADDRESS = '${donationsAddress}';`;
    const newSpheLine = `const SPHE_CONTRACT_ADDRESS = '${spheAddress}';`;

    if (oldDonationsPattern.test(donationsJs)) {
        donationsJs = donationsJs.replace(oldDonationsPattern, newDonationsLine);
    } else {
        // Si no existe el pattern, buscar el placeholder
        donationsJs = donationsJs.replace(
            /const DONATIONS_CONTRACT_ADDRESS = ['"]0x0+['"];/,
            newDonationsLine
        );
    }

    if (oldSphePattern.test(donationsJs)) {
        donationsJs = donationsJs.replace(oldSphePattern, newSpheLine);
    } else {
        donationsJs = donationsJs.replace(
            /const SPHE_CONTRACT_ADDRESS = ['"]0x0+['"];/,
            newSpheLine
        );
    }

    fs.writeFileSync(donationsJsPath, donationsJs);
    console.log("✅ Updated assets/js/donations.js");
    console.log("   Donations Contract:", donationsAddress);
    console.log("   SPHE Token:", spheAddress);

    // 3. Actualizar .env si existe
    const envPath = path.join(__dirname, '..', '.env');
    
    if (fs.existsSync(envPath)) {
        let envContent = fs.readFileSync(envPath, 'utf8');
        
        if (envContent.includes('DONATION_CONTRACT_ADDRESS=')) {
            envContent = envContent.replace(
                /DONATION_CONTRACT_ADDRESS=.*/,
                `DONATION_CONTRACT_ADDRESS=${donationsAddress}`
            );
        } else {
            envContent += `\n# Donations Contract\nDONATION_CONTRACT_ADDRESS=${donationsAddress}\n`;
        }
        
        fs.writeFileSync(envPath, envContent);
        console.log("✅ Updated .env file\n");
    }

    console.log("=" .repeat(60));
    console.log("✅ ALL FRONTEND FILES UPDATED SUCCESSFULLY!");
    console.log("=" .repeat(60));
    console.log("\n📝 Next Steps:");
    console.log("1. Test the donations page: http://localhost/pages/donations.php");
    console.log("2. Connect MetaMask and create a test campaign");
    console.log("3. Make a test donation");
    console.log("4. Start event listener (FASE 3.4)");
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error(error);
        process.exit(1);
    });
