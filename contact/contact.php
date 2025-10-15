<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
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
        .form-input {
            background-color: #0D1117;
            border: 1px solid #30363D;
            transition: border-color 0.2s ease-in-out;
        }
        .form-input:focus {
            border-color: #3B82F6;
            outline: none;
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
                    Contacta con <span class="text-brand-accent">thesocialmask</span>
                </h1>
                <p class="text-xl text-brand-text-secondary max-w-3xl mx-auto">
                    ¿Tienes preguntas, sugerencias o necesitas soporte? Estamos aquí para ayudarte. Envíanos un mensaje y te responderemos lo antes posible.
                </p>
            </div>
        </section>

        <!-- Contact Content -->
        <section class="py-24 js-scroll-animation">
            <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-start">

                <!-- Left Column - Info -->
                <div class="space-y-6">
                    <h2 class="text-3xl font-bold text-brand-text-primary">¿En qué podemos ayudarte?</h2>
                    <div class="space-y-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-brand-bg-secondary rounded-lg flex items-center justify-center border border-brand-border">
                                <svg class="w-6 h-6 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-brand-text-primary mb-1">Soporte Técnico</h3>
                                <p class="text-brand-text-secondary">¿Problemas con tu cuenta, wallet o funciones de la plataforma? Te ayudamos a resolverlo.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-brand-bg-secondary rounded-lg flex items-center justify-center border border-brand-border">
                                <svg class="w-6 h-6 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-brand-text-primary mb-1">Sugerencias</h3>
                                <p class="text-brand-text-secondary">¿Tienes ideas para mejorar thesocialmask? Comparte tus sugerencias con nosotros.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-brand-bg-secondary rounded-lg flex items-center justify-center border border-brand-border">
                                <svg class="w-6 h-6 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V6a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2V6"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-brand-text-primary mb-1">Partnerships</h3>
                                <p class="text-brand-text-secondary">¿Interesado en colaborar o crear alianzas estratégicas? Hablemos.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Form -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8">
                    <h3 class="text-2xl font-bold text-brand-text-primary mb-6">Envíanos un mensaje</h3>
                    <div id="message-container" class="hidden mb-6"></div>
                    <form id="contact-form" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-brand-text-secondary mb-2">Nombre *</label>
                                <input type="text" id="name" name="name" required class="form-input w-full px-4 py-3 rounded-lg text-brand-text-primary" placeholder="Tu nombre completo">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-brand-text-secondary mb-2">Email *</label>
                                <input type="email" id="email" name="email" required class="form-input w-full px-4 py-3 rounded-lg text-brand-text-primary" placeholder="tu@email.com">
                            </div>
                        </div>
                        <div>
                            <label for="subject" class="block text-sm font-medium text-brand-text-secondary mb-2">Asunto *</label>
                            <select id="subject" name="subject" required class="form-input w-full px-4 py-3 rounded-lg text-brand-text-primary">
                                <option value="">Selecciona un tema</option>
                                <option value="soporte">Soporte Técnico</option>
                                <option value="sugerencias">Sugerencias</option>
                                <option value="partnerships">Partnerships</option>
                                <option value="prensa">Prensa y Medios</option>
                                <option value="general">Consulta General</option>
                                <option value="otros">Otros</option>
                            </select>
                        </div>
                        <div>
                            <label for="wallet" class="block text-sm font-medium text-brand-text-secondary mb-2">Wallet Address (Opcional)</label>
                            <input type="text" id="wallet" name="wallet" class="form-input w-full px-4 py-3 rounded-lg text-brand-text-primary" placeholder="0x...">
                            <p class="text-xs text-brand-text-secondary mt-1">Incluye tu wallet si el tema está relacionado con transacciones o membresías</p>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-brand-text-secondary mb-2">Mensaje *</label>
                            <textarea id="message" name="message" required rows="6" class="form-input w-full px-4 py-3 rounded-lg text-brand-text-primary resize-none" placeholder="Describe tu consulta en detalle..."></textarea>
                        </div>
                        <div class="flex items-start space-x-3">
                            <input type="checkbox" id="privacy" name="privacy" required class="mt-1 w-4 h-4 text-brand-accent bg-brand-bg-primary border-brand-border rounded focus:ring-brand-accent">
                            <label for="privacy" class="text-sm text-brand-text-secondary">Acepto que thesocialmask procese mis datos para responder a esta consulta según la política de privacidad. *</label>
                        </div>
                        <button type="submit" id="submit-btn" class="w-full bg-brand-accent text-white font-bold py-4 px-8 rounded-xl transition-colors hover:bg-blue-700 transform hover:scale-105">
                            <span id="btn-text">Enviar Mensaje</span>
                            <span id="btn-loading" class="hidden">Enviando...</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="py-16 text-center border-t border-brand-border js-scroll-animation">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-brand-text-secondary">&copy; 2025 thesocialmask. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        document.getElementById('contact-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const btnLoading = document.getElementById('btn-loading');
            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');
            const formData = new FormData(this);
            try {
                const response = await fetch('../api/send_contact.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showMessage('success', result.message);
                    this.reset();
                } else {
                    showMessage('error', result.message || 'Error al enviar el mensaje');
                }
            } catch (error) {
                showMessage('error', 'Error de conexión. Por favor, intenta nuevamente.');
            } finally {
                submitBtn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
            }
        });

        function showMessage(type, message) {
            const container = document.getElementById('message-container');
            const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
            container.innerHTML = `<div class="${bgColor} text-white p-4 rounded-lg">${message}</div>`;
            container.classList.remove('hidden');
            setTimeout(() => { container.classList.add('hidden'); }, 5000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
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
