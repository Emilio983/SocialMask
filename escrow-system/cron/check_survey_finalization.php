<?php
/**
 * CRON JOB: Check Survey Finalization
 * Verifica las transacciones de finalización de encuestas
 *
 * IMPORTANTE: Configurar en cPanel/Hostinger para ejecutar cada 5 minutos:
 * Command: /usr/bin/php /home/username/public_html/escrow-system/cron/check_survey_finalization.php
 * Interval: every 5 minutes (cron: slash-5 asterisk asterisk asterisk asterisk)
 */

// Solo permitir ejecución desde CLI o localhost
if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    http_response_code(403);
    die('Forbidden');
}

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';
require_once __DIR__ . '/../helpers/Web3Helper.php';

// Log
$log_file = __DIR__ . '/../logs/finalization_check.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

logMessage("===== Starting finalization check job =====");

try {
    $web3 = new Web3Helper();

    // Obtener surveys en estado 'finalizing' (esperando confirmación)
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            u.username as creator_username
        FROM surveys s
        LEFT JOIN users u ON s.created_by = u.user_id
        WHERE s.status = 'finalizing'
          AND s.finalized_tx_hash IS NOT NULL
    ");
    $stmt->execute();
    $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMessage("Found " . count($surveys) . " surveys awaiting finalization confirmation");

    foreach ($surveys as $survey) {
        $tx_hash = $survey['finalized_tx_hash'];
        $survey_id = $survey['id'];

        logMessage("Checking survey #{$survey_id}: {$survey['title']} - TX: {$tx_hash}");

        // Verificar transacción
        $verification = $web3->verifyTransaction($tx_hash);

        if (!$verification) {
            logMessage("  -> Transaction not found yet");
            continue;
        }

        if (!$verification['success']) {
            logMessage("  -> Transaction FAILED - reverting survey status");

            // Revertir estado
            $stmt_revert = $pdo->prepare("
                UPDATE surveys
                SET
                    status = 'active',
                    finalized_tx_hash = NULL
                WHERE id = :survey_id
            ");
            $stmt_revert->execute([':survey_id' => $survey_id]);

            // Marcar payouts como failed
            $stmt_fail_payouts = $pdo->prepare("
                UPDATE payouts
                SET status = 'failed',
                    error_message = 'Finalization transaction failed on blockchain'
                WHERE survey_id = :survey_id
                  AND status = 'pending'
            ");
            $stmt_fail_payouts->execute([':survey_id' => $survey_id]);

            continue;
        }

        $confirmations = $verification['confirmations'];
        logMessage("  -> Transaction successful with {$confirmations} confirmations");

        if ($confirmations >= MIN_CONFIRMATIONS) {
            logMessage("  -> FINALIZING survey");

            // Actualizar survey como finalizado
            $stmt_finalize = $pdo->prepare("
                UPDATE surveys
                SET
                    status = 'finalized',
                    finalized_at = NOW()
                WHERE id = :survey_id
            ");
            $stmt_finalize->execute([':survey_id' => $survey_id]);

            // Actualizar payouts como completed
            $stmt_complete_payouts = $pdo->prepare("
                UPDATE payouts
                SET
                    status = 'completed',
                    tx_hash = :tx_hash,
                    block_number = :block_number,
                    processed_at = NOW()
                WHERE survey_id = :survey_id
                  AND status = 'pending'
            ");
            $stmt_complete_payouts->execute([
                ':tx_hash' => $tx_hash,
                ':block_number' => $verification['block_number'],
                ':survey_id' => $survey_id
            ]);

            // Actualizar log de transacciones
            $stmt_log = $pdo->prepare("
                UPDATE escrow_transactions_log
                SET
                    status = 'confirmed',
                    confirmed_at = NOW(),
                    block_number = :block_number
                WHERE tx_hash = :tx_hash
            ");
            $stmt_log->execute([
                ':block_number' => $verification['block_number'],
                ':tx_hash' => $tx_hash
            ]);

            logMessage("  -> Survey finalized successfully");

            // Opcional: Enviar notificaciones a ganadores
            // sendWinnerNotifications($survey_id);
        } else {
            logMessage("  -> Waiting for confirmations ({$confirmations}/" . MIN_CONFIRMATIONS . ")");
        }
    }

    logMessage("===== Finalization check complete =====");

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());

    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }

    exit(1);
}

exit(0);
