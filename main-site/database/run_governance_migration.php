<?php
/**
 * Script para ejecutar Migration 008 - Governance System
 * Ejecutar desde línea de comandos: php run_governance_migration.php
 */

// Cargar .env manualmente
$env_file = __DIR__ . '/../.env';
if (!file_exists($env_file)) {
    die("ERROR: Archivo .env no encontrado\n");
}

$env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($env_lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') === false) continue;
    
    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    
    // Remover comillas
    if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
        $value = $matches[2];
    }
    
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// Configuración de DB desde variables de entorno
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'thesocialmask';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// Conectar a la base de datos
try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    echo "✅ Conectado a la base de datos: $db_name\n\n";
    
} catch (PDOException $e) {
    die("❌ ERROR de conexión: " . $e->getMessage() . "\n");
}

echo "========================================\n";
echo "MIGRATION 008: GOVERNANCE SYSTEM\n";
echo "========================================\n\n";

try {
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/migrations/008_governance_system.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Archivo de migración no encontrado: $sql_file");
    }
    
    echo "📂 Leyendo archivo: 008_governance_system.sql\n";
    $sql = file_get_contents($sql_file);
    
    // Separar por statements (usando punto y coma como delimitador)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Filtrar comentarios y líneas vacías
            return !empty($stmt) 
                && !preg_match('/^\s*--/', $stmt) 
                && !preg_match('/^\s*\/\*/', $stmt);
        }
    );
    
    echo "📊 Total de statements a ejecutar: " . count($statements) . "\n\n";
    
    // Ejecutar cada statement
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $index => $statement) {
        try {
            // Limpiar el statement
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            // Mostrar progreso
            $preview = substr($statement, 0, 50);
            echo "⏳ Ejecutando statement " . ($index + 1) . "...\n";
            echo "   " . $preview . "...\n";
            
            // Ejecutar
            $pdo->exec($statement);
            $executed++;
            echo "   ✅ OK\n\n";
            
        } catch (PDOException $e) {
            $errors++;
            echo "   ❌ ERROR: " . $e->getMessage() . "\n\n";
            
            // Si la tabla ya existe, no es un error crítico
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "   ℹ️  La tabla ya existe, continuando...\n\n";
                $errors--;
            }
        }
    }
    
    echo "========================================\n";
    echo "RESUMEN\n";
    echo "========================================\n";
    echo "✅ Statements ejecutados: $executed\n";
    echo "❌ Errores: $errors\n";
    
    if ($errors === 0) {
        echo "\n🎉 ¡Migración completada exitosamente!\n";
        
        // Verificar tablas creadas
        echo "\n📋 Verificando tablas creadas:\n";
        $tables = [
            'governance_proposals',
            'governance_votes',
            'governance_delegations',
            'governance_actions',
            'governance_timeline'
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "   ✅ $table\n";
            } else {
                echo "   ❌ $table (no encontrada)\n";
            }
        }
        
        // Verificar vistas
        echo "\n📊 Verificando vistas creadas:\n";
        $views = [
            'governance_proposal_stats',
            'governance_user_voting_power'
        ];
        
        foreach ($views as $view) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$view'");
            if ($stmt->rowCount() > 0) {
                echo "   ✅ $view\n";
            } else {
                echo "   ❌ $view (no encontrada)\n";
            }
        }
        
        echo "\n✅ Sistema de gobernanza listo para usar!\n";
    } else {
        echo "\n⚠️  Migración completada con $errors errores.\n";
        echo "Revisa los mensajes de error arriba.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR FATAL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n========================================\n";
?>
