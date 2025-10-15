<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Learn - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .lesson-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .lesson-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }
        .difficulty-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
        }
        .difficulty-beginner {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .difficulty-intermediate {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
        }
        .difficulty-advanced {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php
    // No require authentication - accessible to everyone
    // Just include navbar which will show appropriate state
    include '../components/navbar.php';
    ?>

    <!-- Hero Section -->
    <div class="pt-40 pb-12 px-4 bg-gradient-to-b from-brand-bg-secondary to-brand-bg-primary border-b border-brand-border">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-blue-400 to-purple-400 text-transparent bg-clip-text">
                Aprende sobre Criptomonedas
            </h1>
            <p class="text-lg md:text-xl text-brand-text-secondary mb-6">
                Descubre el mundo de las criptomonedas, blockchain y DeFi con nuestras lecciones interactivas
            </p>
            <div class="flex flex-wrap justify-center gap-4 text-sm">
                <div class="flex items-center gap-2 bg-brand-bg-secondary px-4 py-2 rounded-lg border border-brand-border">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>100% Gratis</span>
                </div>
                <div class="flex items-center gap-2 bg-brand-bg-secondary px-4 py-2 rounded-lg border border-brand-border">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span>Lecciones Interactivas</span>
                </div>
                <div class="flex items-center gap-2 bg-brand-bg-secondary px-4 py-2 rounded-lg border border-brand-border">
                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Gana SPHE Tokens</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Lessons Grid -->
    <div class="py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
                <h2 class="text-2xl font-bold">Todas las Lecciones</h2>
                <div class="flex flex-wrap gap-2">
                    <button onclick="filterLessons('all')" class="filter-btn active px-4 py-2 rounded-lg bg-brand-accent text-white text-sm font-semibold transition whitespace-nowrap">
                        Todas
                    </button>
                    <button onclick="filterLessons('beginner')" class="filter-btn px-4 py-2 rounded-lg bg-brand-bg-secondary border border-brand-border text-sm font-semibold hover:bg-brand-bg-primary transition whitespace-nowrap">
                        Principiante
                    </button>
                    <button onclick="filterLessons('intermediate')" class="filter-btn px-4 py-2 rounded-lg bg-brand-bg-secondary border border-brand-border text-sm font-semibold hover:bg-brand-bg-primary transition whitespace-nowrap">
                        Intermedio
                    </button>
                    <button onclick="filterLessons('advanced')" class="filter-btn px-4 py-2 rounded-lg bg-brand-bg-secondary border border-brand-border text-sm font-semibold hover:bg-brand-bg-primary transition whitespace-nowrap">
                        Avanzado
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loading-lessons" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-brand-accent mb-4"></div>
                <p class="text-brand-text-secondary">Cargando lecciones...</p>
            </div>

            <div id="lessons-grid" class="hidden grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Se llenará con JS -->
            </div>

            <div id="no-lessons" class="hidden text-center py-12">
                <svg class="w-20 h-20 mx-auto mb-4 text-brand-text-secondary opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <p class="text-lg text-brand-text-secondary">No hay lecciones disponibles</p>
                <p class="text-sm text-brand-text-secondary mt-2">Vuelve pronto para nuevo contenido</p>
            </div>

            <div id="error-lessons" class="hidden text-center py-12">
                <svg class="w-20 h-20 mx-auto mb-4 text-red-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-lg text-red-500 font-semibold mb-2">Error al cargar las lecciones</p>
                <p class="text-sm text-brand-text-secondary mb-4">Por favor, intenta nuevamente</p>
                <button onclick="loadLessons()" class="px-6 py-3 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                    Reintentar
                </button>
            </div>
        </div>
    </div>

    <script>
    let allLessons = [];
    let currentFilter = 'all';

    async function loadLessons() {
        // Show loading state
        document.getElementById('loading-lessons').classList.remove('hidden');
        document.getElementById('lessons-grid').classList.add('hidden');
        document.getElementById('no-lessons').classList.add('hidden');
        document.getElementById('error-lessons').classList.add('hidden');

        try {
            const response = await fetch('../api/learn/get_lessons.php');

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            // Hide loading state
            document.getElementById('loading-lessons').classList.add('hidden');

            if (data.success && data.lessons && data.lessons.length > 0) {
                allLessons = data.lessons;
                renderLessons();
            } else {
                document.getElementById('no-lessons').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading lessons:', error);
            document.getElementById('loading-lessons').classList.add('hidden');
            document.getElementById('error-lessons').classList.remove('hidden');
        }
    }

    function renderLessons() {
        const container = document.getElementById('lessons-grid');
        const noLessons = document.getElementById('no-lessons');

        const filteredLessons = currentFilter === 'all'
            ? allLessons
            : allLessons.filter(l => l.difficulty === currentFilter);

        if (filteredLessons.length === 0) {
            container.classList.add('hidden');
            noLessons.classList.remove('hidden');
            return;
        }

        noLessons.classList.add('hidden');
        container.classList.remove('hidden');

        container.innerHTML = filteredLessons.map(lesson => `
            <div class="lesson-card bg-brand-bg-secondary border border-brand-border rounded-xl overflow-hidden cursor-pointer" onclick="window.location.href='learn_lesson.php?id=${lesson.id}'">
                ${lesson.image_url ? `
                    <img src="${lesson.image_url}" alt="${lesson.title}" class="w-full h-48 object-cover">
                ` : `
                    <div class="w-full h-48 bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center">
                        <svg class="w-16 h-16 text-brand-accent opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                `}

                <div class="p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="difficulty-badge difficulty-${lesson.difficulty}">
                            ${lesson.difficulty === 'beginner' ? 'Principiante' : lesson.difficulty === 'intermediate' ? 'Intermedio' : 'Avanzado'}
                        </span>
                        ${lesson.sphe_reward > 0 ? `
                            <div class="flex items-center gap-1 text-yellow-500 text-sm font-semibold">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                                </svg>
                                +${lesson.sphe_reward} SPHE
                            </div>
                        ` : ''}
                    </div>

                    <h3 class="text-xl font-bold mb-2">${lesson.title}</h3>
                    <p class="text-brand-text-secondary text-sm mb-4 line-clamp-2">${lesson.summary || lesson.description}</p>

                    <div class="flex items-center gap-4 text-xs text-brand-text-secondary">
                        <div class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            ${lesson.estimated_time} min
                        </div>
                        ${lesson.content_count ? `
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                ${lesson.content_count} items
                            </div>
                        ` : ''}
                        ${lesson.progress !== undefined && lesson.progress > 0 ? `
                            <div class="ml-auto">
                                <div class="w-20 bg-brand-bg-primary rounded-full h-2">
                                    <div class="bg-brand-accent h-2 rounded-full" style="width: ${lesson.progress}%"></div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function filterLessons(difficulty) {
        currentFilter = difficulty;

        // Update button states
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-brand-accent', 'text-white');
            btn.classList.add('bg-brand-bg-secondary', 'border', 'border-brand-border');
        });
        event.target.classList.add('active', 'bg-brand-accent', 'text-white');
        event.target.classList.remove('bg-brand-bg-secondary', 'border', 'border-brand-border');

        renderLessons();
    }

    // Load lessons on page load
    loadLessons();
    </script>

</body>
</html>
