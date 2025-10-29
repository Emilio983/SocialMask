#!/bin/bash
# Setup SSL certificates for zcash.socialmask.org using Let's Encrypt

set -e

DOMAIN="zcash.socialmask.org"
EMAIL="admin@socialmask.org" # Update with your email

echo "=== Setting up SSL for $DOMAIN ==="

# Install certbot if not present
if ! command -v certbot &> /dev/null; then
    echo "Installing certbot..."
    apt update
    apt install -y certbot python3-certbot-nginx
fi

# Stop nginx temporarily
echo "Stopping nginx temporarily..."
systemctl stop nginx

# Obtain certificate
echo "Obtaining SSL certificate from Let's Encrypt..."
certbot certonly --standalone \
    --non-interactive \
    --agree-tos \
    --email "$EMAIL" \
    -d "$DOMAIN"

# Start nginx
echo "Starting nginx..."
systemctl start nginx

# Switch to production config with SSL
echo "Switching to production nginx config with SSL..."
cp /var/www/zcash.socialmask.org/scripts/nginx-config.conf /etc/nginx/sites-available/zcash.socialmask.org

# Test config
nginx -t

# Reload nginx
nginx -s reload

echo ""
echo "✅ SSL certificate installed successfully!"
echo ""
echo "Certificate location: /etc/letsencrypt/live/$DOMAIN/"
echo "Auto-renewal is enabled via certbot systemd timer"
echo ""
echo "To test renewal: certbot renew --dry-run"
