# ============================================
# Script de Renombrado: sphoria -> TheSocialMask
# ============================================
# PowerShell script para cambiar todas las referencias
# de "sphoria" a "TheSocialMask" en archivos PHP, JS, SQL
# ============================================

Write-Host "======================================"
Write-Host "Renombrado: sphoria → TheSocialMask"
Write-Host "======================================`n"

# Configuración
$rootPath = "C:\Users\nmemi\OneDrive\Escritorio\html"
$excludeDirs = @("node_modules", "vendor", "uploads", ".git", "MD")

# Patrones de reemplazo
$replacements = @{
    "sphoria" = "thesocialmask"
    "Sphoria" = "TheSocialMask"
    "SPHORIA" = "THE SOCIAL MASK"
    "sphoria_session" = "thesocialmask_session"
    "sphoria_db" = "thesocialmask_db"
    "u910345901_sphoria" = "u910345901_thesocialmask"
}

# Función para procesar archivos
function Replace-InFile {
    param(
        [string]$filePath
    )

    try {
        $content = Get-Content $filePath -Raw -Encoding UTF8
        $original = $content

        foreach ($key in $replacements.Keys) {
            $content = $content -replace $key, $replacements[$key]
        }

        if ($content -ne $original) {
            Set-Content $filePath -Value $content -Encoding UTF8 -NoNewline
            Write-Host "[✓] Actualizado: $filePath" -ForegroundColor Green
            return 1
        }
    }
    catch {
        Write-Host "[✗] Error en: $filePath - $($_.Exception.Message)" -ForegroundColor Red
    }

    return 0
}

# Buscar y procesar archivos PHP
Write-Host "`n[1/4] Procesando archivos PHP..." -ForegroundColor Cyan
$phpFiles = Get-ChildItem -Path $rootPath -Include *.php -Recurse -File |
            Where-Object { $_.FullName -notmatch ($excludeDirs -join '|') }

$phpCount = 0
foreach ($file in $phpFiles) {
    $phpCount += Replace-InFile -filePath $file.FullName
}
Write-Host "PHP: $phpCount archivos actualizados" -ForegroundColor Yellow

# Buscar y procesar archivos JavaScript
Write-Host "`n[2/4] Procesando archivos JavaScript..." -ForegroundColor Cyan
$jsFiles = Get-ChildItem -Path $rootPath -Include *.js -Recurse -File |
           Where-Object { $_.FullName -notmatch ($excludeDirs -join '|') }

$jsCount = 0
foreach ($file in $jsFiles) {
    $jsCount += Replace-InFile -filePath $file.FullName
}
Write-Host "JS: $jsCount archivos actualizados" -ForegroundColor Yellow

# Buscar y procesar archivos SQL
Write-Host "`n[3/4] Procesando archivos SQL..." -ForegroundColor Cyan
$sqlFiles = Get-ChildItem -Path $rootPath -Include *.sql -Recurse -File |
            Where-Object { $_.FullName -notmatch ($excludeDirs -join '|') }

$sqlCount = 0
foreach ($file in $sqlFiles) {
    $sqlCount += Replace-InFile -filePath $file.FullName
}
Write-Host "SQL: $sqlCount archivos actualizados" -ForegroundColor Yellow

# Buscar y procesar archivos HTML
Write-Host "`n[4/4] Procesando archivos HTML..." -ForegroundColor Cyan
$htmlFiles = Get-ChildItem -Path $rootPath -Include *.html -Recurse -File |
             Where-Object { $_.FullName -notmatch ($excludeDirs -join '|') }

$htmlCount = 0
foreach ($file in $htmlFiles) {
    $htmlCount += Replace-InFile -filePath $file.FullName
}
Write-Host "HTML: $htmlCount archivos actualizados" -ForegroundColor Yellow

# Resumen
Write-Host "`n======================================" -ForegroundColor Green
Write-Host "Resumen de cambios:" -ForegroundColor Green
Write-Host "  PHP:  $phpCount archivos" -ForegroundColor White
Write-Host "  JS:   $jsCount archivos" -ForegroundColor White
Write-Host "  SQL:  $sqlCount archivos" -ForegroundColor White
Write-Host "  HTML: $htmlCount archivos" -ForegroundColor White
Write-Host "  TOTAL: $($phpCount + $jsCount + $sqlCount + $htmlCount) archivos" -ForegroundColor White
Write-Host "======================================`n" -ForegroundColor Green

Write-Host "✅ Renombrado completado!" -ForegroundColor Green
Write-Host "`nPróximos pasos:"
Write-Host "1. Verificar que no haya errores de sintaxis"
Write-Host "2. Migrar base de datos (ver MIGRATE_DB_INSTRUCTIONS.txt)"
Write-Host "3. Probar la aplicación en http://localhost`n"

Read-Host "Presiona Enter para salir"
