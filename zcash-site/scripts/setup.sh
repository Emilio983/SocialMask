#!/bin/bash
# Setup script for Zcash Seedless Wallet

set -e

echo "=== Zcash Seedless Wallet - Setup ==="
echo ""

# Check prerequisites
echo "[1/6] Checking prerequisites..."

if ! command -v docker &> /dev/null; then
    echo "❌ Docker not found. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose not found. Please install Docker Compose first."
    exit 1
fi

echo "✓ Docker and Docker Compose found"

# Check Node.js (for frontend development)
if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v)
    echo "✓ Node.js $NODE_VERSION found"
else
    echo "⚠️  Node.js not found (needed for frontend development)"
fi

# Check Rust (for WASM build)
if command -v cargo &> /dev/null; then
    RUST_VERSION=$(cargo --version)
    echo "✓ Rust found: $RUST_VERSION"
else
    echo "⚠️  Rust not found (needed for WASM build)"
fi

echo ""
echo "[2/6] Setting up environment..."

# Copy .env if not exists
if [ ! -f infra/.env ]; then
    cp infra/.env.example infra/.env
    echo "✓ Created infra/.env from example"
    echo "⚠️  Please edit infra/.env with your configuration"
else
    echo "✓ infra/.env already exists"
fi

if [ ! -f frontend/.env ]; then
    echo "VITE_API_BASE_URL=http://localhost:8080" > frontend/.env
    echo "VITE_LIGHTWALLETD_URL=http://localhost:9068" >> frontend/.env
    echo "VITE_NETWORK=testnet" >> frontend/.env
    echo "✓ Created frontend/.env"
else
    echo "✓ frontend/.env already exists"
fi

echo ""
echo "[3/6] Installing backend dependencies..."
cd backend
if [ -f package.json ]; then
    npm install
    echo "✓ Backend dependencies installed"
else
    echo "❌ backend/package.json not found"
    exit 1
fi
cd ..

echo ""
echo "[4/6] Installing frontend dependencies..."
cd frontend
if [ -f package.json ]; then
    npm install
    echo "✓ Frontend dependencies installed"
else
    echo "❌ frontend/package.json not found"
    exit 1
fi
cd ..

echo ""
echo "[5/6] Building WASM module (optional)..."
if command -v wasm-pack &> /dev/null; then
    cd wasm
    ./build.sh
    cd ..
    echo "✓ WASM module built"
else
    echo "⚠️  wasm-pack not found, skipping WASM build (MVP uses stubs)"
fi

echo ""
echo "[6/6] Starting Docker services..."
cd infra
docker-compose up -d
echo "✓ Docker services started"
cd ..

echo ""
echo "==================================="
echo "✅ Setup complete!"
echo ""
echo "Next steps:"
echo "  1. Wait for zebrad to sync (check: docker logs -f zcash-zebrad)"
echo "     This can take 24-48 hours for testnet from genesis"
echo ""
echo "  2. Start frontend development server:"
echo "     cd frontend && npm run dev"
echo ""
echo "  3. Open browser: http://localhost:5173"
echo ""
echo "  4. Check backend health: curl http://localhost:8080/health"
echo ""
echo "For production deployment, see docs/README.md"
echo "==================================="
