<?php
/**
 * Dashboard de Staking
 * Página principal del sistema de staking
 */

require_once '../config/config.php';
require_once '../api/check_session.php';

$pageTitle = "Staking Dashboard";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - thesocialmask</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Web3.js -->
    <script src="https://cdn.jsdelivr.net/npm/web3@1.8.0/dist/web3.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/staking.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-2">
                    <i class="fas fa-coins text-primary"></i>
                    Staking Dashboard
                </h1>
                <p class="text-muted">Stake your SPHE tokens and earn rewards</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-gradient-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-2">Current Staked</h6>
                                <h3 class="mb-0" id="current-staked">0.0000 SPHE</h3>
                            </div>
                            <div class="icon-box">
                                <i class="fas fa-lock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-gradient-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-2">Pending Rewards</h6>
                                <h3 class="mb-0" id="pending-rewards">0.0000 SPHE</h3>
                                <small class="text-white-75">Real-time</small>
                            </div>
                            <div class="icon-box">
                                <i class="fas fa-gift fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-gradient-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-2">Total Rewards</h6>
                                <h3 class="mb-0" id="total-rewards">0.0000 SPHE</h3>
                                <small class="text-white-75">All time</small>
                            </div>
                            <div class="icon-box">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-gradient-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-2">Wallet Balance</h6>
                                <h3 class="mb-0" id="wallet-balance">0.0000 SPHE</h3>
                                <small class="text-white-75">Available</small>
                            </div>
                            <div class="icon-box">
                                <i class="fas fa-wallet fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary btn-lg" id="btn-stake">
                                <i class="fas fa-plus-circle"></i> Stake Tokens
                            </button>
                            <button class="btn btn-danger btn-lg" id="btn-unstake" disabled>
                                <i class="fas fa-minus-circle"></i> Unstake
                            </button>
                            <button class="btn btn-success btn-lg" id="btn-claim">
                                <i class="fas fa-gift"></i> Claim Rewards
                            </button>
                            <button class="btn btn-outline-secondary btn-lg ms-auto" id="btn-refresh">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staking Pools -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-swimming-pool"></i>
                            Staking Pools
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="pools-container">
                            <!-- Pools will be loaded dynamically -->
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading pools...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stake Details & Charts -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i>
                            Your Stake Details
                        </h5>
                    </div>
                    <div class="card-body" id="stake-details">
                        <p class="text-muted">No active stakes</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area"></i>
                            Rewards History
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="rewardsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i>
                            Recent Transactions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="transactions-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Pool</th>
                                        <th>TX Hash</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-tbody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Loading transactions...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Stats -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-lock-open fa-3x text-primary mb-3"></i>
                        <h6 class="text-muted">Total Value Locked</h6>
                        <h3 class="mb-0" id="total-tvl">0.00 SPHE</h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-success mb-3"></i>
                        <h6 class="text-muted">Total Stakers</h6>
                        <h3 class="mb-0" id="total-stakers">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-3x text-warning mb-3"></i>
                        <h6 class="text-muted">Average APY</h6>
                        <h3 class="mb-0" id="average-apy">0.00%</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stake Modal -->
    <div class="modal fade" id="stakeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle"></i>
                        Stake Tokens
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount to Stake</label>
                        <input type="number" class="form-control" id="stake-amount" 
                               placeholder="0.00" step="0.0001" min="0">
                        <small class="text-muted" id="available-balance">Available: 0.0000 SPHE</small>
                        <div id="stake-amount-error" class="mt-1"></div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        You need to approve tokens before staking
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirm-approve">
                        <i class="fas fa-check"></i> 1. Approve
                    </button>
                    <button type="button" class="btn btn-primary" id="confirm-stake" disabled>
                        <i class="fas fa-lock"></i> 2. Stake
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unstake Modal -->
    <div class="modal fade" id="unstakeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-minus-circle"></i>
                        Unstake Tokens
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount to Unstake</label>
                        <input type="number" class="form-control" id="unstake-amount" 
                               placeholder="Leave empty for all" step="0.0001" min="0">
                        <small class="text-muted" id="max-unstake">Staked: 0.0000 SPHE</small>
                        <div id="unstake-amount-error" class="mt-1"></div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Unstaking will also claim your pending rewards
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-unstake">
                        <i class="fas fa-unlock"></i> Unstake
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" style="display: none;">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-light mt-3 loading-text">Loading...</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Staking Scripts -->
    <script src="../assets/js/staking-manager.js"></script>
    <script src="../assets/js/staking-ui.js"></script>
    <script src="../assets/js/staking-charts.js"></script>
    
    <!-- Initialize -->
    <script>
        // Configuration
        const STAKING_CONTRACT_ADDRESS = '<?php echo getenv("STAKING_CONTRACT_ADDRESS") ?: "0x..."; ?>';
        const TOKEN_ADDRESS = '<?php echo getenv("SPHE_TOKEN_ADDRESS") ?: "0x..."; ?>';
        
        // Contract ABIs (load from JSON files or inline)
        const STAKING_CONTRACT_ABI = <?php echo file_get_contents('../escrow-system/abis/TokenStaking.json'); ?>;
        const TOKEN_ABI = <?php echo file_get_contents('../escrow-system/abis/ERC20.json'); ?>;

        // Initialize app
        let stakingManager;
        let stakingUI;

        document.addEventListener('DOMContentLoaded', async () => {
            try {
                // Initialize manager
                stakingManager = new StakingManager();
                await stakingManager.initialize(
                    STAKING_CONTRACT_ADDRESS,
                    STAKING_CONTRACT_ABI,
                    TOKEN_ADDRESS,
                    TOKEN_ABI
                );

                // Initialize UI
                stakingUI = new StakingUI(stakingManager);
                await stakingUI.initialize();

                // Load global stats
                await loadGlobalStats();

                // Load transactions history
                await loadTransactionsHistory();

                // Initialize charts
                initializeCharts();

                console.log('Staking Dashboard initialized successfully');

            } catch (error) {
                console.error('Error initializing dashboard:', error);
                alert('Error al inicializar el dashboard de staking. Por favor recarga la página.');
            }
        });

        // Load global statistics
        async function loadGlobalStats() {
            try {
                const response = await fetch('/api/staking/get_staking_stats.php');
                const data = await response.json();

                if (data.success) {
                    const stats = data.data.global;
                    document.getElementById('total-tvl').textContent = 
                        parseFloat(stats.total_value_locked).toFixed(2) + ' SPHE';
                    document.getElementById('total-stakers').textContent = stats.total_participants;
                    document.getElementById('average-apy').textContent = 
                        parseFloat(stats.weighted_system_apy).toFixed(2) + '%';
                }
            } catch (error) {
                console.error('Error loading global stats:', error);
            }
        }

        // Load transactions history
        async function loadTransactionsHistory() {
            try {
                const userId = sessionStorage.getItem('user_id') || 1;
                const response = await fetch(`/api/staking/get_staking_history.php?user_id=${userId}&limit=10`);
                const data = await response.json();

                if (data.success && data.data.transactions.length > 0) {
                    const tbody = document.getElementById('transactions-tbody');
                    tbody.innerHTML = data.data.transactions.map(tx => `
                        <tr>
                            <td>
                                <span class="badge bg-${getTypeColor(tx.transaction_type)}">
                                    ${tx.type_label}
                                </span>
                            </td>
                            <td>${parseFloat(tx.amount).toFixed(4)} SPHE</td>
                            <td>${tx.pool_name || '-'}</td>
                            <td>
                                <a href="https://polygonscan.com/tx/${tx.tx_hash}" target="_blank" class="text-decoration-none">
                                    ${tx.tx_hash.substring(0, 10)}...
                                    <i class="fas fa-external-link-alt fa-xs"></i>
                                </a>
                            </td>
                            <td><small>${new Date(tx.created_at).toLocaleString()}</small></td>
                            <td>
                                <span class="badge bg-${tx.status === 'confirmed' ? 'success' : 'warning'}">
                                    ${tx.status}
                                </span>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    document.getElementById('transactions-tbody').innerHTML = `
                        <tr><td colspan="6" class="text-center text-muted">No transactions yet</td></tr>
                    `;
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
            }
        }

        function getTypeColor(type) {
            const colors = {
                'stake': 'primary',
                'unstake': 'danger',
                'claim': 'success',
                'emergency_withdraw': 'warning'
            };
            return colors[type] || 'secondary';
        }

        // Refresh button
        document.getElementById('btn-refresh')?.addEventListener('click', async () => {
            await stakingUI.loadDashboard();
            await loadGlobalStats();
            await loadTransactionsHistory();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (stakingUI) stakingUI.cleanup();
        });
    </script>
</body>
</html>
