<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Membresías - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Sistema de notificaciones -->
    <script src="/assets/js/notifications.js"></script>

    <!-- Sistema de pagos con Smart Wallet -->
    <script src="/assets/js/smart-wallet-payments.js"></script>
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
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
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
        .js-scroll-animation {
            opacity: 0;
        }
        html {
            scroll-behavior: smooth;
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0D1117;
        }
        ::-webkit-scrollbar-thumb {
            background: #30363D;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #3B82F6;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

    <!-- Main Content -->
    <main class="px-4">
        <!-- Hero Section -->
        <section class="text-center pt-40 pb-24 js-scroll-animation" style="background: radial-gradient(ellipse at top center, rgba(59, 130, 246, 0.1) 0%, #0D1117 70%);">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-5xl md:text-6xl font-bold mb-4 text-brand-text-primary">
                    Planes de <span class="text-brand-accent">Membresía</span>
                </h1>
                <p class="text-xl text-brand-text-secondary max-w-3xl mx-auto">
                    Desbloquea el poder completo de The Social Mask con nuestros planes premium. Desde comunicación prioritaria hasta herramientas de monetización avanzadas.
                </p>
            </div>
        </section>

        <!-- Plans Grid -->
        <section class="py-24 js-scroll-animation">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">

                    <!-- Free Plan -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8 flex flex-col">
                        <h3 class="font-bold text-2xl text-brand-text-secondary mb-2">Plan Gratuito</h3>
                        <p class="text-4xl font-bold text-brand-text-secondary mb-4">FREE</p>
                        <p class="text-brand-text-secondary text-sm mb-6">Funciones básicas</p>
                        <ul class="text-brand-text-secondary space-y-3 mb-8 flex-grow">
                            <li class="flex items-center">✓ Mensajes básicos</li>
                            <li class="flex items-center">✓ 5 minutos de espera entre mensajes</li>
                            <li class="flex items-center">✓ Perfil básico</li>
                        </ul>
                        <button class="w-full mt-auto bg-brand-border text-brand-text-secondary py-3 rounded-xl font-semibold cursor-not-allowed">
                            Plan Actual
                        </button>
                    </div>

                    <!-- Platinum Plan -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8 flex flex-col">
                        <h3 class="font-bold text-2xl text-brand-text-primary mb-2">Platinum</h3>
                        <p class="text-4xl font-bold text-brand-text-primary mb-2">100 SPHE</p>
                        <p class="text-xs text-brand-accent mb-4">💎 50 pago + 50 stake (30 días)</p>
                        <p class="text-brand-text-secondary text-sm mb-6">Para usuarios activos</p>
                        <ul class="text-brand-text-secondary space-y-3 mb-8 flex-grow">
                            <li class="flex items-center">✓ Nombre con color platinum</li>
                            <li class="flex items-center">✓ 40% menos tiempo de espera (3 min)</li>
                            <li class="flex items-center">✓ Encuestas normales</li>
                            <li class="flex items-center">✓ Monetización básica</li>
                            <li class="flex items-center">✓ +15% más tokens por actividad</li>
                        </ul>
                        <button onclick="purchasePlan('platinum', 100)" class="w-full mt-auto bg-brand-accent text-white font-bold py-3 px-8 rounded-xl text-lg transition-transform transform hover:scale-105">
                            Comprar Platinum
                        </button>
                    </div>

                    <!-- Gold Plan -->
                    <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8 flex flex-col">
                        <h3 class="font-bold text-2xl text-brand-text-primary mb-2">Gold</h3>
                        <p class="text-4xl font-bold text-brand-text-primary mb-2">250 SPHE</p>
                        <p class="text-xs text-brand-accent mb-4">💎 125 pago + 125 stake (30 días)</p>
                        <p class="text-brand-text-secondary text-sm mb-6">Para profesionales</p>
                        <ul class="text-brand-text-secondary space-y-3 mb-8 flex-grow">
                            <li class="flex items-center">✓ Nombre con color dorado</li>
                            <li class="flex items-center">✓ 60% menos tiempo de espera (2 min)</li>
                            <li class="flex items-center">✓ Patrocinadores avanzados</li>
                            <li class="flex items-center">✓ Campañas de donaciones</li>
                            <li class="flex items-center">✓ +30% más tokens por actividad</li>
                        </ul>
                        <button onclick="purchasePlan('gold', 250)" class="w-full mt-auto bg-brand-accent text-white font-bold py-3 px-8 rounded-xl text-lg transition-transform transform hover:scale-105">
                            Comprar Gold
                        </button>
                    </div>

                    <!-- Diamond Plan -->
                    <div class="bg-brand-bg-secondary border-2 border-brand-accent rounded-2xl p-8 flex flex-col relative shadow-2xl shadow-blue-500/20">
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-brand-accent text-white px-4 py-1 rounded-full text-sm font-bold">MÁS POPULAR</div>
                        <h3 class="font-bold text-2xl text-brand-accent mb-2 mt-4">Diamond</h3>
                        <p class="text-4xl font-bold text-brand-accent mb-2">500 SPHE</p>
                        <p class="text-xs text-white mb-4">💎 250 pago + 250 stake (30 días)</p>
                        <p class="text-brand-text-secondary text-sm mb-6">Para empresarios</p>
                        <ul class="text-brand-text-secondary space-y-3 mb-8 flex-grow">
                            <li class="flex items-center">✓ Nombre con color diamante</li>
                            <li class="flex items-center">✓ 90% menos tiempo de espera (30 seg)</li>
                            <li class="flex items-center">✓ Crypto-encuestas con premios</li>
                            <li class="flex items-center">✓ Monetización completa</li>
                            <li class="flex items-center">✓ +50% más tokens por actividad</li>
                        </ul>
                        <button onclick="purchasePlan('diamond', 500)" class="w-full mt-auto bg-brand-accent text-white font-bold py-3 px-8 rounded-xl text-lg transition-transform transform hover:scale-105">
                            Comprar Diamond
                        </button>
                    </div>
                </div>

                <!-- Content Creator Plan -->
                <div class="max-w-4xl mx-auto bg-brand-bg-secondary border-2 border-purple-500 rounded-2xl p-8 flex flex-col md:flex-row items-center gap-8 shadow-2xl shadow-purple-500/20">
                    <div class="flex-grow">
                        <h3 class="text-3xl font-bold text-purple-400 mb-2">Plan Content Creator</h3>
                        <p class="text-5xl font-bold text-purple-400 mb-2">750 SPHE</p>
                        <p class="text-sm text-purple-300 mb-4">💎 375 pago + 375 stake (30 días)</p>
                        <p class="text-brand-text-secondary mb-6">Para creadores de contenido profesionales que buscan monetizar y hacer crecer su comunidad.</p>
                        <ul class="text-brand-text-secondary space-y-2 mb-8">
                            <li>✓ Nombre verificado con badge especial</li>
                            <li>✓ Sin tiempo de espera (0 segundos)</li>
                            <li>✓ 10% de comisión por encuestas</li>
                            <li>✓ Monetización avanzada de comunidades</li>
                            <li>✓ Herramientas de análisis premium</li>
                            <li>✓ +75% más tokens por actividad</li>
                        </ul>
                    </div>
                    <div class="flex-shrink-0">
                        <button onclick="purchasePlan('creator', 750)" class="bg-purple-500 text-white font-bold py-4 px-10 rounded-xl text-xl transition-transform transform hover:scale-105 hover:bg-purple-600">
                            Convertirse en Creator
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits Comparison Table -->
        <section class="py-24 js-scroll-animation">
            <div class="max-w-7xl mx-auto text-center">
                <h2 class="text-4xl font-bold mb-8">Comparación de Beneficios</h2>
                <div class="bg-brand-bg-secondary rounded-2xl p-8 border border-brand-border">
                    <div class="grid grid-cols-5 gap-4 text-sm text-center">
                        <div class="font-bold text-left">Beneficio</div>
                        <div class="font-bold text-brand-text-secondary">Gratuito</div>
                        <div class="font-bold text-brand-text-primary">Platinum</div>
                        <div class="font-bold text-brand-text-primary">Gold</div>
                        <div class="font-bold text-brand-accent">Diamond</div>

                        <div class="font-semibold text-left border-t border-brand-border pt-4 mt-4">Tiempo de espera</div>
                        <div class="border-t border-brand-border pt-4 mt-4">5 min</div>
                        <div class="border-t border-brand-border pt-4 mt-4">3 min</div>
                        <div class="border-t border-brand-border pt-4 mt-4">2 min</div>
                        <div class="border-t border-brand-border pt-4 mt-4 text-brand-accent">30 seg</div>

                        <div class="font-semibold text-left border-t border-brand-border pt-4 mt-4">Encuestas</div>
                        <div class="border-t border-brand-border pt-4 mt-4">No</div>
                        <div class="border-t border-brand-border pt-4 mt-4">Sí (Normal)</div>
                        <div class="border-t border-brand-border pt-4 mt-4">Sí (Normal)</div>
                        <div class="border-t border-brand-border pt-4 mt-4 text-brand-accent">Sí (Crypto)</div>

                        <div class="font-semibold text-left border-t border-brand-border pt-4 mt-4">Monetización</div>
                        <div class="border-t border-brand-border pt-4 mt-4">No</div>
                        <div class="border-t border-brand-border pt-4 mt-4">Básica</div>
                        <div class="border-t border-brand-border pt-4 mt-4">Avanzada</div>
                        <div class="border-t border-brand-border pt-4 mt-4 text-brand-accent">Completa</div>

                        <div class="font-semibold text-left border-t border-brand-border pt-4 mt-4">Tokens extra</div>
                        <div class="border-t border-brand-border pt-4 mt-4">0%</div>
                        <div class="border-t border-brand-border pt-4 mt-4">+15%</div>
                        <div class="border-t border-brand-border pt-4 mt-4">+30%</div>
                        <div class="border-t border-brand-border pt-4 mt-4 text-brand-accent">+50%</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="py-16 text-center border-t border-brand-border js-scroll-animation">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-brand-text-secondary mb-4">
                Los planes se renuevan mensualmente. Puedes cancelar en cualquier momento.
            </p>
            <p class="text-brand-text-secondary">
                &copy; 2025 thesocialmask. Todos los derechos reservados.
            </p>
        </div>
    </footer>

    <script>
        // ============================================
        // SISTEMA DE PAGOS CON SMART WALLET
        // ============================================
        
        const PLAN_PRICES = {
            'free': 0,
            'platinum': 100,
            'gold': 250,
            'diamond': 500,
            'creator': 750
        };

        const PLAN_ORDER = ['free', 'platinum', 'gold', 'diamond', 'creator'];
        
        let currentPlan = null;

        /**
         * Comprar plan usando Smart Wallet (NO Metamask)
         */
        async function purchasePlan(planType, amount) {
            const isLoggedIn = await checkUserSession();
            if (!isLoggedIn) {
                if (typeof window.notify !== 'undefined') {
                    window.notify.warning('Debes iniciar sesión para comprar un plan de membresía', 'Autenticación Requerida', 5000);
                } else {
                    alert('Debes iniciar sesión para comprar un plan');
                }
                setTimeout(() => {
                    window.location.href = '/pages/login.php';
                }, 1500);
                return;
            }

            // Check if user already has this plan
            if (currentPlan && currentPlan.type === planType) {
                if (typeof window.notify !== 'undefined') {
                    window.notify.info(`Ya tienes el plan ${planType.toUpperCase()} activo`, 'Plan Actual', 4000);
                } else {
                    alert('Ya tienes este plan activo');
                }
                return;
            }

            // Calculate price difference for upgrades
            const currentPrice = currentPlan ? PLAN_PRICES[currentPlan.type] : 0;
            const newPrice = PLAN_PRICES[planType];
            const priceToPay = Math.max(0, newPrice - currentPrice);

            // Check if it's a downgrade
            const currentIndex = currentPlan ? PLAN_ORDER.indexOf(currentPlan.type) : 0;
            const newIndex = PLAN_ORDER.indexOf(planType);

            if (newIndex < currentIndex) {
                if (typeof window.notify !== 'undefined') {
                    window.notify.warning('No puedes hacer downgrade de tu plan. Contacta a soporte si deseas cambiar a un plan inferior.', 'Downgrade No Permitido', 6000);
                } else {
                    alert('No puedes hacer downgrade de tu plan. Contacta a soporte si deseas cambiar a un plan inferior.');
                }
                return;
            }

            // Show upgrade message if applicable
            if (currentPlan && currentPlan.type !== 'free' && priceToPay < newPrice) {
                const message = `Upgrade de ${currentPlan.name || currentPlan.type.toUpperCase()} a ${planType.toUpperCase()}\n\n` +
                    `Precio normal: ${newPrice} SPHE\n` +
                    `Ya pagaste: ${currentPrice} SPHE\n` +
                    `Solo pagarás: ${priceToPay} SPHE\n\n` +
                    `El pago se deducirá de tu Smart Wallet.`;

                const confirmUpgrade = await showConfirmDialog(
                    'Confirmar Upgrade',
                    message,
                    'Continuar con Upgrade',
                    'Cancelar'
                );
                if (!confirmUpgrade) return;
            } else if (priceToPay > 0) {
                const message = `Precio: ${priceToPay} SPHE\n\n` +
                    `El pago se deducirá de tu Smart Wallet.\n` +
                    `NO se abrirá Metamask.`;

                const confirmPurchase = await showConfirmDialog(
                    `Comprar Membresía ${planType.toUpperCase()}`,
                    message,
                    'Confirmar Compra',
                    'Cancelar'
                );
                if (!confirmPurchase) return;
            }

            try {
                const button = document.querySelector(`button[onclick*="purchasePlan('${planType}'"]`);
                showLoadingState(planType, 'Procesando pago con Smart Wallet...');

                // Usar el sistema de Smart Wallet
                const result = await window.SmartWalletPayments.processMembershipPayment(
                    planType,
                    priceToPay,
                    'SPHE'
                );

                if (result.success) {
                    showSuccessState(planType);
                    if (typeof window.notify !== 'undefined') {
                        window.notify.success(`Tu membresía ${planType.toUpperCase()} ha sido activada exitosamente. Disfruta de todos los beneficios premium.`, '¡Membresía Activada!', 5000);
                    } else {
                        alert(`✅ ¡Membresía ${planType.toUpperCase()} activada exitosamente!`);
                    }

                    // Recargar página para actualizar el plan
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    throw new Error(result.message || 'Error procesando pago');
                }

            } catch (error) {
                console.error('Error en purchasePlan:', error);
                let errorMsg = error.message || 'Error desconocido';

                if (errorMsg.includes('Balance insuficiente')) {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.insufficientFunds(PLAN_PRICES[planType], 'balance actual', 'SPHE');
                    } else {
                        alert('❌ Balance insuficiente. Por favor recarga tu Smart Wallet con SPHE en la sección Wallet.');
                    }
                } else {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.error(errorMsg, 'Error al Procesar Pago', 6000);
                    } else {
                        alert('❌ Error: ' + errorMsg);
                    }
                }

                showErrorState(planType);
            }
        }

        async function checkUserSession() {
            try {
                const response = await fetch('../api/check_session.php');
                const data = await response.json();
                return data.success;
            } catch (error) {
                return false;
            }
        }

        async function loadCurrentPlan() {
            try {
                const response = await fetch('../api/get_current_plan.php');
                const data = await response.json();

                if (data.success) {
                    currentPlan = data.plan;
                    updateUIWithCurrentPlan();
                }
            } catch (error) {
                console.error('Error loading current plan:', error);
            }
        }

        function updateUIWithCurrentPlan() {
            if (!currentPlan) return;

            const planType = currentPlan.type;

            // Update all buttons based on current plan
            document.querySelectorAll('button[onclick*="purchasePlan"]').forEach(button => {
                const buttonPlanType = button.getAttribute('onclick').match(/purchasePlan\('(\w+)'/)?.[1];

                if (buttonPlanType === planType) {
                    // Current plan
                    button.innerHTML = 'Plan Actual';
                    button.classList.remove('bg-brand-accent', 'bg-purple-500', 'hover:scale-105');
                    button.classList.add('bg-brand-border', 'text-brand-text-secondary', 'cursor-not-allowed');
                    button.disabled = true;
                } else {
                    const currentIndex = PLAN_ORDER.indexOf(planType);
                    const buttonIndex = PLAN_ORDER.indexOf(buttonPlanType);

                    if (buttonIndex < currentIndex) {
                        // Lower plan - show downgrade message
                        button.innerHTML = 'Downgrade (Contacta Soporte)';
                        button.classList.remove('bg-brand-accent', 'bg-purple-500', 'hover:scale-105');
                        button.classList.add('bg-gray-500', 'cursor-not-allowed');
                        button.disabled = true;
                    } else {
                        // Higher plan - show upgrade
                        const priceDiff = PLAN_PRICES[buttonPlanType] - PLAN_PRICES[planType];
                        button.innerHTML = `Upgrade (${priceDiff} SPHE)`;
                    }
                }
            });
        }

        function showLoadingState(planType, message) {
            const button = document.querySelector(`button[onclick*="purchasePlan('${planType}'"]`);
            if (button) {
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${message}`;
            }
        }

        function showSuccessState(planType) {
            const button = document.querySelector(`button[onclick*="purchasePlan('${planType}'"]`);
            if (button) {
                button.innerHTML = '<i class="fas fa-check mr-2"></i>Plan Activado';
                button.classList.remove('bg-brand-accent');
                button.classList.add('bg-green-500');
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        }

        function showErrorState(planType) {
            const button = document.querySelector(`button[onclick*="purchasePlan('${planType}'"]`);
            if (button) {
                button.disabled = false;
                const price = PLAN_PRICES[planType];
                button.innerHTML = `Comprar (${price} SPHE)`;
            }
        }

        /**
         * Modal de confirmación elegante
         */
        function showConfirmDialog(title, message, confirmText = 'Confirmar', cancelText = 'Cancelar') {
            return new Promise((resolve) => {
                // Crear modal
                const modal = document.createElement('div');
                modal.className = 'fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4';
                modal.innerHTML = `
                    <div class="bg-brand-bg-secondary border-2 border-brand-accent rounded-2xl max-w-md w-full p-8 animate-fade-in-up shadow-2xl">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-brand-accent/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-brand-text-primary mb-3">${title}</h3>
                            <p class="text-brand-text-secondary whitespace-pre-line leading-relaxed">${message}</p>
                        </div>
                        <div class="flex gap-3">
                            <button id="confirm-btn" class="flex-1 bg-brand-accent hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                                ${confirmText}
                            </button>
                            <button id="cancel-btn" class="flex-1 bg-brand-bg-primary border border-brand-border hover:bg-brand-border text-brand-text-primary font-semibold py-3 px-6 rounded-lg transition-all">
                                ${cancelText}
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);

                // Event listeners
                modal.querySelector('#confirm-btn').addEventListener('click', () => {
                    modal.remove();
                    resolve(true);
                });

                modal.querySelector('#cancel-btn').addEventListener('click', () => {
                    modal.remove();
                    resolve(false);
                });

                // Close on backdrop click
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.remove();
                        resolve(false);
                    }
                });
            });
        }

        // Cargar plan actual al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            loadCurrentPlan();

            // Observer para animaciones de scroll
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.js-scroll-animation').forEach(el => {
                el.style.opacity = '0';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
