<!-- ============================================
     thesocialmask UNIVERSAL NAVBAR - SIN FLASH/DELAY
     Navbar optimizada para cero delay visual
     ============================================ -->

<?php
// Usar rutas correctas para nginx (URLs limpias sin .php)
$login_url = '/login';
$register_url = '/register';
$token_url = '/token';
$membership_url = '/membership';
$contact_url = '/contact';
$communities_url = '/communities';
$dashboard_url = '/dashboard';
$messages_url = '/messages';
$learn_url = '/learn';
$devices_url = '/devices';
$governance_url = '/governance';
$alias_onboarding_url = '/pages/onboarding/alias';
$home_url = '/';
$profile_url = '/profile';
$wallet_url = '/wallet';
$receive_url = '/receive';
$api_logout_url = '/api/logout';

// IMPORTANTE: NO iniciar sesión aquí - connection.php lo hará con el nombre correcto
// Si iniciamos sesión antes de incluir connection.php, se creará con nombre incorrecto (PHPSESSID)

// Incluir conexión a DB - esto iniciará la sesión con el nombre correcto
if (!isset($pdo)) {
    $connection_path = __DIR__ . '/../config/connection.php';
    if (file_exists($connection_path)) {
        require_once $connection_path;
    } else {
        // Fallback: intentar desde la raíz
        $connection_path_alt = $_SERVER['DOCUMENT_ROOT'] . '/config/connection.php';
        if (file_exists($connection_path_alt)) {
            require_once $connection_path_alt;
        } else {
            error_log("ERROR: No se pudo encontrar connection.php desde navbar.php");

            // Si no podemos incluir connection.php, al menos iniciar sesión con el nombre correcto
            if (session_status() === PHP_SESSION_NONE) {
                session_name('thesocialmask_session');
                session_start();
            }
        }
    }
}

// Detectar si el usuario está autenticado
$is_logged_in = false;
$current_username = 'Profile';
$current_fullname = 'User';
$current_plan = 'free';

// DEBUG: Log de sesión (eliminar en producción)
if (defined('DEBUG') && DEBUG) {
    error_log("NAVBAR DEBUG - Session status: " . session_status());
    error_log("NAVBAR DEBUG - Session data: " . print_r($_SESSION, true));
}

// Verificar si hay sesión activa con datos válidos
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $is_logged_in = true;
    $current_username = $_SESSION['username'] ?? 'User';
    $current_fullname = $_SESSION['username'] ?? 'User';

    // Get user plan from database
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT membership_plan FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch();
            $current_plan = $user_data['membership_plan'] ?? 'free';
        } catch (Exception $e) {
            $current_plan = 'free';
        }
    }
}
?>

<style>
/* Mobile menu animations */
@keyframes slideInFromRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutToRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.mobile-menu-enter {
    animation: slideInFromRight 0.3s ease-out forwards;
}

.mobile-menu-exit {
    animation: slideOutToRight 0.3s ease-in forwards;
}

/* Backdrop fade animation */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.backdrop-enter {
    animation: fadeIn 0.3s ease-out forwards;
}

.backdrop-exit {
    animation: fadeOut 0.3s ease-in forwards;
}
</style>

