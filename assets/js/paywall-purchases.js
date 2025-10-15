/**
 * PayPerView - My Purchases
 * Handles purchase history display and filtering
 */

let allPurchases = [];
let filteredPurchases = [];
let currentPage = 1;
const itemsPerPage = 12;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', async function() {
    await loadPurchases();
});

/**
 * Load Purchases
 */
async function loadPurchases() {
    try {
        const response = await fetch('/api/paywall/my_purchases.php', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            allPurchases = data.purchases || [];
            filteredPurchases = [...allPurchases];
            
            if (allPurchases.length === 0) {
                showEmptyState();
            } else {
                displayPurchases(data);
            }
        } else {
            throw new Error(data.message || 'Failed to load purchases');
        }
        
    } catch (error) {
        console.error('Error loading purchases:', error);
        document.getElementById('loadingState').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Failed to load purchases: ${error.message}
            </div>
        `;
    }
}

/**
 * Display Purchases
 */
function displayPurchases(data) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('statsSection').style.display = 'block';
    
    // Update stats
    document.getElementById('statTotalPurchases').textContent = data.stats.total_purchases;
    document.getElementById('statUniqueContent').textContent = data.stats.unique_content;
    document.getElementById('statTotalSpent').textContent = parseFloat(data.stats.total_spent).toFixed(2);
    document.getElementById('statDaysActive').textContent = data.stats.days_active;
    
    // Render purchases
    renderPurchases();
}

/**
 * Render Purchases
 */
function renderPurchases() {
    const grid = document.getElementById('purchasesGrid');
    
    // Calculate pagination
    const totalPages = Math.ceil(filteredPurchases.length / itemsPerPage);
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pagePurchases = filteredPurchases.slice(start, end);
    
    if (pagePurchases.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">No purchases found with current filters</p>
            </div>
        `;
        document.getElementById('pagination').innerHTML = '';
        return;
    }
    
    grid.innerHTML = pagePurchases.map(purchase => `
        <div class="col-md-4">
            <div class="card purchase-card ${purchase.status}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge bg-${getStatusColor(purchase.status)}">
                            ${purchase.status.toUpperCase()}
                        </span>
                        <small class="text-muted">${formatDate(purchase.purchased_at)}</small>
                    </div>
                    
                    <h5 class="card-title">${escapeHtml(purchase.content_title)}</h5>
                    <p class="card-text text-muted small">${escapeHtml(purchase.description || '').substring(0, 100)}...</p>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-secondary">${purchase.content_type}</span>
                        <strong class="text-primary">💎 ${parseFloat(purchase.price).toFixed(2)}</strong>
                    </div>
                    
                    <div class="d-grid gap-2">
                        ${purchase.status === 'confirmed' ? `
                            <button class="btn btn-sm btn-primary" onclick="viewContent(${purchase.content_id})">
                                <i class="bi bi-unlock"></i> View Content
                            </button>
                        ` : ''}
                        
                        <a href="https://polygonscan.com/tx/${purchase.tx_hash}" 
                           target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right"></i> View Transaction
                        </a>
                        
                        ${purchase.gelato_task_id ? `
                            <a href="https://relay.gelato.digital/tasks/status/${purchase.gelato_task_id}" 
                               target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-info-circle"></i> Track Gelato Task
                            </a>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    // Render pagination
    renderPagination(totalPages);
}

/**
 * Render Pagination
 */
function renderPagination(totalPages) {
    const pagination = document.getElementById('pagination');
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '<nav><ul class="pagination">';
    
    // Previous
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Pages
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    html += '</ul></nav>';
    pagination.innerHTML = html;
}

/**
 * Go to Page
 */
function goToPage(page) {
    currentPage = page;
    renderPurchases();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Filter Purchases
 */
function filterPurchases() {
    const status = document.getElementById('filterStatus').value;
    
    filteredPurchases = allPurchases.filter(purchase => {
        if (status && purchase.status !== status) return false;
        return true;
    });
    
    currentPage = 1;
    renderPurchases();
}

/**
 * Sort Purchases
 */
function sortPurchases() {
    const sortBy = document.getElementById('sortBy').value;
    
    filteredPurchases.sort((a, b) => {
        switch(sortBy) {
            case 'date_desc':
                return new Date(b.purchased_at) - new Date(a.purchased_at);
            case 'date_asc':
                return new Date(a.purchased_at) - new Date(b.purchased_at);
            case 'price_desc':
                return parseFloat(b.price) - parseFloat(a.price);
            case 'price_asc':
                return parseFloat(a.price) - parseFloat(b.price);
            default:
                return 0;
        }
    });
    
    renderPurchases();
}

/**
 * Search Purchases
 */
function searchPurchases() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    
    if (!query) {
        filteredPurchases = [...allPurchases];
    } else {
        filteredPurchases = allPurchases.filter(purchase => 
            purchase.content_title.toLowerCase().includes(query) ||
            (purchase.description && purchase.description.toLowerCase().includes(query))
        );
    }
    
    currentPage = 1;
    renderPurchases();
}

/**
 * View Content
 */
async function viewContent(contentId) {
    try {
        const response = await fetch(`/api/paywall/get_content.php?id=${contentId}`, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.has_access) {
            const modal = new bootstrap.Modal(document.getElementById('contentModal'));
            
            document.getElementById('modalTitle').textContent = data.content.title;
            document.getElementById('modalBody').innerHTML = `
                <div class="mb-3">
                    <strong>Type:</strong> <span class="badge bg-secondary">${data.content.content_type}</span>
                </div>
                <div class="mb-3">
                    <strong>Description:</strong>
                    <p>${escapeHtml(data.content.description)}</p>
                </div>
                ${data.content.content_url ? `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> You have access to this content!
                    </div>
                ` : ''}
            `;
            
            document.getElementById('modalViewContent').href = data.content.content_url;
            
            modal.show();
        } else {
            alert('Access denied or content not found');
        }
        
    } catch (error) {
        console.error('Error viewing content:', error);
        alert('Failed to load content: ' + error.message);
    }
}

/**
 * Show Empty State
 */
function showEmptyState() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('emptyState').style.display = 'block';
}

/**
 * Utility Functions
 */
function getStatusColor(status) {
    switch(status) {
        case 'confirmed': return 'success';
        case 'pending': return 'warning';
        case 'failed': return 'danger';
        default: return 'secondary';
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
