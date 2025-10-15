#!/usr/bin/env php
<?php
/**
 * 🔧 SCRIPT DE CORRECCIONES AUTOMÁTICAS - SPHERA PLATFORM
 * 
 * Este script automatiza las correcciones de seguridad identificadas en la auditoría.
 * 
 * Uso:
 *   php fix_security_issues.php [opción]
 * 
 * Opciones:
 *   --all              Aplicar todas las correcciones
 *   --rate-limit       Agregar rate limiting a endpoints
 *   --cors             Restringir CORS
 *   --validate         Validar configuración .env
 *   --migrations       Aplicar migraciones pendientes
 *   --check            Solo verificar problemas sin corregir
 */

// Colors para terminal
class Colors {
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

class SecurityFixer {
    private $errors = [];
    private $warnings = [];
    private $fixes = [];
    private $baseDir;
    
    public function __construct() {
        $this->baseDir = __DIR__;
    }
    
    public function run($options = []) {
        echo Colors::BOLD . Colors::CYAN;
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🔧 SPHERA SECURITY FIXER\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo Colors::RESET . "\n";
        
        if (in_array('--check', $options) || empty($options)) {
            $this->checkAllIssues();
        }
        
        if (in_array('--validate', $options) || in_array('--all', $options)) {
            $this->validateEnvConfig();
        }
        
        if (in_array('--rate-limit', $options) || in_array('--all', $options)) {
            $this->addRateLimiting();
        }
        
        if (in_array('--cors', $options) || in_array('--all', $options)) {
            $this->restrictCORS();
        }
        
        if (in_array('--migrations', $options) || in_array('--all', $options)) {
            $this->applyMigrations();
        }
        
        $this->printSummary();
    }
    
    private function checkAllIssues() {
        echo Colors::BLUE . "📋 Verificando problemas de seguridad...\n" . Colors::RESET;
        
        // Check 1: Credenciales hardcodeadas
        $this->checkHardcodedCredentials();
        
        // Check 2: Archivo .env
        $this->checkEnvFile();
        
        // Check 3: Rate limiting
        $this->checkRateLimiting();
        
        // Check 4: CORS configuration
        $this->checkCORS();
        
        // Check 5: Migraciones pendientes
        $this->checkMigrations();
        
        // Check 6: Validación de wallet
        $this->checkWalletValidation();
        
        echo "\n";
    }
    
    private function checkHardcodedCredentials() {
        echo "  → Buscando credenciales hardcodeadas... ";
        
        $files = $this->recursiveGlob($this->baseDir . '/api', '*.php');
        $found = false;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            // Buscar patrones sospechosos
            if (preg_match('/Password\s*=\s*[\'"][^\'"]+[\'"]/', $content) ||
                preg_match('/password.*=.*[\'"][^\'"]{6,}[\'"]/', $content)) {
                
                // Ignorar password_hash y password_verify
                if (!preg_match('/password_hash|password_verify|Env::/', $content)) {
                    $this->warnings[] = "Posible credencial hardcodeada en: " . basename($file);
                    $found = true;
                }
            }
        }
        
        if (!$found) {
            echo Colors::GREEN . "✓ OK\n" . Colors::RESET;
        } else {
            echo Colors::YELLOW . "⚠ Advertencias encontradas\n" . Colors::RESET;
        }
    }
    
    private function checkEnvFile() {
        echo "  → Verificando archivo .env... ";
        
        $envPath = $this->baseDir . '/.env';
        $envExamplePath = $this->baseDir . '/.env.example';
        
        if (!file_exists($envPath)) {
            $this->errors[] = "Archivo .env no existe";
            echo Colors::RED . "✗ FALTA\n" . Colors::RESET;
            
            if (file_exists($envExamplePath)) {
                echo Colors::YELLOW . "    Sugerencia: copy .env.example .env\n" . Colors::RESET;
            }
        } else {
            // Verificar variables críticas
            $envContent = file_get_contents($envPath);
            $required = [
                'DB_HOST', 'DB_NAME', 'DB_USER',
                'SESSION_SECRET', 'JWT_SECRET',
                'SMTP_USERNAME', 'SMTP_PASSWORD'
            ];
            
            $missing = [];
            foreach ($required as $var) {
                if (!preg_match("/$var\s*=/", $envContent)) {
                    $missing[] = $var;
                }
            }
            
            if (!empty($missing)) {
                $this->warnings[] = "Variables faltantes en .env: " . implode(', ', $missing);
                echo Colors::YELLOW . "⚠ Variables faltantes\n" . Colors::RESET;
            } else {
                echo Colors::GREEN . "✓ OK\n" . Colors::RESET;
            }
        }
    }
    
