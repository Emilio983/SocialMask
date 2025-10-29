#!/bin/bash

# Algorand Wallet - Deployment Verification Script
# Run this after deployment to verify everything is working

set -e

DOMAIN="algorand.socialmask.org"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "🔍 Algorand Wallet Deployment Verification"
echo "==========================================="
echo ""

# Function to check and report
check() {
    local test_name="$1"
    local command="$2"
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ $test_name${NC}"
        return 0
    else
        echo -e "${RED}❌ $test_name${NC}"
        return 1
    fi
}

# Function to check and show output
check_output() {
    local test_name="$1"
    local command="$2"
    
    output=$(eval "$command" 2>&1)
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ $test_name${NC}"
        echo "   $output"
        return 0
    else
        echo -e "${RED}❌ $test_name${NC}"
        echo "   $output"
        return 1
    fi
}

echo "📋 Prerequisites"
echo "---------------"
check "Node.js installed" "command -v node"
check "npm installed" "command -v npm"
check "Nginx installed" "command -v nginx"
check "Certbot installed" "command -v certbot"
echo ""

echo "🌐 DNS Configuration"
echo "-------------------"
check_output "DNS resolves" "dig +short $DOMAIN | head -1"
echo ""

echo "🔧 Nginx Status"
echo "---------------"
check "Nginx running" "systemctl is-active nginx"
check "Nginx config valid" "nginx -t"
check "Site config exists" "test -f /etc/nginx/sites-available/$DOMAIN"
check "Site enabled" "test -L /etc/nginx/sites-enabled/$DOMAIN"
echo ""

echo "📁 File Structure"
echo "----------------"
check "Web root exists" "test -d /var/www/$DOMAIN"
check "index.html exists" "test -f /var/www/$DOMAIN/index.html"
check "Assets directory" "test -d /var/www/$DOMAIN/assets"
check "Correct permissions" "test -O /var/www/$DOMAIN || test -G /var/www/$DOMAIN"
echo ""

echo "🔒 SSL Certificate"
echo "-----------------"
if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    check "SSL cert exists" "test -f /etc/letsencrypt/live/$DOMAIN/fullchain.pem"
    check "SSL key exists" "test -f /etc/letsencrypt/live/$DOMAIN/privkey.pem"
    
    # Check cert expiry
    expiry=$(openssl x509 -enddate -noout -in /etc/letsencrypt/live/$DOMAIN/fullchain.pem 2>/dev/null | cut -d= -f2)
    if [ -n "$expiry" ]; then
        echo -e "${GREEN}✅ Certificate expires: $expiry${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  SSL certificate not found - run ./setup-ssl.sh${NC}"
fi
echo ""

echo "🌍 HTTP/HTTPS Access"
echo "-------------------"
# Test HTTP (should redirect to HTTPS)
http_code=$(curl -s -o /dev/null -w "%{http_code}" http://$DOMAIN 2>/dev/null || echo "000")
if [ "$http_code" = "301" ] || [ "$http_code" = "302" ]; then
    echo -e "${GREEN}✅ HTTP redirects to HTTPS (${http_code})${NC}"
elif [ "$http_code" = "000" ]; then
    echo -e "${RED}❌ Cannot reach HTTP endpoint${NC}"
else
    echo -e "${YELLOW}⚠️  HTTP returns ${http_code} (expected 301/302)${NC}"
fi

# Test HTTPS
https_code=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN 2>/dev/null || echo "000")
if [ "$https_code" = "200" ]; then
    echo -e "${GREEN}✅ HTTPS working (${https_code})${NC}"
elif [ "$https_code" = "000" ]; then
    echo -e "${RED}❌ Cannot reach HTTPS endpoint${NC}"
else
    echo -e "${YELLOW}⚠️  HTTPS returns ${https_code} (expected 200)${NC}"
fi
echo ""

