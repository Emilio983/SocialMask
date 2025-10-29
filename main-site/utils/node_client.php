<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';

function nodeApiRequest(string $method, string $path, array $data = []): array
{
    $url = rtrim(NODE_BACKEND_BASE_URL, '/') . '/' . ltrim($path, '/');

    $ch = curl_init();
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,           // Reducido de 15 a 5 segundos
        CURLOPT_CONNECTTIMEOUT => 2,    // Timeout de conexión de 2 segundos
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];

    if (!empty($data)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Node API request failed: ' . $error);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Node API response not valid JSON');
    }

    if ($statusCode >= 400) {
        $message = $decoded['message'] ?? 'Unknown error';
        throw new RuntimeException('Node API error: ' . $message, $statusCode);
    }

    return $decoded;
}
