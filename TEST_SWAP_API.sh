#!/bin/bash
# Test swap API endpoints

echo "=== Testing Swap APIs ==="
echo ""

# Check if Node backend is running
echo "1. Backend Node.js status:"
ps aux | grep "node.*backend-node" | grep -v grep || echo "❌ Node backend NOT running"
echo ""

# Check backend port
echo "2. Backend listening on port 3001:"
ss -tlnp | grep :3001 || echo "❌ Port 3001 not listening"
echo ""

# Test PHP balances endpoint
echo "3. Testing PHP balances.php:"
curl -s -X GET "http://localhost/api/wallet/balances.php" \
  -H "Cookie: thesocialmask_session=test" \
  | jq '.' || echo "❌ Failed"
echo ""

# Test Node swap-quote endpoint (via PHP proxy)
echo "4. Testing swap quote (need logged in session):"
echo "   Run this in browser console after login:"
echo "   fetch('/api/wallet/swap_quote.php?fromToken=USDT&toToken=SPHE&amount=1&slippage=1')"
echo ""

# Check Node.js logs
echo "5. Recent Node.js logs:"
tail -n 20 /var/www/html/backend-node/*.log 2>/dev/null || echo "No logs found"
echo ""

# Check 0x API key
echo "6. 0x API Key configured:"
grep "ZEROX_API_KEY" /var/www/html/.env | sed 's/=.*/=***HIDDEN***/' || echo "❌ Not found"
echo ""

echo "=== Test Complete ==="
echo ""
echo "To test in browser (must be logged in):"
echo "1. Open DevTools Console"
echo "2. Run: fetch('/api/wallet/balances.php').then(r=>r.json()).then(console.log)"
echo "3. Run: fetch('/api/wallet/swap_quote.php?fromToken=USDT&toToken=SPHE&amount=1&slippage=1').then(r=>r.json()).then(console.log)"
