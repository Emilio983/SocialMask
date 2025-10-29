<?php
/**
 * ADMIN: TRENDING MANAGEMENT
 * Gestión de contenido trending - promover, despromover
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

// Get trending posts (last 7 days, sorted by engagement)
$stmt = $pdo->query("
    SELECT
        p.*,
        u.username,
        u.profile_image,
        c.name as community_name,
        c.slug as community_slug,
        COUNT(DISTINCT cm.id) as comment_count,
        (p.upvotes - p.downvotes) as net_votes,
        (p.upvotes + p.downvotes + COUNT(DISTINCT cm.id) * 2) as engagement_score
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN communities c ON p.community_id = c.id
    LEFT JOIN comments cm ON p.id = cm.post_id
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY p.id
    ORDER BY engagement_score DESC
    LIMIT 50
");
$trending_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get trending communities
$stmt = $pdo->query("
    SELECT
        c.*,
        COUNT(DISTINCT cm.user_id) as member_count,
        COUNT(DISTINCT p.id) as post_count_week,
        (COUNT(DISTINCT cm.user_id) + COUNT(DISTINCT p.id) * 5) as trending_score
    FROM communities c
    LEFT JOIN community_members cm ON c.id = cm.community_id
    LEFT JOIN posts p ON c.id = p.community_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY c.id
    ORDER BY trending_score DESC
    LIMIT 10
");
$trending_communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trending Content - Admin - The Social Mask</title>
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

    <div class="container mx-auto px-4 py-24 max-w-7xl" x-data="{activeTab: 'posts'}">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Trending Content</h1>
                    <p class="text-brand-text-secondary">Monitor and manage trending posts and communities</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 border-b border-brand-border">
            <button @click="activeTab = 'posts'"
                    :class="activeTab === 'posts' ? 'border-brand-accent text-brand-accent' : 'border-transparent text-brand-text-secondary'"
                    class="px-4 py-2 border-b-2 font-semibold transition-colors">
                🔥 Trending Posts (<?php echo count($trending_posts); ?>)
            </button>
            <button @click="activeTab = 'communities'"
                    :class="activeTab === 'communities' ? 'border-brand-accent text-brand-accent' : 'border-transparent text-brand-text-secondary'"
                    class="px-4 py-2 border-b-2 font-semibold transition-colors">
                📈 Trending Communities (<?php echo count($trending_communities); ?>)
            </button>
        </div>

        <!-- Trending Posts -->
        <div x-show="activeTab === 'posts'" class="space-y-4">
            <?php foreach ($trending_posts as $index => $post): ?>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6 hover:border-brand-accent transition-colors">
                <div class="flex items-start gap-4">
                    <!-- Ranking -->
                    <div class="flex flex-col items-center">
                        <span class="text-3xl font-bold text-yellow-500">#<?php echo $index + 1; ?></span>
                        <span class="text-xs text-brand-text-secondary">Score</span>
                        <span class="text-sm font-bold"><?php echo $post['engagement_score']; ?></span>
                    </div>

                    <!-- Content -->
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <img src="<?php echo htmlspecialchars($post['profile_image'] ?? '/assets/default-avatar.png'); ?>" alt="" class="w-8 h-8 rounded-full">
                            <span class="font-semibold"><?php echo htmlspecialchars($post['username']); ?></span>
                            <span class="text-brand-text-secondary">in</span>
                            <span class="text-brand-accent">/{<?php echo htmlspecialchars($post['community_slug']); ?>}</span>
                            <span class="text-xs text-brand-text-secondary">• <?php echo date('M d', strtotime($post['created_at'])); ?></span>
                        </div>

                        <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="text-brand-text-secondary text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars(substr($post['content'], 0, 200)); ?>...</p>

                        <!-- Metrics -->
                        <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1">
                                <span class="text-green-500">↑ <?php echo $post['upvotes']; ?></span>
                                <span class="text-red-500">↓ <?php echo $post['downvotes']; ?></span>
                            </span>
                            <span class="text-brand-text-secondary"><?php echo $post['comment_count']; ?> comments</span>
                            <span class="text-brand-text-secondary"><?php echo $post['views']; ?> views</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-2">
                        <a href="/pages/community_view?slug=<?php echo urlencode($post['community_slug']); ?>" target="_blank"
                           class="px-4 py-2 bg-brand-accent hover:bg-blue-600 text-white rounded-lg text-sm transition-colors text-center">
                            View Post
                        </a>
                        <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm transition-colors">
                            Remove
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($trending_posts)): ?>
            <div class="text-center py-12">
                <p class="text-brand-text-secondary">No trending posts in the last 7 days</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Trending Communities -->
        <div x-show="activeTab === 'communities'" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
            <?php foreach ($trending_communities as $index => $community): ?>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl overflow-hidden hover:border-brand-accent transition-colors">
                <!-- Banner -->
                <div class="h-20 bg-gradient-to-r from-blue-600 to-purple-600 relative">
                    <?php if ($community['banner_image']): ?>
                    <img src="<?php echo htmlspecialchars($community['banner_image']); ?>" alt="" class="w-full h-full object-cover">
                    <?php endif; ?>
                    <div class="absolute top-2 left-2 bg-yellow-500 text-black px-3 py-1 rounded-full text-sm font-bold">
                        #<?php echo $index + 1; ?> Trending
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <img src="<?php echo htmlspecialchars($community['logo_url'] ?? '/assets/default-community.png'); ?>" alt="" class="w-12 h-12 rounded-full">
                        <div class="flex-1">
                            <h3 class="font-bold"><?php echo htmlspecialchars($community['name']); ?></h3>
                            <p class="text-xs text-brand-text-secondary">/{<?php echo htmlspecialchars($community['slug']); ?>}</p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-2 mb-3 text-center text-xs">
                        <div class="bg-brand-bg-primary rounded p-2">
                            <p class="font-bold"><?php echo $community['member_count']; ?></p>
                            <p class="text-brand-text-secondary">Members</p>
                        </div>
                        <div class="bg-brand-bg-primary rounded p-2">
                            <p class="font-bold"><?php echo $community['post_count_week']; ?></p>
                            <p class="text-brand-text-secondary">Posts/week</p>
                        </div>
                        <div class="bg-brand-bg-primary rounded p-2">
                            <p class="font-bold text-yellow-500"><?php echo $community['trending_score']; ?></p>
                            <p class="text-brand-text-secondary">Score</p>
                        </div>
                    </div>

                    <p class="text-sm text-brand-text-secondary mb-3 line-clamp-2">
                        <?php echo htmlspecialchars($community['description'] ?? 'No description'); ?>
                    </p>

                    <a href="/pages/community_view?slug=<?php echo urlencode($community['slug']); ?>" target="_blank"
                       class="block w-full text-center bg-brand-accent hover:bg-blue-600 text-white py-2 rounded-lg transition-colors">
                        View Community
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($trending_communities)): ?>
            <div class="col-span-2 text-center py-12">
                <p class="text-brand-text-secondary">No trending communities</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
