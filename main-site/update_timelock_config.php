<?php
/**
 * ============================================
 * UPDATE TIMELOCK CONFIG
 * ============================================
 * Update contract address after deployment
 * Usage: php update_timelock_config.php <contract_address>
 */

require_once __DIR__ . '/config/connection.php';

if ($argc < 2) {
    echo "❌ Usage: php update_timelock_config.php <contract_address>\n";
    echo "   Example: php update_timelock_config.php 0x1234567890123456789012345678901234567890\n";
    exit(1);
}

$contractAddress = $argv[1];

// Validate address format
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $contractAddress)) {
    echo "❌ Invalid Ethereum address format\n";
    echo "   Address must be 0x followed by 40 hexadecimal characters\n";
    exit(1);
}

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . $mysqli->connect_error);
    }
    
    echo "✅ Connected to database '{DB_NAME}'\n\n";
    
    // Update contract address
    $stmt = $mysqli->prepare("
        UPDATE governance_timelock_config 
        SET config_value = ?,
            updated_at = NOW(),
            updated_by = 'deployment_script'
        WHERE config_key = 'contract_address'
    ");
    
    $stmt->bind_param("s", $contractAddress);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update config: ' . $stmt->error);
    }
    
    echo "✅ Contract address updated\n";
    echo "   Address: {$contractAddress}\n";
    echo "   Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Verify update
    $result = $mysqli->query("
        SELECT config_key, config_value, updated_at 
        FROM governance_timelock_config 
        WHERE config_key = 'contract_address'
    ");
    
    $row = $result->fetch_assoc();
    
    echo "📋 Current Configuration:\n";
    echo "   Key: {$row['config_key']}\n";
    echo "   Value: {$row['config_value']}\n";
    echo "   Updated: {$row['updated_at']}\n\n";
    
    // Show all config
    echo "📊 All Timelock Configuration:\n";
    $result = $mysqli->query("SELECT * FROM governance_timelock_config ORDER BY config_key");
    
    while ($row = $result->fetch_assoc()) {
        echo "   • {$row['config_key']}: {$row['config_value']}\n";
    }
    
    echo "\n✅ Update completed successfully!\n";
    echo "\n🔗 Next steps:\n";
    echo "   1. Verify contract on block explorer\n";
    echo "   2. Test timelock operations\n";
    echo "   3. Grant roles to appropriate addresses\n\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
