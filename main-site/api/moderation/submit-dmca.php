<?php
/**
 * Submit DMCA Notice
 * Submit DMCA takedown request
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['holder_name', 'holder_email', 'content_type', 'content_id', 'content_url', 'copyright_work'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    $holderName = trim($input['holder_name']);
    $holderEmail = trim($input['holder_email']);
    $holderPhone = trim($input['holder_phone'] ?? '');
    $holderAddress = trim($input['holder_address'] ?? '');
    
    $contentType = $input['content_type'];
    $contentId = intval($input['content_id']);
    $contentUrl = trim($input['content_url']);
    $copyrightWork = trim($input['copyright_work']);
    $originalWorkUrl = trim($input['original_work_url'] ?? '');
    
    $swornStatement = $input['sworn_statement'] ?? false;
    $goodFaithBelief = $input['good_faith_belief'] ?? false;
    $accurateInformation = $input['accurate_information'] ?? false;
    $signature = trim($input['signature'] ?? '');
    
    // Validate email
    if (!filter_var($holderEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate content type
    $validTypes = ['post', 'comment', 'media', 'profile', 'community'];
    if (!in_array($contentType, $validTypes)) {
        throw new Exception('Invalid content type');
    }
    
    // Validate declarations
    if (!$swornStatement || !$goodFaithBelief || !$accurateInformation) {
        throw new Exception('All legal declarations must be acknowledged');
    }
    
    if (empty($signature)) {
        throw new Exception('Electronic signature required');
    }
    
    // Create DMCA notice
    $stmt = $pdo->prepare('
        INSERT INTO dmca_notices (
            holder_name,
            holder_email,
            holder_phone,
            holder_address,
            content_type,
            content_id,
            content_url,
            copyright_work,
            original_work_url,
            sworn_statement,
            good_faith_belief,
            accurate_information,
            signature,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $holderName,
        $holderEmail,
        $holderPhone,
        $holderAddress,
        $contentType,
        $contentId,
        $contentUrl,
        $copyrightWork,
        $originalWorkUrl,
        $swornStatement,
        $goodFaithBelief,
        $accurateInformation,
        $signature,
        'submitted'
    ]);
    
    $noticeId = $pdo->lastInsertId();
    
    // Send confirmation email
    $to = $holderEmail;
    $subject = 'DMCA Notice Received - Sphera';
    $message = "
        Dear {$holderName},
        
        We have received your DMCA takedown notice for:
        Content Type: {$contentType}
        Content URL: {$contentUrl}
        
        Notice ID: {$noticeId}
        
        Your request will be reviewed within 24-48 hours. 
        You will receive an update once a decision has been made.
        
        Thank you,
        Sphera Legal Team
    ";
    
    // TODO: Send actual email
    
    echo json_encode([
        'success' => true,
        'notice_id' => $noticeId,
        'message' => 'DMCA notice submitted successfully. You will receive a confirmation email.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
