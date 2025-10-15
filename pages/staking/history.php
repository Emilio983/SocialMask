<?php
/**
 * Historial de Transacciones de Staking
 */

require_once '../config/config.php';
require_once '../api/check_session.php';

$pageTitle = "Staking History";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - thesocialmask</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/staking.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-2">
                    <i class="fas fa-history text-primary"></i>
                    Staking History
                </h1>
                <p class="text-muted">Complete transaction history of your staking activities</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Transaction Type</label>
                                <select class="form-select" id="filter-type">
                                    <option value="all">All Types</option>
                                    <option value="stake">Stake</option>
                                    <option value="unstake">Unstake</option>
                                    <option value="claim">Claim Rewards</option>
                                    <option value="emergency_withdraw">Emergency Withdraw</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Pool</label>
                                <select class="form-select" id="filter-pool">
                                    <option value="all">All Pools</option>
                                    <option value="0">Flexible</option>
                                    <option value="1">30 Days</option>
                                    <option value="2">90 Days</option>
                                    <option value="3">180 Days</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="filter-from">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="filter-to">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button class="btn btn-primary w-100" id="btn-apply-filters">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-arrow-up fa-2x text-primary mb-2"></i>
                        <h6 class="text-muted">Total Stakes</h6>
                        <h4 id="stats-stakes">0</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                        <h6 class="text-muted">Total Unstakes</h6>
                        <h4 id="stats-unstakes">0</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-gift fa-2x text-success mb-2"></i>
                        <h6 class="text-muted">Total Claims</h6>
                        <h4 id="stats-claims">0</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-warning mb-2"></i>
                        <h6 class="text-muted">Total Volume</h6>
                        <h4 id="stats-volume">0 SPHE</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i>
                            Transactions
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" id="btn-export">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Pool</th>
                                        <th>TX Hash</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2 text-muted">Loading transactions...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav id="pagination-container" class="mt-3" style="display: none;">
                            <ul class="pagination justify-content-center" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // State
        let currentPage = 1;
        const perPage = 50;
        let currentFilters = {
            type: 'all',
            pool: 'all',
            from: '',
            to: ''
        };

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadTransactions();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('btn-apply-filters').addEventListener('click', () => {
                currentFilters = {
                    type: document.getElementById('filter-type').value,
                    pool: document.getElementById('filter-pool').value,
                    from: document.getElementById('filter-from').value,
                    to: document.getElementById('filter-to').value
                };
                currentPage = 1;
                loadTransactions();
            });

            document.getElementById('btn-export').addEventListener('click', exportToCSV);
        }

        // Load transactions
        async function loadTransactions() {
            try {
                const userId = sessionStorage.getItem('user_id') || 1;
                
                // Build query parameters
                const params = new URLSearchParams({
                    user_id: userId,
                    page: currentPage,
                    limit: perPage
                });

                if (currentFilters.type !== 'all') params.append('type', currentFilters.type);
                if (currentFilters.pool !== 'all') params.append('pool_id', currentFilters.pool);
                if (currentFilters.from) params.append('from_date', currentFilters.from);
                if (currentFilters.to) params.append('to_date', currentFilters.to);

                const response = await fetch(`/api/staking/get_staking_history.php?${params}`);
                const data = await response.json();

                if (data.success) {
                    renderTransactions(data.data.transactions);
                    renderPagination(data.data.pagination);
                    updateStats(data.data);
                } else {
                    showError('Error loading transactions');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error loading transactions');
            }
        }

        // Render transactions
        function renderTransactions(transactions) {
            const tbody = document.getElementById('transactions-tbody');

            if (transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No transactions found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = transactions.map(tx => `
                <tr>
                    <td>
                        <div>${formatDate(tx.created_at)}</div>
                        <small class="text-muted">${formatTime(tx.created_at)}</small>
                    </td>
                    <td>
                        <span class="badge bg-${getTypeColor(tx.transaction_type)}">
                            <i class="${getTypeIcon(tx.transaction_type)}"></i>
                            ${tx.type_label}
                        </span>
                    </td>
                    <td>
                        <strong>${parseFloat(tx.amount).toFixed(4)}</strong> SPHE
                    </td>
                    <td>
                        ${tx.pool_name ? `<span class="badge bg-secondary">${tx.pool_name}</span>` : '-'}
                    </td>
                    <td>
                        <a href="https://polygonscan.com/tx/${tx.tx_hash}" 
                           target="_blank" 
                           class="text-decoration-none"
                           title="${tx.tx_hash}">
                            ${tx.tx_hash.substring(0, 8)}...${tx.tx_hash.substring(58)}
                            <i class="fas fa-external-link-alt fa-xs"></i>
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-${tx.status === 'confirmed' ? 'success' : tx.status === 'pending' ? 'warning' : 'danger'}">
                            ${tx.status}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" 
                                onclick="viewDetails('${tx.tx_hash}')"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Render pagination
        function renderPagination(pagination) {
            const container = document.getElementById('pagination-container');
            const paginationEl = document.getElementById('pagination');

            if (pagination.total_pages <= 1) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            let html = '';

            // Previous
            html += `
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;

            // Pages
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

            if (startPage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>`;
                if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                    </li>
                `;
            }

            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.total_pages}); return false;">${pagination.total_pages}</a></li>`;
            }

            // Next
            html += `
                <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;

            paginationEl.innerHTML = html;
        }

        // Update summary stats
        function updateStats(data) {
            const summary = data.summary;
            document.getElementById('stats-stakes').textContent = summary.total_stakes || 0;
            document.getElementById('stats-unstakes').textContent = summary.total_unstakes || 0;
            document.getElementById('stats-claims').textContent = summary.total_claims || 0;
            document.getElementById('stats-volume').textContent = 
                parseFloat(summary.total_volume || 0).toFixed(2) + ' SPHE';
        }

        // Change page
        function changePage(page) {
            currentPage = page;
            loadTransactions();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Export to CSV
        async function exportToCSV() {
            try {
                const userId = sessionStorage.getItem('user_id') || 1;
                const params = new URLSearchParams({
                    user_id: userId,
                    limit: 10000 // Get all
                });

                if (currentFilters.type !== 'all') params.append('type', currentFilters.type);
                if (currentFilters.pool !== 'all') params.append('pool_id', currentFilters.pool);
                if (currentFilters.from) params.append('from_date', currentFilters.from);
                if (currentFilters.to) params.append('to_date', currentFilters.to);

                const response = await fetch(`/api/staking/get_staking_history.php?${params}`);
                const data = await response.json();

                if (data.success) {
                    const csv = convertToCSV(data.data.transactions);
                    downloadCSV(csv, 'staking_history.csv');
                }
            } catch (error) {
                console.error('Error exporting:', error);
                alert('Error exporting data');
            }
        }

        // Convert to CSV
        function convertToCSV(transactions) {
            const headers = ['Date', 'Type', 'Amount', 'Pool', 'TX Hash', 'Status'];
            const rows = transactions.map(tx => [
                formatDate(tx.created_at) + ' ' + formatTime(tx.created_at),
                tx.type_label,
                parseFloat(tx.amount).toFixed(4),
                tx.pool_name || '-',
                tx.tx_hash,
                tx.status
            ]);

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.join(','))
            ].join('\n');

            return csvContent;
        }

        // Download CSV
        function downloadCSV(csv, filename) {
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // View transaction details
        function viewDetails(txHash) {
            window.open(`https://polygonscan.com/tx/${txHash}`, '_blank');
        }

        // Utility functions
        function getTypeColor(type) {
            const colors = {
                'stake': 'primary',
                'unstake': 'danger',
                'claim': 'success',
                'emergency_withdraw': 'warning'
            };
            return colors[type] || 'secondary';
        }

        function getTypeIcon(type) {
            const icons = {
                'stake': 'fas fa-arrow-up',
                'unstake': 'fas fa-arrow-down',
                'claim': 'fas fa-gift',
                'emergency_withdraw': 'fas fa-exclamation-triangle'
            };
            return icons[type] || 'fas fa-circle';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showError(message) {
            alert(message);
        }
    </script>
</body>
</html>
