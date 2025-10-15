# PowerShell script to comment out console.log statements
Write-Host "`nStarting console.log cleanup..." -ForegroundColor Cyan

$files = @(
    "assets/js/governance/governance-web3.js",
    "assets/js/governance/governance-main.js",
    "assets/js/web3/web3-utils.js",
    "assets/js/web3/web3-connector.js",
    "assets/js/web3/web3-contracts.js",
    "assets/js/web3/web3-signatures.js",
    "assets/js/components/wallet-button.js",
    "assets/js/components/network-badge.js"
)

$totalFixed = 0
$filesFixed = 0

foreach ($file in $files) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        $originalCount = ([regex]::Matches($content, "console\.log\(")).Count
        
        if ($originalCount -gt 0) {
            # Comment out console.log statements
            $content = $content -replace '(\s+)(console\.log\()', '$1// $2'
            Set-Content $file $content -NoNewline
            
            $totalFixed += $originalCount
            $filesFixed++
            
            Write-Host "[OK] $file - Commented out $originalCount console.log" -ForegroundColor Green
        } else {
            Write-Host "[SKIP] $file - No console.log found" -ForegroundColor Gray
        }
    } else {
        Write-Host "[WARN] $file - File not found" -ForegroundColor Yellow
    }
}

Write-Host "`nSUMMARY:" -ForegroundColor Cyan
Write-Host "  Files processed: $filesFixed" -ForegroundColor White
Write-Host "  Console.log commented: $totalFixed" -ForegroundColor White
Write-Host "`nCleanup complete!" -ForegroundColor Green
