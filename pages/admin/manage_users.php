<?php
/**
 * ADMIN: USER MANAGEMENT
 * Gestión completa de usuarios - ban, suspend, freeze, delete, verify
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

// Get filters
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = ["role != 'admin'"];
$params = [];

if ($filter_status !== 'all') {
    $where_clauses[] = "account_status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where_clauses[] = "(username LIKE ? OR unique_username LIKE ? OR email LIKE ? OR wallet_address LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Get users
$stmt = $pdo->prepare("
    SELECT
        u.*,
        COUNT(DISTINCT p.id) as total_posts,
        COUNT(DISTINCT c.id) as total_comments,
        COALESCE(SUM(p.upvotes - p.downvotes), 0) as net_votes
    FROM users u
    LEFT JOIN posts p ON u.user_id = p.user_id
    LEFT JOIN comments c ON u.user_id = c.user_id
    $where_sql
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user count by status
$stmt = $pdo->query("
    SELECT
        account_status,
        COUNT(*) as count
    FROM users
    WHERE role != 'admin'
    GROUP BY account_status
");
$status_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['account_status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin - The Social Mask</title>
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

    <div class="container mx-auto px-4 py-24 max-w-7xl" x-data="userManagement()">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">User Management</h1>
                    <p class="text-brand-text-secondary">Manage platform users and their permissions</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Status Filters -->
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="?status=all" class="px-4 py-2 rounded-lg <?php echo $filter_status === 'all' ? 'bg-brand-accent text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                All Users
            </a>
            <a href="?status=active" class="px-4 py-2 rounded-lg <?php echo $filter_status === 'active' ? 'bg-green-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Active (<?php echo $status_counts['active'] ?? 0; ?>)
            </a>
            <a href="?status=suspended" class="px-4 py-2 rounded-lg <?php echo $filter_status === 'suspended' ? 'bg-yellow-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Suspended (<?php echo $status_counts['suspended'] ?? 0; ?>)
            </a>
            <a href="?status=frozen" class="px-4 py-2 rounded-lg <?php echo $filter_status === 'frozen' ? 'bg-blue-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Frozen (<?php echo $status_counts['frozen'] ?? 0; ?>)
            </a>
            <a href="?status=banned" class="px-4 py-2 rounded-lg <?php echo $filter_status === 'banned' ? 'bg-red-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-opacity-80'; ?>">
                Banned (<?php echo $status_counts['banned'] ?? 0; ?>)
            </a>
        </div>

        <!-- Search -->
        <div class="mb-6">
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by username, email, or wallet..."
                    class="flex-1 bg-brand-bg-secondary border border-brand-border rounded-lg px-4 py-2 text-brand-text-primary focus:outline-none focus:border-brand-accent"
                >
                <button type="submit" class="bg-brand-accent hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold">
                    Search
                </button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-brand-bg-secondary border border-brand-border rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-brand-bg-primary">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold">User</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Membership</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Stats</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Balance</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Joined</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-brand-bg-primary">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo htmlspecialchars($u['profile_image'] ?? '/assets/default-avatar.png'); ?>" alt="" class="w-10 h-10 rounded-full">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold"><?php echo htmlspecialchars($u['username']); ?></p>
                                            <?php if ($u['is_verified']): ?>
                                            <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-brand-text-secondary">@<?php echo htmlspecialchars($u['unique_username']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $status_colors = [
                                    'active' => 'bg-green-900 bg-opacity-30 text-green-400',
                                    'suspended' => 'bg-yellow-900 bg-opacity-30 text-yellow-400',
                                    'frozen' => 'bg-blue-900 bg-opacity-30 text-blue-400',
                                    'banned' => 'bg-red-900 bg-opacity-30 text-red-400',
                                ];
                                $color = $status_colors[$u['account_status']] ?? 'bg-brand-bg-primary text-brand-text-primary';
                                ?>
                                <span class="<?php echo $color; ?> px-2 py-1 rounded-full text-xs font-semibold">
                                    <?php echo ucfirst($u['account_status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm capitalize"><?php echo $u['membership_plan']; ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm">
                                    <p><?php echo $u['total_posts']; ?> posts</p>
                                    <p class="text-xs text-brand-text-secondary"><?php echo $u['total_comments']; ?> comments</p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold"><?php echo number_format($u['sphe_balance'], 2); ?> SPHE</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="openModal(<?php echo htmlspecialchars(json_encode($u)); ?>)" class="p-2 hover:bg-brand-bg-primary rounded-lg transition-colors" title="Manage">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Action Modal -->
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
            <div class="relative bg-brand-bg-secondary border border-brand-border rounded-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold">Manage User</h2>
                    <button @click="showModal = false" class="text-brand-text-secondary hover:text-brand-text-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <template x-if="selectedUser">
                    <div>
                        <!-- User Info -->
                        <div class="flex items-center gap-4 mb-6 p-4 bg-brand-bg-primary rounded-lg">
                            <img :src="selectedUser.profile_image || '/assets/default-avatar.png'" alt="" class="w-16 h-16 rounded-full">
                            <div>
                                <p class="text-xl font-bold" x-text="selectedUser.username"></p>
                                <p class="text-sm text-brand-text-secondary" x-text="'@' + selectedUser.unique_username"></p>
                                <p class="text-xs text-brand-text-secondary" x-text="selectedUser.email"></p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <button @click="performAction('suspend')" :disabled="selectedUser.account_status === 'suspended'" class="flex items-center justify-center gap-2 bg-yellow-600 hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                Suspend
                            </button>

                            <button @click="performAction('freeze')" :disabled="selectedUser.account_status === 'frozen'" class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Freeze
                            </button>

                            <button @click="performAction('ban')" :disabled="selectedUser.account_status === 'banned'" class="flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                                Ban
                            </button>

                            <button @click="performAction('unsuspend')" :disabled="selectedUser.account_status === 'active'" class="flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Restore
                            </button>

                            <button @click="performAction('verify')" :disabled="selectedUser.is_verified == '1'" class="flex items-center justify-center gap-2 bg-brand-accent hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Verify
                            </button>

                            <button @click="performAction('delete')" class="flex items-center justify-center gap-2 bg-red-900 hover:bg-red-800 text-white px-4 py-3 rounded-lg font-semibold transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete
                            </button>
                        </div>

                        <!-- Reason Input -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Reason (optional)</label>
                            <textarea
                                x-model="actionReason"
                                placeholder="Enter reason for this action..."
                                rows="3"
                                class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-2 text-brand-text-primary focus:outline-none focus:border-brand-accent"
                            ></textarea>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function userManagement() {
            return {
                showModal: false,
                selectedUser: null,
                actionReason: '',

                openModal(user) {
                    this.selectedUser = user;
                    this.actionReason = '';
                    this.showModal = true;
                },

                async performAction(action) {
                    if (!this.selectedUser) return;

                    // Confirm dangerous actions
                    const dangerousActions = ['ban', 'delete'];
                    if (dangerousActions.includes(action)) {
                        if (!confirm(`Are you sure you want to ${action} ${this.selectedUser.username}? This is a serious action.`)) {
                            return;
                        }
                    }

                    try {
                        const response = await fetch('../../api/admin/user_actions.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                user_id: this.selectedUser.user_id,
                                action: action,
                                reason: this.actionReason
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert(`Action completed: ${action}`);
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to perform action'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error performing action');
                    }
                }
            }
        }
    </script>

</body>
</html>
