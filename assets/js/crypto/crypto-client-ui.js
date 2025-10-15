/**
 * ============================================
 * CRYPTO CLIENT UI
 * ============================================
 * Interfaz de usuario amigable para CryptoClient
 */

class CryptoClientUI {
    constructor() {
        this.cryptoClient = null;
        this.initialized = false;
    }

    /**
     * Inicializar UI
     */
    async init(userId) {
        try {
            // Inicializar CryptoClient
            this.cryptoClient = new CryptoClient();
            await this.cryptoClient.init(userId);

            // Generar o cargar keypair
            await this.cryptoClient.getOrGenerateKeyPair();

            this.initialized = true;
            console.log('✅ CryptoClient UI initialized');

            // Limpiar archivos temporales antiguos
            await this.cryptoClient.cleanupOldTempFiles();

            return true;
        } catch (error) {
            console.error('Error initializing CryptoClientUI:', error);
            return false;
        }
    }

    /**
     * Mostrar diálogo de upload de archivo
     */
    showUploadDialog(recipientIds, onSuccess) {
        const modal = this.createModal('Upload Encrypted File', `
            <div class="crypto-upload-modal">
                <div class="upload-area" id="uploadArea">
                    <i class="fas fa-cloud-upload-alt fa-3x"></i>
                    <p>Drag & drop file here or click to select</p>
                    <input type="file" id="fileInput" style="display: none;">
                    <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                        Select File
                    </button>
                </div>

                <div class="upload-options" style="display: none;" id="uploadOptions">
                    <div class="file-preview" id="filePreview"></div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="generateThumbnail" checked>
                            Generate thumbnail (for images)
                        </label>
                    </div>

                    <div class="recipients-list">
                        <strong>Recipients:</strong>
                        <div id="recipientsList"></div>
                    </div>

                    <div class="progress-container" style="display: none;" id="progressContainer">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                        </div>
                        <p class="progress-text" id="progressText">Starting...</p>
                    </div>

                    <div class="button-group">
                        <button class="btn btn-primary" id="uploadBtn">
                            <i class="fas fa-lock"></i> Encrypt & Upload
                        </button>
                        <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        `);

        document.body.appendChild(modal);

        let selectedFile = null;

        // Handle file selection
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');
        const uploadOptions = document.getElementById('uploadOptions');
        const filePreview = document.getElementById('filePreview');
        const recipientsList = document.getElementById('recipientsList');

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                selectedFile = e.target.files[0];
                this.showFilePreview(selectedFile, filePreview);
                uploadArea.style.display = 'none';
                uploadOptions.style.display = 'block';
            }
        });

        // Drag & drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length > 0) {
                selectedFile = e.dataTransfer.files[0];
                this.showFilePreview(selectedFile, filePreview);
                uploadArea.style.display = 'none';
                uploadOptions.style.display = 'block';
            }
        });

        // Show recipients
        recipientsList.innerHTML = recipientIds.map(id => 
            `<span class="recipient-badge">User ${id}</span>`
        ).join('');

        // Handle upload
        document.getElementById('uploadBtn').addEventListener('click', async () => {
            if (!selectedFile) return;

            const generateThumbnail = document.getElementById('generateThumbnail').checked;
            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const uploadBtn = document.getElementById('uploadBtn');

            progressContainer.style.display = 'block';
            uploadBtn.disabled = true;

            try {
                const result = await this.cryptoClient.encryptAndUploadFile(
                    selectedFile,
                    recipientIds,
                    {
                        generateThumbnail,
                        onProgress: (status) => {
                            const steps = {
                                'thumbnail': { progress: 10, text: 'Generating thumbnail...' },
                                'encrypt': { progress: 40, text: 'Encrypting file...' },
                                'wrap': { progress: 60, text: 'Wrapping keys...' },
                                'upload': { progress: 80, text: 'Uploading to IPFS...' },
                                'metadata': { progress: 95, text: 'Storing metadata...' },
                                'complete': { progress: 100, text: 'Complete!' }
                            };

                            const step = steps[status.step] || { progress: status.progress, text: 'Processing...' };
                            progressFill.style.width = step.progress + '%';
                            progressText.textContent = step.text;
                        }
                    }
                );

                // Success
                this.showNotification('✅ File encrypted and uploaded successfully!', 'success');
                modal.remove();
                
                if (onSuccess) {
                    onSuccess(result);
                }

            } catch (error) {
                console.error('Upload error:', error);
                this.showNotification('❌ Upload failed: ' + error.message, 'error');
                uploadBtn.disabled = false;
            }
        });
    }

    /**
     * Mostrar preview de archivo
     */
    showFilePreview(file, container) {
        const size = this.formatFileSize(file.size);
        const icon = this.getFileIcon(file.type);

        let preview = `
            <div class="file-info">
                <i class="${icon} fa-3x"></i>
                <div class="file-details">
                    <strong>${file.name}</strong>
                    <p>${file.type || 'Unknown type'} • ${size}</p>
                </div>
            </div>
        `;

        // Si es imagen, mostrar preview
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview = `
                    <div class="image-preview">
                        <img src="${e.target.result}" alt="Preview" style="max-width: 100%; max-height: 200px;">
                    </div>
                    ${preview}
                `;
                container.innerHTML = preview;
            };
            reader.readAsDataURL(file);
        } else {
            container.innerHTML = preview;
        }
    }

    /**
     * Descargar y descifrar archivo
     */
    async downloadFile(cid, fileName) {
        try {
            this.showNotification('🔓 Decrypting and downloading...', 'info');

            const result = await this.cryptoClient.downloadAndDecryptFile(cid);

            // Crear download link
            const url = URL.createObjectURL(result.blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName || result.fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.showNotification('✅ File downloaded successfully!', 'success');
            return true;

        } catch (error) {
            console.error('Download error:', error);
            this.showNotification('❌ Download failed: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Exportar claves
     */
    async exportKeys() {
        const password = await this.promptPassword('Enter password to encrypt export file:');
        if (!password) return;

        const confirmPassword = await this.promptPassword('Confirm password:');
        if (password !== confirmPassword) {
            this.showNotification('❌ Passwords do not match', 'error');
            return;
        }

        try {
            const exportBlob = await this.cryptoClient.exportKeys(password);

            // Download
            const url = URL.createObjectURL(exportBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `socialmask-keys-${Date.now()}.encrypted`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.showNotification('✅ Keys exported successfully!', 'success');
            return true;

        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('❌ Export failed: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Importar claves
     */
    async importKeys() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.encrypted';

        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const password = await this.promptPassword('Enter password to decrypt key file:');
            if (!password) return;

            try {
                const result = await this.cryptoClient.importKeys(file, password);
                this.showNotification('✅ Keys imported successfully!', 'success');
                
                // Reinitialize
                await this.init(result.userId);
                
                return true;

            } catch (error) {
                console.error('Import error:', error);
                this.showNotification('❌ Import failed: ' + error.message, 'error');
                return false;
            }
        };

        input.click();
    }

    /**
     * Mostrar estadísticas
     */
    async showStats() {
        const stats = await this.cryptoClient.getStats();

        const modal = this.createModal('Crypto Statistics', `
            <div class="crypto-stats">
                <div class="stat-item">
                    <i class="fas fa-key"></i>
                    <div>
                        <strong>Keypair Status</strong>
                        <p>${stats.hasKeyPair ? '✅ Generated' : '❌ Not generated'}</p>
                    </div>
                </div>
                
                <div class="stat-item">
                    <i class="fas fa-file"></i>
                    <div>
                        <strong>Encrypted Files</strong>
                        <p>${stats.fileMetadataCount} files</p>
                    </div>
                </div>
                
                <div class="stat-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Temporary Files</strong>
                        <p>${stats.tempFilesCount} files</p>
                    </div>
                </div>

                <div class="button-group">
                    <button class="btn btn-primary" onclick="cryptoClientUI.exportKeys()">
                        <i class="fas fa-download"></i> Export Keys
                    </button>
                    <button class="btn btn-secondary" onclick="cryptoClientUI.importKeys()">
                        <i class="fas fa-upload"></i> Import Keys
                    </button>
                </div>
            </div>
        `);

        document.body.appendChild(modal);
    }

    /**
     * Crear modal
     */
    createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal crypto-modal';
        modal.innerHTML = `
            <div class="modal-overlay" onclick="this.closest('.modal').remove()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        `;
        return modal;
    }

    /**
     * Prompt para password
     */
    promptPassword(message) {
        return new Promise((resolve) => {
            const modal = this.createModal('Password Required', `
                <div class="password-prompt">
                    <p>${message}</p>
                    <input type="password" id="passwordInput" class="form-control" autofocus>
                    <div class="button-group">
                        <button class="btn btn-primary" id="confirmBtn">OK</button>
                        <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                    </div>
                </div>
            `);

            document.body.appendChild(modal);

            const input = document.getElementById('passwordInput');
            const confirmBtn = document.getElementById('confirmBtn');

            const submit = () => {
                const password = input.value;
                modal.remove();
                resolve(password);
            };

            confirmBtn.onclick = submit;
            input.onkeypress = (e) => {
                if (e.key === 'Enter') submit();
            };

            // Handle cancel
            modal.querySelector('.modal-overlay').onclick = () => {
                modal.remove();
                resolve(null);
            };
        });
    }

    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Formatear tamaño de archivo
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Obtener icono de archivo
     */
    getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return 'fas fa-image';
        if (mimeType.startsWith('video/')) return 'fas fa-video';
        if (mimeType.startsWith('audio/')) return 'fas fa-music';
        if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
        if (mimeType.includes('word')) return 'fas fa-file-word';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
        if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'fas fa-file-archive';
        return 'fas fa-file';
    }
}

// Instancia global
window.cryptoClientUI = new CryptoClientUI();
