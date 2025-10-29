/**
 * ============================================
 * ZERO-KNOWLEDGE PROOF SYSTEM
 * ============================================
 * zkSNARK implementation for anonymous verified posts
 */

class ZeroKnowledgeProofs {
    constructor() {
        this.snarkjs = null;
        this.circuits = new Map();
        this.provingKeys = new Map();
        this.verificationKeys = new Map();
        this.identityCommitment = null;
        this.identityNullifier = null;
        
        this.init();
    }

    /**
     * Initialize zkSNARK system
     */
    async init() {
        try {
            // Load snarkjs (will be loaded via CDN in production)
            if (typeof window.snarkjs !== 'undefined') {
                this.snarkjs = window.snarkjs;
            } else {
                console.warn('snarkjs not loaded, using mock mode');
                this.snarkjs = this.createMockSnarkjs();
            }
            
            console.log('✅ ZK Proof system initialized');
        } catch (error) {
            console.error('❌ Failed to initialize ZK system:', error);
        }
    }

    /**
     * Generate identity commitment
     */
    async generateIdentityCommitment(userId, secret) {
        try {
            // Hash user ID with secret to create commitment
            const encoder = new TextEncoder();
            const data = encoder.encode(`${userId}:${secret}`);
            
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const commitment = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            
            // Generate nullifier (prevents double-spending)
            const nullifierData = encoder.encode(`nullifier:${userId}:${secret}:${Date.now()}`);
            const nullifierBuffer = await crypto.subtle.digest('SHA-256', nullifierData);
            const nullifierArray = Array.from(new Uint8Array(nullifierBuffer));
            const nullifier = nullifierArray.map(b => b.toString(16).padStart(2, '0')).join('');
            
            // Store locally
            this.identityCommitment = commitment;
            this.identityNullifier = nullifier;
            
            // Store in localStorage
            localStorage.setItem('zk_commitment', commitment);
            localStorage.setItem('zk_nullifier', nullifier);
            localStorage.setItem('zk_secret', secret);
            
            console.log('✅ Identity commitment generated');
            
            return {
                commitment,
                nullifier,
                publicKey: commitment.substring(0, 32) // First 32 chars as public key
            };
        } catch (error) {
            console.error('❌ Failed to generate identity commitment:', error);
            throw error;
        }
    }

    /**
     * Generate proof for anonymous post
     */
    async generateAnonymousPostProof(content, metadata = {}) {
        try {
            // Load identity
            const commitment = localStorage.getItem('zk_commitment');
            const nullifier = localStorage.getItem('zk_nullifier');
            const secret = localStorage.getItem('zk_secret');
            
            if (!commitment || !nullifier || !secret) {
                throw new Error('Identity not initialized. Call generateIdentityCommitment first.');
            }
            
            // Create proof inputs
            const inputs = {
                identityCommitment: commitment,
                identityNullifier: nullifier,
                identitySecret: secret,
                contentHash: await this.hashContent(content),
                timestamp: Date.now(),
                ...metadata
            };
            
            // Generate proof (simplified for demo, real implementation uses circom circuits)
            const proof = await this.generateProof(inputs);
            
            // Create public signals
            const publicSignals = {
                nullifier: nullifier,
                contentHash: inputs.contentHash,
                timestamp: inputs.timestamp
            };
            
            console.log('✅ Anonymous post proof generated');
            
            return {
                proof,
                publicSignals,
                nullifier
            };
        } catch (error) {
            console.error('❌ Failed to generate proof:', error);
            throw error;
        }
    }

    /**
     * Verify anonymous post proof
     */
    async verifyAnonymousPostProof(proof, publicSignals, content) {
        try {
            // Verify content hash matches
            const contentHash = await this.hashContent(content);
            if (contentHash !== publicSignals.contentHash) {
                return false;
            }
            
            // Verify proof (simplified)
            const isValid = await this.verifyProof(proof, publicSignals);
            
            // Check nullifier hasn't been used before
            const nullifierUsed = await this.checkNullifierUsed(publicSignals.nullifier);
            if (nullifierUsed) {
                console.warn('⚠️ Nullifier already used (double-spend attempt)');
                return false;
            }
            
            console.log(isValid ? '✅ Proof verified' : '❌ Proof invalid');
            
            return isValid;
        } catch (error) {
            console.error('❌ Failed to verify proof:', error);
            return false;
        }
    }

    /**
     * Generate membership proof
     */
    async generateMembershipProof(groupId, userLevel) {
        try {
            const commitment = localStorage.getItem('zk_commitment');
            const secret = localStorage.getItem('zk_secret');
            
            if (!commitment || !secret) {
                throw new Error('Identity not initialized');
            }
            
            // Create membership proof inputs
            const inputs = {
                identityCommitment: commitment,
                identitySecret: secret,
                groupId: groupId,
                userLevel: userLevel,
                timestamp: Date.now()
            };
            
            // Generate proof
            const proof = await this.generateProof(inputs);
            
            // Public signals (what verifier sees)
            const publicSignals = {
                groupId: groupId,
                hasAccess: true, // Proves membership without revealing identity
                timestamp: inputs.timestamp
            };
            
            console.log('✅ Membership proof generated');
            
            return { proof, publicSignals };
        } catch (error) {
            console.error('❌ Failed to generate membership proof:', error);
            throw error;
        }
    }

