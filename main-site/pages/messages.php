<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mensajes - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .messages-container {
            height: calc(100vh - 200px);
        }
        .conversation-list {
            height: calc(100vh - 120px);
            overflow-y: auto;
        }
        .messages-area {
            height: calc(100% - 140px);
            overflow-y: auto;
        }
        .message-bubble {
            max-width: 70%;
            word-wrap: break-word;
        }
        .message-sent {
            background: #3B82F6;
            color: white;
            margin-left: auto;
        }
        .message-received {
            background: #161B22;
            border: 1px solid #30363D;
        }
        .conversation-item:hover {
            background: #0D1117;
        }
        .conversation-item.active {
            background: #1a1f28;
            border-left: 3px solid #3B82F6;
        }
        .unread-badge {
            background: #3B82F6;
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

        <!-- Messages Container -->
    <main class="flex-1">
        <div class="pt-32 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-[350px_1fr] gap-0 border border-brand-border rounded-lg overflow-hidden bg-brand-bg-secondary messages-container">

                <!-- Sidebar - Lista de Conversaciones -->
                <div class="border-r border-brand-border flex flex-col">
                    <!-- Header -->
                    <div class="p-4 border-b border-brand-border">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-xl font-bold">Mensajes</h2>
                            <button onclick="openNewMessageModal()" class="p-2 bg-brand-accent hover:bg-brand-accent/80 rounded-lg transition" title="Nuevo mensaje">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="relative">
                            <input
                                type="text"
                                id="search-conversations"
                                placeholder="Buscar conversaciones..."
                                class="w-full bg-brand-bg-primary border border-brand-border rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
                            >
                            <svg class="w-5 h-5 absolute left-3 top-2.5 text-brand-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Lista de Conversaciones -->
                    <div id="conversations-list" class="conversation-list">
                        <!-- Se llenará con JS -->
                    </div>

                    <!-- No conversations -->
                    <div id="no-conversations" class="hidden flex-1 flex items-center justify-center text-center p-6">
                        <div>
                            <svg class="w-16 h-16 mx-auto mb-4 text-brand-text-secondary opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-brand-text-secondary">No tienes conversaciones aún</p>
                        </div>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex flex-col">
                    <!-- Chat Header -->
                    <div id="chat-header" class="hidden p-4 border-b border-brand-border flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img id="chat-user-avatar" src="" alt="Avatar" class="w-10 h-10 rounded-full">
                            <div>
                                <h3 id="chat-user-name" class="font-semibold">Usuario</h3>
                                <p id="chat-user-status" class="text-xs text-brand-text-secondary">En línea</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="viewUserProfile()" class="p-2 hover:bg-brand-bg-primary rounded-lg transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </button>
                            <button onclick="blockUser()" class="p-2 hover:bg-brand-bg-primary rounded-lg transition text-red-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div id="messages-area" class="messages-area p-4 space-y-4">
                        <!-- Se llenará con JS -->
                    </div>

                    <!-- Empty state -->
                    <div id="empty-chat" class="flex-1 flex items-center justify-center text-center p-6">
                        <div>
                            <svg class="w-20 h-20 mx-auto mb-4 text-brand-text-secondary opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                            </svg>
                            <p class="text-brand-text-secondary text-lg mb-2">Selecciona una conversación</p>
                            <p class="text-brand-text-secondary text-sm">Elige un contacto para empezar a chatear</p>
                        </div>
                    </div>

                    <!-- Message Input -->
                    <div id="message-input-container" class="hidden p-4 border-t border-brand-border">
                        <div class="flex gap-2">
                            <button class="p-3 hover:bg-brand-bg-primary rounded-lg transition">
                                <svg class="w-5 h-5 text-brand-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </button>
                            <input
                                type="text"
                                id="message-input"
                                placeholder="Escribe un mensaje..."
                                class="flex-1 bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-accent"
                                onkeypress="if(event.key === 'Enter') sendMessage()"
                            >
                            <button onclick="sendMessage()" class="px-6 py-3 bg-brand-accent text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal: Nuevo Mensaje -->
    <div id="new-message-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg w-full max-w-md">
            <div class="p-4 border-b border-brand-border flex items-center justify-between">
                <h3 class="text-lg font-bold">Nuevo Mensaje</h3>
                <button onclick="closeNewMessageModal()" class="p-1 hover:bg-brand-bg-primary rounded transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <div class="relative mb-4">
                    <input
                        type="text"
                        id="search-users-input"
                        placeholder="Buscar usuarios..."
                        class="w-full bg-brand-bg-primary border border-brand-border rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-brand-accent"
                        oninput="searchUsersForMessage(this.value)"
                    >
                    <svg class="w-5 h-5 absolute left-3 top-2.5 text-brand-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <div id="users-search-results" class="space-y-2 max-h-96 overflow-y-auto">
                    <p class="text-center text-brand-text-secondary text-sm py-8">Busca un usuario para enviar un mensaje</p>
                </div>
            </div>
        </div>
    </div>
</main>

    <script>
    let conversations = [];
    let currentConversation = null;
    let currentOtherUser = null;
    let messagesPollingInterval = null;
    let searchUsersTimeout = null;

    // URL params - si se pasó user_id, abrir chat con ese usuario
    const urlParams = new URLSearchParams(window.location.search);
    const startWithUserId = urlParams.get('user_id');

    async function loadConversations() {
        try {
            const response = await fetch('../api/get_conversations.php');
            const data = await response.json();

            if (data.success) {
                conversations = data.conversations;
                renderConversations();

                // Si se pasó user_id en URL, abrir chat con ese usuario
                if (startWithUserId && conversations.length === 0) {
                    // No hay conversación previa, iniciar nueva
                    startNewConversation(startWithUserId);
                } else if (startWithUserId) {
                    const conv = conversations.find(c => c.other_user_id == startWithUserId);
                    if (conv) {
                        openConversation(conv.conversation_id, conv);
                    } else {
                        startNewConversation(startWithUserId);
                    }
                }
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
        }
    }

    function renderConversations() {
        const container = document.getElementById('conversations-list');
        const noConv = document.getElementById('no-conversations');

        if (conversations.length === 0) {
            noConv.classList.remove('hidden');
            container.classList.add('hidden');
            return;
        }

        noConv.classList.add('hidden');
        container.classList.remove('hidden');

        container.innerHTML = conversations.map(conv => `
            <div class="conversation-item p-4 border-b border-brand-border cursor-pointer flex items-start gap-3" onclick="openConversation(${conv.conversation_id}, ${JSON.stringify(conv).replace(/"/g, '&quot;')})">
                <img src="${conv.other_profile_image || 'https://ui-avatars.com/api/?name=' + conv.other_username + '&size=40&background=3B82F6&color=fff'}" alt="${conv.other_username}" class="w-12 h-12 rounded-full flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-1">
                        <h4 class="font-semibold truncate">@${conv.other_username}</h4>
                        <span class="text-xs text-brand-text-secondary">${formatTime(conv.last_message_at)}</span>
                    </div>
                    <p class="text-sm text-brand-text-secondary truncate">${conv.last_message_content || 'Nueva conversación'}</p>
                </div>
                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
            </div>
        `).join('');
    }

    async function openConversation(conversationId, convData) {
        currentConversation = conversationId;
        currentOtherUser = {
            user_id: convData.other_user_id,
            username: convData.other_username,
            profile_image: convData.other_profile_image
        };

        // Actualizar UI
        document.querySelectorAll('.conversation-item').forEach(el => el.classList.remove('active'));
        event?.currentTarget?.classList.add('active');

        document.getElementById('empty-chat').classList.add('hidden');
        document.getElementById('chat-header').classList.remove('hidden');
        document.getElementById('message-input-container').classList.remove('hidden');

        document.getElementById('chat-user-avatar').src = currentOtherUser.profile_image || 'https://ui-avatars.com/api/?name=' + currentOtherUser.username;
        document.getElementById('chat-user-name').textContent = '@' + currentOtherUser.username;

        // Cargar mensajes
        await loadMessages();

        // Polling para nuevos mensajes
        if (messagesPollingInterval) clearInterval(messagesPollingInterval);
        messagesPollingInterval = setInterval(loadMessages, 3000); // Cada 3 segundos
    }

    async function startNewConversation(userId) {
        try {
            // Obtener info del usuario
            const response = await fetch(`../api/get_profile.php?user_id=${userId}`);
            const data = await response.json();

            if (data.success) {
                currentOtherUser = {
                    user_id: data.profile.user_id,
                    username: data.profile.username,
                    profile_image: data.profile.profile_image
                };

                document.getElementById('empty-chat').classList.add('hidden');
                document.getElementById('chat-header').classList.remove('hidden');
                document.getElementById('message-input-container').classList.remove('hidden');

                document.getElementById('chat-user-avatar').src = currentOtherUser.profile_image || 'https://ui-avatars.com/api/?name=' + currentOtherUser.username;
                document.getElementById('chat-user-name').textContent = '@' + currentOtherUser.username;

                document.getElementById('messages-area').innerHTML = '<p class="text-center text-brand-text-secondary">Escribe un mensaje para iniciar la conversación</p>';
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    async function loadMessages() {
        if (!currentConversation && !currentOtherUser) return;

        try {
            const url = currentConversation
                ? `../api/get_messages.php?conversation_id=${currentConversation}`
                : `../api/get_messages.php?other_user_id=${currentOtherUser.user_id}`;

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                renderMessages(data.messages);

                if (!currentConversation && data.conversation_id) {
                    currentConversation = data.conversation_id;
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    function renderMessages(messages) {
        const container = document.getElementById('messages-area');

        if (messages.length === 0) {
            container.innerHTML = '<p class="text-center text-brand-text-secondary">No hay mensajes todavía</p>';
            return;
        }

        const currentUserId = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;

        container.innerHTML = messages.map(msg => {
            const isSent = msg.sender_id == currentUserId;
            const time = new Date(msg.created_at).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

            return `
                <div class="flex ${isSent ? 'justify-end' : 'justify-start'}">
                    <div class="message-bubble ${isSent ? 'message-sent' : 'message-received'} rounded-2xl px-4 py-2">
                        <p class="text-sm">${escapeHtml(msg.content)}</p>
                        <span class="text-xs opacity-70 mt-1 block">${time}</span>
                    </div>
                </div>
            `;
        }).join('');

        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    async function sendMessage() {
        const input = document.getElementById('message-input');
        const content = input.value.trim();

        if (!content || !currentOtherUser) return;

        try {
            const response = await fetch('../api/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    receiver_id: currentOtherUser.user_id,
                    content: content,
                    message_type: 'text'
                })
            });

            const data = await response.json();

            if (data.success) {
                input.value = '';
                await loadMessages();
                await loadConversations(); // Actualizar lista
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Error al enviar mensaje');
        }
    }

    function viewUserProfile() {
        if (currentOtherUser) {
            window.location.href = `/profile?username=${currentOtherUser.username}`;
        }
    }

    async function blockUser() {
        if (!currentOtherUser) return;

        if (!confirm('¿Estás seguro de que quieres bloquear a este usuario?')) return;

        try {
            const response = await fetch('../api/block_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: currentOtherUser.user_id,
                    action: 'block'
                })
            });

            const data = await response.json();

            if (data.success) {
                alert('Usuario bloqueado');
                window.location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Search conversations and users
    let searchTimeout = null;
    document.getElementById('search-conversations').addEventListener('input', async function(e) {
        const search = e.target.value.trim();

        // Clear previous timeout
        if (searchTimeout) clearTimeout(searchTimeout);

        if (search === '') {
            // Show all conversations
            renderConversations();
            return;
        }

        // Filter existing conversations
        const searchLower = search.toLowerCase();
        document.querySelectorAll('.conversation-item').forEach(item => {
            const username = item.textContent.toLowerCase();
            item.style.display = username.includes(searchLower) ? 'flex' : 'none';
        });

        // Search for new users (debounced)
        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`../api/search_users.php?q=${encodeURIComponent(search)}&limit=5`);
                const data = await response.json();

                if (data.success && data.users.length > 0) {
                    // Add search results section
                    const container = document.getElementById('conversations-list');
                    const existingConvIds = conversations.map(c => c.other_user_id);
                    const newUsers = data.users.filter(u => !existingConvIds.includes(u.user_id));

                    if (newUsers.length > 0) {
                        const searchResultsHTML = `
                            <div class="bg-brand-bg-primary p-2 border-b border-brand-border">
                                <p class="text-xs text-brand-text-secondary font-semibold">USUARIOS</p>
                            </div>
                            ${newUsers.map(user => `
                                <div class="conversation-item p-4 border-b border-brand-border cursor-pointer flex items-start gap-3 hover:bg-brand-bg-primary" onclick="startNewConversation(${user.user_id})">
                                    <img src="${user.profile_image}" alt="${user.username}" class="w-12 h-12 rounded-full flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-semibold truncate">@${user.username}</h4>
                                            ${user.verified ? '<svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>' : ''}
                                        </div>
                                        <p class="text-xs text-brand-text-secondary truncate">${user.bio || 'Click para enviar mensaje'}</p>
                                    </div>
                                </div>
                            `).join('')}
                        `;
                        container.innerHTML += searchResultsHTML;
                    }
                }
            } catch (error) {
                console.error('Error searching users:', error);
            }
        }, 300); // 300ms debounce
    });

    function formatTime(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Ahora';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
        if (diff < 604800000) return Math.floor(diff / 86400000) + 'd';

        return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========================================
    // NEW MESSAGE MODAL FUNCTIONS
    // ========================================
    function openNewMessageModal() {
        document.getElementById('new-message-modal').classList.remove('hidden');
        document.getElementById('search-users-input').value = '';
        document.getElementById('users-search-results').innerHTML = '<p class="text-center text-brand-text-secondary text-sm py-8">Busca un usuario para enviar un mensaje</p>';
    }

    function closeNewMessageModal() {
        document.getElementById('new-message-modal').classList.add('hidden');
    }

    async function searchUsersForMessage(query) {
        if (searchUsersTimeout) clearTimeout(searchUsersTimeout);
        
        const resultsContainer = document.getElementById('users-search-results');
        
        if (query.trim().length < 2) {
            resultsContainer.innerHTML = '<p class="text-center text-brand-text-secondary text-sm py-8">Escribe al menos 2 caracteres</p>';
            return;
        }

        resultsContainer.innerHTML = '<p class="text-center text-brand-text-secondary text-sm py-8">Buscando...</p>';

        searchUsersTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`../api/search_users.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success && data.users.length > 0) {
                    resultsContainer.innerHTML = data.users.map(user => `
                        <div class="flex items-center gap-3 p-3 hover:bg-brand-bg-primary rounded-lg cursor-pointer transition" onclick="selectUserForMessage(${user.user_id})">
                            <img src="${user.profile_image}" alt="${user.username}" class="w-10 h-10 rounded-full flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm">@${user.username}</span>
                                    ${user.is_verified ? '<svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>' : ''}
                                </div>
                                <p class="text-xs text-brand-text-secondary truncate">${user.bio || 'No bio'}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    resultsContainer.innerHTML = '<p class="text-center text-brand-text-secondary text-sm py-8">No se encontraron usuarios</p>';
                }
            } catch (error) {
                console.error('Error searching users:', error);
                resultsContainer.innerHTML = '<p class="text-center text-red-500 text-sm py-8">Error al buscar usuarios</p>';
            }
        }, 300);
    }

    function selectUserForMessage(userId) {
        closeNewMessageModal();
        startNewConversation(userId);
    }

    // Close modal on click outside
    document.getElementById('new-message-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'new-message-modal') {
            closeNewMessageModal();
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (messagesPollingInterval) clearInterval(messagesPollingInterval);
    });

    // Load conversations on page load
    loadConversations();
    </script>
    
    <!-- P2P Client Scripts -->
    <?php include __DIR__ . '/../components/scripts.php'; ?>

</body>
</html>
