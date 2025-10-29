<?php
/**
 * ADMIN DASHBOARD - Control Center
 * Panel principal de administración con estadísticas y acceso rápido
 */

require_once __DIR__ . '/../../config/connection.php';
session_start();

// Verify admin access
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// Get dashboard stats
$stmt = $pdo->query("SELECT * FROM v_admin_dashboard_stats");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent user registrations (last 24h)
$stmt = $pdo->query("
    SELECT COUNT(*) as new_users_24h
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$new_users = $stmt->fetch(PDO::FETCH_ASSOC);

// Get active surveys
$stmt = $pdo->query("
    SELECT COUNT(*) as active_surveys
    FROM surveys
    WHERE status IN ('active', 'closed')
");
$survey_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent admin actions (last 10)
$stmt = $pdo->prepare("
    SELECT
        ual.*,
        u1.username as admin_name,
        u2.username as target_name
    FROM user_action_logs ual
    INNER JOIN users u1 ON ual.admin_id = u1.user_id
    INNER JOIN users u2 ON ual.target_user_id = u2.user_id
    ORDER BY ual.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top communities by activity
$stmt = $pdo->query("
    SELECT
        c.name,
        c.member_count,
        COUNT(p.id) as posts_today
    FROM communities c
    LEFT JOIN posts p ON c.id = p.community_id AND p.created_at >= CURDATE()
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY posts_today DESC
    LIMIT 5
");
$top_communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Social Mask</title>
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
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Admin Dashboard</h1>
                <p class="text-brand-text-secondary">Control center for The Social Mask platform</p>
            </div>
            <div class="flex gap-3">
                <a href="/pages/admin/manage_users" class="bg-brand-accent hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                    Manage Users
                </a>
                <a href="/pages/admin/reports" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                    Reports (<?php echo $stats['pending_reports']; ?>)
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Active Users -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-brand-text-secondary">Active Users</p>
                    <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <p class="text-3xl font-bold"><?php echo number_format($stats['active_users']); ?></p>
                <p class="text-xs text-green-400 mt-1">+<?php echo $new_users['new_users_24h']; ?> in last 24h</p>
            </div>

            <!-- Active Communities -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-brand-text-secondary">Communities</p>
                    <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <p class="text-3xl font-bold"><?php echo number_format($stats['active_communities']); ?></p>
                <p class="text-xs text-brand-text-secondary mt-1">Total active</p>
            </div>

            <!-- Posts Today -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-brand-text-secondary">Posts Today</p>
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <p class="text-3xl font-bold"><?php echo number_format($stats['posts_today']); ?></p>
                <p class="text-xs text-brand-text-secondary mt-1">Content created</p>
            </div>

            <!-- Pending Reports -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-brand-text-secondary">Pending Reports</p>
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <p class="text-3xl font-bold"><?php echo number_format($stats['pending_reports']); ?></p>
                <p class="text-xs text-red-400 mt-1">Requires review</p>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <!-- Suspended Users -->
            <div class="bg-yellow-900 bg-opacity-20 border border-yellow-600 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-brand-text-secondary mb-1">Suspended Users</p>
                        <p class="text-2xl font-bold"><?php echo $stats['suspended_users']; ?></p>
                    </div>
                    <svg class="w-10 h-10 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
            </div>

            <!-- Banned Users -->
            <div class="bg-red-900 bg-opacity-20 border border-red-600 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-brand-text-secondary mb-1">Banned Users</p>
                        <p class="text-2xl font-bold"><?php echo $stats['banned_users']; ?></p>
                    </div>
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                </div>
            </div>

            <!-- Active Audio Rooms -->
            <div class="bg-purple-900 bg-opacity-20 border border-purple-600 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-brand-text-secondary mb-1">Live Audio Rooms</p>
                        <p class="text-2xl font-bold"><?php echo $stats['active_audio_rooms']; ?></p>
                    </div>
                    <svg class="w-10 h-10 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Top Communities -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <h3 class="text-xl font-bold mb-4">Most Active Communities Today</h3>
                <div class="space-y-3">
                    <?php foreach ($top_communities as $community): ?>
                    <div class="flex items-center justify-between p-3 bg-brand-bg-primary rounded-lg">
                        <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($community['name']); ?></p>
                            <p class="text-sm text-brand-text-secondary"><?php echo number_format($community['member_count']); ?> members</p>
                        </div>
                        <span class="bg-brand-accent bg-opacity-20 text-brand-accent px-3 py-1 rounded-full text-sm font-semibold">
                            <?php echo $community['posts_today']; ?> posts
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Admin Actions -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                <h3 class="text-xl font-bold mb-4">Recent Admin Actions</h3>
                <div class="space-y-3">
                    <?php foreach ($recent_actions as $action): ?>
                    <div class="flex items-start gap-3 p-3 bg-brand-bg-primary rounded-lg">
                        <div class="flex-shrink-0 mt-1">
                            <?php
                            $icon_colors = [
                                'suspend' => 'text-yellow-400',
                                'ban' => 'text-red-400',
                                'verify' => 'text-green-400',
                                'delete' => 'text-red-600'
                            ];
                            $color = $icon_colors[$action['action_type']] ?? 'text-brand-text-secondary';
                            ?>
                            <svg class="w-5 h-5 <?php echo $color; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm">
                                <span class="font-semibold"><?php echo htmlspecialchars($action['admin_name']); ?></span>
                                <span class="text-brand-text-secondary"> <?php echo $action['action_type']; ?> </span>
                                <span class="font-semibold"><?php echo htmlspecialchars($action['target_name']); ?></span>
                            </p>
                            <p class="text-xs text-brand-text-secondary mt-1">
                                <?php echo date('M d, Y H:i', strtotime($action['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Admin Tools -->
        <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
            <h3 class="text-xl font-bold mb-4">Admin Tools</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="/pages/admin/manage_users" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-brand-accent mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <span class="font-semibold">Users</span>
                </a>

                <a href="/pages/admin/manage_communities" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-purple-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <span class="font-semibold">Communities</span>
                </a>

                <a href="/pages/admin/reports" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span class="font-semibold">Reports</span>
                    <?php if ($stats['pending_reports'] > 0): ?>
                    <span class="text-xs bg-red-600 text-white px-2 py-0.5 rounded-full mt-1">
                        <?php echo $stats['pending_reports']; ?>
                    </span>
                    <?php endif; ?>
                </a>

                <a href="/pages/admin/survey_review" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-yellow-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span class="font-semibold">Surveys</span>
                </a>

                <a href="/pages/admin/verifications" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-semibold">Verifications</span>
                    <?php if ($stats['pending_verifications'] > 0): ?>
                    <span class="text-xs bg-green-600 text-white px-2 py-0.5 rounded-full mt-1">
                        <?php echo $stats['pending_verifications']; ?>
                    </span>
                    <?php endif; ?>
                </a>

                <a href="/pages/admin/audio_rooms" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-purple-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                    </svg>
                    <span class="font-semibold">Audio Rooms</span>
                </a>

                <a href="/pages/admin/trending" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-orange-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span class="font-semibold">Trending</span>
                </a>

                <a href="/pages/admin/statistics" class="flex flex-col items-center justify-center p-6 bg-brand-bg-primary hover:bg-opacity-80 rounded-lg transition-all">
                    <svg class="w-12 h-12 text-blue-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="font-semibold">Statistics</span>
                </a>
            </div>
        </div>
    </div>

</body>
</html>
