<?php
/**
 * ADMIN API: USER ACTIONS
 * Handle all admin actions on users: suspend, ban, freeze, delete, verify
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');
session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

$user_id = $data['user_id'] ?? null;
$action = $data['action'] ?? null;
$reason = $data['reason'] ?? null;
$duration_hours = $data['duration_hours'] ?? null;

// Validate input
if (!$user_id || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Prevent action on self
if ($user_id == $admin_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot perform action on yourself']);
    exit;
}

// Get target user
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Prevent action on other admins
if ($target_user['role'] === 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cannot perform action on another admin']);
    exit;
}

try {
    $pdo->beginTransaction();

    switch ($action) {
        case 'suspend':
            // Suspend user (temporary, can be lifted)
            $suspended_until = $duration_hours ? date('Y-m-d H:i:s', strtotime("+$duration_hours hours")) : null;

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'suspended',
                    suspension_reason = ?,
                    suspended_until = ?,
                    suspended_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$reason, $suspended_until, $admin_id, $user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason, duration_hours)
                VALUES (?, ?, 'suspend', ?, ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason, $duration_hours]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data)
                VALUES (?, 'system', 'Account Suspended', ?, ?)
            ");
            $notification_message = $reason ? "Your account has been suspended. Reason: $reason" : "Your account has been suspended.";
            $notification_data = json_encode(['action' => 'suspended', 'duration_hours' => $duration_hours]);
            $stmt->execute([$user_id, $notification_message, $notification_data]);

            $response_message = 'User suspended successfully';
            break;

        case 'unsuspend':
            // Restore user to active status
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'active',
                    suspension_reason = NULL,
                    suspended_until = NULL,
                    suspended_by = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'unsuspend', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, 'system', 'Account Restored', 'Your account has been restored to active status.')
            ");
            $stmt->execute([$user_id]);

            $response_message = 'User account restored successfully';
            break;

        case 'freeze':
            // Freeze account (no login, no activity)
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'frozen',
                    suspension_reason = ?,
                    suspended_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$reason, $admin_id, $user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'freeze', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data)
                VALUES (?, 'system', 'Account Frozen', ?, ?)
            ");
            $notification_message = $reason ? "Your account has been frozen. Reason: $reason" : "Your account has been frozen pending investigation.";
            $notification_data = json_encode(['action' => 'frozen']);
            $stmt->execute([$user_id, $notification_message, $notification_data]);

            $response_message = 'User account frozen successfully';
            break;

        case 'unfreeze':
            // Unfreeze account
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'active',
                    suspension_reason = NULL,
                    suspended_by = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'unfreeze', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, 'system', 'Account Unfrozen', 'Your account has been unfrozen and is now active.')
            ");
            $stmt->execute([$user_id]);

            $response_message = 'User account unfrozen successfully';
            break;

        case 'ban':
            // Permanent ban (cannot be lifted easily)
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'banned',
                    suspension_reason = ?,
                    suspended_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$reason, $admin_id, $user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'ban', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data)
                VALUES (?, 'system', 'Account Banned', ?, ?)
            ");
            $notification_message = $reason ? "Your account has been permanently banned. Reason: $reason" : "Your account has been permanently banned from the platform.";
            $notification_data = json_encode(['action' => 'banned']);
            $stmt->execute([$user_id, $notification_message, $notification_data]);

            $response_message = 'User banned permanently';
            break;

        case 'unban':
            // Remove ban
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'active',
                    suspension_reason = NULL,
                    suspended_by = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'unban', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, 'system', 'Ban Lifted', 'Your ban has been lifted. Welcome back!')
            ");
            $stmt->execute([$user_id]);

            $response_message = 'User unbanned successfully';
            break;

        case 'verify':
            // Grant verification badge
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    is_verified = TRUE,
                    verification_date = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'verify', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, 'system', 'Account Verified', 'Congratulations! Your account has been verified.')
            ");
            $stmt->execute([$user_id]);

            // Award 100 SPHE bonus for verification
            $stmt = $pdo->prepare("UPDATE users SET sphe_balance = sphe_balance + 100 WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("
                INSERT INTO sphe_transactions (from_user_id, to_user_id, transaction_type, amount, description)
                VALUES (NULL, ?, 'reward', 100, 'Verification bonus')
            ");
            $stmt->execute([$user_id]);

            $response_message = 'User verified successfully (100 SPHE bonus awarded)';
            break;

        case 'unverify':
            // Remove verification
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    is_verified = FALSE,
                    verification_date = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'unverify', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            $response_message = 'Verification removed';
            break;

        case 'delete':
            // Soft delete (mark as deleted, don't actually remove)
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    account_status = 'deleted',
                    suspension_reason = ?,
                    suspended_by = ?,
                    email = CONCAT('deleted_', user_id, '@deleted.thesocialmask.org'),
                    wallet_address = CONCAT('0xDELETED', user_id)
                WHERE user_id = ?
            ");
            $stmt->execute([$reason, $admin_id, $user_id]);

            // Log action
            $stmt = $pdo->prepare("
                INSERT INTO user_action_logs (admin_id, target_user_id, action_type, reason)
                VALUES (?, ?, 'delete', ?)
            ");
            $stmt->execute([$admin_id, $user_id, $reason]);

            $response_message = 'User account deleted';
            break;

        default:
            throw new Exception('Invalid action');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $response_message,
        'action' => $action,
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to perform action: ' . $e->getMessage()
    ]);
}
?>
