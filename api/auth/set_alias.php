<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $alias = isset($body['alias']) ? strtolower(trim($body['alias'])) : '';

    if (strlen($alias) < 3 || strlen($alias) > 30) {
        throw new RuntimeException('El alias debe tener entre 3 y 30 caracteres.');
    }

    if (!preg_match('/^[a-z0-9_.-]+$/', $alias)) {
        throw new RuntimeException('El alias solo puede contener letras, números, punto, guion y guion bajo.');
    }

    if (filter_var($alias, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('No uses correos electrónicos como alias.');
    }

    $forbiddenPrefixes = ['anon_', 'admin', 'thesocialmask'];
    foreach ($forbiddenPrefixes as $prefix) {
        if (strpos($alias, $prefix) === 0) {
            throw new RuntimeException('Alias no permitido. Elige otro seudónimo.');
        }
    }

    $reservedStmt = $pdo->prepare('SELECT id FROM reserved_usernames WHERE username = ? LIMIT 1');
    $reservedStmt->execute([$alias]);
    if ($reservedStmt->fetch()) {
        throw new RuntimeException('Alias reservado. Selecciona otro.');
    }

    $uniqueStmt = $pdo->prepare('SELECT user_id FROM users WHERE alias = ? AND user_id <> ? LIMIT 1');
    $uniqueStmt->execute([$alias, $_SESSION['user_id']]);
    if ($uniqueStmt->fetch()) {
        throw new RuntimeException('Ese alias ya está en uso. Elige otro.');
    }

    $pdo->prepare('UPDATE users SET alias = ?, updated_at = NOW() WHERE user_id = ?')
        ->execute([$alias, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'alias' => $alias,
    ]);
} catch (Throwable $e) {
    error_log('set_alias.php error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'No se pudo guardar el alias',
    ]);
}
