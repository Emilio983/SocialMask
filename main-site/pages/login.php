<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Iniciar Sesión - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
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
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'spin': 'spin 1s linear infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        spin: {
                            'from': { transform: 'rotate(0deg)' },
                            'to': { transform: 'rotate(360deg)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'inter', sans-serif;
        }
        .status-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        .status-error {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        .status-info {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
            color: #3B82F6;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

    <div class="min-h-screen flex items-center justify-center px-4 py-12 md:py-16 js-scroll-animation">
        <div class="w-full max-w-lg">
            <!-- Header - Optimizado para móvil -->
            <div class="text-center mb-6 md:mb-8">
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-2 md:mb-3 text-brand-text-primary">
                    Bienvenido a <span class="text-brand-accent">The Social Mask</span>
                </h1>
                <p class="text-brand-text-secondary text-sm md:text-base">Inicia sesión con tu passkey</p>
            </div>

            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl md:rounded-2xl p-4 sm:p-6 md:p-8">
                <!-- Status Display -->
                <div id="status-display" class="hidden mb-4 p-3 rounded-xl border">
                    <div class="flex items-center space-x-3">
                        <div id="status-icon" class="w-4 h-4 rounded-full"></div>
                        <span id="status-text" class="font-medium text-sm"></span>
                    </div>
                </div>

                <!-- Wallet Info - Más compacto -->
                <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-3 md:p-4 mb-4">
                    <div class="flex items-start space-x-2 md:space-x-3">
                        <div class="w-4 h-4 md:w-5 md:h-5 bg-brand-accent/20 rounded-full flex-shrink-0 mt-0.5 flex items-center justify-center">
                            <svg class="w-2.5 h-2.5 md:w-3 md:h-3 text-brand-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-brand-text-primary font-medium text-xs md:text-sm mb-0.5">Wallet invisible</h3>
                            <p class="text-brand-text-secondary text-xs leading-tight">Sin fees, sin email, sin teléfono</p>
                        </div>
                    </div>
                </div>

                <!-- 2FA Notice - Más compacto y claro -->
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-3 md:p-4 mb-4 md:mb-6">
                    <div class="flex items-start space-x-2 md:space-x-3">
                        <div class="w-4 h-4 md:w-5 md:h-5 bg-blue-500/20 rounded-full flex-shrink-0 mt-0.5 flex items-center justify-center">
                            <svg class="w-2.5 h-2.5 md:w-3 md:h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-blue-400 font-medium text-xs md:text-sm mb-0.5">¿Nuevo dispositivo?</h3>
                            <p class="text-brand-text-secondary text-xs leading-tight">
                                Si es tu primer login aquí, se abrirá un modal para ingresar el <strong class="text-blue-400">código de 6 dígitos</strong> del panel de Dispositivos
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Login Button -->
                <button id="passkey-login-btn" class="bg-brand-accent w-full py-3 md:py-3.5 rounded-lg text-white text-sm md:text-base font-semibold transition-opacity hover:opacity-90 disabled:opacity-40">
                    Iniciar sesión con Passkey
                </button>

                <!-- Alternative: Generate Code Button -->
                <div class="mt-3 text-center">
                    <p class="text-brand-text-secondary text-xs mb-2">¿No tienes acceso a tu passkey?</p>
                    <button id="generate-code-btn" class="w-full py-2.5 md:py-3 rounded-lg border-2 border-brand-accent text-brand-accent text-sm md:text-base font-semibold hover:bg-brand-accent/10 transition-all">
                        Generar Código de 6 Dígitos
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 md:mt-6">
                <p class="text-brand-text-secondary text-xs">
                    Al autenticarte aceptas los <a href="#" class="text-brand-accent hover:underline">Términos</a> y <a href="#" class="text-brand-accent hover:underline">Privacidad</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Notification System -->
    <script src="/assets/js/notifications.js"></script>

    <!-- Opcional: incluir aquí el script de Web3Auth que defina window.obtainWeb3AuthToken({ challengeId, credential }) -->
    <script src="../assets/js/toast-alerts.js?v=1760221868"></script>
    <script src="/assets/js/2fa-login.js"></script>
    <script src="/assets/js/login-code-generator.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/auth-passkeys-v4.js?v=1760221868"></script>
</body>
</html>