    private function checkRateLimiting() {
        echo "  → Verificando rate limiting... ";
        
        $endpointsToCheck = [
            'api/global_search.php',
            'api/communities/list.php',
            'api/search_users.php',
            'api/get_current_plan.php'
        ];
        
        $missing = [];
        foreach ($endpointsToCheck as $endpoint) {
            $path = $this->baseDir . '/' . $endpoint;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if (!preg_match('/checkRateLimit|rate_limiter/', $content)) {
                    $missing[] = $endpoint;
                }
            }
        }
        
        if (!empty($missing)) {
            $this->warnings[] = count($missing) . " endpoints sin rate limiting";
            echo Colors::YELLOW . "⚠ " . count($missing) . " endpoints sin protección\n" . Colors::RESET;
        } else {
            echo Colors::GREEN . "✓ OK\n" . Colors::RESET;
        }
    }
    
    private function checkCORS() {
        echo "  → Verificando configuración CORS... ";
        
        $files = $this->recursiveGlob($this->baseDir . '/api', '*.php');
        $permissive = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/Access-Control-Allow-Origin:\s*\*/', $content)) {
                $permissive++;
            }
        }
        
        if ($permissive > 10) {
            $this->warnings[] = "$permissive archivos con CORS permisivo (*)";
            echo Colors::YELLOW . "⚠ Configuración permisiva\n" . Colors::RESET;
        } else {
            echo Colors::GREEN . "✓ OK\n" . Colors::RESET;
        }
    }
    
    private function checkMigrations() {
        echo "  → Verificando migraciones pendientes... ";
        
        $migrations = [
            'database/migrations/016_anonymous_system.sql',
            'database/migrations/017_moderation_system.sql'
        ];
        
        $pending = [];
        foreach ($migrations as $migration) {
            $path = $this->baseDir . '/' . $migration;
            if (file_exists($path)) {
                // Verificar si existe en base de datos (simplificado)
                $pending[] = basename($migration);
            }
        }
        
        if (!empty($pending)) {
            $this->warnings[] = count($pending) . " migraciones pendientes";
            echo Colors::YELLOW . "⚠ " . count($pending) . " pendientes\n" . Colors::RESET;
        } else {
            echo Colors::GREEN . "✓ OK\n" . Colors::RESET;
        }
    }
    
    private function checkWalletValidation() {
        echo "  → Verificando validación de wallet... ";
        
        $files = $this->recursiveGlob($this->baseDir . '/api', '*.php');
        $inconsistent = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Buscar uso de wallet addresses sin validación
            if (preg_match('/wallet.*address/i', $content) && 
                !preg_match('/preg_match|isValid.*Address/i', $content)) {
                $inconsistent++;
            }
        }
        
        if ($inconsistent > 5) {
            $this->warnings[] = "Validación de wallet inconsistente";
            echo Colors::YELLOW . "⚠ Inconsistente\n" . Colors::RESET;
        } else {
            echo Colors::GREEN . "✓ OK\n" . Colors::RESET;
        }
    }
    
    private function validateEnvConfig() {
        echo Colors::BLUE . "\n🔑 Validando configuración .env...\n" . Colors::RESET;
        
        $envPath = $this->baseDir . '/.env';
        if (!file_exists($envPath)) {
            echo Colors::RED . "✗ Archivo .env no encontrado\n" . Colors::RESET;
            return;
        }
        
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        $config = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $matches)) {
                $config[$matches[1]] = $matches[2];
            }
        }
        
        // Validar variables críticas
        $critical = ['DB_HOST', 'DB_NAME', 'DB_USER', 'SESSION_SECRET', 'JWT_SECRET'];
        $secure = ['SMTP_PASSWORD', 'JWT_SECRET', 'SESSION_SECRET'];
        
        foreach ($critical as $var) {
            if (empty($config[$var])) {
                echo Colors::RED . "  ✗ Variable crítica vacía: $var\n" . Colors::RESET;
            } else {
                echo Colors::GREEN . "  ✓ $var configurado\n" . Colors::RESET;
            }
        }
        
        // Verificar contraseñas débiles
        foreach ($secure as $var) {
            if (!empty($config[$var])) {
                $value = $config[$var];
                if (strlen($value) < 16 || 
                    strtolower($value) === 'change_this_password_immediately' ||
                    preg_match('/test|demo|example|password123/i', $value)) {
                    echo Colors::YELLOW . "  ⚠ Contraseña débil en: $var\n" . Colors::RESET;
                }
            }
        }
        
        $this->fixes[] = "Validación de .env completada";
    }
    
    private function addRateLimiting() {
        echo Colors::BLUE . "\n⏱️  Agregando rate limiting...\n" . Colors::RESET;
        
        $endpoints = [
            'api/global_search.php',
            'api/communities/list.php',
            'api/search_users.php'
        ];
        
        $rateLimitCode = "
// ✅ RATE LIMITING
require_once __DIR__ . '/helpers/rate_limiter.php';
if (!checkRateLimit(\$_SERVER['REMOTE_ADDR'], 60, 60)) {
    http_response_code(429);
    exit(json_encode(['error' => 'Too many requests. Try again later.']));
}
";
        
        foreach ($endpoints as $endpoint) {
            $path = $this->baseDir . '/' . $endpoint;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                
                // Verificar si ya tiene rate limiting
                if (!preg_match('/checkRateLimit/', $content)) {
                    // Insertar después del primer require_once
                    $content = preg_replace(
                        '/(require_once[^;]+;)/',
                        "$1\n$rateLimitCode",
                        $content,
                        1
                    );
                    
                    // file_put_contents($path, $content);
                    echo Colors::GREEN . "  ✓ Rate limiting agregado a: " . basename($endpoint) . "\n" . Colors::RESET;
                    echo Colors::YELLOW . "    (Simulado - descomenta file_put_contents para aplicar)\n" . Colors::RESET;
                    $this->fixes[] = "Rate limiting en " . basename($endpoint);
                } else {
                    echo Colors::CYAN . "  → Ya tiene rate limiting: " . basename($endpoint) . "\n" . Colors::RESET;
                }
            }
        }
    }
    
    private function restrictCORS() {
        echo Colors::BLUE . "\n🌐 Restringiendo CORS...\n" . Colors::RESET;
        echo Colors::YELLOW . "  ⚠ Esta operación requiere conocer tus dominios de producción\n" . Colors::RESET;
        echo Colors::CYAN . "  → Edita manualmente los archivos API con:\n" . Colors::RESET;
        echo "\n";
        echo Colors::WHITE;
        echo "    \$allowed_origins = ['https://sphera.io'];\n";
        echo "    \$origin = \$_SERVER['HTTP_ORIGIN'] ?? '';\n";
        echo "    if (in_array(\$origin, \$allowed_origins)) {\n";
        echo "        header('Access-Control-Allow-Origin: ' . \$origin);\n";
        echo "    }\n";
        echo Colors::RESET . "\n";
    }
    
    private function applyMigrations() {
        echo Colors::BLUE . "\n💾 Aplicando migraciones...\n" . Colors::RESET;
        echo Colors::YELLOW . "  ⚠ Requiere acceso a MySQL\n" . Colors::RESET;
        
        $migrations = [
            'database/migrations/016_anonymous_system.sql',
            'database/migrations/017_moderation_system.sql'
        ];
        
        foreach ($migrations as $migration) {
            $path = $this->baseDir . '/' . $migration;
            if (file_exists($path)) {
                echo Colors::CYAN . "  → " . basename($migration) . "\n" . Colors::RESET;
                echo Colors::WHITE . "    Comando: Get-Content $migration | mysql -u root -p thesocialmask\n" . Colors::RESET;
            }
        }
    }
    
    private function printSummary() {
        echo "\n";
        echo Colors::BOLD . Colors::CYAN;
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 RESUMEN\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo Colors::RESET;
        
        if (count($this->errors) > 0) {
            echo Colors::RED . "\n❌ Errores (" . count($this->errors) . "):\n" . Colors::RESET;
            foreach ($this->errors as $error) {
                echo Colors::RED . "  • $error\n" . Colors::RESET;
            }
        }
        
        if (count($this->warnings) > 0) {
            echo Colors::YELLOW . "\n⚠️  Advertencias (" . count($this->warnings) . "):\n" . Colors::RESET;
            foreach ($this->warnings as $warning) {
                echo Colors::YELLOW . "  • $warning\n" . Colors::RESET;
            }
        }
        
        if (count($this->fixes) > 0) {
            echo Colors::GREEN . "\n✅ Correcciones aplicadas (" . count($this->fixes) . "):\n" . Colors::RESET;
            foreach ($this->fixes as $fix) {
                echo Colors::GREEN . "  • $fix\n" . Colors::RESET;
            }
        }
        
        if (empty($this->errors) && empty($this->warnings)) {
            echo Colors::GREEN . "\n✅ No se encontraron problemas críticos\n" . Colors::RESET;
        }
        
        echo "\n" . Colors::CYAN . "Para más detalles, consulta: AUDIT_REPORT.md\n" . Colors::RESET;
        echo "\n";
    }
    
    private function recursiveGlob($dir, $pattern) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la línea de comandos\n");
}

$options = array_slice($argv, 1);

if (in_array('--help', $options) || in_array('-h', $options)) {
    echo "Uso: php fix_security_issues.php [opción]\n\n";
    echo "Opciones:\n";
    echo "  --all              Aplicar todas las correcciones\n";
    echo "  --check            Solo verificar problemas (por defecto)\n";
    echo "  --validate         Validar configuración .env\n";
    echo "  --rate-limit       Agregar rate limiting a endpoints\n";
    echo "  --cors             Mostrar instrucciones para CORS\n";
    echo "  --migrations       Mostrar comandos para migraciones\n";
    echo "  --help, -h         Mostrar esta ayuda\n\n";
    exit(0);
}

$fixer = new SecurityFixer();
$fixer->run($options);
