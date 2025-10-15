<?php
/**
 * CRON JOB: CHECK SURVEY DEADLINES
 *
 * Ejecutar cada hora vía cron:
 * 0 * * * * php /path/to/check_survey_deadlines.php
 *
 * Funciones:
 * 1. Cierra encuestas que llegaron a su close_date
 * 2. Notifica a creadores para declarar ganador (inmediatamente después de cerrar)
 * 3. Envía recordatorios a las 24h y 47h
 * 4. Marca encuestas >48h sin respuesta para review de admin
 */

require_once __DIR__ . '/../../config/connection.php';

// Log inicio
$log_file = __DIR__ . '/../../logs/survey_deadlines.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    echo "[{$timestamp}] {$message}\n";
}

logMessage("=== Starting survey deadline check ===");

try {
    // ===================================================================
    // STEP 1: Close surveys that have reached their close_date
    // ===================================================================
    $stmt = $pdo->prepare("
        UPDATE surveys
        SET status = 'closed'
        WHERE status = 'active'
        AND close_date <= NOW()
    ");
    $stmt->execute();
    $closed_count = $stmt->rowCount();

    if ($closed_count > 0) {
        logMessage("Closed {$closed_count} surveys that reached their deadline");

        // Get the surveys that were just closed
        $stmt = $pdo->prepare("
            SELECT id, creator_id, title, close_date
            FROM surveys
            WHERE status = 'closed'
            AND close_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND close_date <= NOW()
        ");
        $stmt->execute();
        $newly_closed = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send initial notification to creators
        foreach ($newly_closed as $survey) {
            try {
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        data,
                        created_at
                    ) VALUES (?, 'survey_action_required', 'Survey Closed - Declare Winner', ?, ?, NOW())
                ");

                $message = "Your survey '{$survey['title']}' has closed. You have 48 hours to declare the winner to receive your 10% commission + deposit refund!";
                $data = json_encode([
                    'survey_id' => $survey['id'],
                    'deadline_hours' => 48,
                    'action' => 'declare_winner'
                ]);

                $stmt_notif->execute([$survey['creator_id'], $message, $data]);
                logMessage("Sent initial notification to creator {$survey['creator_id']} for survey {$survey['id']}");
            } catch (Exception $e) {
                logMessage("Error sending notification: " . $e->getMessage());
            }
        }
    }

    // ===================================================================
    // STEP 2: Send 24-hour reminder
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.creator_id,
            s.title,
            s.close_date,
            TIMESTAMPDIFF(HOUR, s.close_date, NOW()) as hours_since_close
        FROM surveys s
        WHERE s.status = 'closed'
        AND TIMESTAMPDIFF(HOUR, s.close_date, NOW()) >= 24
        AND TIMESTAMPDIFF(HOUR, s.close_date, NOW()) < 25
        AND NOT EXISTS (
            SELECT 1 FROM notifications
            WHERE user_id = s.creator_id
            AND type = 'survey_reminder_24h'
            AND JSON_EXTRACT(data, '$.survey_id') = s.id
        )
    ");
    $stmt->execute();
    $reminder_24h = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reminder_24h as $survey) {
        try {
            $stmt_notif = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    message,
                    data,
                    created_at
                ) VALUES (?, 'survey_reminder_24h', 'Reminder: 24 Hours Left', ?, ?, NOW())
            ");

            $message = "⚠️ Reminder: You have 24 hours left to declare the winner for '{$survey['title']}' or your deposit will be forfeited!";
            $data = json_encode([
                'survey_id' => $survey['id'],
                'hours_remaining' => 24,
                'action' => 'declare_winner'
            ]);

            $stmt_notif->execute([$survey['creator_id'], $message, $data]);
            logMessage("Sent 24h reminder to creator {$survey['creator_id']} for survey {$survey['id']}");
        } catch (Exception $e) {
            logMessage("Error sending 24h reminder: " . $e->getMessage());
        }
    }

    // ===================================================================
    // STEP 3: Send 47-hour URGENT reminder (1 hour before deadline)
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.creator_id,
            s.title,
            s.close_date,
            TIMESTAMPDIFF(HOUR, s.close_date, NOW()) as hours_since_close
        FROM surveys s
        WHERE s.status = 'closed'
        AND TIMESTAMPDIFF(HOUR, s.close_date, NOW()) >= 47
        AND TIMESTAMPDIFF(HOUR, s.close_date, NOW()) < 48
        AND NOT EXISTS (
            SELECT 1 FROM notifications
            WHERE user_id = s.creator_id
            AND type = 'survey_reminder_urgent'
            AND JSON_EXTRACT(data, '$.survey_id') = s.id
        )
    ");
    $stmt->execute();
    $reminder_urgent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reminder_urgent as $survey) {
        try {
            $stmt_notif = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    message,
                    data,
                    created_at
                ) VALUES (?, 'survey_reminder_urgent', '🚨 URGENT: 1 Hour Left!', ?, ?, NOW())
            ");

            $message = "🚨 URGENT: Only 1 hour left to declare winner for '{$survey['title']}'! Declare now or lose your 10 SPHE deposit!";
            $data = json_encode([
                'survey_id' => $survey['id'],
                'hours_remaining' => 1,
                'action' => 'declare_winner',
                'urgent' => true
            ]);

            $stmt_notif->execute([$survey['creator_id'], $message, $data]);
            logMessage("Sent URGENT reminder to creator {$survey['creator_id']} for survey {$survey['id']}");
        } catch (Exception $e) {
            logMessage("Error sending urgent reminder: " . $e->getMessage());
        }
    }

    // ===================================================================
    // STEP 4: Mark surveys >48h as needing admin review
    // ===================================================================
    $stmt = $pdo->prepare("
        UPDATE surveys
        SET
            status = 'awaiting_admin',
            admin_review_required = TRUE,
            deadline_exceeded_at = NOW()
        WHERE status = 'closed'
        AND TIMESTAMPDIFF(HOUR, close_date, NOW()) > 48
    ");
    $stmt->execute();
    $admin_review_count = $stmt->rowCount();

    if ($admin_review_count > 0) {
        logMessage("Marked {$admin_review_count} surveys as requiring admin review (>48h)");

        // Get surveys needing admin review
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.creator_id,
                s.title,
                s.close_date,
                u.username as creator_username,
                (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id) as total_responses,
                (SELECT SUM(usa.payment_amount)
                 FROM survey_responses sr
                 INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
                 WHERE sr.survey_id = s.id) as total_pool
            FROM surveys s
            INNER JOIN users u ON s.creator_id = u.user_id
            WHERE s.status = 'awaiting_admin'
            AND s.deadline_exceeded_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $admin_surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Notify creator that they missed the deadline
        foreach ($admin_surveys as $survey) {
            try {
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        data,
                        created_at
                    ) VALUES (?, 'survey_deadline_missed', 'Deadline Missed - Deposit Forfeited', ?, ?, NOW())
                ");

                $message = "You did not declare a winner for '{$survey['title']}' within 48 hours. Your 10 SPHE deposit has been forfeited. An admin will now handle the survey.";
                $data = json_encode([
                    'survey_id' => $survey['id'],
                    'deposit_forfeited' => 10
                ]);

                $stmt_notif->execute([$survey['creator_id'], $message, $data]);
                logMessage("Notified creator {$survey['creator_id']} about missed deadline for survey {$survey['id']}");
            } catch (Exception $e) {
                logMessage("Error notifying creator: " . $e->getMessage());
            }

            // Log for admin review
            logMessage("Survey {$survey['id']} ({$survey['title']}) by {$survey['creator_username']} needs admin review - {$survey['total_responses']} responses, {$survey['total_pool']} SPHE pool");
        }

        // Create admin notification (notify all admins)
        try {
            $stmt_admins = $pdo->prepare("SELECT user_id FROM users WHERE role = 'admin'");
            $stmt_admins->execute();
            $admins = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);

            foreach ($admins as $admin_id) {
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        created_at
                    ) VALUES (?, 'admin_survey_review', 'Surveys Require Review', ?, NOW())
                ");

                $message = "{$admin_review_count} survey(s) need admin review due to missed creator deadlines. Check the admin panel.";
                $stmt_notif->execute([$admin_id, $message]);
            }
            logMessage("Notified " . count($admins) . " admin(s) about surveys needing review");
        } catch (Exception $e) {
            logMessage("Error notifying admins: " . $e->getMessage());
        }
    }

    // ===================================================================
    // STEP 5: Auto-close surveys in limbo (safeguard)
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT id, title, close_date
        FROM surveys
        WHERE status = 'active'
        AND close_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $limbo_surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($limbo_surveys) > 0) {
        logMessage("WARNING: Found " . count($limbo_surveys) . " surveys in limbo (active but past close date by >24h)");
        foreach ($limbo_surveys as $survey) {
            logMessage("  - Survey {$survey['id']}: {$survey['title']} (close_date: {$survey['close_date']})");
        }
    }

    logMessage("=== Survey deadline check completed successfully ===");

} catch (PDOException $e) {
    logMessage("ERROR: Database error - " . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}

logMessage("");
?>