<nav class="fixed w-full top-0 z-50 bg-transparent backdrop-blur-sm">
    <div class="max-w-7xl mx-auto px-4 md:px-6 py-3 md:py-6">
        <div class="flex justify-between items-center">
            <!-- Logo Section - Centrado verticalmente -->
            <div class="flex items-center">
                <a href="<?php echo $home_url; ?>" class="hover:opacity-80 transition-opacity flex items-center">
                    <span class="text-white font-bold text-base md:text-lg tracking-wider">SocialMask</span>
                </a>
            </div>

            <!-- Global Search (only when logged in) -->
            <?php if ($is_logged_in): ?>
            <div class="hidden lg:block">
                <?php include __DIR__ . '/global_search.php'; ?>
            </div>
            <?php endif; ?>

            <!-- Navigation Links - Centro -->
            <ul id="nav-links" class="hidden lg:flex items-center space-x-8 text-white/80 font-medium text-sm">
                <!-- Default links (always visible) -->
                <li>
                    <a href="<?php echo $token_url; ?>" class="hover:text-white transition-colors duration-300">
                        Token
                    </a>
                </li>
                <li>
                    <a href="<?php echo $learn_url; ?>" class="hover:text-white transition-colors duration-300">
                        Learn
                    </a>
                </li>
                <!-- Membership link (only when NOT logged in) -->
                <li id="membership-link" class="<?php echo $is_logged_in ? 'hidden' : 'block'; ?>">
                    <a href="<?php echo $membership_url; ?>" class="hover:text-white transition-colors duration-300">
                        Membership
                    </a>
                </li>
                <!-- Communities link (only when logged in) -->
                <li id="communities-link" class="<?php echo $is_logged_in ? 'block' : 'hidden'; ?>">
                    <a href="<?php echo $communities_url; ?>" class="hover:text-white transition-colors duration-300">
                        Communities
                    </a>
                </li>
                <!-- Governance link (only when logged in) -->
                <li id="governance-link" class="<?php echo $is_logged_in ? 'block' : 'hidden'; ?>">
                    <a href="<?php echo $governance_url; ?>" class="hover:text-white transition-colors duration-300">
                        Governance
                    </a>
                </li>
            </ul>

            <!-- Right Side Actions -->
            <div class="flex items-center gap-2 md:gap-4">
                <!-- Web3 Wallet Button (only when logged in on governance pages) -->
                <?php if ($is_logged_in && (strpos($_SERVER['REQUEST_URI'], 'governance') !== false || strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false)): ?>
                    <!-- Network Badge - Temporalmente deshabilitado -->
                    <!--<div class="hidden md:block">
                        <?php // include __DIR__ . '/network-badge.php'; ?>
                    </div>-->
                    <!-- Wallet Button - Temporalmente deshabilitado -->
                    <!--<div class="hidden md:block">
                        <?php // include __DIR__ . '/wallet-button.php'; ?>
                    </div>-->
                <?php endif; ?>
                
                <!-- P2P Toggle (visible en móvil y desktop cuando está logueado) -->
                <?php if ($is_logged_in): ?>
                    <div class="flex items-center">
                        <?php include __DIR__ . '/p2p-toggle.php'; ?>
                    </div>
                <?php endif; ?>

                <!-- Logged Out State - DESKTOP Y MÓVIL -->
                <div id="logged-out-actions" class="<?php echo $is_logged_in ? 'hidden' : 'flex'; ?> items-center gap-2 md:gap-3">
                    <!-- Login Button - Solo desktop -->
                    <a href="<?php echo $login_url; ?>" class="hidden md:block text-white/80 hover:text-white font-medium text-sm transition-colors duration-300">
                        Login
                    </a>
                    <!-- Register Button - Desktop y móvil -->
                    <a href="<?php echo $register_url; ?>" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-3 md:px-6 py-1.5 md:py-3 rounded-full text-white font-semibold text-xs md:text-sm transition-all duration-300 transform hover:scale-105 hover:shadow-lg hover:shadow-blue-500/25 whitespace-nowrap">
                        <span class="md:hidden">Unirse</span>
                        <span class="hidden md:inline">Register</span>
                    </a>
                </div>

                <!-- Logged In State - Usuario siempre visible -->
                <div id="logged-in-actions" class="<?php echo $is_logged_in ? 'flex' : 'hidden'; ?> items-center">
                    <!-- User Dropdown -->
                    <div class="relative">
                        <button id="user-menu-btn" class="flex items-center justify-center gap-1 md:gap-2 text-white hover:bg-white/10 font-medium text-sm transition-all duration-300 bg-white/5 px-2 md:px-4 py-1.5 md:py-2 rounded-lg border border-white/10 h-[34px] md:h-[40px]">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-white/70 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <div class="text-left hidden md:block">
                                <div class="flex items-center gap-2">
                                    <span id="fullname-display" class="text-white font-semibold"><?php echo htmlspecialchars($current_fullname); ?></span>
                                    <?php
                                    $plan_badges = [
                                        'platinum' => '<span class="text-xs px-1.5 py-0.5 rounded bg-gray-400/20 text-gray-300">Platinum</span>',
                                        'gold' => '<span class="text-xs px-1.5 py-0.5 rounded bg-yellow-500/20 text-yellow-400">Gold</span>',
                                        'diamond' => '<span class="text-xs px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400">Diamond</span>',
                                        'creator' => '<span class="text-xs px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-400">Creator</span>'
                                    ];

                                    if ($current_plan !== 'free' && isset($plan_badges[$current_plan])) {
                                        echo $plan_badges[$current_plan];
                                    }
                                    ?>
                                </div>
                                <span id="username-display" class="text-xs text-white/50">@<?php echo htmlspecialchars($current_username); ?></span>
                            </div>
                            <svg class="w-4 h-4 text-white/50 hidden md:block flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu - Mejorado para móvil con alineación perfecta -->
                        <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-64 bg-brand-bg-secondary border border-brand-border rounded-lg shadow-xl py-2 max-h-[80vh] overflow-y-auto">
                            <!-- User Info in Dropdown -->
                            <div class="px-4 py-3 border-b border-brand-border">
                                <p class="text-sm font-semibold text-brand-text-primary"><?php echo htmlspecialchars($current_fullname); ?></p>
                                <p class="text-xs text-brand-text-secondary">@<?php echo htmlspecialchars($current_username); ?></p>
                                <?php if ($current_plan !== 'free'): ?>
                                <p class="text-xs mt-1 text-brand-accent">Plan: <?php echo ucfirst($current_plan); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Sección: Mi Cuenta -->
                            <div class="py-2">
                                <div class="px-4 py-1 text-xs font-semibold text-brand-text-secondary uppercase tracking-wider">
                                    Mi Cuenta
                                </div>
                                <a href="<?php echo $dashboard_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <span>Dashboard</span>
                                </a>
                                <a href="<?php echo $profile_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span>Mi Perfil</span>
                                </a>
                                <a href="<?php echo $messages_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span>Mensajes</span>
                                </a>
                            </div>

                            <hr class="my-2 border-brand-border">

                            <!-- Sección: Navegación (solo móvil) -->
                            <div class="py-2 lg:hidden">
                                <div class="px-4 py-1 text-xs font-semibold text-brand-text-secondary uppercase tracking-wider">
                                    Páginas
                                </div>
                                <a href="<?php echo $token_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Token</span>
                                </a>
                                <a href="<?php echo $learn_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <span>Learn</span>
                                </a>
                                <a href="<?php echo $communities_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <span>Communities</span>
                                </a>
                                <a href="<?php echo $governance_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span>Governance</span>
                                </a>
                            </div>

                            <hr class="my-2 border-brand-border lg:hidden">

                            <!-- Sección: Configuración -->
                            <div class="py-2">
                                <div class="px-4 py-1 text-xs font-semibold text-brand-text-secondary uppercase tracking-wider">
                                    Configuración
                                </div>
                                <a href="<?php echo $devices_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-9 4h6m-7 4h8a2 2 0 002-2V6a2 2 0 00-2-2H9l-2 2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Mis dispositivos</span>
                                </a>
                                <a href="<?php echo $membership_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                    </svg>
                                    <span>Membresía</span>
                                </a>
                                <a href="<?php echo $contact_url; ?>" class="flex items-center px-4 py-2 text-sm text-brand-text-primary hover:bg-brand-bg-primary transition-colors">
                                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Contact</span>
                                </a>
                            </div>

                            <hr class="my-2 border-brand-border">

                            <button onclick="logout()" class="flex items-center w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-brand-bg-primary transition-colors">
                                <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                <span>Cerrar Sesión</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Navbar JavaScript -->
