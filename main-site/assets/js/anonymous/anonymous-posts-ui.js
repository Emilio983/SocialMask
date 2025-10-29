/**
 * ============================================
 * ANONYMOUS POSTS UI
 * ============================================
 * User interface for anonymous posting
 */

class AnonymousPostsUI {
    constructor() {
        this.zkProofs = null;
        this.anonymousPosts = null;
        this.anonymousMode = false;
        
        this.init();
    }

    async init() {
        console.log('🎭 Initializing Anonymous Posts UI...');
        
        // Load dependencies from CDN if not available
        if (typeof ZeroKnowledgeProofs === 'undefined') {
            console.log('Loading ZK Proofs via CDN...');
            await this.loadScript('https://cdn.jsdelivr.net/npm/snarkjs@latest/build/snarkjs.min.js');
        }
        
        // Initialize ZK proofs
        this.zkProofs = new ZeroKnowledgeProofs();
        await this.zkProofs.init();
        
        // Initialize anonymous posts
        this.anonymousPosts = new AnonymousPosts(this.zkProofs);
        
        // Set up event handlers
        this.setupEventHandlers();
        
        // Load saved state
        this.loadAnonymousMode();
        
        console.log('✅ Anonymous Posts UI initialized');
    }

    setupEventHandlers() {
        // Anonymous mode toggle
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-toggle-anonymous]')) {
                this.toggleAnonymousMode();
            }
            
            if (e.target.matches('[data-create-anonymous-post]')) {
                this.showAnonymousPostModal();
            }
            
            if (e.target.matches('[data-burn-identity]')) {
                this.burnIdentity();
            }
            
            if (e.target.matches('[data-view-anonymous-feed]')) {
                this.showAnonymousFeed();
            }
        });
        
        // Listen for post submissions
        this.anonymousPosts.onPostCreated = (postId, postData) => {
            this.onPostCreated(postId, postData);
        };
        
        this.anonymousPosts.onAnonymousModeChanged = (enabled) => {
            this.updateAnonymousModeUI(enabled);
        };
        
        this.anonymousPosts.onIdentityBurned = () => {
            this.showNotification('Identity burned successfully', 'success');
            this.updateIdentityUI();
        };
    }

    toggleAnonymousMode() {
        this.anonymousMode = !this.anonymousMode;
        this.anonymousPosts.setAnonymousMode(this.anonymousMode);
        this.updateAnonymousModeUI(this.anonymousMode);
    }

    updateAnonymousModeUI(enabled) {
        const indicator = document.querySelector('[data-anonymous-indicator]');
        const toggle = document.querySelector('[data-toggle-anonymous]');
        
        if (indicator) {
            indicator.style.display = enabled ? 'flex' : 'none';
            indicator.innerHTML = `
                <div class="anonymous-badge">
                    <i class="fas fa-user-secret"></i>
                    <span>Anonymous Mode</span>
                </div>
            `;
        }
        
        if (toggle) {
            toggle.classList.toggle('active', enabled);
            toggle.innerHTML = enabled 
                ? '<i class="fas fa-user-secret"></i> Disable Anonymous' 
                : '<i class="fas fa-user"></i> Enable Anonymous';
        }
        
        // Update post form
        const postForm = document.querySelector('[data-post-form]');
        if (postForm) {
            postForm.classList.toggle('anonymous-mode', enabled);
        }
    }

    async showAnonymousPostModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'anonymousPostModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-secret"></i>
                            Create Anonymous Post
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Your identity will be protected with Zero-Knowledge Proofs. 
                            Your post will be verified but anonymous.
                        </div>
                        
                        <textarea 
                            id="anonymousPostContent" 
                            class="form-control" 
                            rows="5" 
                            placeholder="What would you like to share anonymously?"
                            maxlength="5000"
                        ></textarea>
                        
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="useDisposableIdentity">
                            <label class="form-check-label" for="useDisposableIdentity">
                                Use disposable identity (burn after posting)
                            </label>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt"></i>
                                Generating Zero-Knowledge Proof may take a few seconds...
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="submitAnonymousPost">
                            <i class="fas fa-paper-plane"></i>
                            Post Anonymously
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Handle submission
        document.getElementById('submitAnonymousPost').addEventListener('click', async () => {
            await this.submitAnonymousPost();
        });
        
        // Clean up on close
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    async submitAnonymousPost() {
        const content = document.getElementById('anonymousPostContent').value.trim();
        const useDisposable = document.getElementById('useDisposableIdentity').checked;
        const submitBtn = document.getElementById('submitAnonymousPost');
        
        if (!content) {
            this.showNotification('Please enter some content', 'warning');
            return;
        }
        
        try {
            // Show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating proof...';
            
            let postId;
            if (useDisposable) {
                postId = await this.anonymousPosts.postWithDisposableIdentity(content, {
                    burnAfterUse: true
                });
            } else {
                postId = await this.anonymousPosts.createAnonymousPost(content);
            }
            
            // Success
            this.showNotification('Anonymous post created successfully!', 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('anonymousPostModal'));
            modal.hide();
            
            // Reload feed
            setTimeout(() => this.showAnonymousFeed(), 500);
            
        } catch (error) {
            console.error('Failed to create post:', error);
            this.showNotification('Failed to create post: ' + error.message, 'error');
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post Anonymously';
        }
    }

    async showAnonymousFeed() {
        const container = document.querySelector('[data-anonymous-feed]');
        if (!container) {
            console.error('Anonymous feed container not found');
            return;
        }
        
        try {
            container.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i></div>';
            
            const posts = await this.anonymousPosts.getAnonymousFeed();
            
            if (posts.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-user-secret fa-3x mb-3 text-muted"></i>
                        <p>No anonymous posts yet</p>
                        <button class="btn btn-primary" data-create-anonymous-post>
                            <i class="fas fa-plus"></i>
                            Create First Anonymous Post
                        </button>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = posts.map(post => this.renderAnonymousPost(post)).join('');
            
        } catch (error) {
            console.error('Failed to load feed:', error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load anonymous feed
                </div>
            `;
        }
    }

    renderAnonymousPost(post) {
        const badges = post.author.badges.map(badge => 
            `<span class="badge bg-secondary"><i class="fas fa-${badge}"></i></span>`
        ).join(' ');
        
        return `
            <div class="post anonymous-post" data-post-id="${post.id}">
                <div class="post-header">
                    <div class="d-flex align-items-center">
                        <div class="avatar-anonymous">
                            <i class="fas fa-user-secret"></i>
                        </div>
                        <div class="ms-3">
                            <div class="d-flex align-items-center">
                                <strong>Anonymous</strong>
                                ${post.author.verified ? '<i class="fas fa-check-circle text-primary ms-2" title="Verified"></i>' : ''}
                                ${badges}
                            </div>
                            <small class="text-muted">
                                Reputation: ${post.author.reputation}
                                • ${this.formatDate(post.created_at)}
                            </small>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-link" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" onclick="anonymousPostsUI.reportPost(${post.id})">
                                    <i class="fas fa-flag"></i> Report
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="post-content">
                    <p>${this.escapeHtml(post.content)}</p>
                    ${post.media_url ? `<img src="${post.media_url}" class="img-fluid rounded" alt="Post media">` : ''}
                </div>
                
                <div class="post-actions">
                    <button class="btn btn-sm btn-light" onclick="anonymousPostsUI.votePost(${post.id}, 'upvote')">
                        <i class="fas fa-arrow-up"></i>
                        ${post.upvotes}
                    </button>
                    <button class="btn btn-sm btn-light" onclick="anonymousPostsUI.votePost(${post.id}, 'downvote')">
                        <i class="fas fa-arrow-down"></i>
                        ${post.downvotes}
                    </button>
                    <button class="btn btn-sm btn-light">
                        <i class="fas fa-comment"></i>
                        ${post.comments_count}
                    </button>
                    <button class="btn btn-sm btn-light">
                        <i class="fas fa-eye"></i>
                        ${post.views}
                    </button>
                </div>
            </div>
        `;
    }

    async votePost(postId, voteType) {
        try {
            await this.anonymousPosts.voteAnonymousPost(postId, voteType);
            this.showNotification('Vote registered', 'success');
            this.showAnonymousFeed(); // Reload
        } catch (error) {
            this.showNotification('Failed to vote: ' + error.message, 'error');
        }
    }

    async reportPost(postId) {
        const reason = prompt('Reason for reporting:\n1. Spam\n2. Harassment\n3. Illegal content\n4. Misinformation\n5. Other');
        if (!reason) return;
        
        const reasonMap = {
            '1': 'spam',
            '2': 'harassment',
            '3': 'illegal',
            '4': 'misinformation',
            '5': 'other'
        };
        
        try {
            await this.anonymousPosts.reportAnonymousPost(postId, reasonMap[reason] || 'other');
            this.showNotification('Report submitted', 'success');
        } catch (error) {
            this.showNotification('Failed to report: ' + error.message, 'error');
        }
    }

    async burnIdentity() {
        if (!confirm('Are you sure you want to burn your anonymous identity? This action cannot be undone.')) {
            return;
        }
        
        this.anonymousPosts.burnDisposableIdentity();
    }

    updateIdentityUI() {
        const identity = this.zkProofs.exportIdentity();
        const identityInfo = document.querySelector('[data-identity-info]');
        
        if (identityInfo) {
            if (identity.commitment) {
                identityInfo.innerHTML = `
                    <div class="identity-card">
                        <i class="fas fa-id-card"></i>
                        <div>
                            <strong>Identity Commitment</strong>
                            <code>${identity.commitment.substr(0, 16)}...</code>
                        </div>
                        <button class="btn btn-sm btn-danger" data-burn-identity>
                            <i class="fas fa-fire"></i> Burn
                        </button>
                    </div>
                `;
            } else {
                identityInfo.innerHTML = `
                    <div class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        No identity created yet
                    </div>
                `;
            }
        }
    }

    loadAnonymousMode() {
        this.anonymousMode = localStorage.getItem('anonymous_mode') === '1';
        this.updateAnonymousModeUI(this.anonymousMode);
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification-toast`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    onPostCreated(postId, postData) {
        console.log('Post created:', postId);
    }

    formatDate(date) {
        const d = new Date(date);
        const now = new Date();
        const diff = now - d;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        return `${days}d ago`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
}

// Initialize when DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.anonymousPostsUI = new AnonymousPostsUI();
    });
} else {
    window.anonymousPostsUI = new AnonymousPostsUI();
}
