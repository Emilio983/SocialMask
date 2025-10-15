<?php
/**
 * ADMIN: COMMUNITY MANAGEMENT
 * Gestión de comunidades - editar, eliminar, featured
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

// Get filters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

if ($filter === 'featured') {
    $where_clauses[] = "c.is_featured = 1";
} elseif ($filter === 'private') {
    $where_clauses[] = "c.is_private = 1";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get communities
$stmt = $pdo->prepare("
    SELECT
        c.*,
        u.username as creator_username,
        COUNT(DISTINCT cm.user_id) as member_count,
        COUNT(DISTINCT p.id) as post_count
    FROM communities c
    LEFT JOIN users u ON c.creator_id = u.user_id
    LEFT JOIN community_members cm ON c.id = cm.community_id
    LEFT JOIN posts p ON c.id = p.community_id
    $where_sql
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats = $pdo->query("
    SELECT
        COUNT(*) as total_communities,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_count,
        SUM(CASE WHEN is_private = 1 THEN 1 ELSE 0 END) as private_count
    FROM communities
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Communities - Admin - The Social Mask</title>
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

    <div class="container mx-auto px-4 py-24 max-w-7xl" x-data="communityManagement()">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Community Management</h1>
                    <p class="text-brand-text-secondary">Manage platform communities and settings</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Total Communities</p>
                <p class="text-3xl font-bold"><?php echo $stats['total_communities']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Featured</p>
                <p class="text-3xl font-bold text-yellow-500"><?php echo $stats['featured_count']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                <p class="text-brand-text-secondary text-sm mb-1">Private</p>
                <p class="text-3xl font-bold text-purple-500"><?php echo $stats['private_count']; ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="?filter=all" class="px-4 py-2 rounded-lg <?php echo $filter === 'all' ? 'bg-brand-accent text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                All
            </a>
            <a href="?filter=featured" class="px-4 py-2 rounded-lg <?php echo $filter === 'featured' ? 'bg-yellow-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Featured
            </a>
            <a href="?filter=private" class="px-4 py-2 rounded-lg <?php echo $filter === 'private' ? 'bg-purple-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Private
            </a>
        </div>

        <!-- Search -->
        <div class="mb-6">
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search communities by name, slug, or description..."
                    class="flex-1 bg-brand-bg-secondary border border-brand-border rounded-lg px-4 py-2 text-brand-text-primary focus:outline-none focus:border-brand-accent"
                >
                <button type="submit" class="bg-brand-accent hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold">
                    Search
                </button>
            </form>
        </div>

        <!-- Communities Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($communities as $community): ?>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl overflow-hidden hover:border-brand-accent transition-colors">
                <!-- Community Banner -->
                <div class="h-24 bg-gradient-to-r from-blue-600 to-purple-600 relative">
                    <?php if ($community['banner_image']): ?>
                    <img src="<?php echo htmlspecialchars($community['banner_image']); ?>" alt="" class="w-full h-full object-cover">
                    <?php endif; ?>
                    <?php if ($community['is_featured']): ?>
                    <div class="absolute top-2 right-2 bg-yellow-500 text-black px-2 py-1 rounded text-xs font-bold">
                        FEATURED
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Community Info -->
                <div class="p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <img src="<?php echo htmlspecialchars($community['logo_url'] ?? '/assets/default-community.png'); ?>" alt="" class="w-12 h-12 rounded-full border-2 border-brand-bg-primary">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold truncate"><?php echo htmlspecialchars($community['name']); ?></h3>
                            <p class="text-xs text-brand-text-secondary truncate">/{<?php echo htmlspecialchars($community['slug']); ?>}</p>
                        </div>
                    </div>

                    <p class="text-sm text-brand-text-secondary mb-3 line-clamp-2">
                        <?php echo htmlspecialchars($community['description'] ?? 'No description'); ?>
                    </p>

                    <!-- Stats -->
                    <div class="flex items-center gap-4 text-xs text-brand-text-secondary mb-3">
                        <span><?php echo $community['member_count']; ?> members</span>
                        <span><?php echo $community['post_count']; ?> posts</span>
                        <?php if ($community['is_private']): ?>
                        <span class="text-purple-400">🔒 Private</span>
                        <?php endif; ?>
                    </div>

                    <p class="text-xs text-brand-text-secondary mb-3">
                        Created by @<?php echo htmlspecialchars($community['creator_username']); ?>
                    </p>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button @click="toggleFeatured(<?php echo $community['id']; ?>, <?php echo $community['is_featured']; ?>)"
                                class="flex-1 <?php echo $community['is_featured'] ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-brand-bg-primary hover:bg-opacity-80'; ?> text-white text-sm py-2 rounded-lg transition-colors">
                            <?php echo $community['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                        </button>
                        <button @click="deleteCommunity(<?php echo $community['id']; ?>, '<?php echo htmlspecialchars($community['name']); ?>')"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm py-2 rounded-lg transition-colors">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($communities)): ?>
        <div class="text-center py-12">
            <p class="text-brand-text-secondary">No communities found</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function communityManagement() {
            return {
                async toggleFeatured(communityId, currentStatus) {
                    try {
                        const response = await fetch('../../api/admin/community_actions.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                community_id: communityId,
                                action: 'toggle_featured',
                                featured: currentStatus ? 0 : 1
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to update community'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error updating community');
                    }
                },

                async deleteCommunity(communityId, communityName) {
                    if (!confirm(`Are you sure you want to delete "${communityName}"? This action cannot be undone and will remove all posts, comments, and members.`)) {
                        return;
                    }

                    try {
                        const response = await fetch('../../api/admin/community_actions.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                community_id: communityId,
                                action: 'delete'
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert('Community deleted successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to delete community'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error deleting community');
                    }
                }
            }
        }
    </script>

</body>
</html>
