<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tokenomics - The Social Mask</title>
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

        /* Additional mobile-specific fixes */
        @media (max-width: 767px) {
            /* Uniswap iframe responsive */
            #comprar .grid {
                padding: 1rem;
            }

            #comprar iframe {
                height: 500px !important;
                border-radius: 1rem;
            }

            /* Button text - prevent wrapping */
            a.inline-block {
                font-size: 0.875rem;
                padding: 0.75rem 1.5rem;
                white-space: normal; /* Allow wrapping if needed */
                line-height: 1.3;
                text-align: center;
            }

            /* Hero section */
            section h1 {
                font-size: 2rem;
                line-height: 1.2;
            }

            /* Stepper on mobile - make vertical */
            .flex.flex-col.md\:flex-row > div {
                margin-bottom: 2rem;
            }
        }

        @media (max-width: 374px) {
            #comprar iframe {
                height: 450px !important;
            }
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
                    <span class="text-brand-accent">SPHE</span>: El Token que Potencia Comunidades
                </h1>
                <p class="text-xl text-brand-text-secondary max-w-3xl mx-auto">
                    Descubre una economía digital donde tu voz, tus creaciones y tu participación tienen un valor real y tangible.
                </p>
            </div>
        </section>

        <!-- Horizontal Stepper Section -->
        <section class="py-24 js-scroll-animation">
            <div class="max-w-7xl mx-auto px-4">
                <div class="text-center mb-20">
                    <h2 class="text-5xl font-bold">El Ciclo de Valor de SPHE</h2>
                    <p class="text-xl text-brand-text-secondary mt-4 max-w-2xl mx-auto">Un flujo constante de creación, participación y recompensa.</p>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center space-y-8 md:space-y-0 md:space-x-4">

                    <!-- Step 1 -->
                    <div class="flex-1 text-center js-scroll-animation" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-center">
                            <div class="w-12 h-12 flex items-center justify-center bg-brand-bg-secondary border-2 border-brand-accent rounded-full text-brand-accent font-bold text-xl">1</div>
                            <div class="flex-grow h-0.5 bg-brand-border hidden md:block"></div>
                        </div>
                        <h3 class="mt-4 font-bold text-2xl">Adquiere</h3>
                        <p class="mt-2 text-brand-text-secondary">Compra SPHE en Uniswap para obtener la llave de acceso al ecosistema.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex-1 text-center js-scroll-animation" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-center">
                            <div class="flex-grow h-0.5 bg-brand-border hidden md:block"></div>
                            <div class="w-12 h-12 flex items-center justify-center bg-brand-bg-secondary border-2 border-brand-accent rounded-full text-brand-accent font-bold text-xl">2</div>
                            <div class="flex-grow h-0.5 bg-brand-border hidden md:block"></div>
                        </div>
                        <h3 class="mt-4 font-bold text-2xl">Participa</h3>
                        <p class="mt-2 text-brand-text-secondary">Usa tus tokens para votar en la gobernanza, enviar mensajes prioritarios y crear encuestas.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex-1 text-center js-scroll-animation" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-center">
                            <div class="flex-grow h-0.5 bg-brand-border hidden md:block"></div>
                            <div class="w-12 h-12 flex items-center justify-center bg-brand-bg-secondary border-2 border-brand-accent rounded-full text-brand-accent font-bold text-xl">3</div>
                            <div class="flex-grow h-0.5 bg-brand-border hidden md:block"></div>
                        </div>
                        <h3 class="mt-4 font-bold text-2xl">Crea y Monetiza</h3>
                        <p class="mt-2 text-brand-text-secondary">Ofrece suscripciones, promociona contenido y recibe pagos directos por tu creatividad.</p>
                    </div>

                    <!-- Step 4 -->
                    <div class="flex-1 text-center js-scroll-animation" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-center">
                            <div class="flex-grow h-0.5 bg-brand-border hidden md:block"></div>
                            <div class="w-12 h-12 flex items-center justify-center bg-brand-bg-secondary border-2 border-brand-accent rounded-full text-brand-accent font-bold text-xl">4</div>
                        </div>
                        <h3 class="mt-4 font-bold text-2xl">Gana</h3>
                        <p class="mt-2 text-brand-text-secondary">Obtén rendimientos a través de staking y recibe recompensas por tu contribución a la red.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- CTA Button Section -->
        <section class="text-center py-12 js-scroll-animation">
            <a href="#comprar" class="inline-block bg-brand-accent text-white font-bold py-4 px-10 rounded-xl text-xl transition-transform transform hover:scale-105">
                Comprar SPHE
            </a>
        </section>

        <!-- Uniswap Section -->
        <section id="comprar" class="py-24 bg-brand-bg-secondary border-t border-b border-brand-border js-scroll-animation">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <!-- Left Column: Text -->
                    <div>
                        <h2 class="text-5xl font-bold mb-4">Adquiere SPHE Hoy</h2>
                        <p class="text-xl text-brand-text-secondary mb-8">
                            Únete al ecosistema de The Social Mask comprando tus tokens SPHE de forma segura en Uniswap. Conecta tu wallet, intercambia USDT por SPHE y empieza a construir el futuro de las redes sociales.
                        </p>
                        <a href="https://app.uniswap.org/swap?chain=polygon&inputCurrency=0xc2132D05D31c914a87C6611C10748AEb04B58e8F&outputCurrency=0x059cf53146e980321e7e1eef43bb5fe51bb6565b" target="_blank" rel="noopener noreferrer" class="inline-block bg-gray-700 text-white font-bold py-3 px-8 rounded-xl text-lg transition-transform transform hover:scale-105 hover:bg-gray-600">
                            Ir a Uniswap Directamente
                        </a>
                    </div>

                    <!-- Right Column: Iframe -->
                    <div class="bg-brand-bg-primary rounded-2xl p-2 border border-brand-border shadow-2xl">
                        <iframe
                            src="https://app.uniswap.org/swap?chain=polygon&inputCurrency=0xc2132D05D31c914a87C6611C10748AEb04B58e8F&outputCurrency=0x059cf53146e980321e7e1eef43bb5fe51bb6565b"
                            height="620px"
                            width="100%"
                            style="border: 0; margin: 0 auto; display: block; border-radius: 1rem;"
                        ></iframe>
                    </div>
                </div>
            </div>
        </section>

        <!-- Detailed Benefits Section -->
        <section class="py-24 js-scroll-animation">
            <div class="max-w-7xl mx-auto text-center">
                <h2 class="text-5xl font-bold mb-16">Más Allá de un Token</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center text-left">
                    <div class="js-scroll-animation" style="animation-delay: 0.1s;">
                        <h3 class="text-3xl font-bold text-brand-accent mb-4">Seguridad y Descentralización</h3>
                        <p class="text-xl text-brand-text-secondary">Construido sobre una infraestructura blockchain robusta, SPHE garantiza que tus activos y tu identidad estén protegidos. Al eliminar intermediarios, te devolvemos el control total sobre tus interacciones y tu valor digital.</p>
                    </div>
                    <div class="js-scroll-animation" style="animation-delay: 0.2s;">
                        <h3 class="text-3xl font-bold text-brand-accent mb-4">Transparencia y Confianza</h3>
                        <p class="text-xl text-brand-text-secondary">Cada transacción y decisión de gobernanza se registra en la blockchain, creando un ecosistema transparente y auditable. La confianza ya no es una promesa, es una garantía matemática.</p>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <footer class="py-16 text-center js-scroll-animation border-t border-brand-border">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-brand-text-secondary">&copy; 2025 The Social Mask. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.2
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