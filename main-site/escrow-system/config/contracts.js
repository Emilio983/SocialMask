/**
 * Smart Contract Addresses and Configuration
 * Update these after deployment
 */

const contractConfig = {
    // Network Configuration
    network: {
        chainId: 137, // Polygon Mainnet
        chainName: 'Polygon Mainnet',
        rpcUrl: 'https://polygon-rpc.com',
        blockExplorer: 'https://polygonscan.com'
    },

    // Deployed Contracts
    contracts: {
        // SPHE Token
        SPHE: {
            address: '0x0000000000000000000000000000000000000000', // UPDATE AFTER DEPLOYMENT
            decimals: 18
        },

        // Escrow System
        SocialMediaEscrow: {
            address: '0x0000000000000000000000000000000000000000' // UPDATE AFTER DEPLOYMENT
        },

        // Donations
        DonationSystem: {
            address: '0x0000000000000000000000000000000000000000' // UPDATE AFTER DEPLOYMENT
        },

        // Pay-Per-View
        PayPerView: {
            address: '0x0000000000000000000000000000000000000000' // UPDATE AFTER DEPLOYMENT
        }
    },

    // Gelato Relay Configuration
    gelato: {
        relayApiKey: process.env.GELATO_RELAY_API_KEY || '',
        sponsorApiKey: process.env.GELATO_SPONSOR_API_KEY || ''
    },

    // Fee Configuration
    fees: {
        platformFeePercent: 2.5, // 2.5%
        escrowFeePercent: 1.0,   // 1.0%
        donationFeePercent: 2.5  // 2.5%
    }
};

// Export for Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = contractConfig;
}

// Export for browser
if (typeof window !== 'undefined') {
    window.CONTRACT_CONFIG = contractConfig;
    window.SPHE_TOKEN_ADDRESS = contractConfig.contracts.SPHE.address;
    window.ESCROW_CONTRACT_ADDRESS = contractConfig.contracts.SocialMediaEscrow.address;
    window.DONATION_CONTRACT_ADDRESS = contractConfig.contracts.DonationSystem.address;
    window.PAYWALL_CONTRACT_ADDRESS = contractConfig.contracts.PayPerView.address;
}
