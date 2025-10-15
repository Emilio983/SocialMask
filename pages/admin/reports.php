<?php
/**
 * ADMIN: REPORTS & MODERATION
 * View and handle user reports (spam, harassment, etc.)
 */

require_once __DIR__ . '/../../config/connection.php';
session_start();

// Verify admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$admin_id = $_SESSION['user_id'];

// Get filter
$filter_status = $_GET['status'] ?? 'pending';
$filter_type = $_GET['type'] ?? 'all';

// Build query
$where_clauses = [];
$params = [];

if ($filter_status !== 'all') {
    $where_clauses[] = "r.status = ?";
    $params[] = $filter_status;
}

if ($filter_type !== 'all') {
    $where_clauses[] = "r.report_type = ?";
    $params[] = $filter_type;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get reports
$stmt = $pdo->prepare("
    SELECT
        r.*,
        reporter.username as reporter_name,
        reported.username as reported_name,
        reviewer.username as reviewer_name
    FROM user_reports r
    INNER JOIN users reporter ON r.reporter_id = reporter.user_id
    LEFT JOIN users reported ON r.reported_user_id = reported.user_id
    LEFT JOIN users reviewer ON r.reviewed_by = reviewer.user_id
    $where_sql
    ORDER BY
        CASE r.status
            WHEN 'pending' THEN 1
            WHEN 'reviewing' THEN 2
            ELSE 3
        END,
        r.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts by status
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM user_reports
    GROUP BY status
");
$status_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['status']] = $row['count'];
}

