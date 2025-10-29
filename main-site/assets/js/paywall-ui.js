/**
 * UI Components para Pay-Per-View con Gelato Relay
 */

/**
 * Mostrar modal de compra de contenido
 * @param {number} contentId - ID del contenido
 * @param {object} contentInfo - Información del contenido
 */
async function showPurchaseModal(contentId, contentInfo) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'purchaseModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-lock"></i> Desbloquear Contenido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="content-preview mb-3">
                        <p class="text-muted">
                            Este contenido requiere pago para acceder
                        </p>
                    </div>
                    
                    <div class="price-info mb-3">
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                            <span class="fw-bold">Precio:</span>
                            <span class="fs-4 text-primary">
                                ${contentInfo.price} SPHE
                            </span>
                        </div>
                    </div>
                    
                    <div class="user-balance mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Tu balance:</span>
                            <span id="userBalance" class="text-muted">
                                Cargando...
                            </span>
                        </div>
                    </div>
                    
                    <div class="gasless-info alert alert-info">
                        <i class="fas fa-gas-pump"></i>
                        <strong>Sin gas!</strong> 
                        Esta transacción no requiere MATIC para gas gracias a Gelato Relay.
                    </div>
                    
                    <div id="purchaseStatus" class="mt-3" style="display: none;">
                        <!-- Progress bar -->
                        <div class="progress mb-2" style="height: 25px;">
                            <div id="purchaseProgress" 
                                 class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: 0%">
                                0%
                            </div>
                        </div>
                        <div id="purchaseStatusText" class="text-center text-muted">
                            Iniciando...
                        </div>
                    </div>
                    
                    <div id="purchaseError" class="alert alert-danger mt-3" style="display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnPurchaseGasless">
                        <i class="fas fa-unlock"></i> Comprar sin gas
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btnPurchaseNormal">
                        <i class="fas fa-gas-pump"></i> Comprar con gas
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    // Cargar balance del usuario
    loadUserBalance();
    
    // Event listeners
    document.getElementById('btnPurchaseGasless').addEventListener('click', async () => {
        await purchaseGasless(contentId);
    });
    
    document.getElementById('btnPurchaseNormal').addEventListener('click', async () => {
        await purchaseNormal(contentId);
    });
    
    // Cleanup al cerrar
    modal.addEventListener('hidden.bs.modal', () => {
        modal.remove();
    });
}

/**
 * Cargar balance del usuario
 */
async function loadUserBalance() {
    try {
        const balance = await payPerViewGasless.getUserBalance();
        document.getElementById('userBalance').textContent = `${balance} SPHE`;
        document.getElementById('userBalance').classList.remove('text-muted');
        document.getElementById('userBalance').classList.add('text-success', 'fw-bold');
    } catch (error) {
        console.error("Error loading balance:", error);
        document.getElementById('userBalance').textContent = "Error";
    }
}

/**
 * Comprar contenido con Gelato (gasless)
 */
async function purchaseGasless(contentId) {
    const statusDiv = document.getElementById('purchaseStatus');
    const errorDiv = document.getElementById('purchaseError');
    const progressBar = document.getElementById('purchaseProgress');
    const statusText = document.getElementById('purchaseStatusText');
    const btnGasless = document.getElementById('btnPurchaseGasless');
    const btnNormal = document.getElementById('btnPurchaseNormal');
    
    // Mostrar status
    statusDiv.style.display = 'block';
    errorDiv.style.display = 'none';
    btnGasless.disabled = true;
    btnNormal.disabled = true;
    
    try {
        const result = await payPerViewGasless.purchaseContentGasless(
            contentId,
            (update) => {
                // Update progress bar
                const progress = (update.step / update.total) * 100;
                progressBar.style.width = `${progress}%`;
                progressBar.textContent = `${Math.round(progress)}%`;
                
                // Update status text
                statusText.innerHTML = `
                    <div>
                        ${update.status}
                        ${update.taskId ? `<br><small>Task: ${update.taskId.substring(0, 10)}...</small>` : ''}
                    </div>
                `;
                
                // Update progress bar color based on state
                progressBar.classList.remove('bg-success', 'bg-danger', 'bg-warning');
                if (update.taskState === 'ExecSuccess') {
                    progressBar.classList.add('bg-success');
                } else if (update.taskState === 'Error') {
                    progressBar.classList.add('bg-danger');
                } else {
                    progressBar.classList.add('bg-primary');
                }
            }
        );
        
        // Success!
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        progressBar.classList.add('bg-success');
        statusText.innerHTML = `
            <div class="text-success">
                <i class="fas fa-check-circle"></i> ¡Contenido desbloqueado!
                <br><small>TX: ${result.txHash.substring(0, 10)}...</small>
            </div>
        `;
        
        // Cerrar modal después de 2 segundos y recargar contenido
        setTimeout(() => {
            bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
            location.reload(); // Recargar para mostrar contenido desbloqueado
        }, 2000);
        
    } catch (error) {
        console.error("Purchase error:", error);
        
        progressBar.classList.add('bg-danger');
        progressBar.classList.remove('progress-bar-animated');
        
        errorDiv.style.display = 'block';
        errorDiv.innerHTML = `
            <strong>Error:</strong> ${error.message}
            <br><small>Intenta nuevamente o usa la opción "Comprar con gas"</small>
        `;
        
        btnGasless.disabled = false;
        btnNormal.disabled = false;
    }
}

