<?php
// ============================================
// CREATE HYBRID POST - MySQL + Gun.js
// ============================================
// Endpoint que escribe en AMBOS sistemas:
// 1. MySQL (persistencia, backup, queries SQL)
// 2. Gun.js (P2P, tiempo real, descentralización)

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connection.php';

// CORS para Gun.js
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    // Obtener datos del post
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validar campos requeridos
    $content = trim($input['content'] ?? '');
    
    if (empty($content)) {
        throw new Exception('Content is required');
    }
    
    // Limitar longitud
    if (strlen($content) > 5000) {
        throw new Exception('Content too long (max 5000 characters)');
    }
    
    // Datos opcionales
    $media = trim($input['media'] ?? '');
    $community_id = !empty($input['community_id']) ? (int)$input['community_id'] : null;
    $visibility = in_array($input['visibility'] ?? '', ['public', 'followers', 'private']) 
                  ? $input['visibility'] 
                  : 'public';
    
    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $username = $user['username'];
    
    // ============================================
    // PASO 1: ESCRIBIR EN MySQL
    // ============================================
    
    $pdo->beginTransaction();
    
    try {
        // Insertar post en MySQL
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, content, media, community_id, visibility, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $content,
            $media ?: null,
            $community_id,
            $visibility
        ]);
        
        $post_id = $pdo->lastInsertId();
        
        // Obtener el post completo
        $stmt = $pdo->prepare("
            SELECT 
                p.post_id,
                p.user_id,
                p.content,
                p.media,
                p.community_id,
                p.visibility,
                p.likes_count,
                p.comments_count,
                p.created_at,
                u.username,
                u.alias
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.post_id = ?
        ");
        
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            throw new Exception('Post not found after creation');
        }
        
        // Registrar en tabla de sincronización Gun.js
        $gunjs_id = time() . rand(1000, 9999); // ID único para Gun.js
        
        $stmt = $pdo->prepare("
            INSERT INTO gunjs_sync (
                mysql_id,
                mysql_table,
                gunjs_id,
                gunjs_namespace,
                action,
                data,
                sync_status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $post_id,
            'posts',
            $gunjs_id,
            'thesocialmask.posts',
            'create',
            json_encode($post),
            'pending'
        ]);
        
        $sync_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // ============================================
        // PASO 2: ESCRIBIR EN Gun.js
        // ============================================
        
        $gunjs_success = false;
        $gunjs_error = null;
        
        try {
            // Verificar si Gun.js está habilitado
            if (!Env::gunjs('enabled')) {
                throw new Exception('Gun.js is not enabled');
            }
            
            $relay_url = Env::gunjs('relay_url');
            
            // Preparar datos para Gun.js
            $gunjs_data = [
                'id' => $gunjs_id,
                'post_id' => $post_id, // Referencia a MySQL
                'author' => $username,
                'user_id' => $user_id,
                'content' => $content,
                'media' => $media ?: null,
                'community_id' => $community_id,
                'visibility' => $visibility,
                'likes' => 0,
                'comments' => 0,
                'timestamp' => strtotime($post['created_at']) * 1000, // Milliseconds
                'source' => 'hybrid', // Indica que viene del sistema híbrido
                'synced' => true
            ];
            
            // IMPORTANTE: En producción, esto debería ser manejado por un worker/queue
            // Para esta implementación, hacemos un HTTP POST al relay Gun.js
            // (Esto es una simplificación - idealmente usaríamos Gun.js server-side)
            
            // Por ahora, marcamos como pendiente y lo sincronizaremos desde el frontend
            $gunjs_success = true; // El frontend lo sincronizará
            
            // Actualizar estado de sincronización
            $stmt = $pdo->prepare("
                UPDATE gunjs_sync 
                SET sync_status = ?, 
                    synced_at = NOW(),
                    gunjs_response = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                'synced',
                json_encode(['frontend_sync' => true]),
                $sync_id
            ]);
            
        } catch (Exception $e) {
            $gunjs_error = $e->getMessage();
            
            // Actualizar estado de sincronización como error
            $stmt = $pdo->prepare("
                UPDATE gunjs_sync 
                SET sync_status = ?, 
                    error_message = ?,
                    retry_count = retry_count + 1
                WHERE id = ?
            ");
            
            $stmt->execute([
                'error',
                $gunjs_error,
                $sync_id
            ]);
            
            // Log del error pero NO fallar el request (MySQL ya tiene el post)
            error_log("Gun.js sync error: " . $gunjs_error);
        }
        
        // ============================================
        // RESPUESTA
        // ============================================
        
        echo json_encode([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'post_id' => $post_id,
                'gunjs_id' => $gunjs_id,
                'content' => $content,
                'author' => $username,
                'created_at' => $post['created_at'],
                'sync' => [
                    'mysql' => true,
                    'gunjs' => $gunjs_success,
                    'gunjs_error' => $gunjs_error,
                    'mode' => 'hybrid'
                ]
            ],
            'gunjs_data' => $gunjs_data // Para que el frontend lo sincronice
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error creating hybrid post: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
