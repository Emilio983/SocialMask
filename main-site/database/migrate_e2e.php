<?php
/**
 * ============================================
 * MIGRATE E2E MESSAGING SYSTEM
 * ============================================
 */

require_once __DIR__ . '/../config/connection.php';

echo "🔐 Migrating E2E Messaging System...\n\n";

try {
    // Read SQL migration file
    $sqlFile = __DIR__ . '/../database/migrations/014_e2e_messages.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
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
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        try {
            if ($conn->query($statement)) {
                $success++;
                
                // Extract table/view name for logging
                if (preg_match('/CREATE\s+TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                    echo "✅ Created table: {$matches[1]}\n";
                } elseif (preg_match('/CREATE.*VIEW\s+`?(\w+)`?/i', $statement, $matches)) {
                    echo "✅ Created view: {$matches[1]}\n";
                } elseif (preg_match('/CREATE\s+EVENT\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                    echo "✅ Created event: {$matches[1]}\n";
                }
            }
        } catch (Exception $e) {
            $errors++;
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Create screenshot_attempts table (not in main migration)
    echo "\n📸 Creating screenshot_attempts table...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS screenshot_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            contact_id INT UNSIGNED NOT NULL,
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_user (user_id),
            INDEX idx_contact (contact_id),
            INDEX idx_detected (detected_at),
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (contact_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created screenshot_attempts table\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Migration completed successfully!\n";
    echo "   Tables created: $success\n";
    if ($errors > 0) {
        echo "   Errors: $errors\n";
    }
    echo str_repeat("=", 50) . "\n\n";
    
    echo "🔐 E2E Messaging System ready to use!\n";
    echo "\n📋 Next steps:\n";
    echo "   1. Include scripts in your HTML:\n";
    echo "      <script src='/assets/js/messaging/signal-crypto.js'></script>\n";
    echo "      <script src='/assets/js/messaging/e2e-messaging.js'></script>\n";
    echo "      <script src='/assets/js/messaging/ephemeral-messages.js'></script>\n";
    echo "      <script src='/assets/js/messaging/messaging-ui.js'></script>\n";
    echo "   2. Include CSS:\n";
    echo "      <link rel='stylesheet' href='/assets/css/messaging.css'>\n";
    echo "   3. Test the encryption!\n\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
