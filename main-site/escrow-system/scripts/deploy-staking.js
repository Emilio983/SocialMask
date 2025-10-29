const hre = require("hardhat");
const { ethers } = require("hardhat");

async function main() {
    console.log("🚀 Iniciando deployment del sistema de Staking...\n");

    const [deployer] = await ethers.getSigners();
    console.log("📍 Deploying desde:", deployer.address);
    console.log("💰 Balance:", ethers.formatEther(await ethers.provider.getBalance(deployer.address)), "MATIC\n");

    // ============ Configuración ============
    const config = {
        // Token SPHE (cambiar por el token real en producción)
        stakingTokenAddress: process.env.SPHE_TOKEN_ADDRESS || "0x0000000000000000000000000000000000000000",
        
        // Reward rate: 0.0001 SPHE por segundo = ~8.64 SPHE por día por 1000 SPHE stakeados
        // Esto equivale a aproximadamente 10-15% APY dependiendo del pool
        rewardRatePerSecond: ethers.parseEther("0.0001"),
        
        // Fondos iniciales para reward pool (100,000 SPHE)
        initialRewardPool: ethers.parseEther("100000"),
        
        // Pools a crear
        pools: [
            // Pool 0 (Flexible) ya se crea en el constructor
            {
                id: 1,
                name: "30 Days",
                lockPeriod: 30 * 24 * 3600, // 30 días
                rewardMultiplier: 150, // 1.5x (15% APY)
                minStake: ethers.parseEther("10")
            },
            {
                id: 2,
                name: "90 Days",
                lockPeriod: 90 * 24 * 3600, // 90 días
                rewardMultiplier: 200, // 2x (20% APY)
                minStake: ethers.parseEther("10")
            },
            {
                id: 3,
                name: "180 Days",
                lockPeriod: 180 * 24 * 3600, // 180 días
                rewardMultiplier: 250, // 2.5x (25% APY)
                minStake: ethers.parseEther("10")
            }
        ]
    };

    // ============ Validaciones ============
    if (config.stakingTokenAddress === "0x0000000000000000000000000000000000000000") {
        console.log("⚠️  MODO TESTNET: Deployando MockERC20 token...");
        
        const MockToken = await ethers.getContractFactory("MockERC20");
        const mockToken = await MockToken.deploy(
            "TheSocialMask Token",
            "SPHE",
            ethers.parseEther("10000000") // 10M tokens
        );
        await mockToken.waitForDeployment();
        
        config.stakingTokenAddress = await mockToken.getAddress();
        console.log("✅ MockERC20 deployed:", config.stakingTokenAddress);
        console.log("");
    }

    // ============ Deploy TokenStaking ============
    console.log("📦 Deployando TokenStaking contract...");
    
    const TokenStaking = await ethers.getContractFactory("TokenStaking");
    const tokenStaking = await TokenStaking.deploy(
        config.stakingTokenAddress,
        config.rewardRatePerSecond
    );
    
    await tokenStaking.waitForDeployment();
    const stakingAddress = await tokenStaking.getAddress();
    
    console.log("✅ TokenStaking deployed:", stakingAddress);
    console.log("");

    // ============ Crear Pools Adicionales ============
    console.log("🏊 Creando pools de staking...");
    
    for (const pool of config.pools) {
        console.log(`   Creating pool: ${pool.name} (${pool.lockPeriod / 86400} days, ${pool.rewardMultiplier / 100}x multiplier)`);
        
        const tx = await tokenStaking.createPool(
            pool.id,
            pool.name,
            pool.lockPeriod,
            pool.rewardMultiplier,
            pool.minStake
        );
        await tx.wait();
    }
    
    console.log("✅ Todos los pools creados\n");

    // ============ Fund Reward Pool ============
    console.log("💰 Financiando reward pool...");
    
    const stakingToken = await ethers.getContractAt("IERC20", config.stakingTokenAddress);
    
    // Aprobar tokens
    const approveTx = await stakingToken.approve(stakingAddress, config.initialRewardPool);
    await approveTx.wait();
    console.log("   Aprobación completada");
    
    // Transferir al reward pool
    const fundTx = await tokenStaking.fundRewardPool(config.initialRewardPool);
    await fundTx.wait();
    console.log("✅ Reward pool financiado con", ethers.formatEther(config.initialRewardPool), "SPHE\n");

    // ============ Verificar Deployment ============
    console.log("🔍 Verificando deployment...");
    
    const rewardPool = await tokenStaking.rewardPool();
    const totalStaked = await tokenStaking.totalStaked();
    const rewardRate = await tokenStaking.rewardRatePerSecond();
    
    console.log("   Reward Pool Balance:", ethers.formatEther(rewardPool), "SPHE");
    console.log("   Total Staked:", ethers.formatEther(totalStaked), "SPHE");
    console.log("   Reward Rate:", ethers.formatEther(rewardRate), "SPHE/segundo");
    
    // Verificar pools
    console.log("\n   Pools creados:");
    for (let i = 0; i <= 3; i++) {
        const poolInfo = await tokenStaking.getPoolInfo(i);
        const apy = await tokenStaking.calculateAPY(i);
        console.log(`   - Pool ${i}: ${poolInfo.name} | Lock: ${poolInfo.lockPeriod / 86400} días | Multiplier: ${poolInfo.rewardMultiplier / 100}x | APY: ~${Number(apy) / 100}%`);
    }

    // ============ Guardar Deployment Info ============
    const deploymentInfo = {
        network: hre.network.name,
        chainId: (await ethers.provider.getNetwork()).chainId.toString(),
        timestamp: new Date().toISOString(),
        deployer: deployer.address,
        contracts: {
            TokenStaking: stakingAddress,
            StakingToken: config.stakingTokenAddress
        },
        configuration: {
            rewardRatePerSecond: config.rewardRatePerSecond.toString(),
            initialRewardPool: config.initialRewardPool.toString(),
            minimumStake: (await tokenStaking.minimumStake()).toString(),
            maximumStake: (await tokenStaking.maximumStake()).toString(),
            earlyUnstakeFee: (await tokenStaking.earlyUnstakeFee()).toString()
        },
        pools: config.pools.map(p => ({
            id: p.id,
            name: p.name,
            lockPeriodDays: p.lockPeriod / 86400,
            rewardMultiplier: p.rewardMultiplier / 100,
            minStake: ethers.formatEther(p.minStake)
        }))
    };

    const fs = require("fs");
    const path = require("path");
    
    // Guardar en JSON
    const deploymentsDir = path.join(__dirname, "../deployments");
    if (!fs.existsSync(deploymentsDir)) {
        fs.mkdirSync(deploymentsDir, { recursive: true });
    }
    
    const filename = `staking-${hre.network.name}-${Date.now()}.json`;
    fs.writeFileSync(
        path.join(deploymentsDir, filename),
        JSON.stringify(deploymentInfo, null, 2)
    );

    // Actualizar archivo de configuración principal
    const configPath = path.join(__dirname, "../config/staking-config.json");
    fs.writeFileSync(configPath, JSON.stringify(deploymentInfo, null, 2));

    console.log("\n✅ Información de deployment guardada en:");
    console.log(`   - ${filename}`);
    console.log(`   - staking-config.json\n`);

    // ============ Verificación en Block Explorer ============
    if (hre.network.name !== "hardhat" && hre.network.name !== "localhost") {
        console.log("⏳ Esperando 1 minuto antes de verificar en PolygonScan...");
        await new Promise(resolve => setTimeout(resolve, 60000));

        console.log("🔍 Verificando contratos en PolygonScan...\n");

        try {
            await hre.run("verify:verify", {
                address: stakingAddress,
                constructorArguments: [
                    config.stakingTokenAddress,
                    config.rewardRatePerSecond
                ]
            });
            console.log("✅ TokenStaking verificado en PolygonScan");
        } catch (error) {
            console.log("⚠️  Error verificando en PolygonScan:", error.message);
        }
    }

    // ============ Resumen Final ============
    console.log("\n" + "=".repeat(70));
    console.log("🎉 DEPLOYMENT COMPLETADO EXITOSAMENTE");
    console.log("=".repeat(70));
    console.log("\n📋 RESUMEN:");
    console.log(`   Network: ${hre.network.name}`);
    console.log(`   TokenStaking: ${stakingAddress}`);
    console.log(`   Staking Token: ${config.stakingTokenAddress}`);
    console.log(`   Reward Pool: ${ethers.formatEther(config.initialRewardPool)} SPHE`);
    console.log(`   Pools disponibles: 4 (Flexible, 30d, 90d, 180d)`);
    console.log("\n📚 SIGUIENTES PASOS:");
    console.log("   1. Actualizar frontend con la dirección del contrato");
    console.log("   2. Configurar APIs backend con el ABI y dirección");
    console.log("   3. Testear funciones de stake/unstake");
    console.log("   4. Monitorear reward pool y ajustar reward rate si es necesario");
    console.log("\n💡 COMANDOS ÚTILES:");
    console.log(`   - Verificar en explorer: https://polygonscan.com/address/${stakingAddress}`);
    console.log(`   - Interactuar: npx hardhat console --network ${hre.network.name}`);
    console.log("\n" + "=".repeat(70) + "\n");

    return {
        tokenStaking: stakingAddress,
        stakingToken: config.stakingTokenAddress
    };
}

// Manejo de errores
main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("\n❌ ERROR EN DEPLOYMENT:\n");
        console.error(error);
        process.exit(1);
    });

module.exports = { main };
