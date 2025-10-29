<?php
require_once __DIR__ . '/../config/connection.php';

try {
    $pdo = getConnection();
    $sql = file_get_contents(__DIR__ . '/p2p_system_update.sql');
    
    // Dividir en statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $stmt) {
        if (!empty($stmt) && !preg_match('/^--/', $stmt)) {
            try {
                $pdo->exec($stmt);
                echo "✅ Ejecutado\n";
            } catch (Exception $e) {
                echo "⚠️ " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ Actualización de base de datos completada\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
