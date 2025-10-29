<?php
/**
 * Quadratic Voting System Migration
 * Creates tables for quadratic governance
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n🚀 Starting Quadratic Voting System Migration...\n\n";

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
    $sqlFile = __DIR__ . '/database/migrations/010_quadratic_voting.sql';
    
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
        if (preg_match('/CREATE TABLE.*?governance_quadratic_proposals/i', $statement)) {
            echo "Creating governance_quadratic_proposals table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?governance_quadratic_votes/i', $statement)) {
            echo "Creating governance_quadratic_votes table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?governance_vote_power_snapshots/i', $statement)) {
            echo "Creating governance_vote_power_snapshots table...\n";
            $tableCount++;
        } elseif (preg_match('/CREATE TABLE.*?governance_quadratic_config/i', $statement)) {
            echo "Creating governance_quadratic_config table...\n";
            $tableCount++;
        } elseif (preg_match('/INSERT INTO.*?governance_quadratic_config/i', $statement)) {
            echo "Inserting default configuration...\n";
        } elseif (preg_match('/CREATE.*?VIEW.*?v_active_quadratic_proposals/i', $statement)) {
            echo "Creating v_active_quadratic_proposals view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_quadratic_impact_analysis/i', $statement)) {
            echo "Creating v_quadratic_impact_analysis view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_quadratic_voting_stats/i', $statement)) {
            echo "Creating v_quadratic_voting_stats view...\n";
            $viewCount++;
        } elseif (preg_match('/CREATE.*?VIEW.*?v_whale_impact_comparison/i', $statement)) {
            echo "Creating v_whale_impact_comparison view...\n";
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
    echo "  • governance_quadratic_proposals\n";
    echo "  • governance_quadratic_votes\n";
    echo "  • governance_vote_power_snapshots\n";
    echo "  • governance_quadratic_config\n\n";
    echo "📊 Views:\n";
    echo "  • v_active_quadratic_proposals\n";
    echo "  • v_quadratic_impact_analysis\n";
    echo "  • v_quadratic_voting_stats\n";
    echo "  • v_whale_impact_comparison\n\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n\n";
    exit(1);
}
