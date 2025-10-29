<?php
/**
 * Treasury Management System Migration
 * Creates tables for multi-token treasury management
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n💰 Starting Treasury Management System Migration...\n\n";

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
    $sqlFile = __DIR__ . '/database/migrations/011_treasury_management.sql';
    
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
    $tableCount = 0;
    $viewCount = 0;
    
    foreach ($statements as $index => $statement) {
        if (preg_match('/CREATE TABLE.*?treasury_payments/i', $statement)) {
            echo "Creating treasury_payments table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?treasury_approvals/i', $statement)) {
            echo "Creating treasury_approvals table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?treasury_budgets/i', $statement)) {
            echo "Creating treasury_budgets table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?treasury_balances/i', $statement)) {
            echo "Creating treasury_balances table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?treasury_transactions/i', $statement)) {
            echo "Creating treasury_transactions table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?treasury_config/i', $statement)) {
            echo "Creating treasury_config table...\n";
            $tableCount++;
        } elseif (preg_match('/INSERT INTO.*?treasury_config/i', $statement)) {
            echo "Inserting default configuration...\n";
        } elseif (preg_match('/CREATE.*?VIEW.*?v_pending_payments/i', $statement)) {
            echo "Creating v_pending_payments view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_budget_status/i', $statement)) {
            echo "Creating v_budget_status view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_treasury_overview/i', $statement)) {
            echo "Creating v_treasury_overview view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_recent_transactions/i', $statement)) {
            echo "Creating v_recent_transactions view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_streaming_payments/i', $statement)) {
            echo "Creating v_streaming_payments view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_department_spending/i', $statement)) {
            echo "Creating v_department_spending view...\n";
            $viewCount++;
        }
        
        if (!$mysqli->query($statement)) {
            throw new Exception("Error executing statement: " . $mysqli->error);
        }
        
        echo "✅ Statement " . ($index + 1) . " executed successfully\n";
    }
    
    echo "\n✅ Migration completed successfully!\n\n";
    echo "📊 Created:\n";
    echo "  • $tableCount tables\n";
    echo "  • $viewCount views\n\n";
    echo "📋 Tables:\n";
    echo "  • treasury_payments\n";
    echo "  • treasury_approvals\n";
    echo "  • treasury_budgets\n";
    echo "  • treasury_balances\n";
    echo "  • treasury_transactions\n";
    echo "  • treasury_config\n\n";
    echo "📊 Views:\n";
    echo "  • v_pending_payments\n";
    echo "  • v_budget_status\n";
    echo "  • v_treasury_overview\n";
    echo "  • v_recent_transactions\n";
    echo "  • v_streaming_payments\n";
    echo "  • v_department_spending\n\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n\n";
    exit(1);
}
