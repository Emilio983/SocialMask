#!/bin/bash
# ============================================
# SPHORIA SERVER DIAGNOSIS SCRIPT
# Execute this on the server to diagnose and fix HTTP 500 errors
# ============================================

echo "=== SPHORIA SERVER DIAGNOSIS ==="
echo "$(date)"
echo ""

# 1) Verify files exist and have proper permissions
echo "1) CHECKING FILES AND PERMISSIONS"
echo "=================================="
ls -l /home/u910345901/public_html/api/
echo ""
ls -l /home/u910345901/public_html/config/connection.php
echo ""
stat -c "%A %U:%G %n" /home/u910345901/public_html/api/*.php
echo ""

# 2) Check logs for errors
echo "2) CHECKING ERROR LOGS"
echo "======================"
echo "Checking Apache logs..."
sudo tail -n 50 /var/log/apache2/error.log | grep -E "(Fatal|Error|exception|sphoria)" || echo "No recent errors in Apache log"
echo ""

echo "Checking Nginx logs..."
sudo tail -n 50 /var/log/nginx/error.log | grep -E "(Fatal|Error|exception|sphoria)" || echo "No recent errors in Nginx log"
echo ""

echo "Checking PHP-FPM logs..."
sudo journalctl -u php8.1-fpm -n 50 | grep -E "(Fatal|Error|exception|sphoria)" || echo "No recent errors in PHP-FPM log"
echo ""

echo "Checking hosting error log..."
tail -n 50 /home/u910345901/public_html/error_log | grep -E "(Fatal|Error|exception|sphoria)" || echo "No recent errors in hosting log"
echo ""

# 3) Search for conflicting get_flash functions
echo "3) SEARCHING FOR CONFLICTING FUNCTIONS"
echo "======================================"
echo "Searching for get_flash function definitions..."
grep -R "function get_flash" -n /home/u910345901/public_html/ || echo "No get_flash function definitions found"
echo ""
echo "Searching for get_flash function calls..."
grep -R "get_flash(" -n /home/u910345901/public_html/ | head -20 || echo "No get_flash function calls found"
echo ""

# 4) Reset OPcache (Note: Service restarts require server admin access)
echo "4) RESETTING OPCACHE"
echo "===================="
echo "Resetting OPcache..."
php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "OPcache reset successful\n"; } else { echo "OPcache not available\n"; }'
echo ""

echo "Note: If you have admin access, restart services with:"
echo "sudo systemctl restart php8.1-fpm"
echo "sudo systemctl restart apache2"
echo ""

# 5) Test API endpoints with curl
echo "5) TESTING API ENDPOINTS"
echo "========================"
echo "Testing check_session.php..."
curl -i -v -s 'https://sphoria.org/api/check_session.php' 2>&1 | head -20
echo ""

echo "Testing get_nonce.php..."
curl -i -v -s -X POST 'https://sphoria.org/api/get_nonce.php' -H 'Content-Type: application/json' -d '{"wallet_address":"0x1234567890123456789012345678901234567890"}' 2>&1 | head -20
echo ""

echo "Testing register.php..."
curl -i -v -s -X POST 'https://sphoria.org/api/register.php' -H 'Content-Type: application/json' -d '{}' 2>&1 | head -20
echo ""

echo "Testing login.php..."
curl -i -v -s -X POST 'https://sphoria.org/api/login.php' -H 'Content-Type: application/json' -d '{}' 2>&1 | head -20
echo ""

# 6) Check recent error logs after tests
echo "6) CHECKING LOGS AFTER TESTS"
echo "============================"
echo "Recent error log entries (last 10 lines):"
tail -n 10 /home/u910345901/public_html/error_log
echo ""

echo "=== DIAGNOSIS COMPLETE ==="
echo "$(date)"