<?php
/**
 * FINALIZE SURVEY API
 * Endpoint para que el owner finalice una encuesta y distribuya premios
 * IMPORTANTE: Esta API solo prepara los datos - la transacción real se envía desde el frontend con MetaMask
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
if (!isset($input['survey_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'survey_id is required']);
    exit;
}

$survey_id = intval($input['survey_id']);
$action = isset($input['action']) ? $input['action'] : 'prepare'; // 'prepare' o 'confirm'

try {
    // Obtener información de la encuesta
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            u.username as creator_username,
            u.wallet_address as creator_wallet
        FROM surveys s
        LEFT JOIN users u ON s.created_by = u.user_id
        WHERE s.id = :survey_id
    ");
    $stmt->execute([':survey_id' => $survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$survey) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Survey not found']);
        exit;
    }

    // Verificar que el usuario es el creador
    if ($survey['created_by'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only the survey creator can finalize it']);
        exit;
    }

    // Verificar que no esté ya finalizada
    if ($survey['status'] === 'finalized') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey already finalized']);
        exit;
    }

    if ($action === 'prepare') {
        // PREPARAR: Obtener lista de ganadores y cantidades

        // Obtener participantes confirmados
        $stmt = $pdo->prepare("
            SELECT
                p.from_address,
                p.amount,
                p.confirmed_at,
                u.username,
                sr.responses
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.user_id
            LEFT JOIN survey_responses sr ON p.survey_id = sr.survey_id AND p.from_address = sr.wallet_address
            WHERE p.survey_id = :survey_id
              AND p.confirmed = TRUE
            ORDER BY p.confirmed_at ASC
        ");
        $stmt->execute([':survey_id' => $survey_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($participants) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No confirmed participants yet']);
            exit;
        }

        // Calcular distribución de premios
        $total_pool = floatval($survey['total_prize_pool']);
        $distribution = json_decode($survey['winner_distribution'], true);

        $winners = [];
        $winners_addresses = [];
        $winners_amounts = [];
        $total_distributed = 0;

        if ($distribution && is_array($distribution)) {
            // Distribución automática según configuración
            $participant_index = 0;

            foreach ($distribution as $tier) {
                $winner_count = intval($tier['winner_count']);
                $percentage = floatval($tier['percentage']);

                for ($i = 0; $i < $winner_count && $participant_index < count($participants); $i++, $participant_index++) {
                    $amount = ($total_pool * $percentage) / 100 / $winner_count;
                    $amount_wei = spheToWei($amount);

                    $winners[] = [
                        'address' => $participants[$participant_index]['from_address'],
                        'username' => $participants[$participant_index]['username'],
                        'amount' => $amount,
                        'amount_wei' => $amount_wei,
                        'percentage' => $percentage / $winner_count
                    ];

                    $winners_addresses[] = $participants[$participant_index]['from_address'];
                    $winners_amounts[] = $amount_wei;
                    $total_distributed += $amount;
                }
            }
        } else {
            // Sin distribución configurada - dividir equitativamente
            $amount_per_winner = $total_pool / count($participants);
            $amount_wei = spheToWei($amount_per_winner);

            foreach ($participants as $p) {
                $winners[] = [
                    'address' => $p['from_address'],
                    'username' => $p['username'],
                    'amount' => $amount_per_winner,
                    'amount_wei' => $amount_wei,
                    'percentage' => 100 / count($participants)
                ];

                $winners_addresses[] = $p['from_address'];
                $winners_amounts[] = $amount_wei;
                $total_distributed += $amount_per_winner;
            }
        }

        // Retornar datos para que el frontend envíe la transacción
        echo json_encode([
            'success' => true,
            'action' => 'prepare',
            'survey' => [
                'id' => $survey['id'],
                'title' => $survey['title'],
                'total_pool' => $total_pool,
                'contract_address' => $survey['contract_address'],
                'survey_id_on_chain' => $survey['survey_id_on_chain']
            ],
            'participants_count' => count($participants),
            'winners' => $winners,
            'winners_addresses' => $winners_addresses,
            'winners_amounts' => $winners_amounts,
            'total_distributed' => $total_distributed,
            'message' => 'Review the winners list and click confirm to send the transaction from your wallet.'
        ]);

    } else if ($action === 'confirm') {
        // CONFIRMAR: Registrar la transacción de finalización enviada desde el frontend

        if (!isset($input['tx_hash']) || !isset($input['winners'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'tx_hash and winners are required for confirm action']);
            exit;
        }

        $tx_hash = strtolower(trim($input['tx_hash']));
        $winners = $input['winners'];

        if (!isValidTxHash($tx_hash)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid transaction hash']);
            exit;
        }

        // Actualizar estado de la encuesta
        $stmt = $pdo->prepare("
            UPDATE surveys
            SET
                status = 'finalizing',
                finalized_tx_hash = :tx_hash
            WHERE id = :survey_id
        ");
        $stmt->execute([
            ':tx_hash' => $tx_hash,
            ':survey_id' => $survey_id
        ]);

        // Insertar payouts pendientes
        foreach ($winners as $winner) {
            $stmt = $pdo->prepare("
                INSERT INTO payouts (
                    survey_id,
                    recipient,
                    amount,
                    percentage,
                    status
                ) VALUES (
                    :survey_id,
                    :recipient,
                    :amount,
                    :percentage,
                    'pending'
                )
            ");

            $stmt->execute([
                ':survey_id' => $survey_id,
                ':recipient' => $winner['address'],
                ':amount' => $winner['amount'],
                ':percentage' => $winner['percentage'] ?? null
            ]);
        }

        // Log de transacción
        $stmt = $pdo->prepare("
            INSERT INTO escrow_transactions_log (
                survey_id,
                tx_hash,
                tx_type,
                status,
                from_address,
                to_address
            ) VALUES (
                :survey_id,
                :tx_hash,
                'finalize',
                'pending',
                :from_address,
                :to_address
            )
        ");

        $stmt->execute([
            ':survey_id' => $survey_id,
            ':tx_hash' => $tx_hash,
            ':from_address' => $survey['creator_wallet'],
            ':to_address' => $survey['contract_address']
        ]);

        echo json_encode([
            'success' => true,
            'action' => 'confirm',
            'message' => 'Survey finalization transaction submitted. It will be confirmed automatically.',
            'tx_hash' => $tx_hash,
            'explorer_url' => getExplorerTxUrl($tx_hash)
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    error_log("Finalize survey error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to finalize survey'
    ]);
}
