#!/usr/bin/env php
<?php
/**
 * Script de Verificación Post-Rebranding
 * Verifica que todos los cambios de Sphoria → TheSocialMask
 * se hayan aplicado correctamente
 */

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN POST-REBRANDING: Sphoria → TheSocialMask        ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$errors = 0;
$warnings = 0;
$success = 0;

// Test 1: Verificar que .env tenga el nombre correcto
echo "📋 Test 1: Verificar archivo .env\n";
echo "────────────────────────────────────────────────────────────────\n";

if (!file_exists('.env')) {
    echo "❌ ERROR: Archivo .env no encontrado\n";
    $errors++;
} else {
    $envContent = file_get_contents('.env');

    // Verificar DB_NAME
    if (preg_match('/DB_NAME=thesocialmask/', $envContent)) {
        echo "✅ DB_NAME=thesocialmask\n";
        $success++;
    } else {
        echo "❌ ERROR: DB_NAME no es 'thesocialmask'\n";
        $errors++;
    }

    // Verificar APP_NAME
    if (preg_match('/APP_NAME=TheSocialMask/', $envContent)) {
        echo "✅ APP_NAME=TheSocialMask\n";
        $success++;
    } else {
        echo "❌ ERROR: APP_NAME no es 'TheSocialMask'\n";
        $errors++;
    }

    // Verificar SESSION_NAME
    if (preg_match('/SESSION_NAME=thesocialmask_session/', $envContent)) {
        echo "✅ SESSION_NAME=thesocialmask_session\n";
        $success++;
    } else {
        echo "❌ ERROR: SESSION_NAME no es 'thesocialmask_session'\n";
        $errors++;
    }

    // Verificar que NO haya referencias a "sphoria"
    if (preg_match('/sphoria/i', $envContent)) {
        echo "⚠️  ADVERTENCIA: Aún hay referencias a 'sphoria' en .env\n";
        $warnings++;
    } else {
        echo "✅ No hay referencias a 'sphoria' en .env\n";
        $success++;
    }
}

echo "\n";

// Test 2: Verificar archivos de configuración
echo "📋 Test 2: Verificar archivos de configuración PHP\n";
echo "────────────────────────────────────────────────────────────────\n";

$configFiles = [
    'config/env.php' => 'TheSocialMask\\\Config',
    'config/connection.php' => 'TheSocialMask\\\Config',
    'config/constants.php' => 'TheSocialMask\\\Config',
    'config/TokenManager.php' => 'TheSocialMask\\\Config',
];

foreach ($configFiles as $file => $expectedNamespace) {
    if (!file_exists($file)) {
        echo "❌ ERROR: $file no encontrado\n";
        $errors++;
        continue;
    }

    $content = file_get_contents($file);

    if (strpos($content, $expectedNamespace) !== false) {
        echo "✅ $file - Namespace correcto\n";
        $success++;
    } else {
        echo "❌ ERROR: $file - Namespace incorrecto\n";
        $errors++;
    }

    // Verificar que NO haya "Sphoria\Config"
    if (preg_match('/Sphoria\\\\\Config/', $content)) {
        echo "❌ ERROR: $file - Aún tiene 'Sphoria\\Config'\n";
        $errors++;
    }
}

echo "\n";

// Test 3: Verificar sintaxis de archivos PHP críticos
echo "📋 Test 3: Verificar sintaxis PHP\n";
echo "────────────────────────────────────────────────────────────────\n";

$criticalFiles = [
    'config/env.php',
    'config/connection.php',
    'config/config.php',
    'index.php',
];

foreach ($criticalFiles as $file) {
    if (!file_exists($file)) {
        echo "❌ ERROR: $file no encontrado\n";
        $errors++;
        continue;
    }

    exec("php -l $file 2>&1", $output, $returnVar);

    if ($returnVar === 0 && strpos(implode("\n", $output), "No syntax errors") !== false) {
        echo "✅ $file - Sin errores de sintaxis\n";
        $success++;
    } else {
        echo "❌ ERROR: $file - Tiene errores de sintaxis\n";
        echo "   " . implode("\n   ", $output) . "\n";
        $errors++;
    }
}

echo "\n";

// Test 4: Buscar referencias residuales a "sphoria"
echo "📋 Test 4: Buscar referencias residuales a 'sphoria'\n";
echo "────────────────────────────────────────────────────────────────\n";

$extensions = ['php', 'js', 'html'];
$excludeDirs = ['vendor', 'node_modules', 'MD', 'uploads', '.git'];
$foundReferences = false;

foreach ($extensions as $ext) {
    $cmd = "grep -ri 'sphoria' . --include='*.$ext' 2>/dev/null";

    foreach ($excludeDirs as $dir) {
        $cmd .= " --exclude-dir='$dir'";
    }

    exec($cmd, $output, $returnVar);

    if (!empty($output)) {
        $foundReferences = true;
        echo "⚠️  ADVERTENCIA: Encontradas referencias a 'sphoria' en archivos .$ext:\n";
        foreach (array_slice($output, 0, 5) as $line) {
            echo "   $line\n";
        }

        if (count($output) > 5) {
            echo "   ... y " . (count($output) - 5) . " más\n";
        }
        $warnings++;
    }
}

if (!$foundReferences) {
    echo "✅ No se encontraron referencias a 'sphoria' en código\n";
    $success++;
}

echo "\n";

// Test 5: Verificar base de datos (opcional)
echo "📋 Test 5: Verificar base de datos\n";
echo "────────────────────────────────────────────────────────────────\n";

require_once 'config/connection.php';

try {
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];

    if ($dbName === 'thesocialmask') {
        echo "✅ Conectado a base de datos: $dbName\n";
        $success++;

        // Verificar que existan tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($tables) > 0) {
            echo "✅ Base de datos contiene " . count($tables) . " tablas\n";
            $success++;
        } else {
            echo "⚠️  ADVERTENCIA: Base de datos vacía\n";
            echo "   Ejecutar: mysql -u root -p thesocialmask < database/MAIN.sql\n";
            $warnings++;
        }
    } else {
        echo "❌ ERROR: Conectado a base de datos incorrecta: $dbName\n";
        echo "   Esperado: thesocialmask\n";
        echo "   Ver: MIGRATE_DB_INSTRUCTIONS.txt\n";
        $errors++;
    }
} catch (PDOException $e) {
    echo "❌ ERROR: No se pudo conectar a la base de datos\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Asegúrate de migrar la BD (ver MIGRATE_DB_INSTRUCTIONS.txt)\n";
    $errors++;
}

echo "\n";

// Resumen
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                      RESUMEN DE VERIFICACIÓN                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

echo "✅ Tests exitosos:    $success\n";
echo "⚠️  Advertencias:      $warnings\n";
echo "❌ Errores:           $errors\n\n";

if ($errors === 0 && $warnings === 0) {
    echo "🎉 ¡PERFECTO! El rebranding está completo y sin errores.\n\n";
    echo "Próximos pasos:\n";
    echo "1. Probar login/registro en http://localhost/pages/dashboard.php\n";
    echo "2. Verificar que las sesiones funcionen\n";
    echo "3. Probar funcionalidades principales\n\n";
    exit(0);
} elseif ($errors === 0) {
    echo "✅ El rebranding está completo, pero hay algunas advertencias.\n";
    echo "   Revisa los mensajes anteriores.\n\n";
    exit(0);
} else {
    echo "❌ Hay errores que deben corregirse antes de continuar.\n";
    echo "   Revisa los mensajes de error anteriores.\n\n";
    exit(1);
}
