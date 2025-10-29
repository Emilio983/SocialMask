<?php
require_once __DIR__ . '/../../config/connection.php';

if (!isAuthenticated()) {
    header('Location: /pages/login.php');
    exit;
}

$userStmt = $pdo->prepare('SELECT username, alias FROM users WHERE user_id = ? LIMIT 1');
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();
$currentAlias = $user['alias'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Configura tu Alias - thesocialmask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="../../assets/css/responsive.css" rel="stylesheet">
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
        .status-success { background-color: rgba(40, 167, 69, 0.1); border-color: rgba(40, 167, 69, 0.3); color: #28a745; }
        .status-error { background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.3); color: #dc3545; }
        .status-info { background-color: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); color: #3B82F6; }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <div class="min-h-screen flex items-center justify-center px-4 py-16">
        <div class="w-full max-w-lg">
            <div class="text-center mb-10">
                <h1 class="text-4xl font-bold mb-3">Elige tu Alias</h1>
                <p class="text-brand-text-secondary">
                    Tu alias es público y no debe contener información personal. Puedes cambiarlo más adelante desde tu perfil.
                </p>
            </div>

            <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8">
                <div id="status-display" class="hidden mb-4 p-3 rounded-xl border">
                    <div class="flex items-center space-x-3">
                        <div id="status-icon" class="w-4 h-4 rounded-full"></div>
                        <span id="status-text" class="font-medium text-sm"></span>
                    </div>
                </div>

                <form id="alias-form" class="space-y-6">
                    <div>
                        <label for="alias-input" class="block text-sm font-medium text-brand-text-secondary mb-2">
                            Alias (3-30 caracteres, letras, números, punto, guion y guion bajo)
                        </label>
                        <input
                            id="alias-input"
                            type="text"
                            name="alias"
                            maxlength="30"
                            required
                            value="<?php echo htmlspecialchars($currentAlias ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-brand-text-primary focus:outline-none focus:ring-2 focus:ring-brand-accent"
                        >
                    </div>

                    <button
                        id="alias-submit"
                        type="submit"
                        class="w-full bg-brand-accent text-white py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity disabled:opacity-40"
                    >
                        Guardar alias
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.__thesocialmask_ALIAS__ = <?php echo json_encode($currentAlias ?? ''); ?>;
    </script>
    <script src="../../assets/js/onboarding-alias.js"></script>
</body>
</html>
