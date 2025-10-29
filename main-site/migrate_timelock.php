<?php
/**
 * SUBFASE 7.1 - Timelock Database Migration
 */

echo "🔄 Executing Timelock Database Migration...\n\n";

try {
    $mysqli = new mysqli('localhost', 'root', '', 'sphera');
    
    if ($mysqli->connect_error) {
        die("❌ Connection failed: " . $mysqli->connect_error . "\n");
    }
    
    echo "✅ Connected to database 'sphera'\n\n";
    
    // Table 1: governance_timelock_queue
    echo "Creating governance_timelock_queue table...\n";
    $sql1 = "CREATE TABLE IF NOT EXISTS governance_timelock_queue (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        operation_hash VARCHAR(66) UNIQUE NOT NULL,
        proposal_id VARCHAR(100) NOT NULL,
        target_address VARCHAR(42) NOT NULL,
        value_wei VARCHAR(78) DEFAULT '0',
        call_data TEXT,
        predecessor_hash VARCHAR(66),
        salt VARCHAR(66) NOT NULL,
        proposer VARCHAR(42) NOT NULL,
        description TEXT,
        category ENUM('parameter_change', 'treasury', 'upgrade', 'emergency', 'other') DEFAULT 'other',
        delay_seconds INT UNSIGNED NOT NULL DEFAULT 172800,
        queued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        execution_eta DATETIME NOT NULL,
        status ENUM('queued', 'ready', 'executed', 'cancelled', 'expired') DEFAULT 'queued',
        executed_at TIMESTAMP NULL,
        executed_by VARCHAR(42),
        executed_tx_hash VARCHAR(66),
        cancelled_at TIMESTAMP NULL,
        cancelled_by VARCHAR(42),
        cancellation_reason TEXT,
        is_batch BOOLEAN DEFAULT FALSE,
        batch_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_operation_hash (operation_hash),
        INDEX idx_proposal_id (proposal_id),
        INDEX idx_status (status),
        INDEX idx_execution_eta (execution_eta),
        INDEX idx_proposer (proposer)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($mysqli->query($sql1)) {
        echo "✅ Table governance_timelock_queue created\n";
    } else {
        echo "⚠️  " . $mysqli->error . "\n";
    }
    
    // Table 2: governance_timelock_events
    echo "Creating governance_timelock_events table...\n";
    $sql2 = "CREATE TABLE IF NOT EXISTS governance_timelock_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        operation_hash VARCHAR(66) NOT NULL,
        event_type ENUM('queued', 'executed', 'cancelled', 'emergency_cancel') NOT NULL,
        actor_address VARCHAR(42) NOT NULL,
        event_data JSON,
        block_number BIGINT UNSIGNED,
        transaction_hash VARCHAR(66),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_operation_hash (operation_hash),
        INDEX idx_event_type (event_type),
        INDEX idx_actor (actor_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($mysqli->query($sql2)) {
        echo "✅ Table governance_timelock_events created\n";
    } else {
        echo "⚠️  " . $mysqli->error . "\n";
    }
    
    // Table 3: governance_timelock_config
    echo "Creating governance_timelock_config table...\n";
    $sql3 = "CREATE TABLE IF NOT EXISTS governance_timelock_config (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(50) UNIQUE NOT NULL,
        config_value TEXT NOT NULL,
        config_type ENUM('int', 'string', 'boolean', 'address', 'json') DEFAULT 'string',
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(42),
        INDEX idx_config_key (config_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($mysqli->query($sql3)) {
        echo "✅ Table governance_timelock_config created\n";
    } else {
        echo "⚠️  " . $mysqli->error . "\n";
    }
    
    // Insert default config
    echo "\nInserting default configuration...\n";
    $config_inserts = [
        "('min_delay_seconds', '172800', 'int', 'Minimum delay: 48 hours')",
        "('max_delay_seconds', '2592000', 'int', 'Maximum delay: 30 days')",
        "('auto_execute_enabled', 'false', 'boolean', 'Auto-execute when ready')",
        "('emergency_cancel_enabled', 'true', 'boolean', 'Allow emergency cancellations')",
        "('contract_address', '', 'address', 'Timelock contract address')"
    ];
    
    foreach ($config_inserts as $values) {
        $sql = "INSERT IGNORE INTO governance_timelock_config (config_key, config_value, config_type, description) VALUES $values";
        $mysqli->query($sql);
    }
    echo "✅ Default configuration inserted\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\n📊 Tables created:\n";
    echo "  • governance_timelock_queue\n";
    echo "  • governance_timelock_events\n";
    echo "  • governance_timelock_config\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
