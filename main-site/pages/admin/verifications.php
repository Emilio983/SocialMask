<?php
/**
 * ADMIN: VERIFICATION MANAGEMENT
 * Aprobar o rechazar verificaciones de usuarios
 */

require_once __DIR__ . '/../../config/connection.php';
session_start();

// Verify admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../dashboard');
    exit;
}

// Get verification requests
$filter = $_GET['filter'] ?? 'pending';

$where_sql = "";
if ($filter === 'pending') {
    $where_sql = "WHERE vr.status = 'pending'";
} elseif ($filter === 'approved') {
    $where_sql = "WHERE vr.status = 'approved'";
} elseif ($filter === 'rejected') {
    $where_sql = "WHERE vr.status = 'rejected'";
}

// Check if table exists
$table_exists = $pdo->query("SHOW TABLES LIKE 'verification_requests'")->fetch();

if ($table_exists) {
    $stmt = $pdo->query("
        SELECT
            vr.*,
            u.username,
            u.unique_username,
            u.profile_image,
            u.sphe_balance,
            u.membership_plan,
            COUNT(DISTINCT p.id) as post_count,
            COUNT(DISTINCT f.follower_id) as follower_count
        FROM verification_requests vr
        JOIN users u ON vr.user_id = u.user_id
        LEFT JOIN posts p ON u.user_id = p.user_id
        LEFT JOIN followers f ON u.user_id = f.following_id
        $where_sql
        GROUP BY vr.id
        ORDER BY vr.created_at DESC
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stats
    $stats = $pdo->query("
        SELECT
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM verification_requests
    ")->fetch(PDO::FETCH_ASSOC);
} else {
    $requests = [];
    $stats = [
        'total_requests' => 0,
        'pending_count' => 0,
        'approved_count' => 0,
        'rejected_count' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Requests - Admin - The Social Mask</title>
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

    <div class="container mx-auto px-4 py-24 max-w-7xl" x-data="verificationManagement()">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Verification Requests</h1>
                    <p class="text-brand-text-secondary">Review and approve user verification requests</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Total Requests</p>
                <p class="text-3xl font-bold"><?php echo $stats['total_requests']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Pending</p>
                <p class="text-3xl font-bold text-yellow-500"><?php echo $stats['pending_count']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Approved</p>
                <p class="text-3xl font-bold text-green-500"><?php echo $stats['approved_count']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Rejected</p>
                <p class="text-3xl font-bold text-red-500"><?php echo $stats['rejected_count']; ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="?filter=pending" class="px-4 py-2 rounded-lg <?php echo $filter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Pending (<?php echo $stats['pending_count']; ?>)
            </a>
            <a href="?filter=approved" class="px-4 py-2 rounded-lg <?php echo $filter === 'approved' ? 'bg-green-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Approved
            </a>
            <a href="?filter=rejected" class="px-4 py-2 rounded-lg <?php echo $filter === 'rejected' ? 'bg-red-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Rejected
            </a>
            <a href="?filter=all" class="px-4 py-2 rounded-lg <?php echo $filter === 'all' ? 'bg-brand-accent text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                All
            </a>
        </div>

        <!-- Requests List -->
        <div class="space-y-4">
            <?php foreach ($requests as $request): ?>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <div class="flex items-start gap-4">
                    <!-- User Info -->
                    <img src="<?php echo htmlspecialchars($request['profile_image'] ?? '/assets/default-avatar.png'); ?>" alt="" class="w-16 h-16 rounded-full">

                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h3 class="text-lg font-bold"><?php echo htmlspecialchars($request['username']); ?></h3>
                                <p class="text-sm text-brand-text-secondary">@<?php echo htmlspecialchars($request['unique_username']); ?></p>
                            </div>
                            <div class="text-right">
                                <?php
                                $status_colors = [
                                    'pending' => 'bg-yellow-900 bg-opacity-30 text-yellow-400',
                                    'approved' => 'bg-green-900 bg-opacity-30 text-green-400',
                                    'rejected' => 'bg-red-900 bg-opacity-30 text-red-400',
                                ];
                                $color = $status_colors[$request['status']] ?? 'bg-brand-bg-primary text-brand-text-primary';
                                ?>
                                <span class="<?php echo $color; ?> px-3 py-1 rounded-full text-xs font-semibold">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                                <p class="text-xs text-brand-text-secondary mt-1">
                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <!-- User Stats -->
                        <div class="grid grid-cols-4 gap-4 mb-4 p-4 bg-brand-bg-primary rounded-lg">
                            <div>
                                <p class="text-xs text-brand-text-secondary">Membership</p>
                                <p class="font-semibold capitalize"><?php echo $request['membership_plan']; ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-brand-text-secondary">Posts</p>
                                <p class="font-semibold"><?php echo $request['post_count']; ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-brand-text-secondary">Followers</p>
                                <p class="font-semibold"><?php echo $request['follower_count']; ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-brand-text-secondary">SPHE Balance</p>
                                <p class="font-semibold"><?php echo number_format($request['sphe_balance'], 2); ?></p>
                            </div>
                        </div>

                        <!-- Verification Reason -->
                        <?php if (!empty($request['reason'])): ?>
                        <div class="mb-4 p-3 bg-brand-bg-primary rounded-lg">
                            <p class="text-xs text-brand-text-secondary mb-1">Reason for verification:</p>
                            <p class="text-sm"><?php echo htmlspecialchars($request['reason']); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Documents -->
                        <?php if (!empty($request['documents'])): ?>
                        <div class="mb-4">
                            <p class="text-xs text-brand-text-secondary mb-2">Submitted Documents:</p>
                            <div class="flex gap-2">
                                <?php
                                $docs = json_decode($request['documents'], true);
                                foreach ($docs as $doc):
                                ?>
                                <a href="<?php echo htmlspecialchars($doc); ?>" target="_blank" class="px-3 py-2 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg text-sm text-brand-accent">
                                    📄 View Document
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Admin Notes -->
                        <?php if (!empty($request['admin_notes'])): ?>
                        <div class="mb-4 p-3 bg-blue-900 bg-opacity-20 border border-blue-500 border-opacity-30 rounded-lg">
                            <p class="text-xs text-blue-400 mb-1">Admin Notes:</p>
                            <p class="text-sm text-blue-200"><?php echo htmlspecialchars($request['admin_notes']); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Actions (only for pending) -->
                        <?php if ($request['status'] === 'pending'): ?>
                        <div class="flex gap-2">
                            <button @click="approveRequest(<?php echo $request['id']; ?>, <?php echo $request['user_id']; ?>)"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-semibold transition-colors">
                                ✓ Approve Verification
                            </button>
                            <button @click="rejectRequest(<?php echo $request['id']; ?>)"
                                    class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg font-semibold transition-colors">
                                ✗ Reject Request
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($requests)): ?>
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto mb-4 text-brand-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-brand-text-secondary">No verification requests found</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function verificationManagement() {
            return {
                async approveRequest(requestId, userId) {
                    const notes = prompt('Add admin notes (optional):');

                    try {
                        const response = await fetch('../../api/admin/verification_actions.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                request_id: requestId,
                                user_id: userId,
                                action: 'approve',
                                admin_notes: notes
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert('Verification approved!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to approve verification'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error approving verification');
                    }
                },

                async rejectRequest(requestId) {
                    const reason = prompt('Reason for rejection (will be sent to user):');
                    if (!reason) return;

                    try {
                        const response = await fetch('../../api/admin/verification_actions.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                request_id: requestId,
                                action: 'reject',
                                admin_notes: reason
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert('Verification rejected');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to reject verification'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error rejecting verification');
                    }
                }
            }
        }
    </script>

</body>
</html>
