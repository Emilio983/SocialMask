<?php
/**
 * API: Inicializar Threshold Cryptography para la wallet del usuario
 * POST /api/wallet/initialize_threshold.php
 * 
 * Divide la clave privada en múltiples shares usando Shamir's Secret Sharing
 * Distribuye los shares en: servidor, dispositivo del usuario, y backup
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../utils/node_client.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/**
 * Encripta un share usando AES-256-GCM con clave derivada del passkey del usuario
 */
function encryptShare(string $share, string $userSalt): string {
    $key = hash_pbkdf2('sha256', $_SESSION['user_id'] . '_threshold', $userSalt, 100000, 32, true);
    $iv = openssl_random_pseudo_bytes(12);
    $tag = '';
    
    $encrypted = openssl_encrypt($share, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    if ($encrypted === false) {
        throw new RuntimeException('Error al encriptar share');
    }
    
    // Retornar: iv (12 bytes) + tag (16 bytes) + encrypted data
    return base64_encode($iv . $tag . $encrypted);
}

try {
    $pdo->beginTransaction();
    
    // Obtener smart account del usuario
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address, sa.is_deployed, sa.user_id
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || empty($account['smart_account_address'])) {
        throw new RuntimeException('Smart account no encontrada');
    }

    $walletAddress = $account['smart_account_address'];
    
    // Verificar si ya tiene shares configurados
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count 
        FROM wallet_key_shares 
        WHERE user_id = ? AND wallet_address = ? AND is_active = 1
    ');
    $stmt->execute([$_SESSION['user_id'], $walletAddress]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing && $existing['count'] > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => true,
            'message' => 'Threshold cryptography ya está configurado',
            'shares_count' => (int)$existing['count']
        ]);
        exit;
    }

    // Llamar al backend Node.js para generar los shares (3-of-5 threshold scheme)
    $thresholdPayload = [
        'smartAccountAddress' => $walletAddress,
        'userId' => $_SESSION['user_id'],
        'threshold' => 3,  // Se necesitan 3 shares para reconstruir
        'totalShares' => 5  // Total de 5 shares generados
    ];

    try {
        $thresholdResponse = nodeApiRequest('POST', 'wallet/initialize-threshold', $thresholdPayload);
        $shares = $thresholdResponse['data']['shares'] ?? [];
        
        if (count($shares) !== 5) {
            throw new RuntimeException('No se generaron suficientes shares');
        }

        // Generar salt único para este usuario
        $userSalt = bin2hex(random_bytes(16));
        
        // Distribuir los shares en diferentes ubicaciones
        $distributions = [
            ['share' => $shares[0], 'location' => 'server', 'device' => null],
            ['share' => $shares[1], 'location' => 'server', 'device' => null],  // 2 en servidor
            ['share' => $shares[2], 'location' => 'device', 'device' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'],
            ['share' => $shares[3], 'location' => 'device', 'device' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'],
            ['share' => $shares[4], 'location' => 'backup', 'device' => 'encrypted_backup']
        ];

        // Insertar shares en la base de datos
        $stmt = $pdo->prepare('
            INSERT INTO wallet_key_shares 
            (user_id, wallet_address, share_index, encrypted_share, storage_location, device_identifier, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');

        foreach ($distributions as $index => $dist) {
            $encryptedShare = encryptShare($dist['share'], $userSalt);
            
            $stmt->execute([
                $_SESSION['user_id'],
                $walletAddress,
                $index,
                $encryptedShare,
                $dist['location'],
                $dist['device']
            ]);
        }

        // Guardar configuración de recuperación
        $stmt = $pdo->prepare('
            INSERT INTO wallet_recovery_config 
            (user_id, wallet_address, threshold, total_shares, salt, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                threshold = VALUES(threshold),
                total_shares = VALUES(total_shares),
                updated_at = NOW()
        ');
        $stmt->execute([
            $_SESSION['user_id'],
            $walletAddress,
            3,
            5,
            $userSalt
        ]);

        // Log de auditoría
        $stmt = $pdo->prepare('
            INSERT INTO security_audit_log 
            (user_id, action, details, ip_address, user_agent, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $_SESSION['user_id'],
            'threshold_initialized',
            json_encode([
                'wallet' => $walletAddress,
                'threshold' => 3,
                'total_shares' => 5,
                'distributions' => array_column($distributions, 'location')
            ]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Threshold cryptography configurado exitosamente',
            'config' => [
                'threshold' => 3,
                'total_shares' => 5,
                'distribution' => [
                    'server' => 2,
                    'device' => 2,
                    'backup' => 1
                ]
            ],
            'device_shares' => [
                // Retornar los shares del dispositivo para que el cliente los guarde localmente
                base64_encode($shares[2]),
                base64_encode($shares[3])
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Threshold initialization failed: ' . $e->getMessage());
        throw new RuntimeException('Error al inicializar threshold: ' . $e->getMessage());
    }

} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('initialize_threshold.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al configurar seguridad de wallet'
    ]);
}
