<?php
/**
 * ============================================
 * PINATA IPFS CLIENT
 * ============================================
 * Cliente para subir archivos a Pinata IPFS
 */

class PinataClient {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.pinata.cloud';
    
    public function __construct() {
        // Cargar desde .env
        $this->apiKey = $_ENV['PINATA_API_KEY'] ?? '';
        $this->apiSecret = $_ENV['PINATA_SECRET_API_KEY'] ?? '';
        
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new Exception('Pinata credentials not configured');
        }
    }
    
    /**
     * Subir archivo a IPFS
     */
    public function uploadFile($filePath, $metadata = []) {
        $url = $this->baseUrl . '/pinning/pinFileToIPFS';
        
        $cfile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));
        
        $postData = [
            'file' => $cfile
        ];
        
        if (!empty($metadata)) {
            $postData['pinataMetadata'] = json_encode($metadata);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'pinata_api_key: ' . $this->apiKey,
            'pinata_secret_api_key: ' . $this->apiSecret
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Pinata upload failed: ' . $response);
        }
        
        $result = json_decode($response, true);
        
        return [
            'success' => true,
            'ipfsHash' => $result['IpfsHash'],
            'pinSize' => $result['PinSize'],
            'timestamp' => $result['Timestamp']
        ];
    }
    
    /**
     * Subir JSON a IPFS
     */
    public function uploadJSON($data, $metadata = []) {
        $url = $this->baseUrl . '/pinning/pinJSONToIPFS';
        
        $postData = [
            'pinataContent' => $data
        ];
        
        if (!empty($metadata)) {
            $postData['pinataMetadata'] = $metadata;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'pinata_api_key: ' . $this->apiKey,
            'pinata_secret_api_key: ' . $this->apiSecret
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Pinata JSON upload failed: ' . $response);
        }
        
        $result = json_decode($response, true);
        
        return [
            'success' => true,
            'ipfsHash' => $result['IpfsHash'],
            'pinSize' => $result['PinSize'],
            'timestamp' => $result['Timestamp']
        ];
    }
    
    /**
     * Obtener contenido de IPFS
     */
    public function getContent($ipfsHash) {
        $url = "https://gateway.pinata.cloud/ipfs/{$ipfsHash}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to fetch from IPFS');
        }
        
        return $content;
    }
    
    /**
     * Desanclar archivo (unpinning)
     */
    public function unpin($ipfsHash) {
        $url = $this->baseUrl . '/pinning/unpin/' . $ipfsHash;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'pinata_api_key: ' . $this->apiKey,
            'pinata_secret_api_key: ' . $this->apiSecret
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}
