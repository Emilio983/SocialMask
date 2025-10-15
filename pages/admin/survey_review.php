<?php
/**
 * ADMIN PANEL: SURVEY REVIEW
 * Panel para administradores manejar encuestas sin respuesta del creador
 */

require_once __DIR__ . '/../../config/connection.php';

session_start();

// Verificar que el usuario esté logueado y sea admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Verificar rol de admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// Get surveys awaiting admin review
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.title,
        s.description,
        s.creator_id,
        s.entry_price_sphe,
        s.close_date,
        s.deadline_exceeded_at,
        s.creator_deposit_amount,
        u.username as creator_username,
        u.wallet_address as creator_wallet,
        (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id) as total_responses,
        (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id AND selected_answer = 'A') as votes_a,
        (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id AND selected_answer = 'B') as votes_b,
        (SELECT SUM(usa.payment_amount)
         FROM survey_responses sr
         INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
         WHERE sr.survey_id = s.id AND sr.selected_answer = 'A') as pool_a,
        (SELECT SUM(usa.payment_amount)
         FROM survey_responses sr
         INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
         WHERE sr.survey_id = s.id AND sr.selected_answer = 'B') as pool_b,
        TIMESTAMPDIFF(HOUR, s.close_date, NOW()) as hours_since_close
    FROM surveys s
    INNER JOIN users u ON s.creator_id = u.user_id
    WHERE s.status = 'awaiting_admin'
    ORDER BY s.deadline_exceeded_at ASC
