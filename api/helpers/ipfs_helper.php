<?php
// ============================================
// IPFS HELPER - Pinata Integration
// ============================================
// Helper functions para subir archivos a IPFS usando Pinata
// Pinata: https://pinata.cloud

require_once __DIR__ . '/../config/env.php';

class IPFSHelper {
    
    private static $pinataApiKey;
    private static $pinataSecretKey;
    private static $gatewayUrl;
    
    /**
     * Inicializar configuración de Pinata
     */
    public static function init() {
        $ipfsConfig = Env::ipfs();
        
        if (!$ipfsConfig['enabled']) {
            throw new Exception('IPFS is not enabled in configuration');
        }
        
        self::$pinataApiKey = $ipfsConfig['apiKey'];
        self::$pinataSecretKey = $ipfsConfig['apiSecret'];
        self::$gatewayUrl = $ipfsConfig['gateway'];
        
        if (empty(self::$pinataApiKey) || empty(self::$pinataSecretKey)) {
            throw new Exception('Pinata API credentials not configured');
        }
    }
    
    /**
     * Subir archivo a IPFS vía Pinata
     * 
     * @param string $filePath Ruta del archivo temporal
     * @param string $fileName Nombre del archivo
     * @param array $metadata Metadata opcional
     * @return array ['success' => bool, 'hash' => string, 'url' => string, 'size' => int]
     */
    public static function uploadFile($filePath, $fileName, $metadata = []) {
        self::init();
        
        // Validar que el archivo existe
        if (!file_exists($filePath)) {
            throw new Exception('File not found: ' . $filePath);
        }
        
        $url = 'https://api.pinata.cloud/pinning/pinFileToIPFS';
        
        // Preparar el archivo para cURL
        $cfile = curl_file_create($filePath, mime_content_type($filePath), $fileName);
        
        // Preparar metadata
        $pinataMetadata = json_encode([
            'name' => $fileName,
            'keyvalues' => array_merge([
                'uploaded_at' => time(),
                'source' => 'thesocialmask'
            ], $metadata)
        ]);
        
        // Preparar datos POST
        $postData = [
            'file' => $cfile,
            'pinataMetadata' => $pinataMetadata
        ];
        
        // Headers
        $headers = [
            'pinata_api_key: ' . self::$pinataApiKey,
            'pinata_secret_api_key: ' . self::$pinataSecretKey
        ];
        
        // Ejecutar request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Verificar errores de cURL
        if ($curlError) {
            throw new Exception('IPFS upload failed: ' . $curlError);
        }
        
        // Verificar código HTTP
        if ($httpCode !== 200) {
            throw new Exception('IPFS upload failed with HTTP ' . $httpCode . ': ' . $response);
        }
        
        // Parsear respuesta
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['IpfsHash'])) {
            throw new Exception('Invalid response from Pinata API');
        }
        
        $ipfsHash = $data['IpfsHash'];
        $fileSize = $data['PinSize'] ?? filesize($filePath);
        
        return [
            'success' => true,
            'hash' => $ipfsHash,
            'cid' => $ipfsHash, // Alias
            'url' => self::$gatewayUrl . '/ipfs/' . $ipfsHash,
            'size' => $fileSize,
            'timestamp' => $data['Timestamp'] ?? date('Y-m-d\TH:i:s\Z')
        ];
    }
    
    /**
     * Subir JSON a IPFS
     * 
     * @param array $data Datos a subir
     * @param string $name Nombre del objeto
     * @return array ['success' => bool, 'hash' => string, 'url' => string]
     */
    public static function uploadJSON($data, $name = 'data.json') {
        self::init();
        
        $url = 'https://api.pinata.cloud/pinning/pinJSONToIPFS';
        
        $postData = json_encode([
            'pinataContent' => $data,
            'pinataMetadata' => [
                'name' => $name,
                'keyvalues' => [
                    'uploaded_at' => time(),
                    'source' => 'thesocialmask',
                    'type' => 'json'
                ]
            ]
        ]);
        
        $headers = [
            'Content-Type: application/json',
            'pinata_api_key: ' . self::$pinataApiKey,
            'pinata_secret_api_key: ' . self::$pinataSecretKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('IPFS JSON upload failed: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('IPFS JSON upload failed with HTTP ' . $httpCode);
        }
        
        $responseData = json_decode($response, true);
        
        if (!$responseData || !isset($responseData['IpfsHash'])) {
            throw new Exception('Invalid response from Pinata API');
        }
        
        return [
            'success' => true,
            'hash' => $responseData['IpfsHash'],
            'url' => self::$gatewayUrl . '/ipfs/' . $responseData['IpfsHash']
        ];
    }
    
    /**
     * Obtener URL completa del gateway
     * 
     * @param string $hash IPFS hash (CID)
     * @return string URL completa
     */
    public static function getGatewayUrl($hash) {
        self::init();
        return self::$gatewayUrl . '/ipfs/' . $hash;
    }
    
    /**
     * Validar que un hash IPFS es válido
     * 
     * @param string $hash Hash a validar
     * @return bool
     */
    public static function isValidHash($hash) {
        // IPFS v0 CID (Qm...)
        if (preg_match('/^Qm[1-9A-HJ-NP-Za-km-z]{44}$/', $hash)) {
            return true;
        }
        
        // IPFS v1 CID (b...)
        if (preg_match('/^b[a-z2-7]{58}$/', $hash)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener información de un pin
     * 
     * @param string $hash IPFS hash
     * @return array|null Información del pin o null si no existe
     */
    public static function getPinInfo($hash) {
        self::init();
        
        $url = 'https://api.pinata.cloud/data/pinList?hashContains=' . urlencode($hash);
        
        $headers = [
            'pinata_api_key: ' . self::$pinataApiKey,
            'pinata_secret_api_key: ' . self::$pinataSecretKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['rows']) || empty($data['rows'])) {
            return null;
        }
        
        return $data['rows'][0];
    }
    
    /**
     * Eliminar un pin de Pinata
     * 
     * @param string $hash IPFS hash a desanclar
     * @return bool Success
     */
    public static function unpinFile($hash) {
        self::init();
        
        $url = 'https://api.pinata.cloud/pinning/unpin/' . $hash;
        
        $headers = [
            'pinata_api_key: ' . self::$pinataApiKey,
            'pinata_secret_api_key: ' . self::$pinataSecretKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}
?>
