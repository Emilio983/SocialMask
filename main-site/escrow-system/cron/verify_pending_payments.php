<?php
/**
 * CRON JOB: Verify Pending Payments
 * Verifica los pagos pendientes de confirmación en la blockchain
 *
 * IMPORTANTE: Configurar en cPanel/Hostinger para ejecutar cada 1-2 minutos:
 * Command: /usr/bin/php /home/username/public_html/escrow-system/cron/verify_pending_payments.php
 * Interval: every 1-2 minutes (cron: slash-1 or slash-2 asterisk asterisk asterisk asterisk)
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

// Log de inicio
$log_file = __DIR__ . '/../logs/payment_verification.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

logMessage("===== Starting payment verification job =====");

try {
    $web3 = new Web3Helper();

    // Obtener pagos pendientes (no confirmados)
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            s.contract_address,
            s.title as survey_title
        FROM payments p
        JOIN surveys s ON p.survey_id = s.id
        WHERE p.confirmed = FALSE
        ORDER BY p.created_at ASC
        LIMIT :max_payments
    ");
    $stmt->bindValue(':max_payments', MAX_PAYMENTS_PER_BATCH, PDO::PARAM_INT);
    $stmt->execute();

    $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMessage("Found " . count($pending_payments) . " pending payments to verify");

    $confirmed_count = 0;
    $failed_count = 0;
    $still_pending = 0;

    foreach ($pending_payments as $payment) {
        $tx_hash = $payment['tx_hash'];
        $payment_id = $payment['id'];

        logMessage("Verifying payment #{$payment_id} - TX: {$tx_hash}");

        // Verificar transacción en blockchain
        $verification = $web3->verifyTransaction($tx_hash);

        if (!$verification) {
            logMessage("  -> Transaction not found or still pending");
            $still_pending++;
            continue;
        }

        if (!$verification['success']) {
            // Transacción falló
            logMessage("  -> Transaction FAILED on blockchain");

            // Marcar como rechazado (opcional - puedes crear un campo 'status' en la tabla)
            // Por ahora solo loguear
            $failed_count++;
            continue;
        }

        $confirmations = $verification['confirmations'];
        logMessage("  -> Transaction successful with {$confirmations} confirmations");

        // Actualizar número de confirmaciones en BD
        $stmt_update_confirmations = $pdo->prepare("
            UPDATE payments
            SET confirmations = :confirmations
            WHERE id = :payment_id
        ");
        $stmt_update_confirmations->execute([
            ':confirmations' => $confirmations,
            ':payment_id' => $payment_id
        ]);

        // Si alcanzó las confirmaciones mínimas, confirmar pago
        if ($confirmations >= MIN_CONFIRMATIONS) {
            logMessage("  -> CONFIRMING payment (reached " . MIN_CONFIRMATIONS . " confirmations)");

            $stmt_confirm = $pdo->prepare("
                UPDATE payments
                SET
                    confirmed = TRUE,
                    confirmed_at = NOW(),
                    block_number = :block_number,
                    gas_used = :gas_used
                WHERE id = :payment_id
            ");

            $stmt_confirm->execute([
                ':block_number' => $verification['block_number'],
                ':gas_used' => $verification['gas_used'],
                ':payment_id' => $payment_id
            ]);

            // Opcional: Verificar evento Deposit del contrato para validación extra
            $deposit_event = $web3->getDepositEvent($tx_hash);

            if ($deposit_event) {
                logMessage("  -> Deposit event validated: Survey ID {$deposit_event['survey_id']}, Amount: {$deposit_event['amount']}");

                // Opcional: Verificar que el amount coincida con el registrado
                if ($deposit_event['amount'] != $payment['amount']) {
                    logMessage("  -> WARNING: Amount mismatch! DB: {$payment['amount']}, Blockchain: {$deposit_event['amount']}");
                }
            }

            $confirmed_count++;

            // Opcional: Enviar notificación al usuario
            // sendPaymentConfirmedNotification($payment);

        } else {
            logMessage("  -> Still waiting for confirmations ({$confirmations}/" . MIN_CONFIRMATIONS . ")");
            $still_pending++;
        }
    }

    logMessage("===== Payment verification complete =====");
    logMessage("Confirmed: {$confirmed_count}, Failed: {$failed_count}, Still pending: {$still_pending}");

    // Si estamos en CLI, mostrar resumen
    if (php_sapi_name() === 'cli') {
        echo "Payment Verification Summary:\n";
        echo "- Confirmed: {$confirmed_count}\n";
        echo "- Failed: {$failed_count}\n";
        echo "- Still pending: {$still_pending}\n";
        echo "- Total processed: " . count($pending_payments) . "\n";
    }

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());

    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }

    exit(1);
}

exit(0);
