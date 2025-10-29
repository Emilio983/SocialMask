<?php
// ============================================
// COMMUNITIES LISTING PAGE
// ============================================
require_once __DIR__ . '/../config/connection.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Communities - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet">
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
        .community-card:hover { transform: translateY(-4px); }
        .community-card { transition: all 0.3s ease; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .filter-btn {
            background: #0D1117;
            color: #8B949E;
        }
        .filter-btn.active {
            background: #3B82F6;
            color: white;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="pt-40 pb-12 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8">
                <div>
                    <h1 class="text-4xl md:text-5xl font-bold mb-2">Communities</h1>
                    <p class="text-brand-text-secondary text-lg">Discover, join, and create amazing communities</p>
                </div>
                <button onclick="openCreateModal()" class="bg-brand-accent hover:bg-blue-600 text-white font-semibold px-8 py-3 rounded-xl transition-all transform hover:scale-105 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Community
                </button>
            </div>

            <!-- Search and Filters -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6 mb-8">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input
                            type="text"
                            id="search-input"
                            placeholder="Search communities..."
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                            onkeyup="searchCommunities()"
                        >
                    </div>
                    <div class="flex gap-2">
                        <button onclick="filterCommunities('all')" id="filter-all" class="filter-btn active px-6 py-3 rounded-lg font-medium transition-colors">
                            All
                        </button>
                        <button onclick="filterCommunities('sponsored')" id="filter-sponsored" class="filter-btn px-6 py-3 rounded-lg font-medium transition-colors">
                            Sponsored
                        </button>
                        <button onclick="filterCommunities('my')" id="filter-my" class="filter-btn px-6 py-3 rounded-lg font-medium transition-colors">
                            My Communities
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Communities Grid -->
    <section class="pb-24 px-6">
        <div class="max-w-7xl mx-auto">
            <!-- Loading State -->
            <div id="loading-state" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-brand-accent border-t-transparent"></div>
                <p class="mt-4 text-brand-text-secondary">Loading communities...</p>
            </div>

            <!-- Communities Grid -->
            <div id="communities-grid" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Community cards will be inserted here via JavaScript -->
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="hidden text-center py-12">
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-12">
                    <svg class="w-16 h-16 mx-auto mb-4 text-brand-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <h3 class="text-xl font-bold mb-2">No communities found</h3>
                    <p class="text-brand-text-secondary mb-6">Be the first to create one!</p>
                    <button onclick="openCreateModal()" class="bg-brand-accent hover:bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                        Create Community
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Community Modal -->
    <div id="create-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-6">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold">Create Community</h2>
                    <button onclick="closeCreateModal()" class="text-brand-text-secondary hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="create-community-form" onsubmit="createCommunity(event)">
                    <div class="space-y-6">
                        <!-- Community Name -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Community Name *</label>
                            <input
                                type="text"
                                id="community-name"
                                required
                                maxlength="100"
                                class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none"
                                placeholder="e.g., Crypto Developers"
                            >
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Description *</label>
                            <textarea
                                id="community-description"
                                required
                                rows="4"
                                maxlength="500"
                                class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none resize-none"
                                placeholder="Describe your community..."
                            ></textarea>
                            <p class="text-xs text-brand-text-secondary mt-1">Max 500 characters</p>
                        </div>

                        <!-- Logo Upload -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Logo (max 5MB)</label>
                            <div class="flex items-center gap-4">
                                <div id="logo-preview" class="hidden w-20 h-20 rounded-full border-2 border-brand-border overflow-hidden">
                                    <img id="logo-preview-img" src="" alt="Logo preview" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1">
                                    <input
                                        type="file"
                                        id="community-logo"
                                        accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                                        class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-accent file:text-white hover:file:bg-blue-600"
                                        onchange="previewImage('logo')"
                                    >
                                    <p class="text-xs text-brand-text-secondary mt-1">PNG, JPG, GIF, or WebP. Recommended: 200x200px</p>
                                </div>
                            </div>
                        </div>

                        <!-- Banner Upload -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Banner (max 5MB)</label>
                            <div id="banner-preview" class="hidden mb-3 rounded-lg border-2 border-brand-border overflow-hidden">
                                <img id="banner-preview-img" src="" alt="Banner preview" class="w-full h-32 object-cover">
                            </div>
                            <input
                                type="file"
                                id="community-banner"
                                accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                                class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:border-brand-accent outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-accent file:text-white hover:file:bg-blue-600"
                                onchange="previewImage('banner')"
                            >
                            <p class="text-xs text-brand-text-secondary mt-1">PNG, JPG, GIF, or WebP. Recommended: 1200x400px</p>
                        </div>

                        <!-- Cost Information -->
                        <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="bg-brand-accent/20 p-3 rounded-lg">
                                    <svg class="w-6 h-6 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold">Creation Cost</h3>
                                    <p class="text-2xl font-bold text-brand-accent">2,000 SPHE</p>
                                </div>
                            </div>
                            <p class="text-sm text-brand-text-secondary">
                                By creating a community, you'll automatically become the owner and first member. A default "General" group will be created for discussions.
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-3">
                            <button
                                type="button"
                                onclick="closeCreateModal()"
                                class="flex-1 bg-brand-bg-primary border border-brand-border hover:bg-brand-bg-secondary text-brand-text-primary font-semibold px-6 py-3 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                id="create-btn"
                                class="flex-1 bg-brand-accent hover:bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors"
                            >
                                Pay 2,000 SPHE & Create
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="py-16 text-center border-t border-brand-border">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-brand-text-secondary">&copy; 2025 The Social Mask. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Global state
        let allCommunities = [];
        let currentFilter = 'all';
        let currentUserId = <?php echo json_encode($current_user_id); ?>;

        // Load communities on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCommunities();
        });

        // Load communities from API
        async function loadCommunities() {
            try {
                const response = await fetch('../api/communities/list.php');
                const data = await response.json();

                if (data.success) {
                    allCommunities = data.communities || [];
                    renderCommunities();
                } else {
                    showEmptyState();
                }
            } catch (error) {
                console.error('Error loading communities:', error);
                showEmptyState();
            }
        }

        // Render communities
        function renderCommunities() {
            const grid = document.getElementById('communities-grid');
            const loading = document.getElementById('loading-state');
            const empty = document.getElementById('empty-state');

            loading.classList.add('hidden');

            // Filter communities based on current filter
            let filtered = allCommunities;
            if (currentFilter === 'sponsored') {
                filtered = allCommunities.filter(c => c.is_sponsored);
            } else if (currentFilter === 'my') {
                filtered = allCommunities.filter(c => c.is_member);
            }

            // Apply search filter
            const search = document.getElementById('search-input').value.toLowerCase();
            if (search) {
                filtered = filtered.filter(c =>
                    c.name.toLowerCase().includes(search) ||
                    (c.description && c.description.toLowerCase().includes(search))
                );
            }

            if (filtered.length === 0) {
                grid.classList.add('hidden');
                empty.classList.remove('hidden');
                return;
            }

            empty.classList.add('hidden');
            grid.classList.remove('hidden');
            grid.innerHTML = filtered.map(community => createCommunityCard(community)).join('');
        }

        // Create community card HTML
        function createCommunityCard(community) {
            const defaultLogo = 'https://via.placeholder.com/80/3B82F6/FFFFFF?text=' + community.name.charAt(0);
            const defaultBanner = 'https://via.placeholder.com/400x150/161B22/8B949E?text=Community+Banner';

            // Handle both relative paths (/uploads/...) and full URLs
            const logoUrl = community.logo_url ? (community.logo_url.startsWith('http') ? community.logo_url : '..' + community.logo_url) : defaultLogo;
            const bannerUrl = community.banner_url ? (community.banner_url.startsWith('http') ? community.banner_url : '..' + community.banner_url) : defaultBanner;

            return `
                <div class="community-card bg-brand-bg-secondary border border-brand-border rounded-xl overflow-hidden hover:border-brand-accent cursor-pointer"
                     onclick="viewCommunity(${community.id})">
                    <!-- Banner -->
                    <div class="relative">
                        <img src="${bannerUrl}"
                             alt="${community.name}"
                             class="w-full h-32 object-cover"
                             onerror="this.src='${defaultBanner}'">
                        ${community.is_sponsored ? `
                            <span class="absolute top-2 right-2 bg-brand-accent text-white px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                Sponsor
                            </span>
                        ` : ''}
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <!-- Logo + Name -->
                        <div class="flex items-center gap-4 mb-4">
                            <img src="${logoUrl}"
                                 alt="${community.name}"
                                 class="w-20 h-20 rounded-full border-2 border-brand-border object-cover"
                                 onerror="this.src='${defaultLogo}'">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xl font-bold truncate">${community.name}</h3>
                                <p class="text-sm text-brand-text-secondary">
                                    ${community.member_count || 0} members • ${community.post_count || 0} posts
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-brand-text-secondary text-sm mb-4 line-clamp-2">
                            ${community.description || 'No description available'}
                        </p>

                        <!-- Action Button -->
                        ${community.is_member ? `
                            <button onclick="event.stopPropagation(); viewCommunity(${community.id})"
                                    class="w-full bg-brand-bg-primary border border-brand-border hover:border-brand-accent text-white font-semibold py-2 rounded-lg transition-colors">
                                View Community
                            </button>
                        ` : `
                            <button onclick="event.stopPropagation(); joinCommunity(${community.id})"
                                    class="w-full bg-brand-accent hover:bg-blue-600 text-white font-semibold py-2 rounded-lg transition-colors">
                                Join Community
                            </button>
                        `}
                    </div>
                </div>
            `;
        }

        // Show empty state
        function showEmptyState() {
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('communities-grid').classList.add('hidden');
            document.getElementById('empty-state').classList.remove('hidden');
        }

        // Filter communities
        function filterCommunities(filter) {
            currentFilter = filter;

            // Update button styles
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById('filter-' + filter).classList.add('active');
            renderCommunities();
        }

        // Search communities
        function searchCommunities() {
            renderCommunities();
        }

        // Open create modal
        function openCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
        }

        // Close create modal
        function closeCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
            document.getElementById('create-community-form').reset();
            // Hide previews
            document.getElementById('logo-preview').classList.add('hidden');
            document.getElementById('banner-preview').classList.add('hidden');
        }

        // Preview uploaded image
        function previewImage(type) {
            const input = document.getElementById('community-' + type);
            const preview = document.getElementById(type + '-preview');
            const previewImg = document.getElementById(type + '-preview-img');

            if (input.files && input.files[0]) {
                const file = input.files[0];

                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds 5MB. Please choose a smaller file.');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }

                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Invalid file type. Please upload PNG, JPG, GIF, or WebP.');
                    input.value = '';
                    preview.classList.add('hidden');
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('hidden');
            }
        }

        // Create community
        async function createCommunity(event) {
            event.preventDefault();

            const name = document.getElementById('community-name').value;
            const description = document.getElementById('community-description').value;
            const logoInput = document.getElementById('community-logo');
            const bannerInput = document.getElementById('community-banner');
            const btn = document.getElementById('create-btn');

            // Disable button
            btn.disabled = true;
            btn.innerHTML = 'Uploading images...';

            try {
                let logoPath = null;
                let bannerPath = null;

                // Upload logo if provided
                if (logoInput.files && logoInput.files[0]) {
                    const logoFormData = new FormData();
                    logoFormData.append('file', logoInput.files[0]);
                    logoFormData.append('upload_type', 'logo');

                    const logoResponse = await fetch('../api/upload/community_image.php', {
                        method: 'POST',
                        body: logoFormData
                    });

                    const logoData = await logoResponse.json();
                    if (logoData.success) {
                        logoPath = logoData.upload.file_path;
                    } else {
                        throw new Error('Logo upload failed: ' + logoData.message);
                    }
                }

                // Upload banner if provided
                if (bannerInput.files && bannerInput.files[0]) {
                    const bannerFormData = new FormData();
                    bannerFormData.append('file', bannerInput.files[0]);
                    bannerFormData.append('upload_type', 'banner');

                    const bannerResponse = await fetch('../api/upload/community_image.php', {
                        method: 'POST',
                        body: bannerFormData
                    });

                    const bannerData = await bannerResponse.json();
                    if (bannerData.success) {
                        bannerPath = bannerData.upload.file_path;
                    } else {
                        throw new Error('Banner upload failed: ' + bannerData.message);
                    }
                }

                btn.innerHTML = 'Procesando pago con tu Smart Wallet...';

                // Primero crear la comunidad en la base de datos
                const createResponse = await fetch('../api/communities/create.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        name: name,
                        description: description,
                        logo_url: logoPath,
                        banner_url: bannerPath,
                        payment_pending: true
                    })
                });

                const createData = await createResponse.json();

                if (!createData.success) {
                    throw new Error(createData.message || 'Error al crear comunidad');
                }

                const communityId = createData.community_id;

                // Procesar pago con Smart Wallet (100 SPHE)
                const paymentResponse = await fetch('../api/payments/process_payment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        type: 'GROUP_CREATION',
                        amount: 100,
                        token: 'SPHE',
                        metadata: {
                            group_id: communityId,
                            group_name: name
                        }
                    })
                });

                const paymentData = await paymentResponse.json();

                if (!paymentData.success) {
                    // Si el pago falla, eliminar la comunidad creada
                    await fetch('../api/communities/delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ community_id: communityId })
                    });
                    throw new Error(paymentData.message || 'Error al procesar el pago');
                }

                btn.innerHTML = '✅ ¡Comunidad creada!';
                setTimeout(() => {
                    alert('Comunidad creada exitosamente!');
                    closeCreateModal();
                    loadCommunities();
                }, 500);
            } catch (error) {
                console.error('Error creating community:', error);
                alert('Error: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Pay 2,000 SPHE & Create';
            }
        }

        // Join community
        async function joinCommunity(communityId) {
            if (!confirm('Join this community?')) return;

            try {
                const response = await fetch(`../api/communities/join.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ community_id: communityId })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Joined successfully!');
                    loadCommunities();
                } else {
                    alert('Error: ' + (data.message || 'Failed to join community'));
                }
            } catch (error) {
                console.error('Error joining community:', error);
                alert('Error joining community. Please try again.');
            }
        }

        // View community
        function viewCommunity(communityId) {
            window.location.href = `community_view.php?id=${communityId}`;
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateModal();
            }
        });
    </script>
    <script src="../assets/js/toast-alerts.js"></script>
    
    <!-- P2P Client Scripts -->
    <?php include __DIR__ . '/../components/scripts.php'; ?>
</body>
</html>