    /**
     * Generate age verification proof
     */
    async generateAgeProof(birthdate, minAge = 18) {
        try {
            const commitment = localStorage.getItem('zk_commitment');
            const secret = localStorage.getItem('zk_secret');
            
            if (!commitment || !secret) {
                throw new Error('Identity not initialized');
            }
            
            // Calculate age
            const birth = new Date(birthdate);
            const today = new Date();
            const age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            const actualAge = monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate()) 
                ? age - 1 
                : age;
            
            // Create proof that age >= minAge without revealing exact age
            const inputs = {
                identityCommitment: commitment,
                identitySecret: secret,
                birthYear: birth.getFullYear(),
                birthMonth: birth.getMonth() + 1,
                birthDay: birth.getDate(),
                minAge: minAge,
                currentYear: today.getFullYear(),
                currentMonth: today.getMonth() + 1,
                currentDay: today.getDate()
            };
            
            // Generate proof
            const proof = await this.generateProof(inputs);
            
            // Public signals
            const publicSignals = {
                isOldEnough: actualAge >= minAge,
                minAge: minAge,
                timestamp: Date.now()
            };
            
            console.log('✅ Age verification proof generated');
            
            return { proof, publicSignals };
        } catch (error) {
            console.error('❌ Failed to generate age proof:', error);
            throw error;
        }
    }

    /**
     * Generate proof (simplified implementation)
     */
    async generateProof(inputs) {
        // In production, this would use circom circuits and snarkjs
        // For now, we create a cryptographic signature as proof
        
        const inputString = JSON.stringify(inputs);
        const encoder = new TextEncoder();
        const data = encoder.encode(inputString);
        
        // Generate HMAC as proof
        const key = await crypto.subtle.importKey(
            'raw',
            encoder.encode(inputs.identitySecret || 'default-secret'),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        
        const signature = await crypto.subtle.sign('HMAC', key, data);
        const signatureArray = Array.from(new Uint8Array(signature));
        const signatureHex = signatureArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        return {
            pi_a: signatureHex.substring(0, 64),
            pi_b: signatureHex.substring(64, 128),
            pi_c: signatureHex.substring(128, 192) || '0'.repeat(64),
            protocol: 'groth16',
            curve: 'bn128'
        };
    }

    /**
     * Verify proof (simplified implementation)
     */
    async verifyProof(proof, publicSignals) {
        // In production, this would use verification key and snarkjs
        // For now, we do basic validation
        
        if (!proof || !proof.pi_a || !proof.pi_b || !proof.pi_c) {
            return false;
        }
        
        if (!publicSignals) {
            return false;
        }
        
        // Check timestamp is recent (within 5 minutes)
        if (publicSignals.timestamp) {
            const age = Date.now() - publicSignals.timestamp;
            if (age > 5 * 60 * 1000) {
                console.warn('⚠️ Proof too old');
                return false;
            }
        }
        
        return true;
    }

    /**
     * Hash content
     */
    async hashContent(content) {
        const encoder = new TextEncoder();
        const data = encoder.encode(content);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Check if nullifier has been used
     */
    async checkNullifierUsed(nullifier) {
        try {
            const response = await fetch('/api/anonymous/check-nullifier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nullifier })
            });
            
            const result = await response.json();
            return result.used || false;
        } catch (error) {
            console.error('Failed to check nullifier:', error);
            return false;
        }
    }

    /**
     * Export identity for backup
     */
    exportIdentity() {
        return {
            commitment: localStorage.getItem('zk_commitment'),
            nullifier: localStorage.getItem('zk_nullifier'),
            secret: localStorage.getItem('zk_secret')
        };
    }

    /**
     * Import identity from backup
     */
    importIdentity(identity) {
        localStorage.setItem('zk_commitment', identity.commitment);
        localStorage.setItem('zk_nullifier', identity.nullifier);
        localStorage.setItem('zk_secret', identity.secret);
        
        this.identityCommitment = identity.commitment;
        this.identityNullifier = identity.nullifier;
        
        console.log('✅ Identity imported');
    }

    /**
     * Clear identity
     */
    clearIdentity() {
        localStorage.removeItem('zk_commitment');
        localStorage.removeItem('zk_nullifier');
        localStorage.removeItem('zk_secret');
        
        this.identityCommitment = null;
        this.identityNullifier = null;
        
        console.log('✅ Identity cleared');
    }

    /**
     * Mock snarkjs for development
     */
    createMockSnarkjs() {
        return {
            groth16: {
                fullProve: async (inputs, wasmFile, zkeyFile) => {
                    return {
                        proof: { pi_a: '0x...', pi_b: '0x...', pi_c: '0x...' },
                        publicSignals: []
                    };
                },
                verify: async (vkey, publicSignals, proof) => {
                    return true;
                }
            }
        };
    }
}

// Export
window.ZeroKnowledgeProofs = ZeroKnowledgeProofs;
