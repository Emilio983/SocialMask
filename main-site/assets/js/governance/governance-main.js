/**
 * ============================================
 * MÓDULO PRINCIPAL DE GOVERNANCE
 * ============================================
 */

class GovernanceMain {
    constructor() {
        this.currentFilters = {
            status: '',
            category: '',
            search: ''
        };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadInitialData();
    }
    
    setupEventListeners() {
        // Filtros
        const statusFilter = document.getElementById('statusFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const searchInput = document.getElementById('searchInput');
        
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.currentFilters.status = e.target.value;
                this.applyFilters();
            });
        }
        
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.currentFilters.category = e.target.value;
                this.applyFilters();
            });
        }
        
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentFilters.search = e.target.value;
                    this.applyFilters();
                }, 500);
            });
        }
        
        // Botón crear propuesta
        const createBtn = document.getElementById('createProposalBtn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }
    }
    
    loadInitialData() {
        window.GovernanceStats?.loadStats();
        window.GovernanceProposals?.loadProposals(this.currentFilters);
    }
    
    applyFilters() {
        window.GovernanceProposals?.loadProposals(this.currentFilters);
    }
    
    showCreateModal() {
        const modal = document.getElementById('createProposalModal');
        if (!modal) {
            this.renderCreateModal();
        } else {
            modal.classList.remove('hidden');
        }
    }
    
    renderCreateModal() {
        const modalHTML = `
            <div id="createProposalModal" class="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4 overflow-y-auto">
                <div class="bg-brand-bg-secondary rounded-xl max-w-2xl w-full p-6 relative">
                    <button onclick="document.getElementById('createProposalModal').classList.add('hidden')" class="absolute top-4 right-4 text-brand-text-secondary hover:text-brand-text-primary text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <h2 class="text-2xl font-bold text-brand-text-primary mb-6">
                        <i class="fas fa-file-alt mr-2 text-brand-accent"></i>
                        Crear Nueva Propuesta
                    </h2>
                    
                    <form id="createProposalForm" class="space-y-4">
                        <div>
                            <label class="block text-brand-text-primary font-medium mb-2">
                                Título de la Propuesta
                            </label>
                            <input 
                                type="text" 
                                name="title" 
                                required 
                                minlength="10"
                                class="w-full bg-brand-bg-primary text-brand-text-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:border-brand-accent"
                                placeholder="Ej: Reducir fee de swap al 0.25%"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-brand-text-primary font-medium mb-2">
                                Descripción Completa
                            </label>
                            <textarea 
                                name="description" 
                                required 
                                minlength="50"
                                rows="6"
                                class="w-full bg-brand-bg-primary text-brand-text-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:border-brand-accent resize-none"
                                placeholder="Explica tu propuesta en detalle (mínimo 50 caracteres)..."
                            ></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-brand-text-primary font-medium mb-2">
                                Categoría
                            </label>
                            <select 
                                name="category" 
                                required
                                class="w-full bg-brand-bg-primary text-brand-text-primary border border-brand-border rounded-lg px-4 py-3 focus:outline-none focus:border-brand-accent"
                            >
                                <option value="">Selecciona una categoría</option>
                                <option value="community_rule">Regla de Comunidad</option>
                                <option value="fee_change">Cambio de Tarifa</option>
                                <option value="feature_request">Solicitud de Función</option>
                                <option value="platform_change">Cambio de Plataforma</option>
                            </select>
                        </div>
                    </form>
                    
                    <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4 mt-4">
                        <h4 class="font-semibold text-brand-accent mb-2">
                            <i class="fas fa-bolt text-brand-accent mr-2"></i>
                            Acciones de la Propuesta
                        </h4>
                        <ul class="text-sm text-brand-text-secondary space-y-1">
                            <li>• La propuesta estará activa por <strong class="text-brand-text-primary">3 días</strong></li>
                            <li>• Se necesita un quórum de <strong class="text-brand-text-primary">1,000 SPHE</strong> votando</li>
                            <li>• Los votos se cuentan según tu balance de SPHE</li>
                            <li>• Tus tokens SPHE <strong class="text-brand-accent">NUNCA se bloquean</strong></li>
                        </ul>
                    </div>
                    
                    <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4 mt-4">
                        <h4 class="font-semibold text-brand-text-primary mb-2">
                            <i class="fas fa-info-circle text-brand-accent mr-2"></i>
                            Requisitos
                        </h4>
                        <ul class="text-sm text-brand-text-secondary space-y-1">
                            <li>• Necesitas al menos <strong class="text-brand-text-primary">100 SPHE</strong> para crear una propuesta</li>
                            <li>• La votación comienza inmediatamente</li>
                            <li>• Periodo de votación: <strong class="text-brand-text-primary">3 días</strong></li>
                            <li>• Quórum necesario: <strong class="text-brand-text-primary">1,000 SPHE</strong> deben votar</li>
                            <li>• Máximo 2 propuestas activas por usuario</li>
                        </ul>
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="document.getElementById('createProposalModal').classList.add('hidden')" class="flex-1 bg-brand-bg-primary hover:bg-brand-border text-brand-text-primary py-3 rounded-lg font-semibold transition">
                            Cancelar
                        </button>
                        <button type="button" onclick="window.GovernanceMain.submitProposal()" class="flex-1 bg-brand-accent hover:bg-blue-600 text-white py-3 rounded-lg font-semibold transition">
                            <i class="fas fa-rocket mr-2"></i>
                            Crear Propuesta
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    async submitProposal() {
        const form = document.getElementById('createProposalForm');
        if (!form) return;
        
        const formData = new FormData(form);
        const data = {
            title: formData.get('title'),
            description: formData.get('description'),
            category: formData.get('category')
        };
        
        if (!data.title || data.title.length < 10) {
            alert('El título debe tener al menos 10 caracteres');
            return;
        }
        
        if (!data.description || data.description.length < 50) {
            alert('La descripción debe tener al menos 50 caracteres');
            return;
        }
        
        if (!data.category) {
            alert('Por favor selecciona una categoría');
            return;
        }
        
        try {
            const response = await window.GovernanceAPI.createProposal(data);
            
            if (response.success) {
                alert(response.message);
                document.getElementById('createProposalModal').classList.add('hidden');
                form.reset();
                this.loadInitialData();
            } else {
                alert(response.message || 'Error al crear propuesta');
            }
        } catch (error) {
            console.error('Error:', error);
            alert(error.message || 'Error al crear propuesta');
        }
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.GovernanceMain = new GovernanceMain();
    });
} else {
    window.GovernanceMain = new GovernanceMain();
}
