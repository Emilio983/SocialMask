<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_name('thesocialmask_session');
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title id="page-title">Perfil - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
                        'platinum': '#E5E4E2',
                        'gold': '#FFD700',
                        'diamond': '#B9F2FF',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .badge-free { color: #8B949E; }
        .badge-platinum { color: #E5E4E2; text-shadow: 0 0 10px rgba(229, 228, 226, 0.5); }
        .badge-gold { color: #FFD700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
        .badge-diamond { color: #B9F2FF; text-shadow: 0 0 10px rgba(185, 242, 255, 0.5); }
        .badge-creator {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .cover-gradient {
            background: linear-gradient(180deg, transparent 0%, #0D1117 100%);
        }
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }
        .post-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        .modal {
            display: none;
        }
        .modal.active {
            display: flex;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

    <!-- Container Principal -->
    <div class="pt-40">
        <!-- Cover Image -->
        <div class="relative h-64 md:h-80 bg-brand-bg-secondary overflow-hidden">
            <img id="cover-image" src="" alt="Cover" class="w-full h-full object-cover" onerror="this.style.display='none'">
            <div class="absolute inset-0 cover-gradient"></div>

            <!-- Botón Editar Cover (solo si es dueño) -->
            <button id="edit-cover-btn" class="hidden absolute top-4 right-4 bg-brand-bg-secondary/80 backdrop-blur-sm px-4 py-2 rounded-lg border border-brand-border hover:bg-brand-bg-secondary transition">
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Cambiar Cover
            </button>
        </div>

        <!-- Profile Header -->
        <div class="max-w-6xl mx-auto px-4 -mt-20 relative z-10">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <!-- Profile Image -->
                <div class="flex items-end gap-4">
                    <div class="relative">
                        <img id="profile-image" src="https://ui-avatars.com/api/?name=User&size=128&background=3B82F6&color=fff" alt="Profile" class="w-32 h-32 rounded-full border-4 border-brand-bg-primary">
                        <button id="edit-profile-image-btn" class="hidden absolute bottom-0 right-0 bg-brand-accent p-2 rounded-full">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-2">
                        <h1 id="profile-username" class="text-3xl font-bold flex items-center gap-2">
                            <span id="username-text">@usuario</span>
                            <svg id="verified-badge" class="hidden w-6 h-6 text-brand-accent" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </h1>
                        <p id="membership-badge" class="text-sm font-semibold badge-free">Free Member</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div id="action-buttons" class="flex gap-2">
                    <!-- Botones para visitantes -->
                    <button id="follow-btn" class="hidden px-6 py-2 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                        Seguir
                    </button>
                    <button id="message-btn" class="hidden px-6 py-2 bg-brand-bg-secondary border border-brand-border rounded-lg font-semibold hover:bg-brand-bg-primary transition">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Mensaje
                    </button>

                    <!-- Botones para dueño -->
                    <button id="edit-profile-btn" class="hidden px-6 py-2 bg-brand-bg-secondary border border-brand-border rounded-lg font-semibold hover:bg-brand-bg-primary transition">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Editar Perfil
                    </button>
                </div>
            </div>

            <!-- Bio & Stats -->
            <div class="mt-6 grid md:grid-cols-3 gap-6">
                <!-- Left Column - Bio & Info -->
                <div class="md:col-span-2 space-y-4">
                    <!-- Bio -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                        <p id="profile-bio" class="text-brand-text-primary mb-4">No hay biografía aún.</p>

                        <!-- Info -->
                        <div id="profile-info" class="space-y-2 text-sm text-brand-text-secondary">
                            <!-- Se llenará con JS -->
                        </div>

                        <!-- Social Links -->
                        <div id="social-links" class="mt-4 flex flex-wrap gap-2">
                            <!-- Se llenará con JS -->
                        </div>
                    </div>

                    <!-- Monetization Toggle (solo dueño) -->
                    <div id="monetization-card" class="hidden bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold">Monetización</h3>
                                <p class="text-sm text-brand-text-secondary">Activa anuncios para ganar tokens SPHE</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="ads-toggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-accent"></div>
                            </label>
                        </div>
                        <div id="monetization-stats" class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-brand-text-secondary">Vistas de Ads</p>
                                <p id="ad-views" class="text-xl font-bold">0</p>
                            </div>
                            <div>
                                <p class="text-brand-text-secondary">Clicks</p>
                                <p id="ad-clicks" class="text-xl font-bold">0</p>
                            </div>
                            <div>
                                <p class="text-brand-text-secondary">Ganancia</p>
                                <p id="ad-earnings" class="text-xl font-bold text-brand-accent">0 SPHE</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Stats -->
                <div class="space-y-4">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="stat-card bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold" id="stat-posts">0</p>
                            <p class="text-sm text-brand-text-secondary">Posts</p>
                        </div>
                        <div class="stat-card bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold" id="stat-followers">0</p>
                            <p class="text-sm text-brand-text-secondary">Seguidores</p>
                        </div>
                        <div class="stat-card bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold" id="stat-following">0</p>
                            <p class="text-sm text-brand-text-secondary">Siguiendo</p>
                        </div>
                        <div class="stat-card bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-brand-accent" id="stat-sphe">0</p>
                            <p class="text-sm text-brand-text-secondary">SPHE</p>
                        </div>
                    </div>

                    <!-- Views -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4">
                        <p class="text-sm text-brand-text-secondary mb-2">Vistas de Perfil</p>
                        <p class="text-3xl font-bold" id="stat-views">0</p>
                        <p class="text-xs text-brand-text-secondary mt-1">
                            <span id="stat-views-today">0</span> hoy
                        </p>
                    </div>

                    <!-- Member Since -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                        <p class="text-sm text-brand-text-secondary mb-2">Miembro desde</p>
                        <p id="member-since" class="font-semibold">-</p>
                    </div>
                </div>
            </div>

            <!-- Posts Section -->
            <div class="mt-8">
                <h2 class="text-2xl font-bold mb-6">Posts</h2>
                <div id="posts-container" class="post-grid">
                    <!-- Se llenará con JS -->
                </div>
                <div id="no-posts" class="hidden text-center py-12 text-brand-text-secondary">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-lg">No hay posts todavía</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Perfil -->
    <div id="edit-profile-modal" class="modal fixed inset-0 bg-black/80 backdrop-blur-sm items-center justify-center z-50 p-4">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-brand-bg-secondary border-b border-brand-border p-6 flex justify-between items-center">
                <h2 class="text-2xl font-bold">Editar Perfil</h2>
                <button onclick="closeEditModal()" class="text-brand-text-secondary hover:text-brand-text-primary">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-6">
                <!-- Bio -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Biografía</label>
                    <textarea id="edit-bio" rows="4" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg p-3 text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent" placeholder="Cuéntanos sobre ti..."></textarea>
                </div>

                <!-- Location -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Ubicación</label>
                    <input type="text" id="edit-location" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg p-3 text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent" placeholder="Ciudad, País">
                </div>

                <!-- Website -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Sitio Web</label>
                    <input type="url" id="edit-website" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg p-3 text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent" placeholder="https://tusitio.com">
                </div>

                <!-- Social Handles -->
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Twitter</label>
                        <input type="text" id="edit-twitter" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg p-3 text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent" placeholder="@usuario">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Discord</label>
                        <input type="text" id="edit-discord" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg p-3 text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent" placeholder="usuario#1234">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Telegram</label>
                        <input type="text" id="edit-telegram" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg p-3 text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent" placeholder="@usuario">
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-4">
                    <button onclick="saveProfile()" class="flex-1 bg-brand-accent text-white py-3 rounded-lg font-semibold hover:bg-blue-600 transition">
                        Guardar Cambios
                    </button>
                    <button onclick="closeEditModal()" class="px-6 bg-brand-bg-primary border border-brand-border py-3 rounded-lg font-semibold hover:bg-brand-bg-secondary transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let profileData = null;
    let isOwnProfile = false;

    // Obtener username de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const username = urlParams.get('username') || urlParams.get('user');

    async function loadProfile() {
        try {
            const url = username
                ? `/api/get_profile.php?username=${encodeURIComponent(username)}`
                : '/api/get_profile.php?user_id=<?php echo $_SESSION['user_id'] ?? ''; ?>';

            const response = await fetch(url);
            const data = await response.json();

            if (!data.success) {
                showError(data.message);
                return;
            }

            profileData = data;
            isOwnProfile = data.viewer_context.is_own_profile;

            renderProfile(data);
        } catch (error) {
            console.error('Error loading profile:', error);
            showError('Error al cargar el perfil');
        }
    }

    function renderProfile(data) {
        const profile = data.profile;

        // Título de página
        document.getElementById('page-title').textContent = `@${profile.username} - The Social Mask`;

        // Imágenes
        if (profile.cover_image) {
            document.getElementById('cover-image').src = profile.cover_image;
            document.getElementById('cover-image').style.display = 'block';
        }
        if (profile.profile_image) {
            document.getElementById('profile-image').src = profile.profile_image;
        }

        // Username y badge
        document.getElementById('username-text').textContent = `@${profile.username}`;

        if (profile.membership.is_verified) {
            document.getElementById('verified-badge').classList.remove('hidden');
        }

        // Membership badge con color
        const membershipBadge = document.getElementById('membership-badge');
        const plan = profile.membership.plan;
        membershipBadge.textContent = plan.charAt(0).toUpperCase() + plan.slice(1) + ' Member';
        membershipBadge.className = `text-sm font-semibold badge-${plan}`;

        // Bio
        if (profile.bio) {
            document.getElementById('profile-bio').textContent = profile.bio;
        }

        // Info
        const infoContainer = document.getElementById('profile-info');
        let infoHTML = '';

        if (profile.location) {
            infoHTML += `<p><svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>${profile.location}</p>`;
        }

        if (profile.website) {
            infoHTML += `<p><svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg><a href="${profile.website}" target="_blank" class="text-brand-accent hover:underline">${profile.website}</a></p>`;
        }

        infoContainer.innerHTML = infoHTML;

        // Social links
        if (data.social_links && data.social_links.length > 0) {
            const linksHTML = data.social_links.map(link => `
                <a href="${link.url}" target="_blank" class="px-4 py-2 bg-brand-bg-primary border border-brand-border rounded-lg hover:bg-brand-bg-secondary transition text-sm">
                    ${link.label}
                </a>
            `).join('');
            document.getElementById('social-links').innerHTML = linksHTML;
        }

        // Stats
        document.getElementById('stat-posts').textContent = profile.stats.total_posts || 0;
        document.getElementById('stat-followers').textContent = profile.stats.followers || 0;
        document.getElementById('stat-following').textContent = profile.stats.following || 0;
        document.getElementById('stat-sphe').textContent = parseFloat(profile.stats.sphe_balance || 0).toFixed(2);
        document.getElementById('stat-views').textContent = profile.stats.total_views || 0;
        document.getElementById('stat-views-today').textContent = profile.stats.views_today || 0;

        // Member since
        const memberSince = new Date(profile.member_since);
        document.getElementById('member-since').textContent = memberSince.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

        // Action buttons
        if (isOwnProfile) {
            document.getElementById('edit-profile-btn').classList.remove('hidden');
            document.getElementById('edit-cover-btn').classList.remove('hidden');
            document.getElementById('edit-profile-image-btn').classList.remove('hidden');
            document.getElementById('monetization-card').classList.remove('hidden');

            // Monetization data
            if (data.monetization) {
                document.getElementById('ads-toggle').checked = data.monetization.ads_enabled;
                document.getElementById('ad-views').textContent = data.monetization.total_ad_views || 0;
                document.getElementById('ad-clicks').textContent = data.monetization.total_ad_clicks || 0;
                document.getElementById('ad-earnings').textContent = parseFloat(data.monetization.total_ad_earnings || 0).toFixed(2) + ' SPHE';
            }
        } else {
            document.getElementById('follow-btn').classList.remove('hidden');
            document.getElementById('message-btn').classList.remove('hidden');

            const followBtn = document.getElementById('follow-btn');
            if (data.viewer_context.is_following) {
                followBtn.textContent = 'Siguiendo';
                followBtn.classList.remove('bg-brand-accent');
                followBtn.classList.add('bg-brand-bg-secondary', 'border', 'border-brand-border');
            }

            if (data.viewer_context.is_blocked) {
                document.getElementById('message-btn').classList.add('hidden');
                followBtn.classList.add('hidden');
            }
        }

        // Posts
        renderPosts(data.posts);
    }

    function renderPosts(posts) {
        const container = document.getElementById('posts-container');
        const noPosts = document.getElementById('no-posts');

        if (!posts || posts.length === 0) {
            noPosts.classList.remove('hidden');
            return;
        }

        container.innerHTML = posts.map(post => `
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg overflow-hidden hover:border-brand-accent/50 transition cursor-pointer" onclick="window.location.href='/pages/community_view?slug=${post.community_slug}'">
                ${post.media_urls ? `<img src="${JSON.parse(post.media_urls)[0]}" class="w-full h-48 object-cover">` : ''}
                <div class="p-4">
                    <h3 class="font-semibold mb-2 line-clamp-2">${post.title}</h3>
                    <p class="text-sm text-brand-text-secondary mb-3 line-clamp-3">${post.content}</p>
                    <div class="flex items-center justify-between text-xs text-brand-text-secondary">
                        <span class="flex items-center gap-2">
                            <img src="${post.community_logo || 'https://via.placeholder.com/20'}" class="w-5 h-5 rounded-full">
                            ${post.community_name}
                        </span>
                        <span>${post.upvotes || 0} ⬆️</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Follow/Unfollow
    document.getElementById('follow-btn')?.addEventListener('click', async function() {
        if (!profileData) return;

        const isFollowing = this.textContent.trim() === 'Siguiendo';
        const action = isFollowing ? 'unfollow' : 'follow';

        try {
            const response = await fetch('/api/follow_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: profileData.profile.user_id,
                    action: action
                })
            });

            const data = await response.json();

            if (data.success) {
                if (action === 'follow') {
                    this.textContent = 'Siguiendo';
                    this.classList.remove('bg-brand-accent');
                    this.classList.add('bg-brand-bg-secondary', 'border', 'border-brand-border');
                } else {
                    this.textContent = 'Seguir';
                    this.classList.add('bg-brand-accent');
                    this.classList.remove('bg-brand-bg-secondary', 'border', 'border-brand-border');
                }

                document.getElementById('stat-followers').textContent = data.followers_count;
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });

    // Message button
    document.getElementById('message-btn')?.addEventListener('click', function() {
        window.location.href = `/messages?user_id=${profileData.profile.user_id}`;
    });

    // Edit Profile Modal
    document.getElementById('edit-profile-btn')?.addEventListener('click', function() {
        if (!profileData) return;

        document.getElementById('edit-bio').value = profileData.profile.bio || '';
        document.getElementById('edit-location').value = profileData.profile.location || '';
        document.getElementById('edit-website').value = profileData.profile.website || '';
        document.getElementById('edit-twitter').value = profileData.profile.social_handles.twitter || '';
        document.getElementById('edit-discord').value = profileData.profile.social_handles.discord || '';
        document.getElementById('edit-telegram').value = profileData.profile.social_handles.telegram || '';

        document.getElementById('edit-profile-modal').classList.add('active');
    });

    function closeEditModal() {
        document.getElementById('edit-profile-modal').classList.remove('active');
    }

    async function saveProfile() {
        try {
            const response = await fetch('/api/update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_basic',
                    bio: document.getElementById('edit-bio').value,
                    location: document.getElementById('edit-location').value,
                    website: document.getElementById('edit-website').value,
                    twitter_handle: document.getElementById('edit-twitter').value,
                    discord_handle: document.getElementById('edit-discord').value,
                    telegram_handle: document.getElementById('edit-telegram').value
                })
            });

            const data = await response.json();

            if (data.success) {
                closeEditModal();
                loadProfile(); // Recargar perfil
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al guardar el perfil');
        }
    }

    // Toggle Monetization
    document.getElementById('ads-toggle')?.addEventListener('change', async function() {
        try {
            const response = await fetch('/api/toggle_monetization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ads_enabled: this.checked
                })
            });

            const data = await response.json();

            if (!data.success) {
                this.checked = !this.checked; // Revertir
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            this.checked = !this.checked;
        }
    });

    function showError(message) {
        document.body.innerHTML = `
            <div class="flex items-center justify-center min-h-screen bg-brand-bg-primary text-brand-text-primary">
                <div class="text-center">
                    <h1 class="text-4xl font-bold mb-4">Error</h1>
                    <p class="text-brand-text-secondary mb-6">${message}</p>
                    <a href="/" class="px-6 py-3 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                        Volver al inicio
                    </a>
                </div>
            </div>
        `;
    }

    // Load profile on page load
    loadProfile();
    </script>
    <script src="../assets/js/toast-alerts.js"></script>
    
    <!-- P2P Client Scripts -->
    <?php include __DIR__ . '/../components/scripts.php'; ?>
</body>
</html>