echo "🔐 Security Headers"
echo "------------------"
if [ "$https_code" = "200" ]; then
    headers=$(curl -s -I https://$DOMAIN)
    
    check "Strict-Transport-Security" "echo '$headers' | grep -q 'Strict-Transport-Security'"
    check "X-Frame-Options" "echo '$headers' | grep -q 'X-Frame-Options'"
    check "X-Content-Type-Options" "echo '$headers' | grep -q 'X-Content-Type-Options'"
    check "X-XSS-Protection" "echo '$headers' | grep -q 'X-XSS-Protection'"
else
    echo -e "${YELLOW}⚠️  Cannot check headers (HTTPS not accessible)${NC}"
fi
echo ""

echo "📦 Application Files"
echo "-------------------"
if [ "$https_code" = "200" ]; then
    # Check if it's actually serving the app
    content=$(curl -s https://$DOMAIN)
    
    check "HTML title present" "echo '$content' | grep -q 'Algorand'"
    check "React root div" "echo '$content' | grep -q 'id=\"root\"'"
    check "Assets loaded" "echo '$content' | grep -q 'assets/'"
else
    echo -e "${YELLOW}⚠️  Cannot verify app (HTTPS not accessible)${NC}"
fi
echo ""

echo "🔥 Firewall Status"
echo "-----------------"
if command -v ufw > /dev/null 2>&1; then
    if ufw status | grep -q "Status: active"; then
        echo -e "${GREEN}✅ UFW active${NC}"
        check "Port 80 allowed" "ufw status | grep -q '80'"
        check "Port 443 allowed" "ufw status | grep -q '443'"
    else
        echo -e "${YELLOW}⚠️  UFW not active${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  UFW not installed${NC}"
fi
echo ""

echo "📊 System Resources"
echo "------------------"
# Disk space
disk_usage=$(df -h /var/www | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$disk_usage" -lt 80 ]; then
    echo -e "${GREEN}✅ Disk usage: ${disk_usage}%${NC}"
else
    echo -e "${YELLOW}⚠️  Disk usage high: ${disk_usage}%${NC}"
fi

# Memory
mem_free=$(free -m | awk 'NR==2{printf "%.0f", $7*100/$2}')
if [ "$mem_free" -gt 20 ]; then
    echo -e "${GREEN}✅ Available memory: ${mem_free}%${NC}"
else
    echo -e "${YELLOW}⚠️  Low memory: ${mem_free}% free${NC}"
fi
echo ""

echo "📝 Log Files"
echo "-----------"
check "Access log exists" "test -f /var/log/nginx/$DOMAIN.access.log"
check "Error log exists" "test -f /var/log/nginx/$DOMAIN.error.log"

# Check for recent errors
if [ -f "/var/log/nginx/$DOMAIN.error.log" ]; then
    error_count=$(tail -100 /var/log/nginx/$DOMAIN.error.log 2>/dev/null | grep -c "error" || echo "0")
    if [ "$error_count" -eq 0 ]; then
        echo -e "${GREEN}✅ No recent errors in logs${NC}"
    else
        echo -e "${YELLOW}⚠️  ${error_count} errors in last 100 log lines${NC}"
    fi
fi
echo ""

echo "🧪 Quick Functionality Test"
echo "---------------------------"
if [ "$https_code" = "200" ]; then
    # Check if JavaScript is loaded
    js_files=$(curl -s https://$DOMAIN | grep -o 'src="[^"]*\.js"' | wc -l)
    if [ "$js_files" -gt 0 ]; then
        echo -e "${GREEN}✅ JavaScript files referenced: ${js_files}${NC}"
    else
        echo -e "${RED}❌ No JavaScript files found${NC}"
    fi
    
    # Check CSS
    css_files=$(curl -s https://$DOMAIN | grep -o 'href="[^"]*\.css"' | wc -l)
    if [ "$css_files" -gt 0 ]; then
        echo -e "${GREEN}✅ CSS files referenced: ${css_files}${NC}"
    else
        echo -e "${RED}❌ No CSS files found${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Cannot run functionality tests (site not accessible)${NC}"
fi
echo ""

echo "📱 Accessibility Tests"
echo "---------------------"
if [ "$https_code" = "200" ]; then
    # Check content-type
    content_type=$(curl -s -I https://$DOMAIN | grep -i "content-type" | awk '{print $2}' | tr -d '\r')
    if [[ "$content_type" == *"text/html"* ]]; then
        echo -e "${GREEN}✅ Correct content-type: $content_type${NC}"
    else
        echo -e "${YELLOW}⚠️  Unexpected content-type: $content_type${NC}"
    fi
    
    # Check content-length
    content_length=$(curl -s -I https://$DOMAIN | grep -i "content-length" | awk '{print $2}' | tr -d '\r')
    if [ -n "$content_length" ] && [ "$content_length" -gt 0 ]; then
        echo -e "${GREEN}✅ Content size: $content_length bytes${NC}"
    fi
fi
echo ""

echo "🎯 Summary"
echo "=========="
echo ""

# Overall status
if [ "$https_code" = "200" ]; then
    echo -e "${GREEN}✅ DEPLOYMENT SUCCESSFUL${NC}"
    echo ""
    echo "🌐 Site URL: https://$DOMAIN"
    echo "📊 Status: Online and accessible"
    echo "🔒 SSL: Configured"
    echo ""
    echo "Next steps:"
    echo "1. Test wallet creation with passkey"
    echo "2. Verify ALGO transactions"
    echo "3. Test Binance integration"
    echo "4. Record demo video"
else
    echo -e "${RED}⚠️  DEPLOYMENT INCOMPLETE${NC}"
    echo ""
    echo "Issues found. Please review the checks above."
    echo ""
    echo "Common fixes:"
    echo "1. Ensure DNS has propagated: dig $DOMAIN"
    echo "2. Run SSL setup: ./setup-ssl.sh"
    echo "3. Check Nginx config: nginx -t"
    echo "4. Restart Nginx: systemctl restart nginx"
fi

echo ""
echo "📋 Quick Commands"
echo "----------------"
echo "View access logs:  tail -f /var/log/nginx/$DOMAIN.access.log"
echo "View error logs:   tail -f /var/log/nginx/$DOMAIN.error.log"
echo "Restart Nginx:     systemctl restart nginx"
echo "Check SSL cert:    certbot certificates"
echo "Renew SSL:         certbot renew"
echo "Redeploy:          cd /root/algorand-wallet/infra && ./deploy.sh"
echo ""