/**
 * Comprar contenido normal (con gas)
 */
async function purchaseNormal(contentId) {
    const statusDiv = document.getElementById('purchaseStatus');
    const errorDiv = document.getElementById('purchaseError');
    const btnGasless = document.getElementById('btnPurchaseGasless');
    const btnNormal = document.getElementById('btnPurchaseNormal');
    
    statusDiv.style.display = 'block';
    errorDiv.style.display = 'none';
    btnGasless.disabled = true;
    btnNormal.disabled = true;
    
    document.getElementById('purchaseStatusText').textContent = 'Procesando transacción...';
    
    try {
        const result = await payPerViewGasless.purchaseContentNormal(contentId);
        
        document.getElementById('purchaseStatusText').innerHTML = `
            <div class="text-success">
                <i class="fas fa-check-circle"></i> ¡Contenido desbloqueado!
                <br><small>TX: ${result.txHash.substring(0, 10)}...</small>
            </div>
        `;
        
        setTimeout(() => {
            bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
            location.reload();
        }, 2000);
        
    } catch (error) {
        console.error("Purchase error:", error);
        
        errorDiv.style.display = 'block';
        errorDiv.innerHTML = `<strong>Error:</strong> ${error.message}`;
        
        btnGasless.disabled = false;
        btnNormal.disabled = false;
    }
}

/**
 * Renderizar botón de unlock para contenido bloqueado
 * @param {number} contentId 
 * @param {object} contentInfo 
 * @param {string} targetElementId 
 */
function renderUnlockButton(contentId, contentInfo, targetElementId) {
    const targetElement = document.getElementById(targetElementId);
    if (!targetElement) return;
    
    const button = document.createElement('button');
    button.className = 'btn btn-primary btn-unlock';
    button.innerHTML = `
        <i class="fas fa-lock"></i>
        Desbloquear por ${contentInfo.price} SPHE
        <span class="badge bg-light text-dark ms-2">
            <i class="fas fa-gas-pump" style="text-decoration: line-through;"></i>
            Sin gas
        </span>
    `;
    
    button.addEventListener('click', () => {
        showPurchaseModal(contentId, contentInfo);
    });
    
    targetElement.appendChild(button);
}

/**
 * Renderizar overlay de contenido bloqueado
 * @param {number} contentId 
 * @param {object} contentInfo 
 * @param {string} targetElementId 
 */
function renderContentLock(contentId, contentInfo, targetElementId) {
    const targetElement = document.getElementById(targetElementId);
    if (!targetElement) return;
    
    const overlay = document.createElement('div');
    overlay.className = 'content-lock-overlay';
    overlay.innerHTML = `
        <div class="lock-content">
            <i class="fas fa-lock fa-3x mb-3"></i>
            <h4>Contenido Exclusivo</h4>
            <p class="text-muted">
                Este contenido requiere pago para acceder
            </p>
            <div class="price-badge mb-3">
                ${contentInfo.price} SPHE
            </div>
            <button class="btn btn-primary btn-lg" onclick="showPurchaseModal(${contentId}, ${JSON.stringify(contentInfo).replace(/"/g, '&quot;')})">
                <i class="fas fa-unlock"></i> Desbloquear
                <span class="badge bg-light text-dark ms-2">Sin gas</span>
            </button>
        </div>
    `;
    
    // Aplicar blur al contenido
    targetElement.style.filter = 'blur(10px)';
    targetElement.style.pointerEvents = 'none';
    targetElement.style.position = 'relative';
    
    // Insertar overlay
    targetElement.parentElement.style.position = 'relative';
    targetElement.parentElement.appendChild(overlay);
}

// CSS para los componentes (agregar a main.css)
const paywallStyles = `
<style>
.content-lock-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.lock-content {
    text-align: center;
    padding: 2rem;
}

.price-badge {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50px;
    font-size: 1.5rem;
    font-weight: bold;
}

.btn-unlock {
    font-weight: 600;
    padding: 0.75rem 1.5rem;
}

.gasless-info {
    border-left: 4px solid #17a2b8;
}

.gelato-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

.gelato-status.success {
    background: #d4edda;
    color: #155724;
}

.gelato-status.error {
    background: #f8d7da;
    color: #721c24;
}

.gelato-status.warning {
    background: #fff3cd;
    color: #856404;
}

.gelato-status.info {
    background: #d1ecf1;
    color: #0c5460;
}
</style>
`;

// Inyectar estilos
if (typeof document !== 'undefined') {
    document.head.insertAdjacentHTML('beforeend', paywallStyles);
}
