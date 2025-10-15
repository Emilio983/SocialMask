<?php
/**
 * API: Registrar Compra de Contenido
 * Endpoint: POST /api/paywall/record_purchase.php
 * 
 * Registra una compra de contenido en la base de datos
 * Esta API es llamada después de una transacción exitosa en blockchain
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/response.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Autenticación requerida
$user = authenticate();
if (!$user) {
    sendError('Unauthorized', 401);
}

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
if (!isset($input['content_id']) || !isset($input['tx_hash'])) {
    sendError('content_id and tx_hash are required', 400);
}

$content_id = (int)$input['content_id'];
$tx_hash = trim($input['tx_hash']);
$gelato_task_id = isset($input['gelato_task_id']) ? trim($input['gelato_task_id']) : null;

// Validaciones
if ($content_id <= 0) {
    sendError('Invalid content ID', 400);
}

if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
    sendError('Invalid transaction hash format', 400);
}

try {
    $db = getConnection();
    $db->beginTransaction();
    
    // Verificar que el contenido exista
    $stmt = $db->prepare("
        SELECT id, user_id, price, contract_content_id
        FROM paywall_content
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$content_id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        $db->rollBack();
        sendError('Content not found', 404);
    }
    
    // Verificar que no sea el creador
    if ($content['user_id'] == $user['id']) {
        $db->rollBack();
        sendError('Creators cannot purchase their own content', 400);
    }
    
    // Verificar que la transacción no esté ya registrada
    $stmt = $db->prepare("
        SELECT id, status FROM paywall_purchases
        WHERE tx_hash = ?
    ");
    $stmt->execute([$tx_hash]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        if ($existing['status'] === 'confirmed') {
            $db->rollBack();
            sendSuccess([
                'message' => 'Purchase already recorded',
                'purchase_id' => $existing['id'],
                'already_exists' => true
            ]);
        } else {
            // Actualizar status de pending a confirmed
            $stmt = $db->prepare("
                UPDATE paywall_purchases
                SET status = 'confirmed',
                    confirmed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$existing['id']]);
            $purchase_id = $existing['id'];
        }
    } else {
        // Insertar nueva compra
        $stmt = $db->prepare("
            INSERT INTO paywall_purchases (
                content_id,
                user_id,
                tx_hash,
                gelato_task_id,
                price,
                status,
                confirmed_at
            ) VALUES (?, ?, ?, ?, ?, 'confirmed', CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $content_id,
            $user['id'],
            $tx_hash,
            $gelato_task_id,
            $content['price']
        ]);
        
        $purchase_id = $db->lastInsertId();
    }
    
    // Crear acceso en caché
    $stmt = $db->prepare("
        INSERT IGNORE INTO paywall_access (
            content_id,
            user_id,
            wallet_address
        ) VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $content_id,
        $user['id'],
        $user['wallet_address']
    ]);
    
    // Log de actividad
    $stmt = $db->prepare("
        INSERT INTO activity_log (user_id, action, details)
        VALUES (?, 'paywall_purchase', ?)
    ");
    $stmt->execute([
        $user['id'],
        json_encode([
            'content_id' => $content_id,
            'purchase_id' => $purchase_id,
            'tx_hash' => $tx_hash,
            'price' => $content['price']
        ])
    ]);
    
    // Notificar al creador (opcional)
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, link)
        VALUES (?, 'paywall_sale', ?, ?, ?)
    ");
    $stmt->execute([
        $content['user_id'],
        'New Sale!',
        $user['username'] . ' purchased your content',
        '/paywall/earnings'
    ]);
    
    $db->commit();
    
    // Obtener información completa de la compra
    $stmt = $db->prepare("
        SELECT * FROM v_paywall_purchases_full
        WHERE id = ?
    ");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'message' => 'Purchase recorded successfully',
        'purchase' => $purchase,
        'has_access' => true
    ], 201);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error in record_purchase: " . $e->getMessage());
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in record_purchase: " . $e->getMessage());
    sendError('Server error: ' . $e->getMessage(), 500);
}
