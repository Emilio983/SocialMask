/**
 * ============================================
 * GROUP ENCRYPTION SYSTEM
 * ============================================
 * Sender Keys protocol for encrypted group messaging
 */

class GroupEncryption {
    constructor(signalCrypto) {
        this.signalCrypto = signalCrypto;
        this.groups = new Map(); // groupId -> GroupData
        this.senderKeys = new Map(); // groupId -> SenderKeyStore
        this.db = null;
        
        this.initDatabase();
    }

    /**
     * Initialize IndexedDB for group data
     */
    async initDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('GroupEncryptionDB', 1);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Groups store
                if (!db.objectStoreNames.contains('groups')) {
                    db.createObjectStore('groups', { keyPath: 'groupId' });
                }
                
                // Sender keys store
                if (!db.objectStoreNames.contains('senderKeys')) {
                    const store = db.createObjectStore('senderKeys', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('groupId', 'groupId', { unique: false });
                    store.createIndex('senderId', 'senderId', { unique: false });
                }
                
                // Group members store
                if (!db.objectStoreNames.contains('groupMembers')) {
                    const store = db.createObjectStore('groupMembers', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('groupId', 'groupId', { unique: false });
                    store.createIndex('userId', 'userId', { unique: false });
                }
            };
        });
    }

    /**
     * Create new encrypted group
     */
    async createGroup(groupName, memberIds, options = {}) {
        try {
            const groupId = this.generateGroupId();
            const creatorId = this.signalCrypto.userId;
            
            // Generate sender key for this group
            const senderKey = await this.generateSenderKey(groupId);
            
            // Create group data
            const groupData = {
                groupId,
                name: groupName,
                creatorId,
                adminIds: [creatorId],
                memberIds: [creatorId, ...memberIds],
                senderKeyId: senderKey.id,
                chainKey: senderKey.chainKey,
                iteration: 0,
                createdAt: Date.now(),
                settings: {
                    ephemeralTimer: options.ephemeralTimer || 0,
                    onlyAdminsPost: options.onlyAdminsPost || false,
                    disappearingMessages: options.disappearingMessages || false
                }
            };
            
            // Store group locally
            await this.storeGroup(groupData);
            
            // Create group on server
            const response = await fetch('/api/messaging/create-group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    group_id: groupId,
                    name: groupName,
                    creator_id: creatorId,
                    member_ids: groupData.memberIds,
                    encrypted_key: await this.encryptSenderKeyForMembers(senderKey, groupData.memberIds)
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Distribute sender key to members
                await this.distributeSenderKey(groupId, groupData.memberIds);
                
                console.log('✅ Group created:', groupId);
                return groupId;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('❌ Failed to create group:', error);
            throw error;
        }
    }

    /**
     * Generate sender key for group
     */
    async generateSenderKey(groupId) {
        const chainKey = crypto.getRandomValues(new Uint8Array(32));
        const signingKey = await crypto.subtle.generateKey(
            { name: 'HMAC', hash: 'SHA-256' },
            true,
            ['sign', 'verify']
        );
        
        return {
            id: this.generateKeyId(),
            groupId,
            chainKey: this.arrayBufferToBase64(chainKey),
            signingKey: await crypto.subtle.exportKey('raw', signingKey),
            iteration: 0
        };
    }

    /**
     * Encrypt message for group
     */
    async encryptGroupMessage(groupId, plaintext) {
        try {
            const group = await this.getGroup(groupId);
            if (!group) {
                throw new Error('Group not found');
            }
            
            // Get current sender key
            const senderKey = await this.getSenderKey(groupId);
            if (!senderKey) {
                throw new Error('Sender key not found');
            }
            
            // Derive message key from chain key
            const messageKey = await this.deriveMessageKey(senderKey.chainKey, senderKey.iteration);
            
            // Encrypt plaintext
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const encodedText = new TextEncoder().encode(plaintext);
            
            const cryptoKey = await crypto.subtle.importKey(
                'raw',
                this.base64ToArrayBuffer(messageKey),
                { name: 'AES-GCM' },
                false,
                ['encrypt']
            );
            
            const ciphertext = await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv },
                cryptoKey,
                encodedText
            );
            
            // Create message structure
            const encryptedMessage = {
                groupId,
                senderId: this.signalCrypto.userId,
                senderKeyId: senderKey.id,
                iteration: senderKey.iteration,
                iv: this.arrayBufferToBase64(iv),
                ciphertext: this.arrayBufferToBase64(ciphertext),
                timestamp: Date.now()
            };
            
            // Ratchet chain key forward
            await this.ratchetChainKey(groupId);
            
            return encryptedMessage;
        } catch (error) {
            console.error('❌ Failed to encrypt group message:', error);
            throw error;
        }
    }

    /**
     * Decrypt group message
     */
    async decryptGroupMessage(encryptedMessage) {
        try {
            const { groupId, senderId, senderKeyId, iteration, iv, ciphertext } = encryptedMessage;
            
            // Get sender key for this sender
            const senderKey = await this.getSenderKeyForUser(groupId, senderId);
            if (!senderKey) {
                throw new Error('Sender key not found');
            }
            
            // Derive message key
            const messageKey = await this.deriveMessageKey(senderKey.chainKey, iteration);
            
            // Decrypt
            const cryptoKey = await crypto.subtle.importKey(
                'raw',
                this.base64ToArrayBuffer(messageKey),
                { name: 'AES-GCM' },
                false,
                ['decrypt']
            );
            
            const decrypted = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: this.base64ToArrayBuffer(iv) },
                cryptoKey,
                this.base64ToArrayBuffer(ciphertext)
            );
            
            const plaintext = new TextDecoder().decode(decrypted);
            
            return plaintext;
        } catch (error) {
            console.error('❌ Failed to decrypt group message:', error);
            throw error;
        }
    }

    /**
     * Derive message key from chain key and iteration
     */
    async deriveMessageKey(chainKeyBase64, iteration) {
        const chainKey = this.base64ToArrayBuffer(chainKeyBase64);
        const iterationBytes = new Uint8Array(4);
        new DataView(iterationBytes.buffer).setUint32(0, iteration, false);
        
        // HMAC(chainKey, iteration)
        const key = await crypto.subtle.importKey(
            'raw',
            chainKey,
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        
        const messageKey = await crypto.subtle.sign('HMAC', key, iterationBytes);
        
        return this.arrayBufferToBase64(messageKey);
    }

    /**
     * Ratchet chain key forward
     */
    async ratchetChainKey(groupId) {
        const senderKey = await this.getSenderKey(groupId);
        
        // New chain key = HMAC(current_chain_key, 0x02)
        const key = await crypto.subtle.importKey(
            'raw',
            this.base64ToArrayBuffer(senderKey.chainKey),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        
        const newChainKey = await crypto.subtle.sign(
            'HMAC',
            key,
            new Uint8Array([0x02])
        );
        
        // Update sender key
        senderKey.chainKey = this.arrayBufferToBase64(newChainKey);
        senderKey.iteration += 1;
        
        await this.updateSenderKey(senderKey);
    }

    /**
     * Add member to group
     */
    async addMember(groupId, userId) {
        try {
            const group = await this.getGroup(groupId);
            if (!group) {
                throw new Error('Group not found');
            }
            
            // Check if user is admin
            if (!group.adminIds.includes(this.signalCrypto.userId)) {
                throw new Error('Only admins can add members');
            }
            
            // Check if already a member
            if (group.memberIds.includes(userId)) {
                throw new Error('User is already a member');
            }
            
            // Add member
            group.memberIds.push(userId);
            await this.storeGroup(group);
            
            // Distribute current sender key to new member
            const senderKey = await this.getSenderKey(groupId);
            await this.distributeSenderKeyToUser(groupId, userId, senderKey);
            
            // Notify server
            await fetch('/api/messaging/add-group-member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    group_id: groupId,
                    user_id: userId,
                    added_by: this.signalCrypto.userId
                })
            });
            
            console.log('✅ Member added to group:', userId);
            
            // Send system message
            await this.sendSystemMessage(groupId, `User ${userId} joined the group`);
            
            return true;
        } catch (error) {
            console.error('❌ Failed to add member:', error);
            throw error;
        }
    }

    /**
     * Remove member from group
     */
    async removeMember(groupId, userId) {
        try {
            const group = await this.getGroup(groupId);
            if (!group) {
                throw new Error('Group not found');
            }
            
            // Check if user is admin
            if (!group.adminIds.includes(this.signalCrypto.userId)) {
                throw new Error('Only admins can remove members');
            }
            
            // Cannot remove creator
            if (userId === group.creatorId) {
                throw new Error('Cannot remove group creator');
            }
            
            // Remove member
            group.memberIds = group.memberIds.filter(id => id !== userId);
            group.adminIds = group.adminIds.filter(id => id !== userId);
            await this.storeGroup(group);
            
            // Generate new sender key (forward secrecy)
            await this.rotateSenderKey(groupId);
            
            // Notify server
            await fetch('/api/messaging/remove-group-member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    group_id: groupId,
                    user_id: userId,
                    removed_by: this.signalCrypto.userId
                })
            });
            
            console.log('✅ Member removed from group:', userId);
            
            // Send system message
            await this.sendSystemMessage(groupId, `User ${userId} left the group`);
            
            return true;
        } catch (error) {
            console.error('❌ Failed to remove member:', error);
            throw error;
        }
    }

    /**
     * Rotate sender key (after member removal)
     */
    async rotateSenderKey(groupId) {
        const group = await this.getGroup(groupId);
        
        // Generate new sender key
        const newSenderKey = await this.generateSenderKey(groupId);
        
        // Update group
        group.senderKeyId = newSenderKey.id;
        group.chainKey = newSenderKey.chainKey;
        group.iteration = 0;
        await this.storeGroup(group);
        
        // Distribute to all current members
        await this.distributeSenderKey(groupId, group.memberIds);
        
        console.log('🔄 Sender key rotated for group:', groupId);
    }

    /**
     * Promote user to admin
     */
    async promoteToAdmin(groupId, userId) {
        const group = await this.getGroup(groupId);
        
        if (!group.adminIds.includes(this.signalCrypto.userId)) {
            throw new Error('Only admins can promote members');
        }
        
        if (!group.memberIds.includes(userId)) {
            throw new Error('User is not a member');
        }
        
        if (group.adminIds.includes(userId)) {
            throw new Error('User is already an admin');
        }
        
        group.adminIds.push(userId);
        await this.storeGroup(group);
        
        await this.sendSystemMessage(groupId, `User ${userId} is now an admin`);
        
        console.log('✅ User promoted to admin:', userId);
    }

    /**
     * Distribute sender key to members
     */
    async distributeSenderKey(groupId, memberIds) {
        const senderKey = await this.getSenderKey(groupId);
        
        for (const memberId of memberIds) {
            if (memberId !== this.signalCrypto.userId) {
                await this.distributeSenderKeyToUser(groupId, memberId, senderKey);
            }
        }
    }

    /**
     * Distribute sender key to specific user
     */
    async distributeSenderKeyToUser(groupId, userId, senderKey) {
        try {
            // Encrypt sender key with user's Signal Protocol session
            const hasSession = await this.signalCrypto.hasSession(userId);
            if (!hasSession) {
                await this.signalCrypto.createSession(userId);
            }
            
            const keyData = JSON.stringify(senderKey);
            const encrypted = await this.signalCrypto.encryptMessage(userId, keyData);
            
            // Send via server
            await fetch('/api/messaging/distribute-sender-key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    group_id: groupId,
                    recipient_id: userId,
                    encrypted_key: encrypted.body,
                    message_type: encrypted.type
                })
            });
        } catch (error) {
            console.error('❌ Failed to distribute sender key:', error);
        }
    }

    /**
     * Encrypt sender key for multiple members
     */
    async encryptSenderKeyForMembers(senderKey, memberIds) {
        const encrypted = {};
        const keyData = JSON.stringify(senderKey);
        
        for (const memberId of memberIds) {
            if (memberId !== this.signalCrypto.userId) {
                try {
                    const hasSession = await this.signalCrypto.hasSession(memberId);
                    if (!hasSession) {
                        // Will be distributed later when session is established
                        continue;
                    }
                    
                    const encryptedKey = await this.signalCrypto.encryptMessage(memberId, keyData);
                    encrypted[memberId] = encryptedKey;
                } catch (error) {
                    console.error(`Failed to encrypt key for ${memberId}:`, error);
                }
            }
        }
        
        return encrypted;
    }

    /**
     * Send system message to group
     */
    async sendSystemMessage(groupId, message) {
        // Implementation depends on messaging system
        console.log(`[SYSTEM] ${groupId}: ${message}`);
    }

    /**
     * Storage methods
     */
    async storeGroup(groupData) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['groups'], 'readwrite');
            const store = tx.objectStore('groups');
            const request = store.put(groupData);
            
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getGroup(groupId) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['groups'], 'readonly');
            const store = tx.objectStore('groups');
            const request = store.get(groupId);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getSenderKey(groupId) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['senderKeys'], 'readonly');
            const store = tx.objectStore('senderKeys');
            const index = store.index('groupId');
            const request = index.get(groupId);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getSenderKeyForUser(groupId, senderId) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['senderKeys'], 'readonly');
            const store = tx.objectStore('senderKeys');
            const request = store.openCursor();
            
            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    if (cursor.value.groupId === groupId && cursor.value.senderId === senderId) {
                        resolve(cursor.value);
                    } else {
                        cursor.continue();
                    }
                } else {
                    resolve(null);
                }
            };
            request.onerror = () => reject(request.error);
        });
    }

    async updateSenderKey(senderKey) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['senderKeys'], 'readwrite');
            const store = tx.objectStore('senderKeys');
            const request = store.put(senderKey);
            
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Helper methods
     */
    generateGroupId() {
        return 'group_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    generateKeyId() {
        return Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }
}

// Export
window.GroupEncryption = GroupEncryption;
