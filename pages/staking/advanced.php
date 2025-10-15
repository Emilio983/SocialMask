<?php
/**
 * Advanced Staking Features
 * Auto-Compound, Referrals, Multi-Pool, Governance
 */

require_once '../config/config.php';
require_once '../api/check_session.php';

$pageTitle = "Advanced Staking";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - thesocialmask</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/web3@1.8.0/dist/web3.min.js"></script>
    <link rel="stylesheet" href="../assets/css/staking.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-2">
                    <i class="fas fa-rocket text-primary"></i>
                    Advanced Features
                </h1>
                <p class="text-muted">Maximize your rewards with advanced staking features</p>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#auto-compound-tab">
                    <i class="fas fa-sync-alt"></i> Auto-Compound
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#referrals-tab">
                    <i class="fas fa-users"></i> Referrals
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#multi-pool-tab">
                    <i class="fas fa-layer-group"></i> Multi-Pool
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#governance-tab">
                    <i class="fas fa-vote-yea"></i> Governance
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- AUTO-COMPOUND TAB -->
            <div class="tab-pane fade show active" id="auto-compound-tab">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-sync-alt"></i> Auto-Compound Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Auto-compound</strong> automatically reinvests your rewards back into staking, maximizing your returns through compound interest. A small gas fee (0.5%) is charged per execution.
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="auto-compound-toggle">
                                    <label class="form-check-label" for="auto-compound-toggle">
                                        <strong>Enable Auto-Compound</strong>
                                    </label>
                                </div>

                                <div id="auto-compound-settings" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Frequency (days)</label>
                                        <select class="form-select" id="compound-frequency">
                                            <option value="1">Every 1 day</option>
                                            <option value="3">Every 3 days</option>
                                            <option value="7" selected>Every 7 days</option>
                                            <option value="14">Every 14 days</option>
                                            <option value="30">Every 30 days</option>
                                        </select>
                                        <small class="text-muted">How often should rewards be compounded</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Minimum Rewards</label>
                                        <input type="number" class="form-control" id="min-rewards" 
                                               placeholder="0.0" step="0.01" min="0" value="1">
                                        <small class="text-muted">Minimum SPHE rewards required to trigger compound</small>
                                    </div>

                                    <button class="btn btn-primary btn-lg" id="btn-save-auto-compound">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Current Status</h6>
                            </div>
                            <div class="card-body" id="auto-compound-status">
                                <p class="text-muted">Loading...</p>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Compound History</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody id="compound-history">
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">No history</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- REFERRALS TAB -->
            <div class="tab-pane fade" id="referrals-tab">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-share-alt"></i> Your Referral Code
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-success">
                                    <i class="fas fa-gift"></i>
                                    Earn <strong>5% bonus APY</strong> for every user you refer! They get <strong>2% bonus APY</strong> too.
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Referral Code</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="referral-code" readonly>
                                        <button class="btn btn-outline-secondary" id="btn-copy-code">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Referral Link</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="referral-link" readonly>
                                        <button class="btn btn-outline-secondary" id="btn-copy-link">
                                            <i class="fas fa-link"></i> Copy
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button class="btn btn-success" id="btn-share-twitter">
                                        <i class="fab fa-twitter"></i> Share on Twitter
                                    </button>
                                    <button class="btn btn-primary" id="btn-share-telegram">
                                        <i class="fab fa-telegram"></i> Share on Telegram
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar"></i> Referral Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-4">
                                    <div class="col-4">
                                        <h3 id="total-referrals">0</h3>
                                        <small class="text-muted">Referrals</small>
                                    </div>
                                    <div class="col-4">
                                        <h3 id="referral-rewards">0</h3>
                                        <small class="text-muted">Rewards</small>
                                    </div>
                                    <div class="col-4">
                                        <h3 id="bonus-apy">0%</h3>
                                        <small class="text-muted">Bonus APY</small>
                                    </div>
                                </div>

                                <button class="btn btn-success btn-lg w-100" id="btn-claim-referral">
                                    <i class="fas fa-gift"></i> Claim Referral Rewards
                                </button>

                                <hr>

                                <h6>Your Referrals</h6>
                                <div id="referral-list" class="mt-3">
                                    <p class="text-muted">No referrals yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enter Referral Code -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-plus"></i> Have a Referral Code?
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label">Enter Referral Code</label>
                                        <input type="text" class="form-control" id="enter-referral-code" 
                                               placeholder="ABCD1234">
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-primary w-100" id="btn-apply-referral">
                                            <i class="fas fa-check"></i> Apply Code
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MULTI-POOL TAB -->
            <div class="tab-pane fade" id="multi-pool-tab">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Multi-Pool Staking</strong> allows you to stake in multiple pools simultaneously, diversifying your risk and maximizing returns.
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-layer-group"></i> Your Active Positions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="multi-pool-positions">
                                    <p class="text-muted">Loading positions...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">Active Pools</small>
                                    <h4 id="active-pools-count">0</h4>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Total Staked</small>
                                    <h4 id="multi-pool-total">0 SPHE</h4>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Avg APY</small>
                                    <h4 id="multi-pool-avg-apy">0%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GOVERNANCE TAB -->
            <div class="tab-pane fade" id="governance-tab">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-vote-yea"></i> Governance Tokens
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle"></i>
                                    Earn <strong>1 governance token</strong> for every <strong>100 SPHE</strong> staked or compounded. Use them to vote on platform decisions!
                                </div>

                                <div class="text-center mb-4">
                                    <h1 id="governance-balance">0</h1>
                                    <p class="text-muted">Governance Tokens Available</p>
                                </div>

                                <div class="row text-center mb-4">
                                    <div class="col-6">
                                        <h4 id="governance-earned">0</h4>
                                        <small class="text-muted">Total Earned</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 id="governance-claimed">0</h4>
                                        <small class="text-muted">Total Claimed</small>
                                    </div>
                                </div>

                                <button class="btn btn-warning btn-lg w-100" id="btn-claim-governance">
                                    <i class="fas fa-coins"></i> Claim Governance Tokens
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-poll"></i> Active Proposals
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Coming soon... Governance voting will be enabled here.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" style="display: none;">
        <div class="text-center">
            <div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div>
            <p class="text-light mt-3 loading-text">Loading...</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/staking-manager.js"></script>
    <script src="../assets/js/staking-advanced.js"></script>
    
    <script>
        let stakingManager, advancedManager;

        document.addEventListener('DOMContentLoaded', async () => {
            try {
                // Initialize managers
                stakingManager = new StakingManager();
                await stakingManager.initialize(
                    '<?php echo getenv("STAKING_CONTRACT_ADDRESS"); ?>',
                    <?php echo file_get_contents('../escrow-system/abis/TokenStaking.json'); ?>,
                    '<?php echo getenv("SPHE_TOKEN_ADDRESS"); ?>',
                    <?php echo file_get_contents('../escrow-system/abis/ERC20.json'); ?>
                );

                advancedManager = new StakingAdvancedManager(stakingManager);
                await advancedManager.initialize(
                    '<?php echo getenv("STAKING_ADVANCED_CONTRACT_ADDRESS"); ?>',
                    <?php echo file_get_contents('../contracts/abis/TokenStakingAdvanced.json'); ?>
                );

                // Load all features
                await loadAutoCompoundSettings();
                await loadReferralStats();
                await loadMultiPoolPositions();
                await loadGovernanceBalance();

                setupEventListeners();

            } catch (error) {
                console.error('Initialization error:', error);
                alert('Error al inicializar el sistema de staking avanzado. Por favor recarga la página.');
            }
        });

        // Setup event listeners
        function setupEventListeners() {
            // Auto-compound toggle
            document.getElementById('auto-compound-toggle').addEventListener('change', (e) => {
                document.getElementById('auto-compound-settings').style.display = 
                    e.target.checked ? 'block' : 'none';
            });

            // Save auto-compound
            document.getElementById('btn-save-auto-compound').addEventListener('click', saveAutoCompound);

            // Referral actions
            document.getElementById('btn-copy-code').addEventListener('click', () => copyToClipboard('code'));
            document.getElementById('btn-copy-link').addEventListener('click', () => copyToClipboard('link'));
            document.getElementById('btn-apply-referral').addEventListener('click', applyReferralCode);
            document.getElementById('btn-claim-referral').addEventListener('click', claimReferralRewards);

            // Governance
            document.getElementById('btn-claim-governance').addEventListener('click', claimGovernanceTokens);
        }

        // Load auto-compound settings
        async function loadAutoCompoundSettings() {
            const accounts = await stakingManager.web3.eth.getAccounts();
            const settings = await advancedManager.getAutoCompoundSettings(accounts[0]);
            
            if (settings) {
                document.getElementById('auto-compound-toggle').checked = settings.enabled;
                document.getElementById('auto-compound-settings').style.display = 
                    settings.enabled ? 'block' : 'none';
                
                document.getElementById('auto-compound-status').innerHTML = `
                    <p><strong>Status:</strong> ${settings.enabled ? 'Enabled' : 'Disabled'}</p>
                    ${settings.enabled ? `
                        <p><strong>Frequency:</strong> ${settings.frequency / 86400} days</p>
                        <p><strong>Next Compound:</strong> ${advancedManager.formatTimeRemaining(settings.nextCompoundIn)}</p>
                    ` : ''}
                `;
            }
        }

        // Load referral stats
        async function loadReferralStats() {
            const code = await advancedManager.getReferralCode();
            const link = advancedManager.generateReferralLink(code);
            
            document.getElementById('referral-code').value = code;
            document.getElementById('referral-link').value = link;

            // Load stats from backend
            const analytics = await advancedManager.getAnalytics('user');
            const stats = analytics.user_stats;
            
            document.getElementById('total-referrals').textContent = stats.total_referred || 0;
            document.getElementById('referral-rewards').textContent = 
                parseFloat(stats.referral_rewards_earned || 0).toFixed(2);
            document.getElementById('bonus-apy').textContent = 
                (stats.referrer_bonus_apy || 0) + '%';
        }

        // Load multi-pool positions
        async function loadMultiPoolPositions() {
            const analytics = await advancedManager.getAnalytics('user');
            const positions = analytics.multi_pool_positions || [];
            
            document.getElementById('active-pools-count').textContent = positions.length;
            
            if (positions.length === 0) {
                document.getElementById('multi-pool-positions').innerHTML = 
                    '<p class="text-muted">No active positions</p>';
            } else {
                // Render positions...
            }
        }

        // Load governance balance
        async function loadGovernanceBalance() {
            const analytics = await advancedManager.getAnalytics('user');
            const gov = analytics.governance;
            
            document.getElementById('governance-balance').textContent = 
                parseFloat(gov.tokens_available || 0).toFixed(2);
            document.getElementById('governance-earned').textContent = 
                parseFloat(gov.tokens_earned || 0).toFixed(2);
            document.getElementById('governance-claimed').textContent = 
                parseFloat(gov.tokens_claimed || 0).toFixed(2);
        }

        // Save auto-compound settings
        async function saveAutoCompound() {
            const frequency = parseInt(document.getElementById('compound-frequency').value);
            const minRewards = parseFloat(document.getElementById('min-rewards').value);
            
            try {
                showLoading('Enabling auto-compound...');
                await advancedManager.enableAutoCompound(frequency, minRewards);
                hideLoading();
                showSuccess('Auto-compound enabled successfully!');
                await loadAutoCompoundSettings();
            } catch (error) {
                hideLoading();
                showError('Error enabling auto-compound: ' + error.message);
            }
        }

        // Copy to clipboard
        async function copyToClipboard(type) {
            const text = type === 'code' ? 
                document.getElementById('referral-code').value :
                document.getElementById('referral-link').value;
            
            await advancedManager.copyToClipboard(text);
            showSuccess('Copied to clipboard!');
        }

        // Apply referral code
        async function applyReferralCode() {
            const code = document.getElementById('enter-referral-code').value.trim();
            if (!code) return;
            
            try {
                showLoading('Applying referral code...');
                await advancedManager.registerWithReferralCode(code);
                hideLoading();
                showSuccess('Referral code applied!');
                await loadReferralStats();
            } catch (error) {
                hideLoading();
                showError('Error: ' + error.message);
            }
        }

        // Claim referral rewards
        async function claimReferralRewards() {
            try {
                showLoading('Claiming referral rewards...');
                await advancedManager.claimReferralRewards();
                hideLoading();
                showSuccess('Referral rewards claimed!');
                await loadReferralStats();
            } catch (error) {
                hideLoading();
                showError('Error: ' + error.message);
            }
        }

        // Claim governance tokens
        async function claimGovernanceTokens() {
            try {
                showLoading('Claiming governance tokens...');
                await advancedManager.claimGovernanceTokens();
                hideLoading();
                showSuccess('Governance tokens claimed!');
                await loadGovernanceBalance();
            } catch (error) {
                hideLoading();
                showError('Error: ' + error.message);
            }
        }

        // UI utilities
        function showLoading(message) {
            document.querySelector('.loading-text').textContent = message;
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function showSuccess(message) {
            alert(message); // Replace with toast
        }

        function showError(message) {
            alert(message); // Replace with toast
        }
    </script>
</body>
</html>
