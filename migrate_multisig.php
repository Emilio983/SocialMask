<?php
/**
 * Multi-Signature System Migration
 * Creates tables for 3-of-5 multi-sig governance
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n🚀 Starting Multi-Signature System Migration...\n\n";

// Direct database connection
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    // Connect to database
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "✅ Connected to database '$DB_NAME'\n\n";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/database/migrations/009_multisig_system.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) &&
                   !preg_match('/^\/\*/', $stmt);
        }
    );
    
    echo "📄 Found " . count($statements) . " SQL statements\n\n";
    
    // Execute each statement
    foreach ($statements as $index => $statement) {
        if (preg_match('/CREATE TABLE.*?governance_multisig_proposals/i', $statement)) {
            echo "Creating governance_multisig_proposals table...\n";
        } elseif (preg_match('/CREATE TABLE.*?governance_multisig_signatures/i', $statement)) {
            echo "Creating governance_multisig_signatures table...\n";
        } elseif (preg_match('/CREATE TABLE.*?governance_multisig_signers/i', $statement)) {
            echo "Creating governance_multisig_signers table...\n";
        } elseif (preg_match('/CREATE TABLE.*?governance_multisig_config/i', $statement)) {
            echo "Creating governance_multisig_config table...\n";
        } elseif (preg_match('/INSERT INTO.*?governance_multisig_config/i', $statement)) {
            echo "Inserting default configuration...\n";
        } elseif (preg_match('/CREATE.*?VIEW.*?v_pending_multisig_proposals/i', $statement)) {
            echo "Creating v_pending_multisig_proposals view...\n";
        } elseif (preg_match('/CREATE.*?VIEW.*?v_multisig_signer_stats/i', $statement)) {
            echo "Creating v_multisig_signer_stats view...\n";
        } elseif (preg_match('/CREATE.*?VIEW.*?v_recent_multisig_activity/i', $statement)) {
            echo "Creating v_recent_multisig_activity view...\n";
        }
        
        if (!$mysqli->query($statement)) {
            throw new Exception("Error executing statement: " . $mysqli->error);
        }
        
        echo "✅ Statement " . ($index + 1) . " executed successfully\n";
    }
    
    echo "\n✅ Migration completed successfully!\n\n";
    echo "📊 Tables created:\n";
    echo "  • governance_multisig_proposals\n";
    echo "  • governance_multisig_signatures\n";
    echo "  • governance_multisig_signers\n";
    echo "  • governance_multisig_config\n\n";
    echo "📊 Views created:\n";
    echo "  • v_pending_multisig_proposals\n";
    echo "  • v_multisig_signer_stats\n";
    echo "  • v_recent_multisig_activity\n\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n\n";
    exit(1);
}
