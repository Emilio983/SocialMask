/**
 * ============================================
 * ANONYMOUS POSTS SYSTEM
 * ============================================
 * Create and manage anonymous verified posts
 */

class AnonymousPosts {
    constructor(zkProofs) {
        this.zkProofs = zkProofs;
        this.anonymousMode = false;
        this.disposableIdentity = null;
    }

    /**
     * Toggle anonymous mode
     */
    setAnonymousMode(enabled) {
        this.anonymousMode = enabled;
        localStorage.setItem('anonymous_mode', enabled ? '1' : '0');
        
        // Notify UI
        this.onAnonymousModeChanged(enabled);
        
        console.log(`Anonymous mode: ${enabled ? 'ON' : 'OFF'}`);
    }

    /**
     * Check if anonymous mode is enabled
     */
    isAnonymousMode() {
        return this.anonymousMode || localStorage.getItem('anonymous_mode') === '1';
    }

    /**
     * Create anonymous post
     */
    async createAnonymousPost(content, options = {}) {
        try {
            if (!this.isAnonymousMode()) {
                throw new Error('Anonymous mode not enabled');
            }
            
            // Check if identity exists
            const identity = this.zkProofs.exportIdentity();
            if (!identity.commitment) {
                // Generate new identity
                const secret = this.generateSecret();
                const userId = this.getCurrentUserId();
                await this.zkProofs.generateIdentityCommitment(userId, secret);
            }
            
            // Generate proof for post
            const { proof, publicSignals, nullifier } = await this.zkProofs.generateAnonymousPostProof(
                content,
                {
                    postType: options.type || 'text',
                    hasMedia: options.hasMedia || false
                }
            );
            
            // Create post data
            const postData = {
                content,
                nullifier,
                proof: JSON.stringify(proof),
                public_signals: JSON.stringify(publicSignals),
                content_type: options.type || 'text',
                media_url: options.mediaUrl || null,
                is_anonymous: true,
                created_at: Date.now()
            };
            
            // Send to server
            const response = await fetch('/api/anonymous/create-post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(postData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Anonymous post created:', result.post_id);
                
                // Notify UI
                this.onPostCreated(result.post_id, postData);
                
                return result.post_id;
            } else {
                throw new Error(result.error || 'Failed to create post');
            }
        } catch (error) {
            console.error('❌ Failed to create anonymous post:', error);
            throw error;
        }
    }

    /**
     * Create disposable identity
     */
    async createDisposableIdentity() {
        try {
            const secret = this.generateSecret();
            const tempUserId = `temp_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            
            const identity = await this.zkProofs.generateIdentityCommitment(tempUserId, secret);
            
            // Set expiration (1 hour)
            const expiresAt = Date.now() + (60 * 60 * 1000);
            
            this.disposableIdentity = {
                ...identity,
                tempUserId,
                expiresAt,
                used: false
            };
            
            localStorage.setItem('disposable_identity', JSON.stringify(this.disposableIdentity));
            
            console.log('✅ Disposable identity created (expires in 1 hour)');
            
            return this.disposableIdentity;
        } catch (error) {
            console.error('❌ Failed to create disposable identity:', error);
            throw error;
        }
    }

    /**
     * Use disposable identity for post
     */
    async postWithDisposableIdentity(content, options = {}) {
        try {
            if (!this.disposableIdentity) {
                await this.createDisposableIdentity();
            }
            
            // Check if expired
            if (Date.now() > this.disposableIdentity.expiresAt) {
                console.log('Disposable identity expired, creating new one...');
                await this.createDisposableIdentity();
            }
            
            // Mark as used
            this.disposableIdentity.used = true;
            
            // Create post
            const postId = await this.createAnonymousPost(content, {
                ...options,
                isDisposable: true
            });
            
            // Burn identity after use if requested
            if (options.burnAfterUse !== false) {
                this.burnDisposableIdentity();
            }
            
            return postId;
        } catch (error) {
            console.error('❌ Failed to post with disposable identity:', error);
            throw error;
        }
    }

    /**
     * Burn disposable identity
     */
    burnDisposableIdentity() {
        if (this.disposableIdentity) {
            // Clear from memory and storage
            this.disposableIdentity = null;
            localStorage.removeItem('disposable_identity');
            
            // Clear ZK identity
            this.zkProofs.clearIdentity();
            
            console.log('🔥 Disposable identity burned');
            
            this.onIdentityBurned();
        }
    }

    /**
     * Get anonymous post reputation
     */
    async getAnonymousReputation(nullifier) {
        try {
            const response = await fetch('/api/anonymous/get-reputation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nullifier })
            });
            
            const result = await response.json();
            
            if (result.success) {
                return {
                    score: result.reputation_score,
                    postCount: result.post_count,
                    verified: result.verified,
                    badges: result.badges || []
                };
            }
            
            return { score: 0, postCount: 0, verified: false, badges: [] };
        } catch (error) {
            console.error('Failed to get reputation:', error);
            return { score: 0, postCount: 0, verified: false, badges: [] };
        }
    }

    /**
     * Vote on anonymous post
     */
    async voteAnonymousPost(postId, voteType) {
        try {
            // Generate proof that voter has not voted before
            const identity = this.zkProofs.exportIdentity();
            if (!identity.commitment) {
                throw new Error('Identity required to vote');
            }
            
            const response = await fetch('/api/anonymous/vote-post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    post_id: postId,
                    vote_type: voteType,
                    voter_nullifier: identity.nullifier
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Vote registered');
                return true;
            } else {
                throw new Error(result.error || 'Failed to vote');
            }
        } catch (error) {
            console.error('❌ Failed to vote:', error);
            throw error;
        }
    }

    /**
     * Report anonymous post
     */
    async reportAnonymousPost(postId, reason) {
        try {
            const identity = this.zkProofs.exportIdentity();
            
            const response = await fetch('/api/anonymous/report-post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    post_id: postId,
                    reason,
                    reporter_nullifier: identity.nullifier || 'anonymous'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Report submitted');
                this.onPostReported(postId);
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('❌ Failed to report:', error);
            return false;
        }
    }

    /**
     * Get anonymous post feed
     */
    async getAnonymousFeed(page = 1, limit = 20) {
        try {
            const response = await fetch('/api/anonymous/get-feed.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ page, limit })
            });
            
            const result = await response.json();
            
            if (result.success) {
                return result.posts;
            }
            
            return [];
        } catch (error) {
            console.error('Failed to get anonymous feed:', error);
            return [];
        }
    }

    /**
     * Comment on anonymous post (anonymously)
     */
    async commentAnonymously(postId, content) {
        try {
            const identity = this.zkProofs.exportIdentity();
            if (!identity.commitment) {
                const secret = this.generateSecret();
                const userId = this.getCurrentUserId();
                await this.zkProofs.generateIdentityCommitment(userId, secret);
            }
            
            // Generate proof for comment
            const { proof, publicSignals, nullifier } = await this.zkProofs.generateAnonymousPostProof(content, {
                postType: 'comment',
                parentPostId: postId
            });
            
            const response = await fetch('/api/anonymous/add-comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    post_id: postId,
                    content,
                    nullifier,
                    proof: JSON.stringify(proof),
                    public_signals: JSON.stringify(publicSignals)
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Anonymous comment added');
                return result.comment_id;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('❌ Failed to add comment:', error);
            throw error;
        }
    }

    /**
     * Generate random secret
     */
    generateSecret() {
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        return Array.from(array).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Get current user ID
     */
    getCurrentUserId() {
        return window.currentUser?.id || localStorage.getItem('user_id') || 'anonymous';
    }

    /**
     * Event handlers (override in implementation)
     */
    onAnonymousModeChanged(enabled) {
        console.log('Anonymous mode changed:', enabled);
    }

    onPostCreated(postId, postData) {
        console.log('Post created:', postId);
    }

    onIdentityBurned() {
        console.log('Identity burned');
    }

    onPostReported(postId) {
        console.log('Post reported:', postId);
    }
}

// Export
window.AnonymousPosts = AnonymousPosts;
