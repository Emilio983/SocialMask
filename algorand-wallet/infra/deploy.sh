#!/bin/bash

# Algorand Wallet Deployment Script
# Production deployment to algorand.socialmask.org

set -e

echo "🚀 Starting Algorand Wallet Deployment..."

# Configuration
DOMAIN="algorand.socialmask.org"
WEB_ROOT="/var/www/${DOMAIN}"
NGINX_CONF="/etc/nginx/sites-available/${DOMAIN}"
PROJECT_DIR="/root/algorand-wallet"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root${NC}"
    exit 1
fi

# Step 1: Build frontend
echo -e "${YELLOW}📦 Building frontend...${NC}"
cd ${PROJECT_DIR}/frontend

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    npm install
fi

# Build
npm run build

# Verify build exists
if [ ! -d "dist" ]; then
    echo -e "${RED}❌ Build failed - dist directory not found${NC}"
    exit 1
fi

# Step 2: Create web root if not exists
echo -e "${YELLOW}📁 Setting up web root...${NC}"
mkdir -p ${WEB_ROOT}

# Step 3: Backup existing files (if any)
if [ -d "${WEB_ROOT}" ] && [ "$(ls -A ${WEB_ROOT})" ]; then
    BACKUP_DIR="${WEB_ROOT}_backup_$(date +%Y%m%d_%H%M%S)"
    echo -e "${YELLOW}Backing up existing files to ${BACKUP_DIR}${NC}"
    cp -r ${WEB_ROOT} ${BACKUP_DIR}
fi

# Step 4: Copy built files
echo -e "${YELLOW}📋 Copying files...${NC}"
rm -rf ${WEB_ROOT}/*
cp -r dist/* ${WEB_ROOT}/

# Verify files copied
if [ ! -f "${WEB_ROOT}/index.html" ]; then
    echo -e "${RED}❌ Deployment failed - index.html not found in web root${NC}"
    exit 1
fi

# Step 5: Set permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data ${WEB_ROOT}
chmod -R 755 ${WEB_ROOT}
find ${WEB_ROOT} -type f -exec chmod 644 {} \;

# Step 6: Test Nginx configuration
echo -e "${YELLOW}🧪 Testing Nginx configuration...${NC}"
nginx -t

# Step 7: Reload Nginx
echo -e "${YELLOW}🔄 Reloading Nginx...${NC}"
systemctl reload nginx

# Step 8: Verify deployment
echo -e "${YELLOW}✅ Verifying deployment...${NC}"
sleep 2

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://${DOMAIN})
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✅ HTTPS responding with 200 OK${NC}"
else
    echo -e "${YELLOW}⚠️  HTTPS responding with ${HTTP_CODE}${NC}"
fi

# Show deployment info
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}🌐 Site: https://${DOMAIN}${NC}"
echo -e "${GREEN}📂 Root: ${WEB_ROOT}${NC}"
echo -e "${GREEN}📊 Files: $(ls -1 ${WEB_ROOT} | wc -l) items${NC}"
echo -e "${GREEN}💾 Size: $(du -sh ${WEB_ROOT} | cut -f1)${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Next steps:"
echo "1. Test the wallet at https://${DOMAIN}"
echo "2. Create wallet with passkey"
echo "3. Test ALGO transactions on Mainnet"
echo ""