// Get counts by type
$stmt = $pdo->query("
    SELECT report_type, COUNT(*) as count
    FROM user_reports
    WHERE status = 'pending'
    GROUP BY report_type
");
$type_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $type_counts[$row['report_type']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin - The Social Mask</title>
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

    <div class="container mx-auto px-4 py-24 max-w-7xl" x-data="reportsManagement()">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Reports & Moderation</h1>
                    <p class="text-brand-text-secondary">Review and handle user reports</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6">
            <div class="flex flex-wrap gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Status</label>
                    <div class="flex gap-2">
                        <a href="?status=pending&type=<?php echo $filter_type; ?>" class="px-3 py-1.5 rounded-lg text-sm <?php echo $filter_status === 'pending' ? 'bg-yellow-600 text-white' : 'bg-brand-bg-secondary hover:bg-opacity-80'; ?>">
                            Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                        </a>
                        <a href="?status=reviewing&type=<?php echo $filter_type; ?>" class="px-3 py-1.5 rounded-lg text-sm <?php echo $filter_status === 'reviewing' ? 'bg-blue-600 text-white' : 'bg-brand-bg-secondary hover:bg-opacity-80'; ?>">
                            Reviewing (<?php echo $status_counts['reviewing'] ?? 0; ?>)
                        </a>
                        <a href="?status=resolved&type=<?php echo $filter_type; ?>" class="px-3 py-1.5 rounded-lg text-sm <?php echo $filter_status === 'resolved' ? 'bg-green-600 text-white' : 'bg-brand-bg-secondary hover:bg-opacity-80'; ?>">
                            Resolved (<?php echo $status_counts['resolved'] ?? 0; ?>)
                        </a>
                        <a href="?status=dismissed&type=<?php echo $filter_type; ?>" class="px-3 py-1.5 rounded-lg text-sm <?php echo $filter_status === 'dismissed' ? 'bg-gray-600 text-white' : 'bg-brand-bg-secondary hover:bg-opacity-80'; ?>">
                            Dismissed (<?php echo $status_counts['dismissed'] ?? 0; ?>)
                        </a>
                    </div>
                </div>

                <!-- Type Filter -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Report Type</label>
                    <select onchange="location.href='?status=<?php echo $filter_status; ?>&type=' + this.value" class="bg-brand-bg-secondary border border-brand-border rounded-lg px-4 py-1.5 text-sm">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="spam" <?php echo $filter_type === 'spam' ? 'selected' : ''; ?>>Spam <?php echo isset($type_counts['spam']) ? '(' . $type_counts['spam'] . ')' : ''; ?></option>
                        <option value="harassment" <?php echo $filter_type === 'harassment' ? 'selected' : ''; ?>>Harassment <?php echo isset($type_counts['harassment']) ? '(' . $type_counts['harassment'] . ')' : ''; ?></option>
                        <option value="inappropriate_content" <?php echo $filter_type === 'inappropriate_content' ? 'selected' : ''; ?>>Inappropriate <?php echo isset($type_counts['inappropriate_content']) ? '(' . $type_counts['inappropriate_content'] . ')' : ''; ?></option>
                        <option value="scam" <?php echo $filter_type === 'scam' ? 'selected' : ''; ?>>Scam <?php echo isset($type_counts['scam']) ? '(' . $type_counts['scam'] . ')' : ''; ?></option>
                        <option value="fake_account" <?php echo $filter_type === 'fake_account' ? 'selected' : ''; ?>>Fake Account <?php echo isset($type_counts['fake_account']) ? '(' . $type_counts['fake_account'] . ')' : ''; ?></option>
                        <option value="other" <?php echo $filter_type === 'other' ? 'selected' : ''; ?>>Other <?php echo isset($type_counts['other']) ? '(' . $type_counts['other'] . ')' : ''; ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Reports List -->
        <div class="space-y-4">
            <?php if (empty($reports)): ?>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-12 text-center">
                    <svg class="w-16 h-16 text-brand-text-secondary mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-xl font-semibold mb-2">No reports found</p>
                    <p class="text-brand-text-secondary">No reports match the current filters</p>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="<?php
                                $type_colors = [
                                    'spam' => 'bg-orange-900 bg-opacity-30 text-orange-400',
                                    'harassment' => 'bg-red-900 bg-opacity-30 text-red-400',
                                    'inappropriate_content' => 'bg-purple-900 bg-opacity-30 text-purple-400',
                                    'scam' => 'bg-red-900 bg-opacity-30 text-red-400',
                                    'fake_account' => 'bg-yellow-900 bg-opacity-30 text-yellow-400',
                                    'other' => 'bg-gray-900 bg-opacity-30 text-gray-400',
                                ];
                                echo $type_colors[$report['report_type']] ?? 'bg-brand-bg-primary';
                                ?> px-3 py-1 rounded-full text-xs font-semibold uppercase">
                                    <?php echo str_replace('_', ' ', $report['report_type']); ?>
                                </span>
                                <span class="text-xs text-brand-text-secondary">
                                    Reported <?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?>
                                </span>
                            </div>
                            <p class="text-brand-text-secondary mb-3"><?php echo htmlspecialchars($report['description']); ?></p>
                            <div class="flex items-center gap-4 text-sm">
                                <span><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                <?php if ($report['reported_user_id']): ?>
                                <span><strong>Reported User:</strong> <?php echo htmlspecialchars($report['reported_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4">
                            <?php
                            $status_colors = [
                                'pending' => 'bg-yellow-900 bg-opacity-30 text-yellow-400',
                                'reviewing' => 'bg-blue-900 bg-opacity-30 text-blue-400',
                                'resolved' => 'bg-green-900 bg-opacity-30 text-green-400',
                                'dismissed' => 'bg-gray-900 bg-opacity-30 text-gray-400',
                            ];
                            ?>
                            <span class="<?php echo $status_colors[$report['status']]; ?> px-3 py-1 rounded-full text-sm font-semibold">
                                <?php echo ucfirst($report['status']); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($report['status'] === 'pending'): ?>
                    <div class="flex gap-2 pt-4 border-t border-brand-border">
                        <button
                            @click="handleReport(<?php echo $report['id']; ?>, 'reviewing')"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors"
                        >
                            Start Review
                        </button>
                        <button
                            @click="handleReport(<?php echo $report['id']; ?>, 'dismissed')"
                            class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors"
                        >
                            Dismiss
                        </button>
                    </div>
                    <?php elseif ($report['status'] === 'reviewing'): ?>
                    <div class="flex gap-2 pt-4 border-t border-brand-border">
                        <button
                            @click="handleReport(<?php echo $report['id']; ?>, 'resolved')"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors"
                        >
                            Mark Resolved
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="pt-4 border-t border-brand-border text-sm text-brand-text-secondary">
                        <?php if ($report['reviewer_name']): ?>
                            Handled by <strong><?php echo htmlspecialchars($report['reviewer_name']); ?></strong>
                            on <?php echo date('M d, Y', strtotime($report['resolved_at'])); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function reportsManagement() {
            return {
                async handleReport(reportId, newStatus) {
                    if (!confirm(`Change report status to "${newStatus}"?`)) {
                        return;
                    }

                    try {
                        const response = await fetch('../../api/admin/handle_report.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                report_id: reportId,
                                status: newStatus,
                                admin_notes: ''
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to update report'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error updating report');
                    }
                }
            }
        }
    </script>

</body>
</html>
