#!/bin/bash

# ============================================
# GOVERNANCE SYSTEM DEPLOYMENT SCRIPT
# ============================================
# Complete deployment and verification for governance contracts

set -e # Exit on error

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🚀 GOVERNANCE SYSTEM DEPLOYMENT"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found"
    echo "Please create .env with:"
    echo "  PRIVATE_KEY=your_private_key"
    echo "  INFURA_KEY=your_infura_key"
    echo "  ETHERSCAN_API_KEY=your_etherscan_key"
    exit 1
fi

# Load environment variables
source .env

# Check required variables
if [ -z "$PRIVATE_KEY" ]; then
    echo "❌ Error: PRIVATE_KEY not set in .env"
    exit 1
fi

# Get network (default to localhost)
NETWORK=${1:-localhost}
echo "📍 Deploying to network: $NETWORK"
echo ""

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
    echo ""
fi

# Compile contracts
echo "🔨 Compiling contracts..."
npx hardhat compile
echo "✅ Compilation complete"
echo ""

# Run tests
echo "🧪 Running tests..."
npx hardhat test
echo "✅ Tests passed"
echo ""

# Deploy contracts
echo "🚀 Deploying contracts to $NETWORK..."
npx hardhat run scripts/deploy-governance.js --network $NETWORK

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Deployment successful!"
    echo ""
    
    # Verify on Etherscan (if not localhost/hardhat)
    if [ "$NETWORK" != "localhost" ] && [ "$NETWORK" != "hardhat" ]; then
        echo "📝 Verifying contracts on Etherscan..."
        echo "Note: Verification may take a few minutes..."
        echo ""
        
        # Read deployed addresses
        ADDRESSES_FILE="deployments/${NETWORK}-addresses.json"
        
        if [ -f "$ADDRESSES_FILE" ]; then
            echo "✅ Addresses file found: $ADDRESSES_FILE"
            echo "Run verification manually with:"
            echo "npx hardhat verify --network $NETWORK <CONTRACT_ADDRESS> <CONSTRUCTOR_ARGS>"
        else
            echo "⚠️  Addresses file not found"
        fi
    fi
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🎉 DEPLOYMENT COMPLETE"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "📋 Next Steps:"
    echo "   1. Check deployments/${NETWORK}-addresses.json for contract addresses"
    echo "   2. Update frontend configuration with new addresses"
    echo "   3. Run integration tests: npm run test:integration"
    echo "   4. Verify contracts on block explorer"
    echo "   5. Set up monitoring and alerts"
    echo ""
    
else
    echo ""
    echo "❌ Deployment failed"
    exit 1
fi