<script>
// Estado inicial desde PHP (100% confiable)
const isLoggedInFromPHP = <?php echo json_encode($is_logged_in); ?>;
const usernameFromPHP = <?php echo json_encode($current_username); ?>;

console.log('Navbar: Estado inicial =', isLoggedInFromPHP ? 'Logged In' : 'Logged Out');

document.addEventListener('DOMContentLoaded', function() {
    // Solo actualizar el username display si es necesario
    if (isLoggedInFromPHP) {
        const usernameDisplay = document.getElementById('username-display');
        const fullnameDisplay = document.getElementById('fullname-display');

        if (usernameDisplay && usernameFromPHP) {
            usernameDisplay.textContent = '@' + usernameFromPHP;
        }
        console.log('✅ Navbar: Usuario logueado detectado');
    }

    // ============================================
    // DESKTOP USER DROPDOWN
    // ============================================
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userDropdown = document.getElementById('user-dropdown');

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });

        // Prevent dropdown from closing when clicking inside
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // ============================================
    // SMOOTH SCROLLING
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
});

// ============================================
// LOGOUT FUNCTION
// ============================================
async function logout() {
    try {
        // Mostrar notificación de carga
        if (typeof window.notify !== 'undefined') {
            window.notify.info('Cerrando sesión...', 'Logout', 2000);
        }

        const response = await fetch('<?php echo $api_logout_url; ?>', {
            method: 'POST',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success) {
            if (typeof window.notify !== 'undefined') {
                window.notify.success('Has cerrado sesión correctamente', '¡Hasta pronto!', 3000);
            }
            setTimeout(() => {
                window.location.href = '<?php echo $home_url; ?>';
            }, 500);
        } else {
            if (typeof window.notify !== 'undefined') {
                window.notify.error(data.message || 'No se pudo cerrar la sesión', 'Error', 5000);
            } else {
                alert('Error al cerrar sesión');
            }
        }
    } catch (error) {
        console.error('Error logging out:', error);
        if (typeof window.notify !== 'undefined') {
            window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
        } else {
            alert('Error al cerrar sesión');
        }
    }
}
</script>
