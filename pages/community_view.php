<?php
// ============================================
// COMMUNITY VIEW PAGE (Discord-like Layout)
// ============================================
require_once __DIR__ . '/../config/connection.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$community_id) {
    header('Location: communities.php');
    exit;
}

// Get community data
$community_sql = "
    SELECT
        c.*,
        u.username as owner_username,
        u.username as owner_name,
        (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND user_id = ?) as is_member,
        (SELECT role FROM community_members WHERE community_id = c.id AND user_id = ?) as user_role
    FROM communities c
    LEFT JOIN users u ON c.owner_id = u.user_id
    WHERE c.id = ? AND c.deleted_at IS NULL
";

$stmt = $pdo->prepare($community_sql);
$stmt->execute([$current_user_id, $current_user_id, $community_id]);
$community = $stmt->fetch();

if (!$community) {
    header('Location: communities.php');
    exit;
}

$is_member = (bool)$community['is_member'];
$user_role = $community['user_role'] ?? null;
$is_owner = ($user_role === 'owner');
$is_admin = ($user_role === 'admin' || $is_owner);

// If not member, redirect to join
if (!$is_member) {
    header('Location: communities.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($community['name']); ?> - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@latest/dist/web3.min.js"></script>
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
                        'brand-success': '#3FB950',
                        'brand-warning': '#D29922',
                        'brand-error': '#F85149'
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <div class="pt-40 flex h-screen">
        <!-- Left Sidebar: Ad Space (toggleable by owner/admin) -->
        <?php if ($community['left_ad_enabled']): ?>
        <div class="hidden lg:flex w-[300px] flex-shrink-0 bg-brand-bg-secondary border-r border-brand-border p-4">
            <div class="sticky top-24 w-full">
                <div class="bg-brand-bg-primary border border-brand-border rounded-xl h-[600px] flex items-center justify-center">
                    <?php if ($is_admin): ?>
                        <div class="text-center p-6">
                            <p class="text-brand-text-secondary text-sm mb-4">Left Ad Space</p>
                            <button onclick="openAdManager('left')" class="bg-brand-accent hover:bg-blue-600 text-white text-sm px-4 py-2 rounded-lg">
                                Manage Ads
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="text-brand-text-secondary text-center px-6 text-sm">Ad Space</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Community Header -->
            <div class="bg-brand-bg-secondary border-b border-brand-border p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <?php
                        $logo_url = $community['logo_url']
                            ? ($community['logo_url'][0] === '/' ? '..' . $community['logo_url'] : $community['logo_url'])
                            : 'https://via.placeholder.com/80/3B82F6/FFFFFF?text=' . substr($community['name'], 0, 1);
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>"
                             alt="<?php echo htmlspecialchars($community['name']); ?>"
                             class="w-16 h-16 rounded-full border-2 border-brand-border object-cover">
                        <div>
                            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($community['name']); ?></h1>
                            <p class="text-sm text-brand-text-secondary">
                                <?php echo number_format($community['member_count']); ?> members •
                                <?php echo number_format($community['post_count']); ?> posts
                            </p>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                    <div class="flex gap-2">
                        <button onclick="toggleAdSpace('left')" class="bg-brand-bg-primary border border-brand-border hover:border-brand-accent text-sm px-4 py-2 rounded-lg transition-colors">
                            <?php echo $community['left_ad_enabled'] ? 'Hide' : 'Show'; ?> Left Ad
                        </button>
                        <button onclick="toggleAdSpace('right')" class="bg-brand-bg-primary border border-brand-border hover:border-brand-accent text-sm px-4 py-2 rounded-lg transition-colors">
                            <?php echo $community['right_ad_enabled'] ? 'Hide' : 'Show'; ?> Right Ad
                        </button>
                        <button onclick="openSettings()" class="bg-brand-accent hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Banner (if exists) -->
            <?php if ($community['banner_url']): ?>
            <div class="w-full h-48 overflow-hidden">
                <?php
                $banner_url = $community['banner_url'][0] === '/'
                    ? '..' . $community['banner_url']
                    : $community['banner_url'];
                ?>
                <img src="<?php echo htmlspecialchars($banner_url); ?>"
                     alt="Banner"
                     class="w-full h-full object-cover">
            </div>
            <?php endif; ?>

            <!-- Posts Feed -->
            <div class="flex-1 overflow-y-auto p-6 scrollbar-hide">
                <div id="posts-container" class="max-w-3xl mx-auto space-y-6">
                    <!-- Post Composer -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6">
                        <div class="flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-brand-accent flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="flex-1">
                                <textarea
                                    id="post-content"
                                    placeholder="What's on your mind?"
                                    rows="3"
                                    class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none resize-none"
                                ></textarea>
                                <div class="flex items-center justify-between mt-3">
                                    <div class="flex gap-2">
                                        <button class="text-brand-text-secondary hover:text-brand-accent transition-colors p-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </button>
                                        <button onclick="openSurveyModal()" id="survey-btn" class="text-brand-text-secondary hover:text-brand-accent transition-colors p-2" title="Create Survey">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <button onclick="createPost()" class="bg-brand-accent hover:bg-blue-600 text-white font-semibold px-6 py-2 rounded-lg transition-colors">
                                        Post
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Posts List -->
                    <div id="posts-list">
                        <div class="text-center py-12">
                            <p class="text-brand-text-secondary">No posts yet. Be the first to post!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar: Groups & Community Info -->
        <div class="hidden md:flex w-[280px] flex-shrink-0 bg-brand-bg-secondary border-l border-brand-border flex-col overflow-hidden">
            <div class="p-4 border-b border-brand-border">
                <h2 class="font-bold text-lg">Groups</h2>
            </div>

            <div class="flex-1 overflow-y-auto scrollbar-hide p-4 space-y-2">
                <!-- Groups will be loaded here -->
                <div id="groups-list">
                    <div class="text-center py-6">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-2 border-brand-accent border-t-transparent"></div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <button onclick="openCreateGroupModal()" class="w-full bg-brand-accent hover:bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Group
                </button>
                <?php endif; ?>
            </div>

            <!-- Community Info -->
            <div class="p-4 border-t border-brand-border">
                <h3 class="font-bold text-sm mb-2">About</h3>
                <p class="text-sm text-brand-text-secondary mb-4">
                    <?php echo htmlspecialchars($community['description'] ?? 'No description'); ?>
                </p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2 text-brand-text-secondary">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                        </svg>
                        <?php echo number_format($community['member_count']); ?> members
                    </div>
                    <div class="flex items-center gap-2 text-brand-text-secondary">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        Created <?php echo date('M d, Y', strtotime($community['created_at'])); ?>
                    </div>
                    <div class="flex items-center gap-2 text-brand-text-secondary">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Owner: <span class="text-brand-accent"><?php echo htmlspecialchars($community['owner_username']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Ad Space (if enabled) -->
        <?php if ($community['right_ad_enabled']): ?>
        <div class="hidden xl:flex w-[300px] flex-shrink-0 bg-brand-bg-secondary border-l border-brand-border p-4">
            <div class="sticky top-24 w-full">
                <div class="bg-brand-bg-primary border border-brand-border rounded-xl h-[600px] flex items-center justify-center">
                    <?php if ($is_admin): ?>
                        <div class="text-center p-6">
                            <p class="text-brand-text-secondary text-sm mb-4">Right Ad Space</p>
                            <button onclick="openAdManager('right')" class="bg-brand-accent hover:bg-blue-600 text-white text-sm px-4 py-2 rounded-lg">
                                Manage Ads
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="text-brand-text-secondary text-center px-6 text-sm">Ad Space</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Survey Creation Modal -->
    <div id="survey-modal" class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center z-50 p-4">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-white">Create Crypto Survey</h2>
                    <button onclick="closeSurveyModal()" class="text-brand-text-secondary hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Warning for required plan -->
                <div class="bg-yellow-900 bg-opacity-30 border border-yellow-600 rounded-lg p-4 mb-6">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold text-yellow-500 mb-1">Premium Feature</p>
                            <p class="text-sm text-yellow-100">Requires Diamond, Gold, or Creator membership + $10 SPHE deposit</p>
                        </div>
                    </div>
                </div>

                <form id="survey-form" class="space-y-6">
                    <!-- Survey Title -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Survey Title</label>
                        <input
                            type="text"
                            id="survey-title"
                            placeholder="e.g., Will Bitcoin reach $100k in 2025?"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                            required
                        />
                    </div>

                    <!-- Survey Description -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Description (Optional)</label>
                        <textarea
                            id="survey-description"
                            placeholder="Add more context about your survey..."
                            rows="3"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none resize-none"
                        ></textarea>
                    </div>

                    <!-- Entry Price -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Entry Price (SPHE)</label>
                        <input
                            type="number"
                            id="survey-price"
                            placeholder="10"
                            min="1"
                            step="0.1"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                            required
                        />
                        <p class="text-xs text-brand-text-secondary mt-1">Participants will pay this amount to vote</p>
                    </div>

                    <!-- Option A -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Option A</label>
                        <input
                            type="text"
                            id="survey-option-a"
                            placeholder="e.g., Yes"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                            required
                        />
                    </div>

                    <!-- Option B -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Option B</label>
                        <input
                            type="text"
                            id="survey-option-b"
                            placeholder="e.g., No"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                            required
                        />
                    </div>

                    <!-- Duration -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Survey Duration</label>
                        <select
                            id="survey-duration"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                            required
                        >
                            <option value="">Select duration</option>
                            <option value="1">1 hour</option>
                            <option value="6">6 hours</option>
                            <option value="12">12 hours</option>
                            <option value="24">1 day</option>
                            <option value="48">2 days</option>
                            <option value="72">3 days</option>
                            <option value="168">1 week</option>
                        </select>
                    </div>

                    <!-- Deposit Information -->
                    <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4">
                        <h3 class="font-semibold mb-2">Creator Deposit Required</h3>
                        <ul class="space-y-2 text-sm text-brand-text-secondary">
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-brand-accent flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span>You must deposit <strong>1000 SPHE</strong> to create this survey (REFUNDABLE)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-brand-accent flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span>Declare winner within <strong>48 hours</strong> to get deposit + 10% commission</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span>No response = deposit forfeited to treasury</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            onclick="closeSurveyModal()"
                            class="flex-1 bg-brand-bg-primary border border-brand-border hover:border-brand-accent text-white font-semibold px-6 py-3 rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            id="create-survey-btn"
                            class="flex-1 bg-brand-accent hover:bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors"
                        >
                            Create Survey (Deposit 1000 SPHE)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const communityId = <?php echo $community_id; ?>;
        const currentUserId = <?php echo $current_user_id; ?>;
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        let currentGroupId = null;

        // Load groups on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadGroups();
            loadPosts();
        });

        // Load groups
        async function loadGroups() {
            try {
                const response = await fetch(`../api/groups/list.php?community_id=${communityId}`);
                const data = await response.json();

                if (data.success && data.groups.length > 0) {
                    renderGroups(data.groups);
                    // Select first group by default
                    if (data.groups.length > 0) {
                        selectGroup(data.groups[0].id);
                    }
                } else {
                    document.getElementById('groups-list').innerHTML = '<p class="text-sm text-brand-text-secondary text-center py-4">No groups yet</p>';
                }
            } catch (error) {
                console.error('Error loading groups:', error);
            }
        }

        // Render groups
        function renderGroups(groups) {
            const html = groups.map(group => `
                <div id="group-${group.id}"
                     onclick="selectGroup(${group.id})"
                     class="group-item px-4 py-3 rounded-lg cursor-pointer transition-colors hover:bg-brand-bg-primary ${group.is_default ? 'bg-brand-bg-primary' : ''}">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">#</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">${group.name}</p>
                            ${group.is_default ? '<p class="text-xs text-brand-text-secondary">Default</p>' : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            document.getElementById('groups-list').innerHTML = html;
        }

        // Select group
        function selectGroup(groupId) {
            currentGroupId = groupId;

            // Update UI
            document.querySelectorAll('.group-item').forEach(item => {
                item.classList.remove('bg-brand-bg-primary', 'border-l-4', 'border-brand-accent');
            });

            const selected = document.getElementById('group-' + groupId);
            if (selected) {
                selected.classList.add('bg-brand-bg-primary', 'border-l-4', 'border-brand-accent');
            }

            loadPosts();
        }

        // Load posts
        async function loadPosts() {
            if (!currentGroupId) return;

            try {
                const response = await fetch(`../api/posts/list.php?group_id=${currentGroupId}`);
                const data = await response.json();

                if (data.success && data.posts.length > 0) {
                    renderPosts(data.posts);
                } else {
                    document.getElementById('posts-list').innerHTML = '<div class="text-center py-12"><p class="text-brand-text-secondary">No posts yet. Be the first to post!</p></div>';
                }
            } catch (error) {
                console.error('Error loading posts:', error);
            }
        }

        // Render posts
        function renderPosts(posts) {
            if (!posts || posts.length === 0) {
                document.getElementById('posts-list').innerHTML = '<div class="text-center py-12"><p class="text-brand-text-secondary">No posts yet. Be the first to post!</p></div>';
                return;
            }

            const html = posts.map(post => {
                // Badge de modo (P2P o Centralizado)
                const modeBadge = post.p2p_mode ?
                    '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-green-500/20 border border-green-500/30 text-green-400 text-xs font-semibold">🌐 P2P</span>' :
                    '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-500/20 border border-gray-500/30 text-gray-400 text-xs font-semibold">🗄️ Central</span>';

                // Formatear fecha
                const postDate = new Date(post.created_at);
                const now = new Date();
                const diffMs = now - postDate;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);

                let timeAgo;
                if (diffMins < 1) timeAgo = 'Just now';
                else if (diffMins < 60) timeAgo = `${diffMins}m ago`;
                else if (diffHours < 24) timeAgo = `${diffHours}h ago`;
                else if (diffDays < 7) timeAgo = `${diffDays}d ago`;
                else timeAgo = postDate.toLocaleDateString();

                // Procesar imágenes
                let imagesHtml = '';
                if (post.images && post.images.length > 0) {
                    imagesHtml = '<div class="mt-3 grid grid-cols-2 gap-2">';
                    post.images.forEach(img => {
                        imagesHtml += `<img src="${escapeHtml(img)}" class="rounded-lg w-full h-48 object-cover cursor-pointer" onclick="openImageModal('${escapeHtml(img)}')" onerror="handleImageError(this, ${post.p2p_mode})">`;
                    });
                    imagesHtml += '</div>';
                }

                return `
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6 hover:border-brand-accent/30 transition-all">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-brand-accent flex items-center justify-center text-white font-bold">
                                    ${post.username.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-brand-text-primary">${escapeHtml(post.full_name || post.username)}</span>
                                        ${modeBadge}
                                    </div>
                                    <span class="text-xs text-brand-text-secondary">@${escapeHtml(post.username)} • ${timeAgo}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <p class="text-brand-text-primary whitespace-pre-wrap">${escapeHtml(post.content)}</p>

                        <!-- Images -->
                        ${imagesHtml}

                        <!-- Actions -->
                        <div class="flex items-center gap-6 mt-4 pt-4 border-t border-brand-border">
                            <button class="flex items-center gap-2 text-brand-text-secondary hover:text-red-400 transition-colors" onclick="likePost(${post.id})">
                                <svg class="w-5 h-5 ${post.user_liked ? 'fill-current text-red-400' : ''}" fill="${post.user_liked ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                <span class="text-sm font-medium">${post.likes_count}</span>
                            </button>
                            <button class="flex items-center gap-2 text-brand-text-secondary hover:text-brand-accent transition-colors" onclick="openCommentsModal(${post.id})">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <span class="text-sm font-medium">${post.comments_count}</span>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('posts-list').innerHTML = html;
        }

        // Helper: Escape HTML para prevenir XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Helper: Manejar error de imagen (fallback IPFS)
        function handleImageError(img, isP2P) {
            if (isP2P && !img.dataset.fallbackAttempt) {
                // Intentar con gateway alternativo
                const originalSrc = img.src;
                const hash = originalSrc.split('/ipfs/')[1];

                if (hash) {
                    img.dataset.fallbackAttempt = '1';
                    img.src = `https://ipfs.io/ipfs/${hash}`;
                    console.warn('⚠️ Pinata gateway failed, trying ipfs.io for:', hash);
                }
            } else if (isP2P && img.dataset.fallbackAttempt === '1') {
                // Segundo fallback
                const originalSrc = img.src;
                const hash = originalSrc.split('/ipfs/')[1];

                if (hash) {
                    img.dataset.fallbackAttempt = '2';
                    img.src = `https://cloudflare-ipfs.com/ipfs/${hash}`;
                    console.warn('⚠️ ipfs.io gateway failed, trying cloudflare-ipfs for:', hash);
                }
            } else {
                // Imagen no disponible
                img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"%3E%3Crect fill="%23374151" width="200" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%239CA3AF" font-size="14"%3EImage not available%3C/text%3E%3C/svg%3E';
            }
        }

        // Helper: Abrir modal de imagen
        function openImageModal(src) {
            // Implementación simple
            window.open(src, '_blank');
        }

        // Helper: Like post
        async function likePost(postId) {
            try {
                const response = await fetch(`../api/posts/like.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ post_id: postId })
                });

                const data = await response.json();

                if (data.success) {
                    loadPosts(); // Recargar para actualizar contador
                }
            } catch (error) {
                console.error('Error liking post:', error);
            }
        }

        // Helper: Abrir modal de comentarios
        function openCommentsModal(postId) {
            alert('Comments modal - to be implemented');
        }

        // Create post
        async function createPost() {
            const content = document.getElementById('post-content').value.trim();

            if (!content) {
                alert('Please write something');
                return;
            }

            if (!currentGroupId) {
                alert('Please select a group');
                return;
            }

            // Deshabilitar botón de post
            const postButton = event.target;
            const originalText = postButton.innerHTML;
            postButton.disabled = true;

            try {
                // Verificar si P2P mode está activo
                const p2pMode = localStorage.getItem('p2pMode') === 'true';

                console.log('📝 Creando post en modo:', p2pMode ? 'P2P' : 'Centralizado');

                if (p2pMode && window.p2pClient && window.p2pClient.isConnected) {
                    // MODO P2P: Crear post P2P
                    console.log('🌐 Creando post P2P...');

                    // Mostrar loading
                    postButton.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Uploading to IPFS...';
                    showNotification('📤 Uploading to IPFS...', 'info');

                    // Crear post usando P2P Client
                    const postData = await window.p2pClient.createPost(content, []);

                    // Actualizar estado
                    postButton.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...';

                    // También guardarlo en MySQL para híbrido
                    const response = await fetch('../api/posts/create.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            group_id: currentGroupId,
                            content: content,
                            p2pMode: true,
                            ipfsHashes: postData.images || []
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        console.log('✅ Post P2P creado exitosamente');
                        showNotification('✅ Post created successfully (P2P mode)', 'success');
                        document.getElementById('post-content').value = '';
                        loadPosts();
                    } else {
                        throw new Error(data.message || 'Failed to create post');
                    }
                } else {
                    // MODO CENTRALIZADO: Crear post normal en MySQL
                    console.log('🗄️ Creando post centralizado...');

                    postButton.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Posting...';

                    const response = await fetch('../api/posts/create.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            group_id: currentGroupId,
                            content: content,
                            p2pMode: false
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        console.log('✅ Post centralizado creado exitosamente');
                        showNotification('✅ Post created successfully', 'success');
                        document.getElementById('post-content').value = '';
                        loadPosts();
                    } else {
                        throw new Error(data.message || 'Failed to create post');
                    }
                }
            } catch (error) {
                console.error('Error creating post:', error);
                showNotification('❌ Error: ' + error.message, 'error');
            } finally {
                // Restaurar botón
                postButton.disabled = false;
                postButton.innerHTML = originalText;
            }
        }

        // Helper: Mostrar notificación
        function showNotification(message, type = 'info') {
            const colors = {
                info: 'bg-blue-500',
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500'
            };

            const notification = document.createElement('div');
            notification.className = `fixed top-24 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-xl transform transition-all duration-300 translate-x-0 opacity-100`;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Auto-remover después de 3 segundos
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Toggle ad space (admin only)
        async function toggleAdSpace(side) {
            if (!isAdmin) return;

            try {
                const response = await fetch('../api/communities/toggle_ad.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        community_id: communityId,
                        side: side
                    })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error toggling ad:', error);
            }
        }

        // Open settings modal
        function openSettings() {
            alert('Settings modal - to be implemented');
        }

        // Open ad manager
        function openAdManager(side) {
            alert('Ad manager for ' + side + ' side - to be implemented');
        }

        // Open create group modal
        function openCreateGroupModal() {
            alert('Create group modal - to be implemented');
        }

        // Survey Modal Functions
        async function openSurveyModal() {
            // First check if user has required membership plan
            try {
                const response = await fetch('../api/get_current_plan.php');
                const data = await response.json();

                if (data.success) {
                    const plan = data.plan.type;
                    const allowedPlans = ['diamond', 'gold', 'creator'];

                    if (!allowedPlans.includes(plan)) {
                        alert('Sorry, you need Diamond, Gold, or Creator membership to create surveys.\n\nUpgrade your plan in Membership section.');
                        return;
                    }

                    // Show modal
                    document.getElementById('survey-modal').classList.remove('hidden');
                    document.getElementById('survey-modal').classList.add('flex');
                } else {
                    alert('Error checking your membership plan');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error verifying membership');
            }
        }

        function closeSurveyModal() {
            document.getElementById('survey-modal').classList.add('hidden');
            document.getElementById('survey-modal').classList.remove('flex');
            document.getElementById('survey-form').reset();
        }

        // Handle survey form submission
        document.getElementById('survey-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const title = document.getElementById('survey-title').value.trim();
            const description = document.getElementById('survey-description').value.trim();
            const price = parseFloat(document.getElementById('survey-price').value);
            const optionA = document.getElementById('survey-option-a').value.trim();
            const optionB = document.getElementById('survey-option-b').value.trim();
            const duration = parseInt(document.getElementById('survey-duration').value);

            if (!title || !price || !optionA || !optionB || !duration) {
                alert('Please fill all required fields');
                return;
            }

            // Disable submit button
            const btn = document.getElementById('create-survey-btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Processing...';

            try {
                // Usar wallet interna del usuario (no MetaMask)
                btn.innerHTML = 'Verificando saldo...';

                // Obtener saldo de SPHE del usuario
                const balanceResp = await fetch('/api/wallet/balances.php');
                const balanceData = await balanceResp.json();

                if (!balanceResp.ok || !balanceData.success) {
                    throw new Error('No se pudo obtener el saldo de tu wallet');
                }

                const spheBalance = parseFloat(balanceData.balances?.sphe?.formatted || '0');
                const depositAmount = 1000; // 1000 SPHE deposit

                if (spheBalance < depositAmount) {
                    throw new Error(`Saldo insuficiente. Necesitas al menos ${depositAmount} SPHE para el depósito (reembolsable al declarar ganador).\n\nTu saldo actual: ${spheBalance.toFixed(2)} SPHE`);
                }

                btn.innerHTML = 'Enviando depósito (sin gas)...';

                // Dirección de treasury
                const TREASURY_WALLET = '0xa1052872c755B5B2192b54ABD5F08546eeE6aa20';

                // Ejecutar acción gasless para transferir SPHE a treasury
                const gaslessResp = await fetch('/api/wallet/gasless_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        actionType: 'SURVEY_DEPOSIT',
                        amount: depositAmount.toString(),
                        recipientId: null, // Treasury no es un usuario
                        metadata: {
                            treasury: TREASURY_WALLET,
                            survey_title: title
                        }
                    })
                });

                const gaslessData = await gaslessResp.json();

                if (!gaslessResp.ok || !gaslessData.success) {
                    throw new Error(gaslessData.message || 'No se pudo procesar el depósito');
                }

                const txHash = gaslessData.data?.txHash || 'gasless_' + Date.now();

                btn.innerHTML = 'Creating survey...';

                // Create survey via API
                const createResponse = await fetch('../api/surveys/create.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        title: title,
                        description: description,
                        entry_price: price,
                        option_a: optionA,
                        option_b: optionB,
                        duration_hours: duration,
                        deposit_tx_hash: txHash,
                        wallet_address: userAccount,
                        community_id: communityId,
                        group_id: currentGroupId
                    })
                });

                const result = await createResponse.json();

                if (result.success) {
                    // Mostrar alerta de éxito con mejor UI
                    showAlert('Survey creado exitosamente! Tu depósito de ' + depositAmount + ' SPHE ha sido recibido.\n\nRecuerda: Declara el ganador dentro de 48 horas después del cierre para recuperar tu depósito + 10% de comisión!', 'success');
                    closeSurveyModal();
                    loadPosts(); // Reload posts to show new survey
                } else {
                    throw new Error(result.message || 'Failed to create survey');
                }

            } catch (error) {
                console.error('Error creating survey:', error);
                alert('Error: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });

        // Check user plan on page load and hide survey button if not eligible
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch('../api/get_current_plan.php');
                const data = await response.json();

                if (data.success) {
                    const plan = data.plan.type;
                    const allowedPlans = ['diamond', 'gold', 'creator'];

                    if (!allowedPlans.includes(plan)) {
                        // Hide survey button for free and platinum users
                        const surveyBtn = document.getElementById('survey-btn');
                        if (surveyBtn) {
                            surveyBtn.style.display = 'none';
                        }
                    }
                }
            } catch (error) {
                console.error('Error checking plan:', error);
            }
        });
    </script>
    <script src="../assets/js/toast-alerts.js"></script>
    
    <!-- P2P Client Scripts -->
    <?php include __DIR__ . '/../components/scripts.php'; ?>
</body>
</html>