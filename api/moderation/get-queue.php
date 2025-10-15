<?php
/**
 * Get Moderation Queue
 * Retrieve pending reports for moderators
 * Requires moderator role
 */

require_once '../../config/config.php';
require_once '../utils.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $moderatorId = $_SESSION['user_id'];
    
    // Check if user is moderator
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$moderatorId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['moderator', 'admin'])) {
        throw new Exception('Moderator privileges required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $status = $input['status'] ?? 'pending';
    $category = $input['category'] ?? null;
    $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
    $limit = isset($input['limit']) ? min(50, max(1, intval($input['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Build query
    $where = ['cr.status = ?'];
    $params = [$status];
    
    if ($category) {
        $where[] = 'cr.category = ?';
        $params[] = $category;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get reports
    $stmt = $pdo->prepare("
        SELECT 
            cr.id,
            cr.target_type,
            cr.target_id,
            cr.category,
            cr.description,
            cr.evidence,
            cr.priority,
            cr.status,
            cr.created_at,
            cr.updated_at,
            u.username as reporter_username,
            u.id as reporter_id,
            m.username as assigned_moderator,
            COUNT(*) OVER (PARTITION BY cr.target_type, cr.target_id) as report_count
        FROM content_reports cr
        LEFT JOIN users u ON cr.reporter_id = u.id
        LEFT JOIN users m ON cr.assigned_to = m.id
        WHERE {$whereClause}
        ORDER BY cr.priority DESC, cr.created_at ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM content_reports cr
        WHERE {$whereClause}
    ");
    $stmt->execute(array_slice($params, 0, -2));
    $totalReports = $stmt->fetchColumn();
    
    // Format reports
    foreach ($reports as &$report) {
        $report['evidence'] = json_decode($report['evidence'] ?? '[]', true);
        
        // Get target details
        switch ($report['target_type']) {
            case 'post':
                $stmt = $pdo->prepare('SELECT user_id, content, created_at FROM posts WHERE id = ?');
                $stmt->execute([$report['target_id']]);
                $report['target_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'comment':
                $stmt = $pdo->prepare('SELECT user_id, content, created_at FROM comments WHERE id = ?');
                $stmt->execute([$report['target_id']]);
                $report['target_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'user':
                $stmt = $pdo->prepare('SELECT username, email, created_at FROM users WHERE id = ?');
                $stmt->execute([$report['target_id']]);
                $report['target_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
        }
    }
    
    // Get statistics
    $stmt = $pdo->query('
        SELECT 
            COUNT(*) as total_pending,
            SUM(CASE WHEN priority >= 3 THEN 1 ELSE 0 END) as high_priority,
            COUNT(DISTINCT target_type, target_id) as unique_targets
        FROM content_reports
        WHERE status = "pending"
    ');
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalReports,
            'pages' => ceil($totalReports / $limit)
        ],
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
