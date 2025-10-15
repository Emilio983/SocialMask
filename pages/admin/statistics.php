<?php
/**
 * ADMIN: PLATFORM STATISTICS
 * Estadísticas detalladas de la plataforma
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

// Get comprehensive statistics
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) as total,
                           SUM(CASE WHEN account_status = 'active' THEN 1 ELSE 0 END) as active,
                           SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_week,
                           SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_month
                    FROM users WHERE role != 'admin'")->fetch(PDO::FETCH_ASSOC),

    'communities' => $pdo->query("SELECT COUNT(*) as total,
                                 SUM(CASE WHEN is_private = 0 THEN 1 ELSE 0 END) as public,
                                 SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured
                          FROM communities")->fetch(PDO::FETCH_ASSOC),

    'content' => $pdo->query("SELECT
                            (SELECT COUNT(*) FROM posts) as total_posts,
                            (SELECT COUNT(*) FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as posts_week,
                            (SELECT COUNT(*) FROM comments) as total_comments,
                            (SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as comments_week")->fetch(PDO::FETCH_ASSOC),

    'tokens' => $pdo->query("SELECT
                           SUM(sphe_balance) as total_circulation,
                           AVG(sphe_balance) as avg_balance,
                           MAX(sphe_balance) as max_balance
                    FROM users")->fetch(PDO::FETCH_ASSOC),

    'memberships' => $pdo->query("SELECT
                                 membership_plan,
                                 COUNT(*) as count
                          FROM users
                          WHERE role != 'admin'
                          GROUP BY membership_plan")->fetchAll(PDO::FETCH_ASSOC)
];

// User growth (last 30 days)
$user_growth = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as new_users
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top communities
$top_communities = $pdo->query("
    SELECT c.name, c.slug, COUNT(DISTINCT cm.user_id) as members
    FROM communities c
    LEFT JOIN community_members cm ON c.id = cm.community_id
    GROUP BY c.id
    ORDER BY members DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Top users
$top_users = $pdo->query("
    SELECT u.username, u.unique_username, u.profile_image,
           COUNT(DISTINCT p.id) as post_count,
           COUNT(DISTINCT f.follower_id) as followers
    FROM users u
    LEFT JOIN posts p ON u.user_id = p.user_id
    LEFT JOIN followers f ON u.user_id = f.following_id
    WHERE u.role != 'admin'
    GROUP BY u.user_id
    ORDER BY followers DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Statistics - Admin - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Platform Statistics</h1>
                    <p class="text-brand-text-secondary">Comprehensive analytics and insights</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">👥 User Statistics</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">Total Users</p>
                    <p class="text-3xl font-bold"><?php echo number_format($stats['users']['total']); ?></p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">Active Users</p>
                    <p class="text-3xl font-bold text-green-500"><?php echo number_format($stats['users']['active']); ?></p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">New This Week</p>
                    <p class="text-3xl font-bold text-blue-500"><?php echo number_format($stats['users']['new_week']); ?></p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">New This Month</p>
                    <p class="text-3xl font-bold text-purple-500"><?php echo number_format($stats['users']['new_month']); ?></p>
                </div>
            </div>
        </div>

        <!-- Community & Content Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <!-- Communities -->
            <div>
                <h2 class="text-2xl font-bold mb-4">🏘️ Communities</h2>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-brand-text-secondary">Total Communities</span>
                            <span class="text-2xl font-bold"><?php echo number_format($stats['communities']['total']); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-brand-text-secondary">Public</span>
                            <span class="text-xl font-bold text-green-500"><?php echo number_format($stats['communities']['public']); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-brand-text-secondary">Featured</span>
                            <span class="text-xl font-bold text-yellow-500"><?php echo number_format($stats['communities']['featured']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div>
                <h2 class="text-2xl font-bold mb-4">📝 Content</h2>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-brand-text-secondary">Total Posts</span>
                            <span class="text-2xl font-bold"><?php echo number_format($stats['content']['total_posts']); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-brand-text-secondary">Posts This Week</span>
                            <span class="text-xl font-bold text-blue-500"><?php echo number_format($stats['content']['posts_week']); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-brand-text-secondary">Total Comments</span>
                            <span class="text-xl font-bold"><?php echo number_format($stats['content']['total_comments']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Token Economics -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">🪙 Token Economics (SPHE)</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">Total in Circulation</p>
                    <p class="text-3xl font-bold text-yellow-500"><?php echo number_format($stats['tokens']['total_circulation'], 2); ?> SPHE</p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">Average Balance</p>
                    <p class="text-3xl font-bold"><?php echo number_format($stats['tokens']['avg_balance'], 2); ?> SPHE</p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                    <p class="text-brand-text-secondary text-sm mb-1">Highest Balance</p>
                    <p class="text-3xl font-bold text-green-500"><?php echo number_format($stats['tokens']['max_balance'], 2); ?> SPHE</p>
                </div>
            </div>
        </div>

        <!-- Membership Distribution -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">💎 Membership Distribution</h2>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <?php foreach ($stats['memberships'] as $membership): ?>
                    <div class="text-center p-4 bg-brand-bg-primary rounded-lg">
                        <p class="text-2xl font-bold"><?php echo $membership['count']; ?></p>
                        <p class="text-sm text-brand-text-secondary capitalize"><?php echo $membership['membership_plan']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Communities -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">🏆 Top Communities</h2>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                <div class="space-y-3">
                    <?php foreach ($top_communities as $index => $community): ?>
                    <div class="flex items-center justify-between p-3 bg-brand-bg-primary rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="text-xl font-bold text-brand-text-secondary">#<?php echo $index + 1; ?></span>
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($community['name']); ?></p>
                                <p class="text-xs text-brand-text-secondary">/{<?php echo htmlspecialchars($community['slug']); ?>}</p>
                            </div>
                        </div>
                        <span class="text-brand-accent font-bold"><?php echo number_format($community['members']); ?> members</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Users -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">⭐ Top Users</h2>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                <div class="space-y-3">
                    <?php foreach ($top_users as $index => $top_user): ?>
                    <div class="flex items-center justify-between p-3 bg-brand-bg-primary rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="text-xl font-bold text-brand-text-secondary">#<?php echo $index + 1; ?></span>
                            <img src="<?php echo htmlspecialchars($top_user['profile_image'] ?? '/assets/default-avatar.png'); ?>" alt="" class="w-10 h-10 rounded-full">
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($top_user['username']); ?></p>
                                <p class="text-xs text-brand-text-secondary">@<?php echo htmlspecialchars($top_user['unique_username']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-brand-accent font-bold"><?php echo number_format($top_user['followers']); ?> followers</p>
                            <p class="text-xs text-brand-text-secondary"><?php echo number_format($top_user['post_count']); ?> posts</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- User Growth Chart -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">📈 User Growth (Last 30 Days)</h2>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // User Growth Chart
        const ctx = document.getElementById('userGrowthChart').getContext('2d');
        const chartData = <?php echo json_encode($user_growth); ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.date),
                datasets: [{
                    label: 'New Users',
                    data: chartData.map(d => d.new_users),
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#8B949E'
                        },
                        grid: {
                            color: '#30363D'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#8B949E'
                        },
                        grid: {
                            color: '#30363D'
                        }
                    }
                }
            }
        });
    </script>

</body>
</html>
