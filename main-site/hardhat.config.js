require("@nomicfoundation/hardhat-toolbox");
require("dotenv").config();

/**
 * ============================================
 * HARDHAT CONFIGURATION
 * ============================================
 * Configuration for deploying and testing governance contracts
 */

const PRIVATE_KEY = process.env.PRIVATE_KEY || "0x0000000000000000000000000000000000000000000000000000000000000000";
const INFURA_KEY = process.env.INFURA_KEY || "";
const ETHERSCAN_API_KEY = process.env.ETHERSCAN_API_KEY || "";

module.exports = {
    solidity: {
        version: "0.8.20",
        settings: {
            optimizer: {
                enabled: true,
                runs: 200
            },
            viaIR: true
        }
    },

    networks: {
        // Local Hardhat Network
        hardhat: {
            chainId: 31337,
            accounts: {
                count: 10,
                accountsBalance: "10000000000000000000000" // 10,000 ETH
            }
        },

        // Localhost (for local node)
        localhost: {
            url: "http://127.0.0.1:8545",
            chainId: 31337
        },

        // Ethereum Sepolia Testnet
        sepolia: {
            url: `https://sepolia.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 11155111,
            gasPrice: "auto"
        },

        // Ethereum Goerli Testnet
        goerli: {
            url: `https://goerli.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 5,
            gasPrice: "auto"
        },

        // Ethereum Mainnet
        mainnet: {
            url: `https://mainnet.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 1,
            gasPrice: "auto"
        },

        // Polygon Mumbai Testnet
        mumbai: {
            url: `https://polygon-mumbai.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 80001,
            gasPrice: "auto"
        },

        // Polygon Mainnet
        polygon: {
            url: `https://polygon-mainnet.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 137,
            gasPrice: "auto"
        },

        // Arbitrum Sepolia Testnet
        arbitrumSepolia: {
            url: `https://arbitrum-sepolia.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 421614,
            gasPrice: "auto"
        },

        // Arbitrum One Mainnet
        arbitrum: {
            url: `https://arbitrum-mainnet.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 42161,
            gasPrice: "auto"
        },

        // Optimism Sepolia Testnet
        optimismSepolia: {
            url: `https://optimism-sepolia.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 11155420,
            gasPrice: "auto"
        },

        // Optimism Mainnet
        optimism: {
            url: `https://optimism-mainnet.infura.io/v3/${INFURA_KEY}`,
            accounts: [PRIVATE_KEY],
            chainId: 10,
            gasPrice: "auto"
        },

        // Base Sepolia Testnet
        baseSepolia: {
            url: "https://sepolia.base.org",
            accounts: [PRIVATE_KEY],
            chainId: 84532,
            gasPrice: "auto"
        },

        // Base Mainnet
        base: {
            url: "https://mainnet.base.org",
            accounts: [PRIVATE_KEY],
            chainId: 8453,
            gasPrice: "auto"
        }
    },

    etherscan: {
        apiKey: {
            mainnet: ETHERSCAN_API_KEY,
            sepolia: ETHERSCAN_API_KEY,
            goerli: ETHERSCAN_API_KEY,
            polygon: ETHERSCAN_API_KEY,
            polygonMumbai: ETHERSCAN_API_KEY,
            arbitrumOne: ETHERSCAN_API_KEY,
            arbitrumSepolia: ETHERSCAN_API_KEY,
            optimisticEthereum: ETHERSCAN_API_KEY,
            optimisticSepolia: ETHERSCAN_API_KEY,
            base: ETHERSCAN_API_KEY,
            baseSepolia: ETHERSCAN_API_KEY
        }
    },

    gasReporter: {
        enabled: process.env.REPORT_GAS === "true",
        currency: "USD",
        coinmarketcap: process.env.COINMARKETCAP_API_KEY,
        outputFile: "gas-report.txt",
        noColors: true
    },

    paths: {
        sources: "./contracts",
        tests: "./test",
        cache: "./cache",
        artifacts: "./artifacts"
    },

    mocha: {
        timeout: 200000 // 200 seconds
    }
};
