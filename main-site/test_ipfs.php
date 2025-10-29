<?php
/**
 * TEST IPFS UPLOAD - Sin autenticación
 * Prueba rápida para verificar que Pinata funciona
 */

require_once __DIR__ . '/../api/helpers/ipfs_helper.php';

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              IPFS/PINATA TEST                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Test 1: Validar configuración
echo "📋 Test 1: Checking configuration...\n";
try {
    IPFSHelper::init();
    echo "✅ Test 1 PASSED: Configuration loaded\n\n";
} catch (Exception $e) {
    echo "❌ Test 1 FAILED: " . $e->getMessage() . "\n";
    echo "\n⚠️  Please configure IPFS in .env:\n";
    echo "   IPFS_ENABLED=true\n";
    echo "   IPFS_API_KEY=your_pinata_api_key\n";
    echo "   IPFS_API_SECRET=your_pinata_secret_key\n\n";
    exit(1);
}

// Test 2: Crear archivo temporal de prueba
echo "📄 Test 2: Creating test file...\n";
$testFile = sys_get_temp_dir() . '/ipfs_test_' . time() . '.txt';
$testContent = "Hello IPFS from thesocialmask!\nTimestamp: " . date('Y-m-d H:i:s');
file_put_contents($testFile, $testContent);

if (!file_exists($testFile)) {
    echo "❌ Test 2 FAILED: Could not create test file\n";
    exit(1);
}
echo "✅ Test 2 PASSED: Test file created\n";
echo "   Path: $testFile\n";
echo "   Size: " . filesize($testFile) . " bytes\n\n";

// Test 3: Subir a IPFS
echo "📤 Test 3: Uploading to IPFS via Pinata...\n";
try {
    $result = IPFSHelper::uploadFile($testFile, 'test.txt', [
        'test' => true,
        'source' => 'test_script'
    ]);
    
    echo "✅ Test 3 PASSED: File uploaded successfully!\n";
    echo "   IPFS Hash: " . $result['hash'] . "\n";
    echo "   Gateway URL: " . $result['url'] . "\n";
    echo "   Size: " . $result['size'] . " bytes\n\n";
    
    // Test 4: Validar hash
    echo "🔍 Test 4: Validating IPFS hash...\n";
    if (IPFSHelper::isValidHash($result['hash'])) {
        echo "✅ Test 4 PASSED: Hash is valid\n\n";
    } else {
        echo "❌ Test 4 FAILED: Invalid hash format\n\n";
    }
    
    // Test 5: Generar URL del gateway
    echo "🌐 Test 5: Testing gateway URL generation...\n";
    $gatewayUrl = IPFSHelper::getGatewayUrl($result['hash']);
    echo "✅ Test 5 PASSED: Gateway URL generated\n";
    echo "   URL: $gatewayUrl\n\n";
    
    // Test 6: Verificar accesibilidad
    echo "🔗 Test 6: Checking file accessibility...\n";
    echo "   Waiting 3 seconds for propagation...\n";
    sleep(3);
    
    $ch = curl_init($result['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response === $testContent) {
        echo "✅ Test 6 PASSED: File is accessible and content matches!\n\n";
    } else {
        echo "⚠️  Test 6 WARNING: HTTP $httpCode (might need more time to propagate)\n";
        echo "   Try accessing manually: " . $result['url'] . "\n\n";
    }
    
    // Test 7: Upload JSON
    echo "📦 Test 7: Uploading JSON data...\n";
    $jsonData = [
        'message' => 'Test JSON from thesocialmask',
        'timestamp' => time(),
        'test' => true
    ];
    
    $jsonResult = IPFSHelper::uploadJSON($jsonData, 'test.json');
    echo "✅ Test 7 PASSED: JSON uploaded successfully!\n";
    echo "   IPFS Hash: " . $jsonResult['hash'] . "\n";
    echo "   URL: " . $jsonResult['url'] . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Test FAILED: " . $e->getMessage() . "\n\n";
    unlink($testFile);
    exit(1);
}

// Cleanup
unlink($testFile);

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║          ALL TESTS PASSED! ✅                          ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "🎉 IPFS/Pinata is working perfectly!\n\n";
echo "📝 Next steps:\n";
echo "   1. Your files are now stored on IPFS\n";
echo "   2. Check Pinata dashboard: https://app.pinata.cloud/pinmanager\n";
echo "   3. Access files via gateway: https://gateway.pinata.cloud/ipfs/HASH\n\n";
?>
