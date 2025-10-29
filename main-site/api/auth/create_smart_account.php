<?php
/**
 * Crea Smart Account en background después del login
 * Este endpoint se llama automáticamente desde el dashboard
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../utils/node_client.php';

try {
    // Verificar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    // ✅ VERIFICAR si ya tiene smart account (en ambas tablas)
    $stmt = $pdo->prepare('SELECT smart_account_address FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!empty($user['smart_account_address'])) {
        // Ya tiene smart account
        echo json_encode([
            'success' => true,
            'smartAccount' => $user['smart_account_address'],
            'status' => 'exists'
        ]);
        exit;
    }

    // ✅ VERIFICACIÓN ADICIONAL: Comprobar en tabla smart_accounts
    $stmtCheck = $pdo->prepare('SELECT smart_account_address FROM smart_accounts WHERE user_id = ? LIMIT 1');
    $stmtCheck->execute([$userId]);
    $existingAccount = $stmtCheck->fetch();

    if ($existingAccount && !empty($existingAccount['smart_account_address'])) {
        // Ya existe en smart_accounts, sincronizar con users
        $pdo->prepare('UPDATE users SET smart_account_address = ?, updated_at = NOW() WHERE user_id = ?')
            ->execute([$existingAccount['smart_account_address'], $userId]);
        
        echo json_encode([
            'success' => true,
            'smartAccount' => $existingAccount['smart_account_address'],
            'status' => 'exists_synced'
        ]);
        exit;
    }

    // Obtener datos de la sesión
    if (!isset($_SESSION['pending_smart_account'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No pending smart account data']);
        exit;
    }

    $pendingData = $_SESSION['pending_smart_account'];
    
    // Verificar que los datos no sean muy antiguos (máximo 5 minutos)
    if (time() - $pendingData['timestamp'] > 300) {
        unset($_SESSION['pending_smart_account']);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Smart account data expired']);
        exit;
    }

    // Crear smart account llamando al backend Node
    $smartAccountResp = nodeApiRequest('POST', 'devices/link', [
        'ownerAddress' => $pendingData['ownerAddress'],
        'devicePublicKey' => $pendingData['devicePublicKey'],
    ]);

    $smartData = $smartAccountResp['data'] ?? $smartAccountResp;
    $smartAccountAddress = strtolower($smartData['smartAccountAddress'] ?? '');

    if (empty($smartAccountAddress)) {
        throw new RuntimeException('Failed to create smart account');
    }

    // Guardar en base de datos
    $pdo->beginTransaction();

    // ✅ INSERT con user_id como UNIQUE constraint para evitar duplicados
    $insertSql = 'INSERT INTO smart_accounts (
                    user_id, 
                    smart_account_address, 
                    owner_address, 
                    deployment_tx_hash, 
                    is_deployed, 
                    created_at, 
                    updated_at
                  )
                  VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                  ON DUPLICATE KEY UPDATE 
                    updated_at = NOW()';
    
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        $userId,
        $smartAccountAddress,
        $pendingData['ownerAddress'],
        $smartData['deploymentTxHash'] ?? null,
        isset($smartData['isDeployed']) ? (int)$smartData['isDeployed'] : 0
    ]);

    // ✅ Solo actualizar users si NO tiene smart account
    $pdo->prepare('UPDATE users 
                   SET smart_account_address = ?, updated_at = NOW() 
                   WHERE user_id = ? AND (smart_account_address IS NULL OR smart_account_address = "")')
        ->execute([$smartAccountAddress, $userId]);

    $pdo->commit();

    // Limpiar datos pendientes
    unset($_SESSION['pending_smart_account']);

    echo json_encode([
        'success' => true,
        'smartAccount' => $smartAccountAddress,
        'status' => 'created',
        'isDeployed' => isset($smartData['isDeployed']) ? (bool)$smartData['isDeployed'] : false
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('create_smart_account.php error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create smart account',
        'error' => $e->getMessage()
    ]);
}
