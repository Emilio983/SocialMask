<?php
/**
 * Página: Mis Compras
 * Ruta: /pages/paywall/my-purchases.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'My Purchases';
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
    <style>
        .purchase-card {
            transition: all 0.3s;
            border-left: 4px solid #dee2e6;
        }
        
        .purchase-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .purchase-card.confirmed {
            border-left-color: #198754;
        }
        
        .purchase-card.pending {
            border-left-color: #ffc107;
        }
        
        .purchase-card.failed {
            border-left-color: #dc3545;
        }
        
        .content-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
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
                        <h1><i class="bi bi-bag-check"></i> My Purchases</h1>
                        <p class="text-muted">Content you've unlocked</p>
                    </div>
                    <a href="/pages/paywall/browse" class="btn btn-primary">
                        <i class="bi bi-search"></i> Browse Content
                    </a>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3">Loading your purchases...</p>
        </div>

        <!-- Stats Section -->
        <div id="statsSection" style="display: none;">
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="bi bi-cart-check text-primary" style="font-size: 2.5rem;"></i>
                            <h3 class="mt-2 mb-0" id="statTotalPurchases">0</h3>
                            <p class="text-muted mb-0">Total Purchases</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="bi bi-collection text-success" style="font-size: 2.5rem;"></i>
                            <h3 class="mt-2 mb-0" id="statUniqueContent">0</h3>
                            <p class="text-muted mb-0">Unique Content</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="bi bi-wallet2 text-warning" style="font-size: 2.5rem;"></i>
                            <h3 class="mt-2 mb-0">💎 <span id="statTotalSpent">0</span></h3>
                            <p class="text-muted mb-0">Total Spent</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-check text-info" style="font-size: 2.5rem;"></i>
                            <h3 class="mt-2 mb-0" id="statDaysActive">0</h3>
                            <p class="text-muted mb-0">Days Active</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Status</label>
                            <select class="form-select" id="filterStatus" onchange="filterPurchases()">
                                <option value="">All Status</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort by</label>
                            <select class="form-select" id="sortBy" onchange="sortPurchases()">
                                <option value="date_desc">Newest First</option>
                                <option value="date_asc">Oldest First</option>
                                <option value="price_desc">Price: High to Low</option>
                                <option value="price_asc">Price: Low to High</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Search by title..." onkeyup="searchPurchases()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchases Grid -->
            <div id="purchasesGrid" class="row g-4">
                <!-- Populated by JavaScript -->
            </div>

            <!-- Pagination -->
            <div id="pagination" class="mt-4 d-flex justify-content-center">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" style="display: none;" class="text-center py-5">
            <i class="bi bi-bag-x text-muted" style="font-size: 5rem;"></i>
            <h3 class="mt-4">No Purchases Yet</h3>
            <p class="text-muted">Start exploring and unlock exclusive content</p>
            <a href="/pages/paywall/browse" class="btn btn-primary btn-lg mt-3">
                <i class="bi bi-search"></i> Browse Content
            </a>
        </div>
    </div>

    <!-- Content Modal -->
    <div class="modal fade" id="contentModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <a href="#" id="modalViewContent" class="btn btn-primary" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i> View Full Content
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/paywall-purchases.js"></script>
</body>
</html>
