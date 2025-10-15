/**
 * ============================================
 * MODERATION SYSTEM
 * ============================================
 * Content moderation and reporting
 */

class ModerationSystem {
    constructor() {
        this.isModerator = false;
        this.queueRefreshInterval = null;
        this.init();
    }

    async init() {
        console.log('🛡️ Initializing Moderation System...');
        
        // Check if user is moderator
        await this.checkModeratorRole();
        
        // Setup event listeners
        this.setupEventListeners();
        
        console.log('✅ Moderation System initialized');
    }

    async checkModeratorRole() {
        try {
            const response = await fetch('/api/auth/check-role.php');
            const result = await response.json();
            this.isModerator = result.is_moderator || result.is_admin;
            
            if (this.isModerator) {
                this.showModeratorUI();
            }
        } catch (error) {
            console.error('Failed to check moderator role:', error);
        }
    }

    setupEventListeners() {
        // Report buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-report-content]')) {
                const targetType = e.target.dataset.targetType;
                const targetId = e.target.dataset.targetId;
                this.showReportModal(targetType, targetId);
            }
            
            if (e.target.matches('[data-block-user]')) {
                const userId = e.target.dataset.userId;
                this.blockUser(userId);
            }
            
            if (e.target.matches('[data-moderate]')) {
                const reportId = e.target.dataset.reportId;
                this.showModerateModal(reportId);
            }
        });
    }

    /**
     * Show report modal
     */
    showReportModal(targetType, targetId) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'reportModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-flag"></i>
                            Report Content
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Reports are reviewed by moderators within 24 hours.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" id="reportCategory">
                                <option value="">Select a category...</option>
                                <option value="spam">Spam</option>
                                <option value="harassment">Harassment</option>
                                <option value="hate_speech">Hate Speech</option>
                                <option value="violence">Violence</option>
                                <option value="sexual">Sexual Content</option>
                                <option value="illegal">Illegal Activity</option>
                                <option value="copyright">Copyright Violation</option>
                                <option value="misinformation">Misinformation</option>
                                <option value="self_harm">Self Harm</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea 
                                class="form-control" 
                                id="reportDescription" 
                                rows="4" 
                                placeholder="Please provide details about why you're reporting this..."
                            ></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="reportBlockUser">
                            <label class="form-check-label" for="reportBlockUser">
                                Also block this user
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-danger" 
                            id="submitReport"
                            data-target-type="${targetType}"
                            data-target-id="${targetId}"
                        >
                            <i class="fas fa-flag"></i>
                            Submit Report
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Handle submission
        document.getElementById('submitReport').addEventListener('click', async () => {
            await this.submitReport(targetType, targetId);
        });
        
        // Clean up
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    }

    /**
     * Submit report
     */
    async submitReport(targetType, targetId) {
        const category = document.getElementById('reportCategory').value;
        const description = document.getElementById('reportDescription').value.trim();
        const blockUser = document.getElementById('reportBlockUser').checked;
        const submitBtn = document.getElementById('submitReport');
        
        if (!category) {
            this.showNotification('Please select a category', 'warning');
            return;
        }
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            const response = await fetch('/api/moderation/report-content.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    target_type: targetType,
                    target_id: targetId,
                    category,
                    description
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Report submitted successfully', 'success');
                
                // Block user if requested
                if (blockUser && targetType === 'user') {
                    await this.blockUser(targetId);
                }
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
                modal.hide();
            } else {
                throw new Error(result.error || 'Failed to submit report');
            }
        } catch (error) {
            console.error('Report submission failed:', error);
            this.showNotification('Failed to submit report: ' + error.message, 'error');
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-flag"></i> Submit Report';
        }
    }

    /**
     * Block user
     */
    async blockUser(userId) {
        if (!confirm('Are you sure you want to block this user?')) {
            return;
        }
        
        try {
            const response = await fetch('/api/moderation/block-user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    block_type: 'full'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('User blocked successfully', 'success');
                
                // Hide content from blocked user
                this.hideBlockedUserContent(userId);
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            this.showNotification('Failed to block user: ' + error.message, 'error');
        }
    }

    /**
     * Hide blocked user content
     */
    hideBlockedUserContent(userId) {
        const posts = document.querySelectorAll(`[data-user-id="${userId}"]`);
        posts.forEach(post => {
            post.style.display = 'none';
        });
    }

    /**
     * Show moderator UI
     */
    showModeratorUI() {
        console.log('✅ Moderator UI enabled');
        
        // Add moderator badge
        const userMenu = document.querySelector('.user-menu');
        if (userMenu) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning text-dark ms-2';
            badge.innerHTML = '<i class="fas fa-shield-alt"></i> Moderator';
            userMenu.appendChild(badge);
        }
        
        // Add moderation dashboard link
        const navbar = document.querySelector('.navbar-nav');
        if (navbar) {
            const link = document.createElement('li');
            link.className = 'nav-item';
            link.innerHTML = `
                <a class="nav-link" href="/pages/moderation-dashboard.php">
                    <i class="fas fa-gavel"></i>
                    Moderation
                    <span class="badge bg-danger ms-1" id="pendingReportsCount">0</span>
                </a>
            `;
            navbar.appendChild(link);
        }
        
        // Start queue monitoring
        this.startQueueMonitoring();
    }

    /**
     * Start queue monitoring
     */
    startQueueMonitoring() {
        this.updatePendingCount();
        
        // Update every minute
        this.queueRefreshInterval = setInterval(() => {
            this.updatePendingCount();
        }, 60000);
    }

    /**
     * Update pending reports count
     */
    async updatePendingCount() {
        try {
            const response = await fetch('/api/moderation/get-queue.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    status: 'pending',
                    limit: 1
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const badge = document.getElementById('pendingReportsCount');
                if (badge) {
                    badge.textContent = result.stats.total_pending || 0;
                    badge.style.display = result.stats.total_pending > 0 ? 'inline' : 'none';
                }
            }
        } catch (error) {
            console.error('Failed to update pending count:', error);
        }
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification-toast`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Cleanup
     */
    destroy() {
        if (this.queueRefreshInterval) {
            clearInterval(this.queueRefreshInterval);
        }
    }
}

// Initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.moderationSystem = new ModerationSystem();
    });
} else {
    window.moderationSystem = new ModerationSystem();
}
