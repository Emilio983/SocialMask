/**
 * PayPerView - Earnings Dashboard
 * Handles earnings display and withdrawal
 */

let earningsData = null;
let monthlyChart = null;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', async function() {
    await loadEarningsData();
});

/**
 * Load Earnings Data
 */
async function loadEarningsData() {
    try {
        const response = await fetch('/api/paywall/get_earnings.php', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            earningsData = data;
            
            // Check if user has any content
            if (data.stats.total_content === 0) {
                showEmptyState();
            } else {
                displayEarnings(data);
            }
        } else {
            throw new Error(data.message || 'Failed to load earnings');
        }
        
    } catch (error) {
        console.error('Error loading earnings:', error);
        document.getElementById('loadingState').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Failed to load earnings: ${error.message}
            </div>
        `;
    }
}

/**
 * Display Earnings
 */
function displayEarnings(data) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('statsSection').style.display = 'block';
    
    // Update stats cards
    document.getElementById('statTotalContent').textContent = data.stats.total_content;
    document.getElementById('statTotalSales').textContent = data.stats.total_sales;
    document.getElementById('statGrossRevenue').textContent = parseFloat(data.stats.total_revenue).toFixed(2);
    document.getElementById('statNetEarnings').textContent = parseFloat(data.stats.net_earnings).toFixed(2);
    document.getElementById('statUniqueBuyers').textContent = data.stats.unique_buyers;
    document.getElementById('statConversion').textContent = parseFloat(data.stats.avg_conversion_rate).toFixed(1);
    
    const totalFees = data.stats.total_revenue - data.stats.net_earnings;
    document.getElementById('statFees').textContent = totalFees.toFixed(2);
    document.getElementById('statPending').textContent = parseFloat(data.stats.net_earnings).toFixed(2);
    
    // Update withdraw button balance
    document.getElementById('withdrawBalance').textContent = parseFloat(data.stats.net_earnings).toFixed(2);
    
    // Disable withdraw if no balance
    if (parseFloat(data.stats.net_earnings) === 0) {
        document.getElementById('btnWithdraw').disabled = true;
        document.getElementById('btnWithdraw').title = 'No balance to withdraw';
    }
    
    // Display content performance
    displayContentPerformance(data.earnings_by_content);
    
    // Display recent earnings
    displayRecentEarnings(data.recent_earnings);
    
    // Create monthly chart
    createMonthlyChart(data.monthly_earnings);
}

/**
 * Display Content Performance
 */
function displayContentPerformance(contents) {
    const tbody = document.getElementById('contentTable');
    
    if (!contents || contents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    No sales yet
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = contents.map(content => `
        <tr class="content-row">
            <td>
                <strong>${escapeHtml(content.title)}</strong>
                <br>
                <small class="text-muted">ID: ${content.contract_content_id}</small>
            </td>
            <td>
                <span class="badge bg-secondary">${content.content_type}</span>
            </td>
            <td>💎 ${parseFloat(content.price).toFixed(2)}</td>
            <td><strong>${content.total_earnings || 0}</strong></td>
            <td>💎 ${parseFloat(content.total_amount || 0).toFixed(2)}</td>
            <td><strong class="text-success">💎 ${parseFloat(content.total_net || 0).toFixed(2)}</strong></td>
        </tr>
    `).join('');
}

/**
 * Display Recent Earnings
 */
function displayRecentEarnings(earnings) {
    const tbody = document.getElementById('recentTable');
    
    if (!earnings || earnings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-3">
                    No recent earnings
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = earnings.map(earning => `
        <tr>
            <td><small>${formatDate(earning.earned_at)}</small></td>
            <td><small>${escapeHtml(earning.content_title)}</small></td>
            <td><small>@${escapeHtml(earning.buyer_username)}</small></td>
            <td><small>💎 ${parseFloat(earning.amount).toFixed(2)}</small></td>
            <td><small class="text-danger">-${parseFloat(earning.fee).toFixed(2)}</small></td>
            <td><small class="text-success">💎 ${parseFloat(earning.net_amount).toFixed(2)}</small></td>
            <td>
                <a href="https://polygonscan.com/tx/${earning.tx_hash}" 
                   target="_blank" class="text-decoration-none" title="View on PolygonScan">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </td>
        </tr>
    `).join('');
}

/**
 * Create Monthly Chart
 */
function createMonthlyChart(monthlyData) {
    const ctx = document.getElementById('monthlyChart');
    
    if (!monthlyData || monthlyData.length === 0) {
        ctx.parentElement.innerHTML = '<p class="text-center text-muted py-5">No data yet</p>';
        return;
    }
    
    // Sort by month ascending
    monthlyData.sort((a, b) => a.month.localeCompare(b.month));
    
    const labels = monthlyData.map(d => {
        const [year, month] = d.month.split('-');
        return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    
    const revenueData = monthlyData.map(d => parseFloat(d.total_amount));
    const earningsData = monthlyData.map(d => parseFloat(d.total_net));
    
    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Gross Revenue',
                    data: revenueData,
                    backgroundColor: 'rgba(255, 193, 7, 0.5)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 2
                },
                {
                    label: 'Net Earnings',
                    data: earningsData,
                    backgroundColor: 'rgba(25, 135, 84, 0.5)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': 💎 ' + context.parsed.y.toFixed(2) + ' SPHE';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '💎 ' + value;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Show Empty State
 */
function showEmptyState() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('emptyState').style.display = 'block';
}

/**
 * Withdraw Funds
 */
function withdrawFunds() {
    const modal = new bootstrap.Modal(document.getElementById('withdrawModal'));
    
    // Reset modal state
    document.getElementById('withdrawForm').style.display = 'block';
    document.getElementById('withdrawProgress').style.display = 'none';
    document.getElementById('withdrawSuccess').style.display = 'none';
    document.getElementById('withdrawError').style.display = 'none';
    document.getElementById('btnConfirmWithdraw').style.display = 'inline-block';
    
    modal.show();
}

/**
 * Confirm Withdraw
 */
async function confirmWithdraw() {
    document.getElementById('withdrawForm').style.display = 'none';
    document.getElementById('withdrawProgress').style.display = 'block';
    document.getElementById('btnConfirmWithdraw').style.display = 'none';
    
    try {
        // Get contract
        const contract = await getPayPerViewContract();
        
        // Update status
        document.getElementById('withdrawStatus').textContent = 'Sending transaction...';
        
        // Call withdrawFunds
        const tx = await contract.withdrawFunds();
        
        document.getElementById('withdrawStatus').textContent = 'Confirming transaction...';
        
        // Wait for confirmation
        const receipt = await tx.wait();
        
        console.log('Withdrawal successful:', receipt);
        
        // Show success
        document.getElementById('withdrawProgress').style.display = 'none';
        document.getElementById('withdrawSuccess').style.display = 'block';
        
        // Reload earnings after 2 seconds
        setTimeout(() => {
            location.reload();
        }, 2000);
        
    } catch (error) {
        console.error('Withdrawal error:', error);
        
        document.getElementById('withdrawProgress').style.display = 'none';
        document.getElementById('withdrawError').style.display = 'block';
        document.getElementById('withdrawErrorMsg').textContent = error.message || 'Transaction failed';
    }
}

/**
 * Get PayPerView contract instance
 */
async function getPayPerViewContract() {
    if (!window.smartWalletProvider) {
        throw new Error('Smart Wallet not available');
    }
    
    const provider = new ethers.BrowserProvider(window.smartWalletProvider);
    const signer = await provider.getSigner();
    
    // Load ABI
    const response = await fetch('/escrow-system/abis/PayPerView.json');
    const artifact = await response.json();
    
    return new ethers.Contract(
        window.PAYWALL_CONTRACT_ADDRESS,
        artifact.abi,
        signer
    );
}

/**
 * Utility Functions
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
