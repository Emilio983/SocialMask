const hre = require("hardhat");

async function main() {
    console.log("💰 Checking Wallet Balance\n");
    console.log("=".repeat(60));

    const [deployer] = await hre.ethers.getSigners();
    
    console.log("Network:", hre.network.name);
    console.log("Wallet Address:", deployer.address);
    
    const balance = await hre.ethers.provider.getBalance(deployer.address);
    const balanceInMatic = hre.ethers.formatEther(balance);
    
    console.log("Balance:", balanceInMatic, "MATIC");
    console.log("=".repeat(60));

    // Verificar si tiene suficiente
    const minBalance = 0.5; // 0.5 MATIC recomendado
    
    if (parseFloat(balanceInMatic) < minBalance) {
        console.log("\n⚠️  WARNING: Low balance!");
        console.log(`   Recommended: ${minBalance} MATIC`);
        console.log(`   Current: ${balanceInMatic} MATIC`);
        
        if (hre.network.name === "amoy") {
            console.log("\n💧 Get free testnet MATIC:");
            console.log("   https://faucet.polygon.technology/");
        } else {
            console.log("\n💵 Buy MATIC on an exchange (Binance, Coinbase, etc.)");
        }
        
        process.exit(1);
    } else {
        console.log("\n✅ Balance sufficient for deployment!");
        console.log(`   Estimated gas cost: ~0.3-0.8 MATIC`);
        console.log(`   Your balance: ${balanceInMatic} MATIC`);
    }
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error(error);
        process.exit(1);
    });
