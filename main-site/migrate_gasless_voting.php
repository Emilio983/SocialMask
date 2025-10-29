<?php
/**
 * ============================================
 * GASLESS VOTING MIGRATION
 * ============================================
 * Migrates database for gasless voting system
 */

// Database configuration - Direct connection
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    echo "🚀 Starting Gasless Voting System Migration...\n\n";
    
    // Connect to database
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Connection failed: ' . $mysqli->connect_error);
    }
    
    echo "✅ Connected to database '" . $DB_NAME . "'\n\n";
    
    // Create governance_gasless_votes table
    echo "Creating governance_gasless_votes table...\n";
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_gasless_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            proposal_id VARCHAR(50) NOT NULL,
            support TINYINT NOT NULL COMMENT '0=Against, 1=For, 2=Abstain',
            voter_address VARCHAR(42) NOT NULL,
            nonce BIGINT UNSIGNED NOT NULL,
            deadline BIGINT UNSIGNED NOT NULL,
            signature VARCHAR(132) NOT NULL,
            relayer_address VARCHAR(42) DEFAULT NULL,
            tx_hash VARCHAR(66) DEFAULT NULL,
            block_number BIGINT UNSIGNED DEFAULT NULL,
            status ENUM('pending', 'submitted', 'confirmed', 'failed') DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            gas_saved INT UNSIGNED DEFAULT 80000,
            gas_used INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            confirmed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_proposal_id (proposal_id),
            INDEX idx_voter_address (voter_address),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            UNIQUE KEY unique_vote (proposal_id, voter_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ") or die("Error creating governance_gasless_votes: " . $mysqli->error);
    echo "✅ Table governance_gasless_votes created\n";
    
    // Create governance_relayer_transactions table
    echo "Creating governance_relayer_transactions table...\n";
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_relayer_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tx_hash VARCHAR(66) NOT NULL UNIQUE,
            from_address VARCHAR(42) NOT NULL,
            to_address VARCHAR(42) NOT NULL,
            gas_price VARCHAR(78) DEFAULT NULL,
            gas_limit INT UNSIGNED DEFAULT NULL,
            gas_used INT UNSIGNED DEFAULT NULL,
            gas_cost VARCHAR(78) DEFAULT NULL,
            function_name VARCHAR(100) DEFAULT NULL,
            votes_count INT DEFAULT 1,
            status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
            block_number BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            confirmed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_from_address (from_address),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ") or die("Error creating governance_relayer_transactions: " . $mysqli->error);
    echo "✅ Table governance_relayer_transactions created\n";
    
    // Create governance_nonces table
    echo "Creating governance_nonces table...\n";
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_nonces (
            id INT AUTO_INCREMENT PRIMARY KEY,
            address VARCHAR(42) NOT NULL UNIQUE,
            current_nonce BIGINT UNSIGNED DEFAULT 0,
            last_nonce_used BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_address (address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ") or die("Error creating governance_nonces: " . $mysqli->error);
    echo "✅ Table governance_nonces created\n";
    
    // Create governance_relayer_config table
    echo "Creating governance_relayer_config table...\n";
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_relayer_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(50) NOT NULL UNIQUE,
            config_value TEXT NOT NULL,
            config_type ENUM('string', 'int', 'boolean', 'address', 'json') DEFAULT 'string',
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(42) DEFAULT NULL,
            INDEX idx_config_key (config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ") or die("Error creating governance_relayer_config: " . $mysqli->error);
    echo "✅ Table governance_relayer_config created\n";
    
    // Insert default configuration
    echo "\nInserting default configuration...\n";
    $configs = [
        ['contract_address', '', 'address', 'GaslessVoting contract address'],
        ['relayer_address', '', 'address', 'Active relayer address'],
        ['chain_id', '1', 'int', 'Blockchain network ID'],
        ['rpc_endpoint', 'https://eth-mainnet.g.alchemy.com/v2/YOUR_KEY', 'string', 'Ethereum RPC endpoint'],
        ['rate_limit_per_minute', '10', 'int', 'Max votes per address per minute'],
        ['rate_limit_per_hour', '100', 'int', 'Max votes per address per hour'],
        ['batch_size', '10', 'int', 'Maximum votes per batch'],
        ['signature_deadline_seconds', '3600', 'int', 'Max signature validity (1 hour)'],
        ['enable_gasless_voting', 'true', 'boolean', 'Enable/disable gasless voting']
    ];
    
    foreach ($configs as $config) {
        $stmt = $mysqli->prepare("
            INSERT INTO governance_relayer_config (config_key, config_value, config_type, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->bind_param("ssss", $config[0], $config[1], $config[2], $config[3]);
        $stmt->execute();
    }
    echo "✅ Default configuration inserted\n";
    
    echo "\n✅ Migration completed successfully!\n\n";
    echo "📊 Tables created:\n";
    echo "  • governance_gasless_votes\n";
    echo "  • governance_relayer_transactions\n";
    echo "  • governance_nonces\n";
    echo "  • governance_relayer_config\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
