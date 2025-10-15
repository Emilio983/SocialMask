/**
 * PayPerView - Content Card Component
 * Reusable component for displaying locked/unlocked content
 */

class PaywallContentCard {
    constructor(content, options = {}) {
        this.content = content;
        this.options = {
            showPreview: options.showPreview !== false,
            showCreator: options.showCreator !== false,
            showStats: options.showStats !== false,
            onPurchase: options.onPurchase || null,
            onView: options.onView || null
        };
    }

    /**
     * Render the card HTML
     */
    render() {
        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;

        return `
            <div class="paywall-content-card card h-100" data-content-id="${this.content.id}">
                ${this.renderHeader()}
                ${this.renderBody()}
                ${this.renderFooter()}
            </div>
        `;
    }

    /**
     * Render card header
     */
    renderHeader() {
        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;

        return `
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="badge bg-secondary">${this.content.content_type}</span>
                ${isOwner ? '<span class="badge bg-primary"><i class="bi bi-person"></i> Your Content</span>' : ''}
                ${hasAccess ? '<span class="badge bg-success"><i class="bi bi-unlock"></i> Unlocked</span>' : ''}
            </div>
        `;
    }

    /**
     * Render card body
     */
    renderBody() {
        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;

        return `
            <div class="card-body">
                ${this.renderPreview()}
                
                <h5 class="card-title mt-3">
                    ${this.escapeHtml(this.content.title)}
                    ${!hasAccess && !isOwner ? '<i class="bi bi-lock-fill text-muted"></i>' : ''}
                </h5>
                
                ${this.renderDescription()}
                
                ${this.options.showCreator ? this.renderCreator() : ''}
                
                ${this.options.showStats ? this.renderStats() : ''}
            </div>
        `;
    }

    /**
     * Render preview
     */
    renderPreview() {
        if (!this.options.showPreview) return '';

        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;

        if (hasAccess || isOwner) {
            // Show full preview
            if (this.content.content_type === 'image') {
                return `<img src="${this.content.content_url}" class="card-img-top" alt="Content">`;
            } else if (this.content.preview_url) {
                return `<img src="${this.content.preview_url}" class="card-img-top" alt="Preview">`;
            }
        } else {
            // Show blurred preview
            if (this.content.preview_url) {
                return `
                    <div class="position-relative">
                        <img src="${this.content.preview_url}" class="card-img-top paywall-blur" alt="Preview">
                        <div class="paywall-lock-overlay">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                    </div>
                `;
            }
        }

        return '';
    }

    /**
     * Render description
     */
    renderDescription() {
        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;

        if (hasAccess || isOwner) {
            return `<p class="card-text">${this.escapeHtml(this.content.description || '').substring(0, 150)}...</p>`;
        } else if (this.content.preview_text) {
            return `<p class="card-text text-muted">${this.escapeHtml(this.content.preview_text)}...</p>`;
        } else {
            return `<p class="card-text text-muted">Unlock to view full content</p>`;
        }
    }

    /**
     * Render creator info
     */
    renderCreator() {
        if (!this.content.creator_username) return '';

        return `
            <div class="d-flex align-items-center mb-2">
                <img src="${this.content.creator_avatar || '/assets/img/default-avatar.png'}" 
                     class="rounded-circle me-2" width="24" height="24" alt="Creator">
                <small class="text-muted">@${this.escapeHtml(this.content.creator_username)}</small>
            </div>
        `;
    }

    /**
     * Render stats
     */
    renderStats() {
        return `
            <div class="d-flex justify-content-between text-muted small mt-2">
                <span><i class="bi bi-eye"></i> ${this.content.views || 0} views</span>
                <span><i class="bi bi-cart-check"></i> ${this.content.total_sales || 0} sales</span>
            </div>
        `;
    }

    /**
     * Render footer
     */
    renderFooter() {
        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;
        const price = parseFloat(this.content.price);

        return `
            <div class="card-footer bg-transparent">
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-primary">💎 ${price.toFixed(2)} SPHE</strong>
                    ${this.renderActionButton()}
                </div>
            </div>
        `;
    }

