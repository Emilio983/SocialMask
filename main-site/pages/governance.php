<?php
/**
 * ============================================
 * GOVERNANCE DASHBOARD
 * ============================================
 * Vista principal del sistema de gobernanza DAO
 */

require_once __DIR__ . '/../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Get user wallet if available
$stmt = $pdo->prepare("SELECT wallet_address FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userWallet = $user['wallet_address'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Governance - Sphera DAO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                }
            }
        };
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Custom animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        .proposal-card {
            transition: all 0.3s ease;
        }

        .proposal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2);
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .progress-bar {
            transition: width 0.5s ease;
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #161B22;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #30363D;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #484F58;
        }

        /* Modal responsive fixes */
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        @media (max-width: 640px) {
            .modal-content {
                max-height: 95vh;
                margin: 1rem;
            }
        }

        /* ERROR STYLING - Dark theme friendly */
        .error-message, .error-text {
            background: rgba(239, 68, 68, 0.1) !important;
            color: #FCA5A5 !important;
            border: 1px solid rgba(239, 68, 68, 0.3) !important;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid #EF4444;
            color: #FCA5A5;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .alert-warning {
            background: rgba(251, 191, 36, 0.1);
            border-left: 3px solid #FBBF24;
            color: #FCD34D;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border-left: 3px solid #3B82F6;
            color: #93C5FD;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        /* Form inputs dark theme */
        input[type="text"], input[type="number"], textarea, select {
            background: #0D1117 !important;
            border: 1px solid #30363D !important;
            color: #C9D1D9 !important;
        }

        input[type="text"]:focus, input[type="number"]:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3B82F6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input::placeholder, textarea::placeholder {
            color: #8B949E !important;
        }

        /* Button styling */
        .btn-primary {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">
    <!-- Navigation -->
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12">
        <!-- Header Section -->
        <div class="mb-8 fade-in">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-brand-text-primary mb-2">
                        Sphera Governance
                    </h1>
                    <p class="text-brand-text-secondary text-base sm:text-lg">
                        Participa en las decisiones de la DAO y da forma al futuro de Sphera
                    </p>
                </div>
                <div class="mt-4 md:mt-0">
                    <?php if ($userWallet): ?>
                        <button onclick="document.getElementById('createProposalModal').classList.remove('hidden')" class="bg-brand-accent hover:bg-blue-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold transition duration-200 shadow-lg hover:shadow-xl text-sm sm:text-base">
                            <i class="fas fa-plus mr-2"></i>
                            Crear Propuesta
                        </button>
                    <?php else: ?>
                        <button onclick="window.notify.warning('Por favor conecta tu wallet primero para crear propuestas', 'Wallet Requerida')" class="bg-brand-bg-secondary cursor-not-allowed text-brand-text-secondary px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold text-sm sm:text-base">
                            <i class="fas fa-wallet mr-2"></i>
                            Conectar Wallet
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div id="governanceStats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
            <!-- Stats will be loaded here -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg shadow p-6 animate-pulse">
                <div class="h-4 bg-brand-border rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-brand-border rounded w-1/2"></div>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg shadow p-6 animate-pulse">
                <div class="h-4 bg-brand-border rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-brand-border rounded w-1/2"></div>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg shadow p-6 animate-pulse">
                <div class="h-4 bg-brand-border rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-brand-border rounded w-1/2"></div>
            </div>
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg shadow p-6 animate-pulse">
                <div class="h-4 bg-brand-border rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-brand-border rounded w-1/2"></div>
            </div>
        </div>

        <!-- User Voting Power Widget -->
        <?php if ($userWallet): ?>
        <div id="votingPowerWidget" class="mb-8">
            <!-- Will be loaded by JS -->
        </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg shadow p-4 sm:p-6 mb-8 fade-in">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-brand-text-primary mb-2">Category</label>
                    <select id="categoryFilter" class="w-full bg-brand-bg-primary border border-brand-border text-brand-text-primary rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-accent focus:border-transparent">
                        <option value="">All Categories</option>
                        <option value="parameter_change">Parameter Change</option>
                        <option value="treasury_allocation">Treasury Allocation</option>
                        <option value="protocol_upgrade">Protocol Upgrade</option>
                        <option value="ecosystem_initiative">Ecosystem Initiative</option>
                        <option value="emergency_action">Emergency Action</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-text-primary mb-2">Status</label>
                    <select id="statusFilter" class="w-full bg-brand-bg-primary border border-brand-border text-brand-text-primary rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-accent focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="succeeded">Succeeded</option>
                        <option value="defeated">Defeated</option>
                        <option value="queued">Queued</option>
                        <option value="executed">Executed</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-brand-text-primary mb-2">Search</label>
                    <div class="relative">
                        <input
                            type="text"
                            id="searchInput"
                            placeholder="Search proposals by title or description..."
                            class="w-full bg-brand-bg-primary border border-brand-border text-brand-text-primary rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-brand-accent focus:border-transparent placeholder-brand-text-secondary"
                        >
                        <i class="fas fa-search absolute left-3 top-3 text-brand-text-secondary"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Proposals List -->
        <div id="proposalsList" class="space-y-4 sm:space-y-6">
            <!-- Loading state -->
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-brand-accent mb-4"></i>
                <p class="text-brand-text-secondary">Loading proposals...</p>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="mt-8 flex justify-center">
            <!-- Will be loaded by JS -->
        </div>
    </main>

    <!-- Create Proposal Modal -->
    <div id="createProposalModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-2 sm:p-4 overflow-y-auto">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg max-w-4xl w-full p-4 sm:p-6 my-4 max-h-[95vh] overflow-y-auto custom-scrollbar relative">
            <button onclick="document.getElementById('createProposalModal').classList.add('hidden')" class="sticky top-0 right-0 float-right text-brand-text-secondary hover:text-brand-text-primary text-2xl z-10 bg-brand-bg-secondary rounded-full w-8 h-8 flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>
            
            <h2 class="text-2xl font-bold text-brand-text-primary mb-6">
                <i class="fas fa-file-alt mr-2 text-brand-accent"></i>
                Crear Nueva Propuesta
            </h2>
            
            <form id="createProposalForm" class="space-y-4">
                <div>
                    <label class="block text-brand-text-primary font-medium mb-2">
                        Título de la Propuesta
                    </label>
                    <input 
                        type="text" 
                        name="title" 
                        required 
                        minlength="10"
                        class="w-full bg-brand-bg-primary text-brand-text-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:border-brand-accent"
                        placeholder="Ej: Reducir fee de swap al 0.25%"
                    >
                </div>
                
                <div>
                    <label class="block text-brand-text-primary font-medium mb-2">
                        Descripción Completa
                    </label>
                    <textarea 
                        name="description" 
                        required 
                        minlength="50"
                        rows="6"
                        class="w-full bg-brand-bg-primary text-brand-text-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:border-brand-accent resize-none"
                        placeholder="Explica tu propuesta en detalle (mínimo 50 caracteres)..."
                    ></textarea>
                </div>
                
                <div>
                    <label class="block text-brand-text-primary font-medium mb-2">
                        Categoría
                    </label>
                    <select 
                        name="category" 
                        required
                        class="w-full bg-brand-bg-primary text-brand-text-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:border-brand-accent"
                    >
                        <option value="">Selecciona una categoría</option>
                        <option value="community_rule">Regla de Comunidad</option>
                        <option value="fee_change">Cambio de Tarifa</option>
                        <option value="feature_request">Solicitud de Función</option>
                        <option value="platform_change">Cambio de Plataforma</option>
                    </select>
                </div>
                
                <div class="bg-brand-bg-primary border border-blue-500/30 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-400 mb-2">
                        <i class="fas fa-bolt text-blue-400 mr-2"></i>
                        Acciones de la Propuesta
                    </h4>
                    <ul class="text-sm text-brand-text-secondary space-y-1">
                        <li>• La propuesta estará activa por <strong class="text-brand-text-primary">3 días</strong></li>
                        <li>• Se necesita un quórum de <strong class="text-brand-text-primary">1,000 SPHE</strong> votando</li>
                        <li>• Los votos se cuentan según tu balance de SPHE</li>
                        <li>• Tus tokens SPHE <strong class="text-green-400">NUNCA se bloquean</strong></li>
                    </ul>
                </div>
                
                <div class="bg-brand-bg-primary border border-yellow-500/30 rounded-lg p-4">
                    <h4 class="font-semibold text-yellow-400 mb-2">
                        <i class="fas fa-info-circle text-yellow-400 mr-2"></i>
                        Requisitos
                    </h4>
                    <ul class="text-sm text-brand-text-secondary space-y-1">
                        <li>• Necesitas al menos <strong class="text-brand-text-primary">100 SPHE</strong> para crear una propuesta</li>
                        <li>• La votación comienza inmediatamente</li>
                        <li>• Periodo de votación: <strong class="text-brand-text-primary">3 días</strong></li>
                        <li>• Quórum necesario: <strong class="text-brand-text-primary">1,000 SPHE</strong> deben votar</li>
                        <li>• Máximo 2 propuestas activas por usuario</li>
                    </ul>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="document.getElementById('createProposalModal').classList.add('hidden')" class="flex-1 bg-brand-bg-primary hover:bg-brand-border text-brand-text-primary py-3 rounded-lg font-semibold transition">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 bg-brand-accent hover:bg-blue-600 text-white py-3 rounded-lg font-semibold transition">
                        <i class="fas fa-rocket mr-2"></i>
                        Crear Propuesta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Proposal Detail Modal -->
    <div id="proposalDetailModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg max-w-4xl w-full modal-content custom-scrollbar relative">
            <button onclick="document.getElementById('proposalDetailModal').classList.add('hidden')" class="absolute top-4 right-4 text-brand-text-secondary hover:text-brand-text-primary text-2xl z-10">
                <i class="fas fa-times"></i>
            </button>
            <!-- Modal content will be loaded here -->
        </div>
    </div>

    <!-- Delegate Modal -->
    <div id="delegateModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg max-w-md w-full p-6 relative">
            <button onclick="document.getElementById('delegateModal').classList.add('hidden')" class="absolute top-4 right-4 text-brand-text-secondary hover:text-brand-text-primary text-xl">
                <i class="fas fa-times"></i>
            </button>
            <!-- Modal content will be loaded here -->
        </div>
    </div>

    <!-- Global config for JS -->
    <script>
        window.__SPHERA_GOVERNANCE__ = {
            userId: <?php echo $userId; ?>,
            username: '<?php echo htmlspecialchars($username); ?>',
            userWallet: '<?php echo $userWallet ? htmlspecialchars($userWallet) : ''; ?>',
            apiBase: '/api/governance',
            web3ApiBase: '/api/web3',
            hasWallet: <?php echo $userWallet ? 'true' : 'false'; ?>,
            // Contract addresses (update with your deployed contracts)
            contracts: {
                governor: '0x0000000000000000000000000000000000000000', // TODO: Update
                token: '0x0000000000000000000000000000000000000000',    // TODO: Update
                timelock: '0x0000000000000000000000000000000000000000' // TODO: Update
            },
            // Supported chain IDs
            supportedChains: ['0x89', '0x13882'] // Polygon, Amoy
        };
        
        // Handler para el formulario de crear propuesta
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createProposalForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    const data = {
                        title: formData.get('title'),
                        description: formData.get('description'),
                        category: formData.get('category')
                    };
                    
                    if (!data.title || data.title.length < 10) {
                        window.notify.warning('El título debe tener al menos 10 caracteres', 'Validación');
                        return;
                    }

                    if (!data.description || data.description.length < 50) {
                        window.notify.warning('La descripción debe tener al menos 50 caracteres', 'Validación');
                        return;
                    }

                    if (!data.category) {
                        window.notify.warning('Por favor selecciona una categoría', 'Validación');
                        return;
                    }
                    
                    try {
                        const response = await fetch('/api/governance/create_proposal.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(data)
                        });
                        
                        const result = await response.json();

                        if (result.success) {
                            window.notify.success(result.message || 'Propuesta creada exitosamente', '¡Éxito!');
                            document.getElementById('createProposalModal').classList.add('hidden');
                            form.reset();
                            setTimeout(() => location.reload(), 1500); // Recargar para ver la nueva propuesta
                        } else {
                            window.notify.error(result.message || 'Error al crear propuesta', 'Error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        window.notify.error('Error al crear propuesta. Por favor intenta de nuevo.', 'Error');
                    }
                });
            }
        });
    </script>

    <!-- Notification System -->
    <script src="/assets/js/notifications.js"></script>

    <!-- Ethers.js library -->
    <script src="https://cdn.ethers.io/lib/ethers-5.7.2.umd.min.js"></script>

    <!-- Load Web3 utilities -->
    <script src="/assets/js/web3/web3-utils.js"></script>
    <script src="/assets/js/web3/web3-connector.js"></script>
    <script src="/assets/js/web3/web3-contracts.js"></script>
    <script src="/assets/js/web3/web3-signatures.js"></script>

    <!-- Load UI components -->
    <script src="/assets/js/components/wallet-button.js"></script>
    <script src="/assets/js/components/network-badge.js"></script>

    <!-- Load governance scripts -->
    <script src="/assets/js/governance/governance-api.js?v=1760320720"></script>
    <script src="/assets/js/governance/governance-stats.js?v=1760320720"></script>
    <script src="/assets/js/governance/governance-proposals.js?v=1760320720"></script>
    <script src="/assets/js/governance/governance-modal.js?v=1760320720"></script>
    <script src="/assets/js/governance/governance-web3.js"></script>
    <script src="/assets/js/governance/governance-main.js?v=1760320720"></script>
</body>
</html>
