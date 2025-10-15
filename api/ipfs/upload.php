<?php
/**
 * ============================================
 * IPFS UPLOAD API - Pinata
 * ============================================
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../cors_helper.php';
require_once '../check_session.php';

handleCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/env.php';
    use thesocialmask\Config\Env;
    Env::load();
    
    $pinataApiKey = Env::get('PINATA_API_KEY');
    $pinataSecretKey = Env::get('PINATA_SECRET_API_KEY');
    
    if (!$pinataApiKey || !$pinataSecretKey) {
        throw new Exception('Pinata API keys not configured');
    }
    
    $file = $_FILES['file'];
    $metadata = isset($_POST['metadata']) ? json_decode($_POST['metadata'], true) : [];
    
    // Preparar datos para Pinata
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    
    $postData = '';
    
    // Agregar archivo
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="file"; filename="' . $file['name'] . '"' . "\r\n";
    $postData .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
    $postData .= file_get_contents($file['tmp_name']) . "\r\n";
    
    // Agregar metadata
    if (!empty($metadata)) {
        $pinataMetadata = [
            'name' => $metadata['name'] ?? $file['name'],
            'keyvalues' => array_merge([
                'userId' => $_SESSION['user_id'],
                'uploadedAt' => date('c')
            ], $metadata)
        ];
        
        $postData .= "--" . $delimiter . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="pinataMetadata"' . "\r\n\r\n";
        $postData .= json_encode($pinataMetadata) . "\r\n";
    }
    
    $postData .= "--" . $delimiter . "--\r\n";
    
    // Hacer request a Pinata
    $ch = curl_init('https://api.pinata.cloud/pinning/pinFileToIPFS');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: multipart/form-data; boundary=' . $delimiter,
            'pinata_api_key: ' . $pinataApiKey,
            'pinata_secret_api_key: ' . $pinataSecretKey
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Pinata error: " . $response);
        throw new Exception('Failed to upload to IPFS');
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['IpfsHash'])) {
        throw new Exception('Invalid response from Pinata');
    }
    
    // Guardar metadata en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO p2p_metadata (user_id, type, ipfs_hash, metadata)
        VALUES (?, 'file', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $result['IpfsHash'],
        json_encode([
            'filename' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'metadata' => $metadata,
            'timestamp' => time()
        ])
    ]);
    
    echo json_encode([
        'success' => true,
        'ipfsHash' => $result['IpfsHash'],
        'pinSize' => $result['PinSize'],
        'timestamp' => $result['Timestamp'],
        'url' => 'https://gateway.pinata.cloud/ipfs/' . $result['IpfsHash']
    ]);
    
} catch (Exception $e) {
    error_log("IPFS upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