    /**
     * Render action button
     */
    renderActionButton() {
        const hasAccess = this.content.has_access || false;
        const isOwner = this.content.is_owner || false;

        if (isOwner) {
            return `
                <a href="/pages/paywall/edit-content.php?id=${this.content.id}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            `;
        } else if (hasAccess) {
            return `
                <button class="btn btn-sm btn-success" onclick="viewContent(${this.content.id})">
                    <i class="bi bi-unlock"></i> View
                </button>
            `;
        } else {
            return `
                <button class="btn btn-sm btn-primary paywall-unlock-btn" 
                        data-content-id="${this.content.id}"
                        data-price="${this.content.price}"
                        onclick="unlockContent(${this.content.id})">
                    <i class="bi bi-unlock"></i> Unlock
                </button>
            `;
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

/**
 * Render multiple content cards
 */
function renderContentCards(contents, containerId, options = {}) {
    const container = document.getElementById(containerId);
    
    if (!contents || contents.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">No content available</p>
            </div>
        `;
        return;
    }

    container.innerHTML = contents.map(content => {
        const card = new PaywallContentCard(content, options);
        return `<div class="col-md-4 col-lg-3">${card.render()}</div>`;
    }).join('');
}

/**
 * Unlock Content (gasless purchase)
 */
async function unlockContent(contentId) {
    try {
        // Get content details
        const response = await fetch(`/api/paywall/get_content.php?id=${contentId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Content not found');
        }

        const content = data.content;

        // Show purchase modal
        showPurchaseModal(content);

    } catch (error) {
        console.error('Error unlocking content:', error);
        alert('Failed to unlock content: ' + error.message);
    }
}

/**
 * Show Purchase Modal
 */
function showPurchaseModal(content) {
    // Check if modal exists, create if not
    let modal = document.getElementById('paywallPurchaseModal');
    
    if (!modal) {
        // Create modal dynamically
        const modalHTML = `
            <div class="modal fade" id="paywallPurchaseModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-unlock"></i> Unlock Content</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="purchaseDetails"></div>
                            <div id="purchaseProgress" style="display: none;" class="text-center py-4">
                                <div class="spinner-border text-primary mb-3"></div>
                                <p id="purchaseStatus">Processing...</p>
                            </div>
                            <div id="purchaseSuccess" style="display: none;" class="text-center py-4">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">Content Unlocked!</h5>
                                <p class="text-muted">You now have access to this content</p>
                            </div>
                            <div id="purchaseError" style="display: none;" class="text-center py-4">
                                <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">Purchase Failed</h5>
                                <p class="text-danger" id="purchaseErrorMsg"></p>
                            </div>
                        </div>
                        <div class="modal-footer" id="purchaseFooter">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="btnConfirmPurchase">
                                Confirm Purchase
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        modal = document.getElementById('paywallPurchaseModal');
    }

    // Populate details
    const price = parseFloat(content.price);
    const netPrice = (price * 0.975).toFixed(2);

    document.getElementById('purchaseDetails').innerHTML = `
        <h6>${content.title}</h6>
        <p class="text-muted">${content.description || ''}</p>
        <hr>
        <div class="d-flex justify-content-between mb-2">
            <span>Price:</span>
            <strong>💎 ${price.toFixed(2)} SPHE</strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span>Creator receives:</span>
            <span class="text-success">💎 ${netPrice} SPHE</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span>Platform fee (2.5%):</span>
            <span class="text-muted">💎 ${(price * 0.025).toFixed(2)} SPHE</span>
        </div>
        <div class="alert alert-info mt-3">
            <small><i class="bi bi-lightning-charge"></i> This is a gasless transaction powered by Gelato Relay</small>
        </div>
    `;

    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Handle purchase
    document.getElementById('btnConfirmPurchase').onclick = async () => {
        await executePurchase(content.id, bsModal);
    };
}

/**
 * Execute Purchase
 */
async function executePurchase(contentId, modalInstance) {
    document.getElementById('purchaseDetails').style.display = 'none';
    document.getElementById('purchaseProgress').style.display = 'block';
    document.getElementById('purchaseFooter').style.display = 'none';

    try {
        // Use paywall-gasless.js function
        if (typeof window.PaywallGasless === 'undefined') {
            throw new Error('Paywall Gasless library not loaded');
        }

        const result = await window.PaywallGasless.purchaseContentGasless(contentId, (status) => {
            document.getElementById('purchaseStatus').textContent = status;
        });

        // Success!
        document.getElementById('purchaseProgress').style.display = 'none';
        document.getElementById('purchaseSuccess').style.display = 'block';

        // Reload page after 2 seconds
        setTimeout(() => {
            location.reload();
        }, 2000);

    } catch (error) {
        console.error('Purchase error:', error);
        document.getElementById('purchaseProgress').style.display = 'none';
        document.getElementById('purchaseError').style.display = 'block';
        document.getElementById('purchaseErrorMsg').textContent = error.message;
    }
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
            // Open content in new tab or modal
            if (data.content.content_url) {
                window.open(data.content.content_url, '_blank');
            } else {
                alert('Content URL not available');
            }
        } else {
            alert('Access denied or content not found');
        }

    } catch (error) {
        console.error('Error viewing content:', error);
        alert('Failed to view content: ' + error.message);
    }
}

// CSS Styles
const paywallStyles = `
<style>
.paywall-content-card {
    transition: transform 0.3s, box-shadow 0.3s;
}

.paywall-content-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.paywall-blur {
    filter: blur(10px);
}

.paywall-lock-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.8);
    text-shadow: 0 2px 10px rgba(0,0,0,0.5);
}

.paywall-unlock-btn {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
</style>
`;

// Inject styles
if (document.getElementById('paywall-styles') === null) {
    document.head.insertAdjacentHTML('beforeend', paywallStyles);
}
