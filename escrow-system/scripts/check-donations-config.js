const hre = require("hardhat");

/**
 * Script para verificar la configuración del contrato Donations desplegado
 * Uso: node check-donations-config.js <network> <address>
 */

async function main() {
    const network = process.argv[2] || hre.network.name;
    const contractAddress = process.argv[3];

    if (!contractAddress) {
        console.error("❌ Error: Proporciona la dirección del contrato");
        console.log("Uso: node check-donations-config.js <network> <address>");
        console.log("Ejemplo: node check-donations-config.js amoy 0x1234...");
        process.exit(1);
    }

    console.log("🔍 Checking Donations Contract Configuration\n");
    console.log("=".repeat(60));
    console.log("Network:", network);
    console.log("Contract:", contractAddress);
    console.log("=".repeat(60));

    try {
        // Conectar al contrato
        const Donations = await hre.ethers.getContractFactory("Donations");
        const donations = Donations.attach(contractAddress);

        // Obtener configuración
        const treasury = await donations.treasury();
        const feePercentage = await donations.feePercentage();
        const minDonation = await donations.minDonation();
        const owner = await donations.owner();

        console.log("\n📋 Contract Configuration:");
        console.log("   Owner:", owner);
        console.log("   Treasury:", treasury);
        console.log("   Fee Percentage:", Number(feePercentage) / 100, "%");
        console.log("   Min Donation:", hre.ethers.formatEther(minDonation), "tokens");

        // Verificar tokens permitidos
        console.log("\n🪙 Allowed Tokens:");
        
        const SPHE_TOKEN = process.env.SPHE_TOKEN_ADDRESS || "0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b";
        const WMATIC_TOKEN = network === "polygon" 
            ? "0x0d500B1d8E8eF31E21C99d1Db9A6444d3ADf1270"
            : "0x9c3C9283D3e44854697Cd22D3Faa240Cfb032889";

        const spheAllowed = await donations.allowedTokens(SPHE_TOKEN);
        const wmaticAllowed = await donations.allowedTokens(WMATIC_TOKEN);

        console.log("   SPHE Token:", SPHE_TOKEN);
        console.log("   Status:", spheAllowed ? "✅ Allowed" : "❌ Not Allowed");
        
        console.log("   WMATIC Token:", WMATIC_TOKEN);
        console.log("   Status:", wmaticAllowed ? "✅ Allowed" : "❌ Not Allowed");

        // Estadísticas
        console.log("\n📊 Statistics:");
        try {
            const totalCampaigns = await donations.campaignCounter();
            console.log("   Total Campaigns:", totalCampaigns.toString());
        } catch (e) {
            console.log("   Total Campaigns: 0 (no campaigns yet)");
        }

        console.log("\n✅ Contract is properly configured!");
        console.log("=".repeat(60));

    } catch (error) {
        console.error("\n❌ Error checking contract:", error.message);
        process.exit(1);
    }
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error(error);
        process.exit(1);
    });
