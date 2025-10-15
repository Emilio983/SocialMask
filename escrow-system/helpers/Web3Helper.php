<?php
/**
 * WEB3 HELPER
 * Helper class para interactuar con Polygon usando Infura RPC
 * Sin dependencias externas - solo cURL y JSON-RPC
 */

class Web3Helper {
    private $rpc_url;
    private $chain_id;

    public function __construct($rpc_url = null, $chain_id = null) {
        $this->rpc_url = $rpc_url ?? CURRENT_RPC;
        $this->chain_id = $chain_id ?? CURRENT_CHAIN_ID;
    }

    /**
     * Hacer llamada JSON-RPC a Infura
     * @param string $method
     * @param array $params
     * @return mixed
     */
    private function rpcCall($method, $params = []) {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => time()
        ]);

        $ch = curl_init($this->rpc_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("RPC call failed: HTTP {$http_code}");
            return false;
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            error_log("RPC error: " . json_encode($data['error']));
            return false;
        }

        return $data['result'] ?? null;
    }

    /**
     * Obtener el número de bloque actual
     * @return int|false
     */
    public function getBlockNumber() {
        $result = $this->rpcCall('eth_blockNumber');
        return $result ? hexdec($result) : false;
    }

    /**
     * Obtener información de una transacción
     * @param string $tx_hash
     * @return array|false
     */
    public function getTransaction($tx_hash) {
        return $this->rpcCall('eth_getTransactionByHash', [$tx_hash]);
    }

    /**
     * Obtener recibo de una transacción
     * @param string $tx_hash
     * @return array|false
     */
    public function getTransactionReceipt($tx_hash) {
        return $this->rpcCall('eth_getTransactionReceipt', [$tx_hash]);
    }

    /**
     * Verificar si una transacción fue exitosa
     * @param string $tx_hash
     * @return array ['success' => bool, 'confirmations' => int, 'receipt' => array]
     */
    public function verifyTransaction($tx_hash) {
        $receipt = $this->getTransactionReceipt($tx_hash);

        if (!$receipt) {
            return [
                'success' => false,
                'confirmations' => 0,
                'receipt' => null,
                'error' => 'Transaction not found or pending'
            ];
        }

        // Status: 0x1 = success, 0x0 = failed
        $status = isset($receipt['status']) ? hexdec($receipt['status']) : 0;

        // Calcular confirmaciones
        $current_block = $this->getBlockNumber();
        $tx_block = hexdec($receipt['blockNumber']);
        $confirmations = $current_block - $tx_block + 1;

        return [
            'success' => ($status === 1),
            'confirmations' => $confirmations,
            'block_number' => $tx_block,
            'gas_used' => hexdec($receipt['gasUsed']),
            'receipt' => $receipt
        ];
    }

    /**
     * Obtener balance de tokens ERC-20
     * @param string $token_address
     * @param string $wallet_address
     * @return string|false Balance en Wei
     */
    public function getTokenBalance($token_address, $wallet_address) {
        // Codificar llamada a balanceOf(address)
        $method_signature = '0x70a08231'; // balanceOf(address)
        $padded_address = str_pad(substr($wallet_address, 2), 64, '0', STR_PAD_LEFT);
        $data = $method_signature . $padded_address;

        $result = $this->rpcCall('eth_call', [
            [
                'to' => $token_address,
                'data' => $data
            ],
            'latest'
        ]);

        if (!$result) {
            return false;
        }

        // Convertir hex a decimal
        return hexdec($result);
    }

    /**
     * Decodificar logs de eventos del contrato
     * @param array $logs
     * @param string $event_signature
     * @return array
     */
    public function decodeLogs($logs, $event_signature) {
        $decoded = [];

        $signature_hash = '0x' . hash('sha3-256', $event_signature);

        foreach ($logs as $log) {
            if (isset($log['topics'][0]) && $log['topics'][0] === $signature_hash) {
                $decoded[] = [
                    'topics' => $log['topics'],
                    'data' => $log['data'],
                    'address' => $log['address'],
                    'block_number' => hexdec($log['blockNumber']),
                    'transaction_hash' => $log['transactionHash']
                ];
            }
        }

        return $decoded;
    }

    /**
     * Llamar a una función de solo lectura del contrato
     * @param string $contract_address
     * @param string $encoded_data
     * @return string|false
     */
    public function callContract($contract_address, $encoded_data) {
        return $this->rpcCall('eth_call', [
            [
                'to' => $contract_address,
                'data' => $encoded_data
            ],
            'latest'
        ]);
    }

    /**
     * Obtener información de depósito de un participante en una encuesta
     * @param string $contract_address
     * @param int $survey_id
     * @param string $participant_address
     * @return string|false
     */
    public function getSurveyDeposit($contract_address, $survey_id, $participant_address) {
        // getDeposit(uint256,address) = 0x...
        // Por simplicidad, esta función asume que ya tienes el método signature
        // En producción, usarías web3.php o generarías el signature

        // Ejemplo simplificado - implementar según ABI
        return '0'; // TODO: Implementar encoding correcto
    }

    /**
     * Verificar evento Deposit en una transacción
     * @param string $tx_hash
     * @return array|false
     */
    public function getDepositEvent($tx_hash) {
        $receipt = $this->getTransactionReceipt($tx_hash);

        if (!$receipt || !isset($receipt['logs'])) {
            return false;
        }

        // Event Deposit(uint256 indexed surveyId, address indexed participant, uint256 amount, uint256 timestamp)
        $event_signature = 'Deposit(uint256,address,uint256,uint256)';
        $signature_hash = '0x' . hash('sha3-256', $event_signature);

        foreach ($receipt['logs'] as $log) {
            if (isset($log['topics'][0]) && strtolower($log['topics'][0]) === strtolower($signature_hash)) {
                // Decodificar topics y data
                $survey_id = hexdec($log['topics'][1]);
                $participant = '0x' . substr($log['topics'][2], 26); // Últimos 20 bytes (40 chars)

                // Data contiene amount y timestamp (cada uno 32 bytes)
                $data = substr($log['data'], 2); // Quitar 0x
                $amount = hexdec(substr($data, 0, 64));
                $timestamp = hexdec(substr($data, 64, 64));

                return [
                    'survey_id' => $survey_id,
                    'participant' => $participant,
                    'amount' => $amount,
                    'timestamp' => $timestamp,
                    'block_number' => hexdec($log['blockNumber']),
                    'transaction_hash' => $log['transactionHash']
                ];
            }
        }

        return false;
    }

    /**
     * Obtener información de una encuesta del contrato
     * @param string $contract_address
     * @param int $survey_id
     * @return array|false
     */
    public function getSurveyInfo($contract_address, $survey_id) {
        // getSurveyInfo(uint256) returns (uint256 totalDeposited, uint256 totalPaidOut, bool finalized, uint256 participantsCount)
        $method_signature = '0x...'; // TODO: Calcular signature correcto

        // Por ahora retornamos estructura vacía
        // En producción, implementar encoding/decoding correcto
        return [
            'total_deposited' => 0,
            'total_paid_out' => 0,
            'finalized' => false,
            'participants_count' => 0
        ];
    }

    /**
     * Esperar confirmaciones de una transacción
     * @param string $tx_hash
     * @param int $min_confirmations
     * @param int $timeout_seconds
     * @return bool
     */
    public function waitForConfirmations($tx_hash, $min_confirmations = 3, $timeout_seconds = 300) {
        $start_time = time();

        while (time() - $start_time < $timeout_seconds) {
            $verification = $this->verifyTransaction($tx_hash);

            if (!$verification['success']) {
                return false; // Transacción falló
            }

            if ($verification['confirmations'] >= $min_confirmations) {
                return true; // Confirmada
            }

            sleep(5); // Esperar 5 segundos antes de volver a verificar
        }

        return false; // Timeout
    }

    /**
     * Obtener precio de gas actual
     * @return string|false Gas price en Wei
     */
    public function getGasPrice() {
        $result = $this->rpcCall('eth_gasPrice');
        return $result ? hexdec($result) : false;
    }

    /**
     * Estimar gas para una transacción
     * @param array $transaction
     * @return int|false
     */
    public function estimateGas($transaction) {
        $result = $this->rpcCall('eth_estimateGas', [$transaction]);
        return $result ? hexdec($result) : false;
    }

    /**
     * Obtener nonce de una dirección
     * @param string $address
     * @return int|false
     */
    public function getTransactionCount($address) {
        $result = $this->rpcCall('eth_getTransactionCount', [$address, 'latest']);
        return $result ? hexdec($result) : false;
    }
}
