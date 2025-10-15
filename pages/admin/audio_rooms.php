<?php
/**
 * ADMIN: AUDIO ROOMS MANAGEMENT
 * Gestión de salas de audio - moderar, cerrar
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

// Placeholder data (audio rooms feature not yet implemented)
$rooms = [];
$stats = [
    'total_rooms' => 0,
    'active_rooms' => 0,
    'participants' => 0
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Rooms - Admin - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
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
                    <h1 class="text-3xl font-bold mb-2">Audio Rooms Management</h1>
                    <p class="text-brand-text-secondary">Manage and moderate audio rooms</p>
                </div>
                <a href="/pages/admin/dashboard" class="text-brand-accent hover:underline">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6 text-center">
                <p class="text-brand-text-secondary text-sm mb-2">Total Rooms</p>
                <p class="text-4xl font-bold"><?php echo $stats['total_rooms']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6 text-center">
                <p class="text-brand-text-secondary text-sm mb-2">Active Now</p>
                <p class="text-4xl font-bold text-green-500"><?php echo $stats['active_rooms']; ?></p>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6 text-center">
                <p class="text-brand-text-secondary text-sm mb-2">Total Participants</p>
                <p class="text-4xl font-bold text-purple-500"><?php echo $stats['participants']; ?></p>
            </div>
        </div>

        <!-- Coming Soon Message -->
        <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-12 text-center">
            <svg class="w-24 h-24 mx-auto mb-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
            </svg>
            <h2 class="text-3xl font-bold mb-4">Audio Rooms Coming Soon!</h2>
            <p class="text-brand-text-secondary text-lg max-w-2xl mx-auto">
                La funcionalidad de Audio Rooms está en desarrollo. Pronto podrás gestionar salas de audio en vivo,
                moderar conversaciones y monitorear la actividad en tiempo real.
            </p>
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-3xl mx-auto">
                <div class="p-4 bg-brand-bg-primary rounded-lg">
                    <p class="text-2xl mb-2">🎙️</p>
                    <p class="font-semibold mb-1">Live Moderation</p>
                    <p class="text-sm text-brand-text-secondary">Control de calidad en tiempo real</p>
                </div>
                <div class="p-4 bg-brand-bg-primary rounded-lg">
                    <p class="text-2xl mb-2">📊</p>
                    <p class="font-semibold mb-1">Analytics</p>
                    <p class="text-sm text-brand-text-secondary">Estadísticas detalladas de uso</p>
                </div>
                <div class="p-4 bg-brand-bg-primary rounded-lg">
                    <p class="text-2xl mb-2">🔒</p>
                    <p class="font-semibold mb-1">Safety Tools</p>
                    <p class="text-sm text-brand-text-secondary">Herramientas de seguridad</p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
