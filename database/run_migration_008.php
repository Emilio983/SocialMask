<?php
/**
 * ============================================
 * RUN MIGRATION 008: GOVERNANCE SYSTEM
 * ============================================
 * Standalone script to create governance tables
 */

// Database configuration
$dbHost = 'localhost';
$dbName = 'sphera_social';
$dbUser = 'root';
$dbPass = '';

echo "\n============================================\n";
echo "🏛️  MIGRATION 008: GOVERNANCE SYSTEM\n";
echo "============================================\n\n";

try {
    // Connect to database
    echo "📡 Connecting to database...\n";
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    echo "✅ Connected to database: $dbName\n\n";
    
    // Read migration file
    $migrationFile = __DIR__ . '/migrations/008_governance_system.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    echo "📄 Reading migration file...\n";
    $sql = file_get_contents($migrationFile);
    echo "✅ Migration file loaded\n\n";
    
    // Split SQL into statements
    echo "🔧 Executing migration...\n\n";
    
    // Remove comments and split by delimiter
    $statements = [];
    $currentStatement = '';
    $inDelimiter = false;
    
    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || substr($line, 0, 2) === '--') {
            continue;
        }
        
        // Handle DELIMITER changes
        if (stripos($line, 'DELIMITER') === 0) {
            if (!$inDelimiter) {
                $inDelimiter = true;
            } else {
                $inDelimiter = false;
                if (!empty($currentStatement)) {
                    $statements[] = $currentStatement;
                    $currentStatement = '';
                }
            }
            continue;
        }
        
        $currentStatement .= $line . "\n";
        
        // If not in delimiter block and line ends with ;, it's a complete statement
        if (!$inDelimiter && substr(rtrim($line), -1) === ';') {
            $statements[] = $currentStatement;
            $currentStatement = '';
        }
    }
    
    // Add last statement if any
    if (!empty($currentStatement)) {
        $statements[] = $currentStatement;
    }
    
    // Execute each statement
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show what was executed
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches);
                echo "  ✅ Created table: " . ($matches[1] ?? 'unknown') . "\n";
            } elseif (stripos($statement, 'CREATE VIEW') !== false) {
                preg_match('/CREATE.*?VIEW.*?`?(\w+)`?/i', $statement, $matches);
                echo "  ✅ Created view: " . ($matches[1] ?? 'unknown') . "\n";
            } elseif (stripos($statement, 'CREATE TRIGGER') !== false) {
                preg_match('/CREATE TRIGGER.*?`?(\w+)`?/i', $statement, $matches);
                echo "  ✅ Created trigger: " . ($matches[1] ?? 'unknown') . "\n";
            } elseif (stripos($statement, 'INSERT INTO') !== false) {
                preg_match('/INSERT INTO\s+`?(\w+)`?/i', $statement, $matches);
                echo "  ✅ Inserted data into: " . ($matches[1] ?? 'unknown') . "\n";
            }
        } catch (PDOException $e) {
            $errors++;
            
            // Check if error is "already exists" - not critical
            if (
                stripos($e->getMessage(), 'already exists') !== false ||
                stripos($e->getMessage(), 'Duplicate') !== false
            ) {
                echo "  ⚠️  Already exists (skipped)\n";
            } else {
                echo "  ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n============================================\n";
    echo "📊 MIGRATION SUMMARY\n";
    echo "============================================\n";
    echo "✅ Statements executed: $executed\n";
    echo ($errors > 0 ? "⚠️" : "✅") . "  Errors encountered: $errors\n";
    
    // Verify tables were created
    echo "\n🔍 Verifying tables...\n";
    $tables = [
        'governance_proposals',
        'governance_votes',
        'governance_delegations',
        'governance_stats',
        'governance_sync_log'
    ];
    
    $allExist = true;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✅ $table\n";
        } else {
            echo "  ❌ $table (NOT FOUND)\n";
            $allExist = false;
        }
    }
    
    // Verify views
    echo "\n🔍 Verifying views...\n";
    $views = [
        'v_active_proposals',
        'v_user_governance_activity'
    ];
    
    foreach ($views as $view) {
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_$dbName = '$view'");
        if ($stmt->rowCount() > 0) {
            echo "  ✅ $view\n";
        } else {
            echo "  ⚠️  $view (NOT FOUND - may be optional)\n";
        }
    }
    
    echo "\n============================================\n";
    if ($allExist && $errors === 0) {
        echo "🎉 MIGRATION COMPLETED SUCCESSFULLY!\n";
    } elseif ($allExist) {
        echo "✅ MIGRATION COMPLETED WITH WARNINGS\n";
    } else {
        echo "❌ MIGRATION COMPLETED WITH ERRORS\n";
    }
    echo "============================================\n\n";
    
    // Show table stats
    echo "📊 Table Statistics:\n";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "  - $table: $count rows\n";
        } catch (PDOException $e) {
            echo "  - $table: Error reading\n";
        }
    }
    
    echo "\n✅ Database is ready for governance system!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ FATAL ERROR:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