");
$stmt->execute();
$pending_surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get survey questions
function getSurveyOptions($pdo, $survey_id) {
    $stmt = $pdo->prepare("
        SELECT question_text, question_order
        FROM survey_questions
        WHERE survey_id = ?
        ORDER BY question_order ASC
    ");
    $stmt->execute([$survey_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey Review - Admin Panel - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-bg-primary': '#0D1117',
                        'brand-bg-secondary': '#161B22',
                        'brand-border': '#30363D',
                        'brand-text-primary': '#C9D1D9',
                        'brand-text-secondary': '#8B949E',
                        'brand-accent': '#3B82F6',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary font-sans">

    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <div class="container mx-auto px-4 py-24 max-w-7xl">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">Survey Review Panel</h1>
            <p class="text-brand-text-secondary">Manage surveys where creators did not respond within 48 hours</p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <p class="text-sm text-brand-text-secondary mb-1">Pending Review</p>
                <p class="text-3xl font-bold"><?php echo count($pending_surveys); ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <p class="text-sm text-brand-text-secondary mb-1">Total Pool</p>
                <p class="text-3xl font-bold">
                    <?php
                    $total_pool = 0;
                    foreach ($pending_surveys as $s) {
                        $total_pool += floatval($s['pool_a']) + floatval($s['pool_b']);
                    }
                    echo number_format($total_pool, 2);
                    ?> SPHE
                </p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <p class="text-sm text-brand-text-secondary mb-1">Forfeited Deposits</p>
                <p class="text-3xl font-bold"><?php echo count($pending_surveys) * 10; ?> SPHE</p>
            </div>
        </div>

        <!-- Surveys List -->
        <div class="space-y-6">
            <?php if (empty($pending_surveys)): ?>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-12 text-center">
                    <svg class="w-16 h-16 text-brand-text-secondary mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-xl font-semibold mb-2">All Clear!</p>
                    <p class="text-brand-text-secondary">No surveys require admin review at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_surveys as $survey):
                    $options = getSurveyOptions($pdo, $survey['id']);
                    $option_a = $options[0]['question_text'] ?? 'Option A';
                    $option_b = $options[1]['question_text'] ?? 'Option B';
                    $total_pool = floatval($survey['pool_a']) + floatval($survey['pool_b']);
                ?>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                    <!-- Survey Header -->
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex-1">
                            <h2 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($survey['title']); ?></h2>
                            <p class="text-sm text-brand-text-secondary mb-4">
                                <?php echo htmlspecialchars($survey['description'] ?? 'No description'); ?>
                            </p>
                            <div class="flex flex-wrap gap-4 text-sm">
                                <span class="text-brand-text-secondary">
                                    <strong>Creator:</strong> <?php echo htmlspecialchars($survey['creator_username']); ?>
                                </span>
                                <span class="text-brand-text-secondary">
                                    <strong>Closed:</strong> <?php echo date('M d, Y H:i', strtotime($survey['close_date'])); ?>
                                </span>
                                <span class="text-red-500 font-semibold">
                                    <strong>Overdue:</strong> <?php echo intval($survey['hours_since_close']) - 48; ?> hours
                                </span>
                                <span class="text-brand-text-secondary">
                                    <strong>Responses:</strong> <?php echo $survey['total_responses']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <span class="bg-red-900 bg-opacity-30 text-red-400 px-3 py-1 rounded-full text-sm font-semibold">
                                Requires Review
                            </span>
                        </div>
                    </div>

                    <!-- Voting Results -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold">Option A</h3>
                                <span class="text-sm text-brand-text-secondary"><?php echo $survey['votes_a']; ?> votes</span>
                            </div>
                            <p class="text-brand-text-secondary mb-2"><?php echo htmlspecialchars($option_a); ?></p>
                            <p class="text-2xl font-bold text-blue-400"><?php echo number_format($survey['pool_a'], 2); ?> SPHE</p>
                        </div>

                        <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold">Option B</h3>
                                <span class="text-sm text-brand-text-secondary"><?php echo $survey['votes_b']; ?> votes</span>
                            </div>
                            <p class="text-brand-text-secondary mb-2"><?php echo htmlspecialchars($option_b); ?></p>
                            <p class="text-2xl font-bold text-purple-400"><?php echo number_format($survey['pool_b'], 2); ?> SPHE</p>
                        </div>
                    </div>

                    <!-- Financial Breakdown -->
                    <div class="bg-yellow-900 bg-opacity-20 border border-yellow-600 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold mb-3">Financial Breakdown</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p class="text-brand-text-secondary mb-1">Total Pool</p>
                                <p class="font-bold"><?php echo number_format($total_pool, 2); ?> SPHE</p>
                            </div>
                            <div>
                                <p class="text-brand-text-secondary mb-1">Entry Price</p>
                                <p class="font-bold"><?php echo number_format($survey['entry_price_sphe'], 2); ?> SPHE</p>
                            </div>
                            <div>
                                <p class="text-brand-text-secondary mb-1">Creator Deposit</p>
                                <p class="font-bold text-red-400"><?php echo number_format($survey['creator_deposit_amount'], 2); ?> SPHE (Forfeited)</p>
                            </div>
                            <div>
                                <p class="text-brand-text-secondary mb-1">Total Responses</p>
                                <p class="font-bold"><?php echo $survey['total_responses']; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    <div class="flex flex-wrap gap-3">
                        <button
                            onclick="declareWinner(<?php echo $survey['id']; ?>, 'A')"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors"
                        >
                            Declare Option A as Winner
                        </button>
                        <button
                            onclick="declareWinner(<?php echo $survey['id']; ?>, 'B')"
                            class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors"
                        >
                            Declare Option B as Winner
                        </button>
                        <button
                            onclick="refundAll(<?php echo $survey['id']; ?>)"
                            class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors"
                        >
                            Refund All Participants
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function declareWinner(surveyId, winningOption) {
            if (!confirm(`Are you sure you want to declare Option ${winningOption} as the winner?\n\nThis action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('../../api/admin/declare_survey_winner.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        survey_id: surveyId,
                        winning_option: winningOption
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Success! Option ${winningOption} declared as winner.\n\nWinners: ${data.total_winners}\nTotal distributed: ${data.total_distributed} SPHE`);
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to declare winner'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error declaring winner');
            }
        }

        async function refundAll(surveyId) {
            if (!confirm('Are you sure you want to refund all participants?\n\nEach participant will receive their entry fee back.\n\nThis action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('../../api/admin/refund_survey.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        survey_id: surveyId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Success! Refunded ${data.total_refunded} participants.\n\nTotal refunded: ${data.total_amount} SPHE`);
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to refund'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error processing refund');
            }
        }
    </script>
</body>
</html>
