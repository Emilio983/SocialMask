<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>The Social Mask - Crea, Conecta, Gana</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/responsive.css" rel="stylesheet">
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
        /* La clase para la animación se añade vía JS, los elementos son visibles por defecto */
        .js-scroll-animation {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include 'components/navbar.php'; ?>

    <!-- Hero Section -->
    <section
        class="min-h-screen flex items-center justify-center text-center px-4 pt-40 js-scroll-animation"
        style="background: radial-gradient(ellipse at top center, rgba(59, 130, 246, 0.1) 0%, #0D1117 70%);"
    >
        <div>
            <h1 class="font-bold text-6xl mb-4">Crea, Conecta, Gana.</h1>
            <p class="text-xl text-brand-text-secondary max-w-2xl mx-auto mb-8">
                La plataforma social descentralizada donde tus interacciones y comunidades tienen valor real. Impulsado por el token SPHE.
            </p>
            <div class="flex items-center justify-center space-x-4">
                <a href="/register" class="bg-brand-accent text-white font-bold py-3 px-8 rounded-xl text-lg transition-transform transform hover:scale-105">
                    Únete a la Comunidad
                </a>
                <a href="#descubre" class="border border-brand-border text-brand-text-primary font-bold py-3 px-8 rounded-xl text-lg transition-colors hover:border-brand-accent hover:text-brand-accent">
                    Descubre Más
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-12 border-t border-b border-brand-border js-scroll-animation">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                <div>
                    <p class="text-4xl font-bold text-brand-text-primary">$50M</p>
                    <p class="text-brand-text-secondary">SPHE Tokens Distribuidos</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-brand-text-primary">500K+</p>
                    <p class="text-brand-text-secondary">Miembros Activos</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-brand-text-primary">15K+</p>
                    <p class="text-brand-text-secondary">Comunidades Creadas</p>
                </div>
            </div>
        </div>
    </section>

    <!-- "What is The Social Mask?" Section -->
    <section id="descubre" class="py-24 js-scroll-animation">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <h2 class="font-bold text-5xl mb-12">¿Qué es The Social Mask?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8 text-left transition-all duration-300 hover:border-brand-accent">
                    <h3 class="text-2xl font-bold text-brand-text-primary mb-4">Crea y Modera</h3>
                    <p class="text-xl text-brand-text-secondary">
                        Construye tus propias comunidades con reglas personalizadas, membresías y sistemas de recompensas únicos.
                    </p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8 text-left transition-all duration-300 hover:border-brand-accent">
                    <h3 class="text-2xl font-bold text-brand-text-primary mb-4">Gana por Interactuar</h3>
                    <p class="text-xl text-brand-text-secondary">
                        Tu actividad social tiene valor. Recibe tokens SPHE por tus contribuciones y participación en la red.
                    </p>
                </div>
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8 text-left transition-all duration-300 hover:border-brand-accent">
                    <h3 class="text-2xl font-bold text-brand-text-primary mb-4">Gobernanza Real</h3>
                    <p class="text-xl text-brand-text-secondary">
                        Participa en las decisiones clave de la plataforma y moldea el futuro del ecosistema con tus tokens.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- SPHE Token Economics Section -->
    <section class="py-24 bg-brand-bg-secondary border-t border-b border-brand-border js-scroll-animation">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <h2 class="font-bold text-5xl mb-4">SPHE Token Economics</h2>
                    <p class="text-xl text-brand-text-secondary mb-8">
                        SPHE es el token de utilidad nativo que impulsa todas las interacciones, recompensas y la gobernanza dentro del ecosistema de The Social Mask.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-brand-bg-primary border border-brand-border rounded-xl p-6">
                            <p class="text-3xl font-bold text-brand-text-primary">1B</p>
                            <p class="text-brand-text-secondary">Total Supply</p>
                        </div>
                        <div class="bg-brand-bg-primary border border-brand-border rounded-xl p-6">
                            <p class="text-3xl font-bold text-brand-text-primary">18%</p>
                            <p class="text-brand-text-secondary">Max Staking APY</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-brand-bg-primary border border-brand-border rounded-2xl p-6">
                        <h4 class="text-xl font-bold text-brand-text-primary mb-2">Gobernanza</h4>
                        <p class="text-brand-text-secondary">Usa SPHE para votar en propuestas y tomar decisiones sobre el futuro de la plataforma.</p>
                    </div>
                    <div class="bg-brand-bg-primary border border-brand-border rounded-2xl p-6">
                        <h4 class="text-xl font-bold text-brand-text-primary mb-2">Recompensas</h4>
                        <p class="text-brand-text-secondary">Gana tokens por crear contenido, interactuar y fortalecer a la comunidad.</p>
                    </div>
                    <div class="bg-brand-bg-primary border border-brand-border rounded-2xl p-6">
                        <h4 class="text-xl font-bold text-brand-text-primary mb-2">Staking</h4>
                        <p class="text-brand-text-secondary">Bloquea tus tokens para asegurar la red y obtener un rendimiento pasivo.</p>
                    </div>
                    <div class="bg-brand-bg-primary border border-brand-border rounded-2xl p-6">
                        <h4 class="text-xl font-bold text-brand-text-primary mb-2">Acceso</h4>
                        <p class="text-brand-text-secondary">Utiliza SPHE para acceder a funciones premium y contenido exclusivo.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-16 text-center js-scroll-animation">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-brand-text-secondary">&copy; 2025 The Social Mask. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Añade la clase de animación de Tailwind cuando el elemento es visible
                        entry.target.classList.add('animate-fade-in-up');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observa todos los elementos con la clase 'js-scroll-animation'
            document.querySelectorAll('.js-scroll-animation').forEach(section => {
                // Para evitar el "parpadeo", ocultamos el elemento con JS y lo mostramos con la animación
                section.style.opacity = '0';
                observer.observe(section);
            });
        });
    </script>

</body>
</html>