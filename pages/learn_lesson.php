<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lección - The Social Mask Learn</title>
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
        .progress-bar-container {
            position: sticky;
            top: 72px;
            z-index: 40;
            background: #0D1117;
            border-bottom: 1px solid #30363D;
        }
        @media (max-width: 767px) {
            .progress-bar-container {
                top: 64px;
            }
        }
        .progress-fill {
            height: 8px;
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
            transition: width 0.5s ease;
            border-radius: 4px;
        }
        .option-btn {
            transition: all 0.3s;
            min-height: 56px;
        }
        .option-btn:hover {
            transform: translateX(4px);
            background: rgba(59, 130, 246, 0.1);
        }
        .option-btn.correct {
            background: rgba(34, 197, 94, 0.2);
            border-color: #22c55e;
        }
        .option-btn.incorrect {
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
        }
        @media (max-width: 767px) {
            .option-btn {
                font-size: 0.875rem;
                padding: 1rem;
            }
        }
        .fade-in {
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

    <!-- Progress Bar -->
    <div class="progress-bar-container py-4 px-4">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-2">
                <h2 id="lesson-title" class="text-lg font-bold">Cargando lección...</h2>
                <span id="progress-text" class="text-sm text-brand-text-secondary">0%</span>
            </div>
            <div class="w-full bg-brand-bg-secondary rounded-full h-2">
                <div id="progress-fill" class="progress-fill" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <!-- Content Area -->
    <div class="py-12 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Loading State -->
            <div id="loading-content" class="text-center py-20">
                <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-brand-accent mb-4"></div>
                <p class="text-lg text-brand-text-secondary">Cargando lección...</p>
            </div>

            <!-- Error State -->
            <div id="error-content" class="hidden text-center py-20">
                <svg class="w-20 h-20 mx-auto mb-4 text-red-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-lg text-red-500 font-semibold mb-2">Error al cargar la lección</p>
                <p class="text-sm text-brand-text-secondary mb-4">Por favor, verifica tu conexión e intenta nuevamente</p>
                <a href="/learn" class="inline-block px-6 py-3 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                    Volver a lecciones
                </a>
            </div>

            <!-- Content Container -->
            <div id="content-container" class="hidden mb-8">
                <!-- Dynamic content will be loaded here -->
            </div>

            <!-- Navigation Buttons -->
            <div id="navigation-buttons" class="flex justify-between items-center">
                <button id="prev-btn" onclick="previousContent()" class="hidden px-6 py-3 bg-brand-bg-secondary border border-brand-border rounded-lg font-semibold hover:bg-brand-bg-primary transition">
                    ← Anterior
                </button>
                <button id="next-btn" onclick="nextContent()" class="hidden ml-auto px-6 py-3 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                    Siguiente →
                </button>
            </div>

            <!-- Completion Card -->
            <div id="completion-card" class="hidden bg-gradient-to-br from-blue-500/20 to-purple-500/20 border border-blue-500/30 rounded-xl p-8 text-center">
                <svg class="w-20 h-20 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-3xl font-bold mb-2">¡Lección Completada!</h2>
                <p class="text-brand-text-secondary mb-6">Has completado todas las preguntas correctamente</p>
                <div id="reward-section" class="hidden mb-6">
                    <div class="inline-flex items-center gap-2 bg-yellow-500/20 border border-yellow-500/30 rounded-lg px-6 py-3">
                        <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xl font-bold text-yellow-500">+<span id="reward-amount">0</span> SPHE</span>
                    </div>
                    <p id="reward-message" class="text-sm text-brand-text-secondary mt-2"></p>
                </div>
                <div class="flex gap-4 justify-center">
                    <a href="/learn" class="px-6 py-3 bg-brand-bg-secondary border border-brand-border rounded-lg font-semibold hover:bg-brand-bg-primary transition">
                        Ver más lecciones
                    </a>
                    <button onclick="retakeLesson()" class="px-6 py-3 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                        Repetir lección
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let lessonData = null;
    let allContent = [];
    let currentIndex = 0;
    let answeredCorrectly = new Set();
    let pendingQuestions = [];
    let userAnswers = {};
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    const urlParams = new URLSearchParams(window.location.search);
    const lessonId = urlParams.get('id');

    async function loadLesson() {
        if (!lessonId) {
            window.location.href = 'learn.php';
            return;
        }

        // Show loading state
        document.getElementById('loading-content').classList.remove('hidden');
        document.getElementById('content-container').classList.add('hidden');
        document.getElementById('error-content').classList.add('hidden');

        try {
            const response = await fetch(`../api/learn/get_lesson.php?id=${lessonId}`);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success && data.lesson && data.content) {
                lessonData = data;
                allContent = data.content;
                document.getElementById('lesson-title').textContent = data.lesson.title;

                // Hide loading and show content
                document.getElementById('loading-content').classList.add('hidden');
                document.getElementById('content-container').classList.remove('hidden');

                renderCurrentContent();
            } else {
                throw new Error('Lesson data invalid');
            }
        } catch (error) {
            console.error('Error loading lesson:', error);
            document.getElementById('loading-content').classList.add('hidden');
            document.getElementById('error-content').classList.remove('hidden');
        }
    }

    function renderCurrentContent() {
        const container = document.getElementById('content-container');
        const content = allContent[currentIndex];

        if (!content) {
            completeLesson();
            return;
        }

        container.innerHTML = '';
        container.className = 'mb-8 fade-in';

        if (content.content_type === 'text') {
            container.innerHTML = `
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-8">
                    <div class="prose prose-invert max-w-none">
                        <p class="text-lg leading-relaxed">${content.content_text}</p>
                    </div>
                </div>
            `;
            document.getElementById('next-btn').classList.remove('hidden');
        } else if (content.content_type === 'image') {
            container.innerHTML = `
                <div class="bg-brand-bg-secondary border border-brand-border rounded-xl overflow-hidden">
                    <img src="${content.image_url}" alt="Lesson image" class="w-full">
                </div>
            `;
            document.getElementById('next-btn').classList.remove('hidden');
        } else if (content.content_type === 'question') {
            renderQuestion(content);
        }

        // Update navigation buttons
        if (currentIndex > 0) {
            document.getElementById('prev-btn').classList.remove('hidden');
        } else {
            document.getElementById('prev-btn').classList.add('hidden');
        }

        updateProgress();
    }

    function renderQuestion(content) {
        const container = document.getElementById('content-container');
        const isRepeating = pendingQuestions.includes(content.id);

        container.innerHTML = `
            <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-8">
                ${isRepeating ? '<div class="mb-4 px-4 py-2 bg-yellow-500/20 border border-yellow-500/30 rounded-lg text-center"><p class="text-sm text-yellow-500 font-semibold">⚠️ Pregunta repetida - Responde correctamente para continuar</p></div>' : ''}
                <h3 class="text-2xl font-bold mb-6">${content.question_text}</h3>
                <div class="space-y-3">
                    ${['A', 'B', 'C', 'D'].map(letter => `
                        <button onclick="selectAnswer('${letter}', ${content.id})"
                                class="option-btn w-full text-left px-6 py-4 bg-brand-bg-primary border-2 border-brand-border rounded-lg hover:border-brand-accent transition">
                            <span class="font-semibold mr-3">${letter}.</span>
                            ${content['option_' + letter.toLowerCase()]}
                        </button>
                    `).join('')}
                </div>
            </div>
            <div id="feedback-${content.id}" class="hidden mt-6"></div>
        `;

        document.getElementById('next-btn').classList.add('hidden');
    }

    async function selectAnswer(answer, contentId) {
        const content = allContent.find(c => c.id === contentId);
        const isCorrect = answer === content.correct_answer;

        // Disable all buttons
        document.querySelectorAll('.option-btn').forEach(btn => btn.disabled = true);

        // Highlight selected answer
        const buttons = document.querySelectorAll('.option-btn');
        buttons.forEach((btn, index) => {
            const letter = ['A', 'B', 'C', 'D'][index];
            if (letter === answer) {
                btn.classList.add(isCorrect ? 'correct' : 'incorrect');
            }
            if (letter === content.correct_answer) {
                btn.classList.add('correct');
            }
        });

        // Show feedback
        const feedbackDiv = document.getElementById(`feedback-${contentId}`);
        feedbackDiv.className = `mt-6 p-6 rounded-xl border-2 ${isCorrect ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'}`;
        feedbackDiv.innerHTML = `
            <div class="flex items-start gap-4">
                <svg class="w-6 h-6 flex-shrink-0 ${isCorrect ? 'text-green-500' : 'text-red-500'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${isCorrect
                        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                    }
                </svg>
                <div class="flex-1">
                    <h4 class="font-bold mb-2 ${isCorrect ? 'text-green-500' : 'text-red-500'}">
                        ${isCorrect ? '¡Correcto!' : 'Incorrecto'}
                    </h4>
                    <p class="text-brand-text-primary">${content.explanation}</p>
                </div>
            </div>
        `;
        feedbackDiv.classList.remove('hidden');

        // Submit answer to backend
        await submitAnswer(contentId, answer, isCorrect);

        if (isCorrect) {
            answeredCorrectly.add(contentId);
            // Remove from pending if it was there
            pendingQuestions = pendingQuestions.filter(id => id !== contentId);

            setTimeout(() => {
                document.getElementById('next-btn').classList.remove('hidden');
            }, 1500);
        } else {
            // Add to pending if not already there
            if (!pendingQuestions.includes(contentId)) {
                pendingQuestions.push(contentId);
            }

            setTimeout(() => {
                document.getElementById('next-btn').classList.remove('hidden');
            }, 2000);
        }
    }

    async function submitAnswer(contentId, answer, isCorrect) {
        try {
            await fetch('../api/learn/submit_answer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    content_id: contentId,
                    answer: answer,
                    is_correct: isCorrect
                })
            });
        } catch (error) {
            console.error('Error submitting answer:', error);
        }
    }

    function nextContent() {
        currentIndex++;

        // Check if we've reached the end of content
        if (currentIndex >= allContent.length) {
            // If there are pending questions, add them to the end
            if (pendingQuestions.length > 0) {
                const pendingContent = allContent.filter(c => pendingQuestions.includes(c.id));
                allContent = [...allContent, ...pendingContent];
            }
        }

        renderCurrentContent();
    }

    function previousContent() {
        if (currentIndex > 0) {
            currentIndex--;
            renderCurrentContent();
        }
    }

    function updateProgress() {
        const totalQuestions = allContent.filter(c => c.content_type === 'question').length;
        const correctAnswers = answeredCorrectly.size;
        const percentage = totalQuestions > 0 ? Math.round((correctAnswers / totalQuestions) * 100) : 0;

        document.getElementById('progress-fill').style.width = percentage + '%';
        document.getElementById('progress-text').textContent = percentage + '%';
    }

    async function completeLesson() {
        document.getElementById('content-container').classList.add('hidden');
        document.getElementById('navigation-buttons').classList.add('hidden');
        document.getElementById('completion-card').classList.remove('hidden');

        // Show reward if applicable
        if (lessonData.lesson.sphe_reward > 0) {
            document.getElementById('reward-section').classList.remove('hidden');
            document.getElementById('reward-amount').textContent = lessonData.lesson.sphe_reward;

            if (isLoggedIn) {
                document.getElementById('reward-message').textContent = 'Los tokens se han agregado a tu cuenta';
            } else {
                document.getElementById('reward-message').textContent = 'Inicia sesión para recibir tus tokens SPHE';
            }
        }

        // Mark lesson as complete
        try {
            await fetch('../api/learn/complete_lesson.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lesson_id: lessonId })
            });
        } catch (error) {
            console.error('Error completing lesson:', error);
        }
    }

    function retakeLesson() {
        currentIndex = 0;
        answeredCorrectly.clear();
        pendingQuestions = [];
        userAnswers = {};
        document.getElementById('completion-card').classList.add('hidden');
        document.getElementById('content-container').classList.remove('hidden');
        document.getElementById('navigation-buttons').classList.remove('hidden');
        renderCurrentContent();
    }

    // Load lesson on page load
    loadLesson();
    </script>

</body>
</html>
