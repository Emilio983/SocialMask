<?php
// ============================================
// RUN IPFS MIGRATION
// ============================================

require_once __DIR__ . '/../config/connection.php';

try {
    echo "🔄 Running IPFS uploads migration...\n";
    
    $sql = file_get_contents(__DIR__ . '/../database/migrations/012_ipfs_uploads.sql');
    
    $pdo->exec($sql);
    
    echo "✅ Migration executed successfully!\n";
    echo "📊 Table 'ipfs_uploads' created\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
