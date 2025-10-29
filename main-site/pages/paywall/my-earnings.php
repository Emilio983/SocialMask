<?php
/**
 * Página: Dashboard de Ganancias
 * Ruta: /pages/paywall/my-earnings.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'My Earnings';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - thesocialmask</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card.primary { border-color: #0d6efd; }
        .stat-card.success { border-color: #198754; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card.info { border-color: #0dcaf0; }
        
        .content-row {
            transition: background 0.2s;
        }
        
        .content-row:hover {
            background: #f8f9fa;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .withdraw-btn {
            font-size: 1.2rem;
            padding: 15px 30px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <div class="container py-5">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1><i class="bi bi-graph-up-arrow"></i> My Earnings</h1>
                        <p class="text-muted">Track your Pay-Per-View revenue</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/pages/paywall/create-content" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Content
                        </a>
                        <button class="btn btn-success withdraw-btn" onclick="withdrawFunds()" id="btnWithdraw">
                            <i class="bi bi-cash-coin"></i> Withdraw
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3">Loading earnings data...</p>
        </div>

        <!-- Stats Cards -->
        <div id="statsSection" style="display: none;">
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-2">Total Content</h6>
                                    <h2 class="mb-0" id="statTotalContent">0</h2>
                                </div>
                                <i class="bi bi-file-earmark-text text-primary" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-2">Total Sales</h6>
                                    <h2 class="mb-0" id="statTotalSales">0</h2>
                                </div>
                                <i class="bi bi-cart-check text-success" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-2">Gross Revenue</h6>
                                    <h2 class="mb-0">💎 <span id="statGrossRevenue">0</span></h2>
                                </div>
                                <i class="bi bi-cash-stack text-warning" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted mb-2">Net Earnings</h6>
                                    <h2 class="mb-0 text-success">💎 <span id="statNetEarnings">0</span></h2>
                                </div>
                                <i class="bi bi-piggy-bank text-info" style="font-size: 2rem;"></i>
                            </div>
                            <small class="text-muted">Available to withdraw</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Monthly Earnings (Last 12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Unique Buyers</span>
                                    <strong id="statUniqueBuyers">0</strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Avg. Conversion</span>
                                    <strong><span id="statConversion">0</span>%</strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Platform Fee (2.5%)</span>
                                    <strong>💎 <span id="statFees">0</span></strong>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Pending Withdrawal</span>
                                    <strong class="text-success">💎 <span id="statPending">0</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Performance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Content Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Content</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Sales</th>
                                    <th>Revenue</th>
                                    <th>Your Earnings</th>
                                </tr>
                            </thead>
                            <tbody id="contentTable">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                        <p class="mb-0 mt-2">No content yet</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Earnings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Earnings</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Content</th>
                                    <th>Buyer</th>
                                    <th>Amount</th>
                                    <th>Fee</th>
                                    <th>Net</th>
                                    <th>TX</th>
                                </tr>
                            </thead>
                            <tbody id="recentTable">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        No recent earnings
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" style="display: none;" class="text-center py-5">
            <i class="bi bi-wallet2 text-muted" style="font-size: 5rem;"></i>
            <h3 class="mt-4">No Earnings Yet</h3>
            <p class="text-muted">Start creating paid content to earn SPHE tokens</p>
            <a href="/pages/paywall/create-content" class="btn btn-primary btn-lg mt-3">
                <i class="bi bi-plus-circle"></i> Create Your First Content
            </a>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div class="modal fade" id="withdrawModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin"></i> Withdraw Funds</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="withdrawProgress" style="display: none;" class="text-center py-4">
                        <div class="spinner-border text-primary mb-3"></div>
                        <p id="withdrawStatus">Processing withdrawal...</p>
                    </div>
                    
                    <div id="withdrawForm">
                        <div class="alert alert-info">
                            <strong>Available Balance:</strong> 💎 <span id="withdrawBalance">0</span> SPHE
                        </div>
                        <p>You are about to withdraw your earnings from the smart contract to your wallet.</p>
                        <ul class="small text-muted">
                            <li>Gas fees will be paid by you</li>
                            <li>Transaction is instant</li>
                            <li>Funds go directly to your connected wallet</li>
                        </ul>
                    </div>
                    
                    <div id="withdrawSuccess" style="display: none;" class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Withdrawal Successful!</h5>
                        <p class="text-muted">Funds have been sent to your wallet</p>
                    </div>
                    
                    <div id="withdrawError" style="display: none;" class="text-center py-4">
                        <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Withdrawal Failed</h5>
                        <p class="text-danger" id="withdrawErrorMsg"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="btnConfirmWithdraw" onclick="confirmWithdraw()">
                        <i class="bi bi-check-lg"></i> Confirm Withdrawal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ethers@6.7.0/dist/ethers.umd.min.js"></script>
    <script src="/assets/js/wallet.js"></script>
    <script src="/assets/js/paywall-earnings.js"></script>
</body>
</html>
