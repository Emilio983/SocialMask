/**
 * ============================================
 * PROPOSAL TEMPLATES UI
 * ============================================
 * Easy-to-use interface for creating proposals from templates
 */

class ProposalTemplatesUI {
    constructor() {
        this.apiBase = '/api/governance';
        this.contractAddress = null;
        this.selectedTemplate = null;
    }

    /**
     * Initialize templates system
     */
    async init(contractAddress) {
        this.contractAddress = contractAddress;
        await this.loadTemplates();
    }

    /**
     * Load all templates
     */
    async loadTemplates() {
        try {
            const response = await fetch(`${this.apiBase}/templates-list.php`);
            const result = await response.json();

            if (result.success) {
                this.renderTemplatesGrid(result.templates);
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    }

    /**
     * Render templates grid
     */
    renderTemplatesGrid(templates) {
        const container = document.getElementById('templates-grid');
        if (!container) return;

        // Group by category
        const categories = {
            'TREASURY': { name: 'Treasury', icon: '💰', color: '#3b82f6' },
            'PARAMETER': { name: 'Parameters', icon: '⚙️', color: '#8b5cf6' },
            'MEMBER': { name: 'Team', icon: '👥', color: '#10b981' },
            'EMERGENCY': { name: 'Emergency', icon: '🚨', color: '#ef4444' },
            'UPGRADE': { name: 'Upgrades', icon: '⬆️', color: '#f59e0b' },
            'CUSTOM': { name: 'Custom', icon: '✨', color: '#6b7280' }
        };

        const groupedTemplates = {};
        templates.forEach(template => {
            if (!groupedTemplates[template.category]) {
                groupedTemplates[template.category] = [];
            }
            groupedTemplates[template.category].push(template);
        });

        container.innerHTML = Object.keys(categories).map(categoryKey => {
            const category = categories[categoryKey];
            const categoryTemplates = groupedTemplates[categoryKey] || [];
            
            if (categoryTemplates.length === 0) return '';

            return `
                <div class="template-category">
                    <div class="category-header">
                        <span class="category-icon">${category.icon}</span>
                        <h3>${category.name}</h3>
                        <span class="category-count">${categoryTemplates.length}</span>
                    </div>
                    <div class="templates-cards">
                        ${categoryTemplates.map(template => this.renderTemplateCard(template, category.color)).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Render individual template card
     */
    renderTemplateCard(template, color) {
        return `
            <div class="template-card" onclick="proposalTemplates.selectTemplate(${template.template_id})" style="border-left-color: ${color}">
                <div class="template-header">
                    <h4>${template.name}</h4>
                    <span class="usage-badge">${template.usage_count} uses</span>
                </div>
                <p class="template-description">${template.description}</p>
                <div class="template-fields">
                    <i class="fas fa-list"></i>
                    <span>${template.fields.length} fields</span>
                </div>
            </div>
        `;
    }

    /**
     * Select template and show form
     */
    async selectTemplate(templateId) {
        try {
            const response = await fetch(`${this.apiBase}/template-details.php?id=${templateId}`);
            const result = await response.json();

            if (result.success) {
                this.selectedTemplate = result.template;
                this.showTemplateForm(result.template);
            }
        } catch (error) {
            console.error('Failed to load template:', error);
        }
    }

    /**
     * Show template form modal
     */
    showTemplateForm(template) {
        const modal = document.getElementById('template-modal') || this.createModal();
        
        const fields = JSON.parse(template.fields);
        
        modal.innerHTML = `
            <div class="modal-content-template">
                <span class="close" onclick="proposalTemplates.closeModal()">&times;</span>
                
                <div class="modal-header">
                    <h2>${template.name}</h2>
                    <p>${template.description}</p>
                </div>

                <form id="template-form" onsubmit="proposalTemplates.submitProposal(event)">
                    <div class="form-section">
                        <h3>Proposal Details</h3>
                        
                        <div class="form-group">
                            <label for="proposal-title">Title *</label>
                            <input type="text" id="proposal-title" required 
                                   placeholder="Enter a descriptive title"
                                   value="${template.name}">
                        </div>

                        <div class="form-group">
                            <label for="proposal-description">Description</label>
                            <textarea id="proposal-description" rows="3"
                                      placeholder="Provide additional context">${template.description}</textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Template Fields</h3>
                        
                        ${fields.map((field, index) => this.renderFormField(field, index)).join('')}
                    </div>

                    <div class="form-section">
                        <div class="form-preview">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Preview:</strong>
                                <p>Review all fields before submission. This proposal will go through the standard governance process.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="proposalTemplates.closeModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Proposal
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        modal.style.display = 'flex';
    }

    /**
     * Render form field based on type
     */
    renderFormField(field, index) {
        const required = field.required ? 'required' : '';
        const placeholder = field.description || `Enter ${field.name}`;

        let inputHtml = '';

        switch (field.type) {
            case 'address':
                inputHtml = `
                    <input type="text" 
                           id="field-${index}" 
                           name="${field.name}"
                           pattern="0x[a-fA-F0-9]{40}"
                           placeholder="${placeholder}"
                           ${required}>
                `;
                break;

            case 'uint256':
                inputHtml = `
                    <input type="number" 
                           id="field-${index}" 
                           name="${field.name}"
                           min="0"
                           step="1"
                           placeholder="${placeholder}"
                           ${required}>
                `;
                break;

            case 'bool':
                inputHtml = `
                    <select id="field-${index}" name="${field.name}" ${required}>
                        <option value="">Select...</option>
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>
                `;
                break;

            case 'string':
            default:
                if (field.description && field.description.length > 50) {
                    inputHtml = `
                        <textarea id="field-${index}" 
                                  name="${field.name}"
                                  rows="3"
                                  placeholder="${placeholder}"
                                  ${required}></textarea>
                    `;
                } else {
                    inputHtml = `
                        <input type="text" 
                               id="field-${index}" 
                               name="${field.name}"
                               placeholder="${placeholder}"
                               ${required}>
                    `;
                }
                break;
        }

        return `
            <div class="form-group">
                <label for="field-${index}">
                    ${field.name} ${field.required ? '*' : ''}
                    <span class="field-type">(${field.type})</span>
                </label>
                <p class="field-description">${field.description}</p>
                ${inputHtml}
            </div>
        `;
    }

    /**
     * Submit proposal
     */
    async submitProposal(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const title = document.getElementById('proposal-title').value;
        const description = document.getElementById('proposal-description').value;

        const fieldValues = {};
        formData.forEach((value, key) => {
            if (key !== 'title' && key !== 'description') {
                fieldValues[key] = value;
            }
        });

        try {
            const response = await fetch(`${this.apiBase}/create-from-template.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    templateId: this.selectedTemplate.template_id,
                    title: title,
                    description: description,
                    fieldValues: fieldValues
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Proposal created successfully!');
                this.closeModal();
                setTimeout(() => window.location.reload(), 2000);
            } else {
                throw new Error(result.error || 'Failed to create proposal');
            }

        } catch (error) {
            this.showError(error.message);
        }
    }

    /**
     * Create modal
     */
    createModal() {
        const modal = document.createElement('div');
        modal.id = 'template-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
        return modal;
    }

    /**
     * Close modal
     */
    closeModal() {
        const modal = document.getElementById('template-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showNotification(message, 'error');
    }

    /**
     * Show notification
     */
    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

// Global instance
const proposalTemplates = new ProposalTemplatesUI();

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    const contractAddress = document.querySelector('[data-templates-contract]')?.dataset.templatesContract;
    if (contractAddress) {
        proposalTemplates.init(contractAddress);
    }
});
