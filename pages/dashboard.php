<?php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../api/utils.php';

if (!isAuthenticated()) {
    header('Location: /pages/login.php');
    exit;
}

// Obtener datos del usuario y su smart account
$stmt = $pdo->prepare('
    SELECT u.username, u.sphe_balance, sa.smart_account_address
    FROM users u
    LEFT JOIN smart_accounts sa ON u.user_id = sa.user_id
    WHERE u.user_id = ?
    LIMIT 1
');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$smartAccount = $user['smart_account_address'] ?? null;
$spheBalanceDb = $user['sphe_balance'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - thesocialmask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
                        'brand-success': '#28A745',
                        'brand-error': '#DC3545',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'inter', sans-serif; }
        .status-info { background-color: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); color: #3B82F6; }
        .status-success { background-color: rgba(40, 167, 69, 0.1); border-color: rgba(40, 167, 69, 0.3); color: #28a745; }
        .status-error { background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.3); color: #dc3545; }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 pt-24 md:pt-32 pb-24 space-y-8" id="dashboard-root">
        <!-- Header -->
        <header class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div>
                <p class="text-brand-text-secondary text-sm uppercase tracking-wide">Bienvenido de vuelta</p>
                <h1 class="text-3xl md:text-4xl font-bold mt-2 text-brand-text-primary">
                    <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <p class="text-brand-text-secondary mt-2 text-sm">
                    Gestiona tus activos, transacciones y explora la plataforma
                </p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl px-4 py-3 text-xs">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-brand-text-secondary">Smart Account:</span>
                    <span id="smart-account-display" class="text-brand-accent font-mono text-[10px]">
                        <?php echo $smartAccount ? substr($smartAccount, 0, 6) . '...' . substr($smartAccount, -4) : 'Pendiente'; ?>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-brand-text-secondary">SPHE (DB):</span>
                    <span class="text-brand-text-primary font-semibold"><?php echo number_format((float)$spheBalanceDb, 2); ?></span>
                </div>
            </div>
        </header>

        <!-- Quick Links -->
        <section class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <a href="/wallet" class="flex flex-col items-center justify-center p-4 bg-brand-bg-secondary border border-brand-border rounded-xl hover:border-brand-accent transition-all group">
                <svg class="w-8 h-8 text-brand-text-secondary group-hover:text-brand-accent transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <span class="text-xs text-brand-text-secondary group-hover:text-brand-text-primary transition-colors font-medium">Wallet</span>
            </a>

            <a href="/receive" class="flex flex-col items-center justify-center p-4 bg-brand-bg-secondary border border-brand-border rounded-xl hover:border-brand-accent transition-all group">
                <svg class="w-8 h-8 text-brand-text-secondary group-hover:text-brand-accent transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="text-xs text-brand-text-secondary group-hover:text-brand-text-primary transition-colors font-medium">Recibir</span>
            </a>

            <a href="/messages" class="flex flex-col items-center justify-center p-4 bg-brand-bg-secondary border border-brand-border rounded-xl hover:border-brand-accent transition-all group">
                <svg class="w-8 h-8 text-brand-text-secondary group-hover:text-brand-accent transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <span class="text-xs text-brand-text-secondary group-hover:text-brand-text-primary transition-colors font-medium">Mensajes</span>
            </a>

            <a href="/communities" class="flex flex-col items-center justify-center p-4 bg-brand-bg-secondary border border-brand-border rounded-xl hover:border-brand-accent transition-all group">
                <svg class="w-8 h-8 text-brand-text-secondary group-hover:text-brand-accent transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="text-xs text-brand-text-secondary group-hover:text-brand-text-primary transition-colors font-medium">Comunidades</span>
            </a>

            <a href="/governance" class="flex flex-col items-center justify-center p-4 bg-brand-bg-secondary border border-brand-border rounded-xl hover:border-brand-accent transition-all group">
                <svg class="w-8 h-8 text-brand-text-secondary group-hover:text-brand-accent transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="text-xs text-brand-text-secondary group-hover:text-brand-text-primary transition-colors font-medium">Governance</span>
            </a>

            <a href="/devices" class="flex flex-col items-center justify-center p-4 bg-brand-bg-secondary border border-brand-border rounded-xl hover:border-brand-accent transition-all group">
                <svg class="w-8 h-8 text-brand-text-secondary group-hover:text-brand-accent transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <span class="text-xs text-brand-text-secondary group-hover:text-brand-text-primary transition-colors font-medium">Dispositivos</span>
            </a>
        </section>

        <!-- Balances Cards -->
        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3" id="balance-cards">
            <article class="bg-brand-bg-secondary border border-brand-border rounded-xl p-5">
                <header class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-brand-text-primary">SPHE Disponible</h2>
                    <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </header>
                <p class="text-2xl font-bold text-brand-text-primary mb-1" id="sphe-balance-display">
                    <span class="inline-block w-16 h-8 bg-brand-border animate-pulse rounded"></span>
                </p>
                <p class="text-xs text-brand-text-secondary">Balance on-chain de tu smart account</p>
            </article>

            <article class="bg-brand-bg-secondary border border-brand-border rounded-xl p-5">
                <header class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-brand-text-primary">USDT Disponible</h2>
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </header>
                <p class="text-2xl font-bold text-brand-text-primary mb-1" id="usdt-balance-display">
                    <span class="inline-block w-16 h-8 bg-brand-border animate-pulse rounded"></span>
                </p>
                <p class="text-xs text-brand-text-secondary">Puede convertirse automáticamente a SPHE</p>
            </article>

            <article class="bg-brand-bg-secondary border border-brand-border rounded-xl p-5">
                <header class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-brand-text-primary">Acciones Rápidas</h2>
                    <button onclick="window.reloadBalances && window.reloadBalances()" class="text-brand-accent hover:text-brand-accent/80 transition" title="Recargar balances">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </header>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button class="bg-brand-accent/10 border border-brand-accent/30 text-brand-accent font-semibold rounded-lg py-2 hover:bg-brand-accent/20 transition" data-action="send">
                        Enviar
                    </button>
                    <button class="bg-brand-accent/10 border border-brand-accent/30 text-brand-accent font-semibold rounded-lg py-2 hover:bg-brand-accent/20 transition" onclick="window.location.href='/receive'">
                        Recibir
                    </button>
                    <button class="bg-brand-accent/10 border border-brand-accent/30 text-brand-accent font-semibold rounded-lg py-2 hover:bg-brand-accent/20 transition" onclick="window.location.href='/swap'">
                        Swap
                    </button>
                    <button class="bg-brand-accent/10 border border-brand-accent/30 text-brand-accent font-semibold rounded-lg py-2 hover:bg-brand-accent/20 transition" onclick="window.location.href='/wallet'">
                        Ver Wallet
                    </button>
                </div>
            </article>
        </section>

        <!-- Activity Section -->
        <section class="bg-brand-bg-secondary border border-brand-border rounded-xl p-5">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-brand-text-primary">Actividad Reciente</h2>
                <a href="/wallet" class="text-xs text-brand-accent hover:underline">Ver todo</a>
            </header>
            <div id="recent-activity" class="text-brand-text-secondary text-sm">
                <div class="flex items-center justify-center py-8">
                    <div class="text-center">
                        <svg class="w-12 h-12 text-brand-text-secondary mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-sm">No hay transacciones aún</p>
                        <p class="text-xs text-brand-text-secondary mt-1">Tus transacciones aparecerán aquí</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Info Cards -->
        <section class="grid gap-4 md:grid-cols-2">
            <div class="bg-gradient-to-br from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-xl p-5">
                <div class="flex items-start gap-4">
                    <div class="bg-blue-500/20 rounded-lg p-3">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-brand-text-primary mb-1">Gas Patrocinado</h3>
                        <p class="text-xs text-brand-text-secondary">Todas tus transacciones SPHE son gratuitas. SocialMask paga los fees de gas por ti.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500/10 to-emerald-500/10 border border-green-500/20 rounded-xl p-5">
                <div class="flex items-start gap-4">
                    <div class="bg-green-500/20 rounded-lg p-3">
                        <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-brand-text-primary mb-1">Seguridad Total</h3>
                        <p class="text-xs text-brand-text-secondary">Tu wallet está protegida con passkeys. Sin seed phrases que perder.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        window.__thesocialmask_DASHBOARD__ = <?php echo json_encode([
            'username' => $user['username'],
            'smartAccount' => $smartAccount,
        ]); ?>;
    </script>
    <script src="../assets/js/smart-account-init.js"></script>
    <script src="../assets/js/dashboard-wallet.js"></script>
    <script src="../assets/js/toast-alerts.js"></script>

    <!-- Gun.js P2P Scripts -->
    <?php include __DIR__ . '/../components/scripts.php'; ?>
</body>
</html>
