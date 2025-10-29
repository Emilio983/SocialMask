<?php
// ============================================
// RUN GUNJS SYNC MIGRATION
// Ejecutar migración de tabla gunjs_sync
// ============================================

// Leer .env manualmente
$env_file = __DIR__ . '/../.env';
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value);
    }
}

// Obtener credenciales
$host = $env_vars['DB_HOST'] ?? 'localhost';
$dbname = $env_vars['DB_NAME'] ?? 'thesocialmask';
$user = $env_vars['DB_USER'] ?? 'root';
$password = $env_vars['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . "\n");
}

echo "==============================================\n";
echo "  MIGRACIÓN 013: GUNJS SYNC TABLE\n";
echo "==============================================\n\n";

try {
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/migrations/013_gunjs_sync.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: $sql_file");
    }
    
    echo "📄 Leyendo archivo de migración...\n";
    $sql = file_get_contents($sql_file);
    
    if (empty($sql)) {
        throw new Exception("Migration file is empty");
    }
    
    echo "✅ Archivo leído correctamente\n\n";
    
    // Separar las declaraciones SQL
    // Necesitamos manejar los DELIMITER especiales para triggers/procedures
    $statements = [];
    $current_statement = '';
    $in_delimiter_block = false;
    
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Ignorar comentarios y líneas vacías
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            continue;
        }
        
        // Detectar cambio de delimitador
        if (stripos($trimmed, 'DELIMITER $$') !== false) {
            $in_delimiter_block = true;
            continue;
        }
        
        if (stripos($trimmed, 'DELIMITER ;') !== false) {
            if (!empty($current_statement)) {
                $statements[] = trim($current_statement);
                $current_statement = '';
            }
            $in_delimiter_block = false;
            continue;
        }
        
        // Agregar línea al statement actual
        $current_statement .= $line . "\n";
        
        // Si no estamos en bloque delimitado y encontramos punto y coma, es fin de statement
        if (!$in_delimiter_block && substr(rtrim($line), -1) === ';') {
            $statements[] = trim($current_statement);
            $current_statement = '';
        }
    }
    
    // Agregar último statement si existe
    if (!empty($current_statement)) {
        $statements[] = trim($current_statement);
    }
    
    echo "📊 Se encontraron " . count($statements) . " declaraciones SQL\n\n";
    
    // Ejecutar cada statement
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;
        
        echo "Ejecutando statement " . ($index + 1) . "...\n";
        
        try {
            // Mostrar preview del statement
            $preview = substr($statement, 0, 100);
            if (strlen($statement) > 100) {
                $preview .= '...';
            }
            echo "  → " . str_replace("\n", " ", $preview) . "\n";
            
            $pdo->exec($statement);
            echo "  ✅ OK\n\n";
            $success_count++;
            
        } catch (PDOException $e) {
            // Algunos errores son aceptables (tabla ya existe, etc.)
            $error_msg = $e->getMessage();
            
            if (
                strpos($error_msg, 'already exists') !== false ||
                strpos($error_msg, 'Duplicate') !== false
            ) {
                echo "  ⚠️  Ya existe (ignorado)\n\n";
                $success_count++;
            } else {
                echo "  ❌ ERROR: " . $error_msg . "\n\n";
                $error_count++;
            }
        }
    }
    
    echo "\n==============================================\n";
    echo "  RESUMEN\n";
    echo "==============================================\n";
    echo "✅ Exitosos: $success_count\n";
    echo "❌ Errores: $error_count\n\n";
    
    if ($error_count === 0) {
        echo "🎉 Migración completada exitosamente!\n\n";
        
        // Verificar que la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'gunjs_sync'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabla 'gunjs_sync' creada correctamente\n";
            
            // Mostrar estructura
            echo "\n📋 Estructura de la tabla:\n";
            $stmt = $pdo->query("DESCRIBE gunjs_sync");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  - {$row['Field']}: {$row['Type']}\n";
            }
            
            // Verificar vistas
            echo "\n📊 Vistas creadas:\n";
            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                if (strpos($row[0], 'v_gunjs_') === 0) {
                    echo "  ✅ {$row[0]}\n";
                }
            }
            
            // Verificar stored procedures
            echo "\n⚙️  Stored Procedures:\n";
            $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strpos($row['Name'], 'sp_') === 0) {
                    echo "  ✅ {$row['Name']}\n";
                }
            }
            
            // Verificar funciones
            echo "\n🔧 Funciones:\n";
            $stmt = $pdo->query("SHOW FUNCTION STATUS WHERE Db = DATABASE()");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strpos($row['Name'], 'fn_gunjs_') === 0) {
                    echo "  ✅ {$row['Name']}\n";
                }
            }
            
        } else {
            echo "⚠️  Advertencia: Tabla 'gunjs_sync' no encontrada\n";
        }
    } else {
        echo "⚠️  Migración completada con errores\n";
        echo "Revisa los errores anteriores\n\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO:\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}

echo "\n==============================================\n\n";
