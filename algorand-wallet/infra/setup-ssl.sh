#!/bin/bash

# SSL Certificate Setup Script for algorand.socialmask.org
# Uses Let's Encrypt / Certbot

set -e

DOMAIN="algorand.socialmask.org"
EMAIL="admin@socialmask.org"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "🔒 Setting up SSL certificate for ${DOMAIN}..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root${NC}"
    exit 1
fi

# Check if certbot is installed
if ! command -v certbot &> /dev/null; then
    echo -e "${YELLOW}Installing certbot...${NC}"
    apt-get update -qq
    apt-get install -y certbot python3-certbot-nginx -qq
fi

# Check DNS resolution
echo -e "${YELLOW}Checking DNS resolution...${NC}"
RESOLVED_IP=$(dig +short ${DOMAIN} | head -1)
SERVER_IP=$(curl -s -4 ifconfig.me)

if [ -z "$RESOLVED_IP" ]; then
    echo -e "${RED}DNS not resolved for ${DOMAIN}${NC}"
    echo "Please ensure your DNS A record is set up correctly"
    exit 1
fi

echo -e "${GREEN}✅ DNS resolves to: ${RESOLVED_IP}${NC}"
echo -e "${GREEN}✅ Server IP: ${SERVER_IP}${NC}"

if [ "$RESOLVED_IP" != "$SERVER_IP" ]; then
    echo -e "${YELLOW}⚠️  Warning: DNS IP (${RESOLVED_IP}) differs from server IP (${SERVER_IP})${NC}"
    echo -e "${YELLOW}Continuing anyway - this might be correct if behind proxy...${NC}"
fi

# Check if certificate already exists
if [ -d "/etc/letsencrypt/live/${DOMAIN}" ]; then
    echo -e "${YELLOW}Certificate already exists for ${DOMAIN}${NC}"
    echo -e "${YELLOW}Attempting to renew...${NC}"
    certbot renew --force-renewal -d ${DOMAIN}
else
    # Obtain certificate
    echo -e "${YELLOW}Obtaining SSL certificate...${NC}"
    certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos --email ${EMAIL} --redirect
fi

# Update nginx config to enable HTTPS section
NGINX_CONF="/etc/nginx/sites-available/${DOMAIN}"
if [ -f "$NGINX_CONF" ]; then
    echo -e "${YELLOW}Updating Nginx configuration for HTTPS...${NC}"
    # Certbot should have already done this, but we can verify
    if grep -q "443 ssl" "$NGINX_CONF"; then
        echo -e "${GREEN}✅ HTTPS configuration already present${NC}"
    else
        echo -e "${YELLOW}⚠️  HTTPS not configured - certbot may have failed${NC}"
    fi
fi

# Test nginx configuration
echo -e "${YELLOW}Testing Nginx configuration...${NC}"
nginx -t

# Reload nginx
echo -e "${YELLOW}Reloading Nginx...${NC}"
systemctl reload nginx

# Test renewal
echo -e "${YELLOW}Testing certificate renewal...${NC}"
certbot renew --dry-run

# Setup auto-renewal (if not already set)
if ! crontab -l 2>/dev/null | grep -q "certbot renew"; then
    echo -e "${YELLOW}Setting up auto-renewal cron job...${NC}"
    (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx' >> /var/log/certbot-renew.log 2>&1") | crontab -
    echo -e "${GREEN}✅ Auto-renewal cron job configured${NC}"
else
    echo -e "${GREEN}✅ Auto-renewal already configured${NC}"
fi

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ SSL certificate installed successfully!${NC}"
echo -e "${GREEN}🔒 Certificate will auto-renew at 3 AM daily${NC}"
echo -e "${GREEN}🌐 Visit: https://${DOMAIN}${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Show certificate info
echo -e "${YELLOW}Certificate information:${NC}"
certbot certificates -d ${DOMAIN} 2>/dev/null || echo "Run 'certbot certificates' to view cert details"
