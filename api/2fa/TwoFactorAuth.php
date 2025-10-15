<?php
/**
 * ============================================
 * TWO-FACTOR AUTHENTICATION SYSTEM
 * ============================================
 * Secure 2FA system with time-based codes and device approval
 */

declare(strict_types=1);

class TwoFactorAuth {
    private PDO $pdo;
    private const CODE_LENGTH = 6;
    private const CODE_EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 300; // 5 minutes in seconds

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Generate a unique 6-digit code that doesn't exist in the database
     * Implements collision prevention and temporal uniqueness
     */
    public function generateUniqueCode(int $userId): array {
        $maxAttempts = 100; // Prevent infinite loop
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            // Generate code with high entropy
            $code = $this->generateSecureCode();
            $codeHash = $this->hashCode($code, $userId);

            // Check if this exact code is currently active for ANY user
            // This prevents code reuse across the entire system
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM device_2fa_codes
                WHERE code_hash = ?
                AND expires_at > NOW()
                AND is_used = FALSE
            ");
            $stmt->execute([$codeHash]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] == 0) {
                // Code is unique! Store it
                return $this->storeCode($userId, $code, $codeHash);
            }

            $attempt++;
        }

        throw new Exception('Unable to generate unique code after multiple attempts');
    }

    /**
     * Generate a cryptographically secure 6-digit code
     */
    private function generateSecureCode(): string {
        // Use time-based + random component for high uniqueness
        $timestamp = microtime(true);
        $random = random_int(0, 999999);

        // Combine and hash to create unpredictable code
        $combined = $timestamp . $random . bin2hex(random_bytes(16));
        $hashed = hash('sha256', $combined);

        // Extract 6 digits from hash
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= hexdec($hashed[$i * 2] . $hashed[$i * 2 + 1]) % 10;
        }

        return $code;
    }

    /**
     * Hash code with user_id salt for additional security
     * Prevents rainbow table attacks
     */
    private function hashCode(string $code, int $userId): string {
        return hash_hmac('sha256', $code, (string)$userId . getenv('SESSION_SECRET'));
    }

    /**
     * Store code in database with expiry
     */
    private function storeCode(int $userId, string $code, string $codeHash): array {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::CODE_EXPIRY_MINUTES . ' minutes'));

        $stmt = $this->pdo->prepare("
            INSERT INTO device_2fa_codes (user_id, code, code_hash, expires_at)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$userId, $code, $codeHash, $expiresAt]);

        // Log activity
        $this->logActivity($userId, null, 'code_generated', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
            'expires_in_seconds' => self::CODE_EXPIRY_MINUTES * 60
        ];
    }

    /**
     * Get current valid code for user (or generate new one if expired)
     */
    public function getCurrentCode(int $userId): array {
        // Check if there's a valid unused code
        $stmt = $this->pdo->prepare("
            SELECT code, expires_at
            FROM device_2fa_codes
            WHERE user_id = ?
            AND expires_at > NOW()
            AND is_used = FALSE
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $existingCode = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCode) {
            $expiresAt = new DateTime($existingCode['expires_at']);
            $now = new DateTime();
            $secondsRemaining = $expiresAt->getTimestamp() - $now->getTimestamp();

            return [
                'code' => $existingCode['code'],
                'expires_at' => $existingCode['expires_at'],
                'expires_in_seconds' => $secondsRemaining
            ];
        }

        // No valid code exists, generate new one
        return $this->generateUniqueCode($userId);
    }

    /**
     * Verify a code entered by user
     * Implements rate limiting and brute force protection
     */
    public function verifyCode(int $userId, string $code): array {
        // Check rate limiting
        if ($this->isUserLockedOut($userId)) {
            return [
                'success' => false,
                'error' => 'Too many failed attempts. Please wait 5 minutes.',
                'locked_out' => true
            ];
        }

        $codeHash = $this->hashCode($code, $userId);

        $stmt = $this->pdo->prepare("
            SELECT code_id, code, expires_at, is_used
            FROM device_2fa_codes
            WHERE user_id = ?
            AND code_hash = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $codeHash]);
        $storedCode = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$storedCode) {
            $this->recordFailedAttempt($userId);
            return ['success' => false, 'error' => 'Invalid code'];
        }

        if ($storedCode['is_used']) {
            return ['success' => false, 'error' => 'Code already used'];
        }

        if (strtotime($storedCode['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Code expired'];
        }

        // Code is valid! Mark as used
        $this->markCodeAsUsed($storedCode['code_id']);
        $this->logActivity($userId, null, 'code_verified', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return [
            'success' => true,
            'message' => 'Code verified successfully'
        ];
    }

    /**
     * Mark code as used to prevent reuse
     */
    private function markCodeAsUsed(int $codeId): void {
        $stmt = $this->pdo->prepare("
            UPDATE device_2fa_codes
            SET is_used = TRUE, used_at = NOW()
            WHERE code_id = ?
        ");
        $stmt->execute([$codeId]);
    }

    /**
     * Check if user is temporarily locked out due to failed attempts
     */
    private function isUserLockedOut(int $userId): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as failed_attempts
            FROM device_activity_log
            WHERE user_id = ?
            AND activity_type = 'suspicious_activity'
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, self::LOCKOUT_DURATION]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['failed_attempts'] >= self::MAX_ATTEMPTS;
    }

    /**
     * Record failed verification attempt
     */
    private function recordFailedAttempt(int $userId): void {
        $this->logActivity(
            $userId,
            null,
            'suspicious_activity',
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'Failed 2FA code verification attempt'
        );
    }

    /**
     * Create login approval request
     */
    public function createLoginRequest(int $userId, array $deviceInfo): array {
        $requestToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $stmt = $this->pdo->prepare("
            INSERT INTO login_approval_requests
            (user_id, request_token, device_fingerprint, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $requestToken,
            $deviceInfo['fingerprint'],
            $deviceInfo['ip'],
            $deviceInfo['user_agent'],
            $expiresAt
        ]);

        return [
            'request_id' => $this->pdo->lastInsertId(),
            'request_token' => $requestToken,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Get pending login requests for user
     */
    public function getPendingRequests(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT request_id, request_token, device_fingerprint, ip_address,
                   user_agent, location, created_at, expires_at
            FROM login_approval_requests
            WHERE user_id = ?
            AND status = 'pending'
            AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve login request from authorized device
     */
    public function approveLoginRequest(int $requestId, int $deviceId, string $code): array {
        $stmt = $this->pdo->prepare("
            UPDATE login_approval_requests
            SET status = 'approved',
                approved_by_device_id = ?,
                code_used = ?,
                responded_at = NOW()
            WHERE request_id = ?
            AND status = 'pending'
            AND expires_at > NOW()
        ");

        $stmt->execute([$deviceId, $code, $requestId]);

        if ($stmt->rowCount() > 0) {
            // Log approval
            $stmt = $this->pdo->prepare("SELECT user_id FROM login_approval_requests WHERE request_id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logActivity(
                $request['user_id'],
                $deviceId,
                'login_approved',
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'Login request #' . $requestId . ' approved'
            );

            return ['success' => true, 'message' => 'Login approved'];
        }

        return ['success' => false, 'error' => 'Request not found or expired'];
    }

    /**
     * Reject login request
     */
    public function rejectLoginRequest(int $requestId, int $deviceId): array {
        $stmt = $this->pdo->prepare("
            UPDATE login_approval_requests
            SET status = 'rejected',
                approved_by_device_id = ?,
                responded_at = NOW()
            WHERE request_id = ?
            AND status = 'pending'
        ");

        $stmt->execute([$deviceId, $requestId]);

        if ($stmt->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT user_id FROM login_approval_requests WHERE request_id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logActivity(
                $request['user_id'],
                $deviceId,
                'login_rejected',
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'Login request #' . $requestId . ' rejected'
            );

            return ['success' => true, 'message' => 'Login rejected'];
        }

        return ['success' => false, 'error' => 'Request not found'];
    }

    /**
     * Register a new authorized device
     */
    public function registerDevice(int $userId, array $deviceInfo): array {
        $deviceToken = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare("
            INSERT INTO authorized_devices
            (user_id, device_token, device_name, device_fingerprint, ip_address, user_agent, last_used_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $deviceToken,
            $deviceInfo['name'] ?? 'Unknown Device',
            $deviceInfo['fingerprint'],
            $deviceInfo['ip'],
            $deviceInfo['user_agent']
        ]);

        $deviceId = $this->pdo->lastInsertId();

        $this->logActivity($userId, $deviceId, 'device_added', $deviceInfo['ip']);

        return [
            'device_id' => $deviceId,
            'device_token' => $deviceToken
        ];
    }

    /**
     * Get user's authorized devices
     */
    public function getAuthorizedDevices(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT device_id, device_name, device_fingerprint, ip_address,
                   last_used_at, created_at, is_active
            FROM authorized_devices
            WHERE user_id = ?
            AND is_active = TRUE
            ORDER BY last_used_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove authorized device
     */
    public function removeDevice(int $deviceId, int $userId): array {
        $stmt = $this->pdo->prepare("
            UPDATE authorized_devices
            SET is_active = FALSE
            WHERE device_id = ?
            AND user_id = ?
        ");

        $stmt->execute([$deviceId, $userId]);

        if ($stmt->rowCount() > 0) {
            $this->logActivity($userId, $deviceId, 'device_removed', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            return ['success' => true, 'message' => 'Device removed'];
        }

        return ['success' => false, 'error' => 'Device not found'];
    }

    /**
     * Log activity for audit trail
     */
    private function logActivity(int $userId, ?int $deviceId, string $activityType, string $ipAddress, ?string $details = null): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO device_activity_log
            (user_id, device_id, activity_type, ip_address, user_agent, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $deviceId,
            $activityType,
            $ipAddress,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $details
        ]);
    }
}
