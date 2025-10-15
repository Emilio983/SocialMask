<?php
/**
 * FASE 6.5 - Setup Database
 */

echo "\n🔧 Creating sphera database...\n";

try {
    $mysqli = new mysqli('localhost', 'root', '');
    
    if ($mysqli->connect_error) {
        die("❌ Cannot connect to MySQL: " . $mysqli->connect_error . "\n");
    }
    
    // Create database
    $mysqli->query("CREATE DATABASE IF NOT EXISTS sphera CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database 'sphera' created/verified\n";
    
    // Switch to database
    $mysqli->select_db('sphera');
    
    // Create governance_proposals table
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_proposals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            proposal_id VARCHAR(100) UNIQUE NOT NULL,
            proposer VARCHAR(42) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category ENUM('parameter_change', 'treasury_management', 'contract_upgrade', 'feature_proposal', 'emergency_action') DEFAULT 'feature_proposal',
            status ENUM('pending', 'active', 'succeeded', 'defeated', 'queued', 'executed', 'cancelled') DEFAULT 'pending',
            votes_for DECIMAL(30,0) DEFAULT 0,
            votes_against DECIMAL(30,0) DEFAULT 0,
            votes_abstain DECIMAL(30,0) DEFAULT 0,
            start_block BIGINT UNSIGNED,
            end_block BIGINT UNSIGNED,
            execution_eta BIGINT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_proposer (proposer),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'governance_proposals' created\n";
    
    // Create governance_votes table
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_votes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            proposal_id VARCHAR(100) NOT NULL,
            voter VARCHAR(42) NOT NULL,
            user_id INT UNSIGNED,
            vote_type TINYINT NOT NULL COMMENT '0=AGAINST, 1=FOR, 2=ABSTAIN',
            voting_power DECIMAL(30,0) NOT NULL,
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_vote (proposal_id, voter),
            INDEX idx_proposal_id (proposal_id),
            INDEX idx_voter (voter),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'governance_votes' created\n";
    
    // Create governance_delegations table
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_delegations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            delegator VARCHAR(42) NOT NULL,
            delegatee VARCHAR(42) NOT NULL,
            user_id INT UNSIGNED,
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_delegation (delegator, active),
            INDEX idx_delegator (delegator),
            INDEX idx_delegatee (delegatee)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'governance_delegations' created\n";
    
    // Create governance_comments table
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS governance_comments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            proposal_id VARCHAR(100) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            wallet_address VARCHAR(42),
            parent_id BIGINT UNSIGNED,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_proposal_id (proposal_id),
            INDEX idx_user_id (user_id),
            INDEX idx_parent_id (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'governance_comments' created\n";
    
    // Create users table if not exists
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            wallet_address VARCHAR(42),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wallet_address (wallet_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'users' created\n";
    
    echo "\n✅ Database setup complete!\n\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
