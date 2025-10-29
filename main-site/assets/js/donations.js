// ===============================
// DONATIONS.JS - Sistema de Donaciones
// ===============================

// Configuración
const DONATIONS_CONTRACT_ADDRESS = '0x0000000000000000000000000000000000000000'; // TODO: Actualizar después del deploy
const SPHE_CONTRACT_ADDRESS = '0x0000000000000000000000000000000000000000'; // TODO: Actualizar desde .env

// ABIs simplificados
const DONATIONS_ABI = [
    "function donate(uint256 campaignId, uint256 amount, string memory message) external",
    "function getCampaign(uint256 campaignId) external view returns (address creator, uint256 goal, uint256 raised, bool active)",
    "event DonationReceived(uint256 indexed campaignId, address indexed donor, uint256 amount, string message)"
];

const ERC20_ABI = [
    "function approve(address spender, uint256 amount) external returns (bool)",
    "function allowance(address owner, address spender) external view returns (uint256)",
    "function balanceOf(address account) external view returns (uint256)",
    "function decimals() external view returns (uint8)"
];

// Variables globales
let provider, signer, donationsContract, spheContract;
let currentCampaignId = null;
let currentFilter = 'all';
let userBalance = 0;

// ===============================
// INICIALIZACIÓN
// ===============================

async function init() {
    console.log('🚀 Inicializando sistema de donaciones...');
    
    // Cargar campañas
    await loadCampaigns();
    
    // Inicializar Web3 si está disponible
    if (window.smartWalletProvider) {
        try {
            provider = new ethers.BrowserProvider(window.smartWalletProvider);
            signer = await provider.getSigner();
            
            donationsContract = new ethers.Contract(
                DONATIONS_CONTRACT_ADDRESS,
                DONATIONS_ABI,
                signer
            );
            
            spheContract = new ethers.Contract(
                SPHE_CONTRACT_ADDRESS,
                ERC20_ABI,
                signer
            );
            
            // Cargar balance del usuario
            await loadUserBalance();
            
            console.log('✅ Web3 inicializado');
        } catch (error) {
            console.error('❌ Error inicializando Web3:', error);
            showToast('Please connect your wallet', 'error');
        }
    } else {
        console.warn('⚠️ Smart Wallet no detectada');
    }
}

// Cargar balance del usuario
async function loadUserBalance() {
    if (!signer || !spheContract) return;
    
    try {
        const address = await signer.getAddress();
        const balance = await spheContract.balanceOf(address);
        userBalance = parseFloat(ethers.formatEther(balance));
        
        const balanceEl = document.getElementById('user-balance');
        if (balanceEl) {
            balanceEl.textContent = userBalance.toFixed(2);
        }
    } catch (error) {
        console.error('Error loading balance:', error);
    }
}

// ===============================
// CARGAR CAMPAÑAS
// ===============================

async function loadCampaigns(filter = 'all') {
    currentFilter = filter;
    
    const loading = document.getElementById('campaigns-loading');
    const list = document.getElementById('campaigns-list');
    const empty = document.getElementById('campaigns-empty');
    
    loading.style.display = 'block';
    list.style.display = 'none';
    empty.style.display = 'none';
    
    try {
        let url = '/api/donations/get_campaigns.php?status=active';
        
        if (filter === 'my') {
            url += `&user_id=${USER_ID}`;
        } else if (filter === 'completed') {
            url = '/api/donations/get_campaigns.php?status=completed';
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.campaigns && data.campaigns.length > 0) {
            displayCampaigns(data.campaigns);
            list.style.display = 'grid';
        } else {
            empty.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading campaigns:', error);
        showToast('Error loading campaigns', 'error');
        empty.style.display = 'block';
    } finally {
        loading.style.display = 'none';
    }
}

// Mostrar campañas en el DOM
function displayCampaigns(campaigns) {
    const container = document.getElementById('campaigns-list');
    container.innerHTML = '';
    
    campaigns.forEach(campaign => {
        const progress = campaign.goal_amount > 0 
            ? (campaign.raised_amount / campaign.goal_amount) * 100 
            : 0;
        
        const isOwner = campaign.user_id == USER_ID;
        
        const card = document.createElement('div');
        card.className = 'campaign-card';
        card.innerHTML = `
            <div class="campaign-card-header">
                <div class="campaign-creator">
                    <div class="creator-avatar">
                        ${campaign.username ? campaign.username.charAt(0).toUpperCase() : 'U'}
                    </div>
                    <span class="creator-name">${escapeHtml(campaign.username || 'Anonymous')}</span>
                </div>
                <span class="campaign-status ${campaign.status}">
                    ${campaign.status}
                </span>
            </div>
            
            <h3 class="campaign-title">${escapeHtml(campaign.title)}</h3>
            <p class="campaign-description">${escapeHtml(campaign.description)}</p>
            
            <div class="campaign-progress">
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: ${Math.min(progress, 100)}%"></div>
                </div>
                <div class="progress-info">
                    <span><strong>${campaign.raised_amount || 0}</strong> SPHE raised</span>
                    <span>of <strong>${campaign.goal_amount}</strong> goal</span>
                </div>
            </div>
            
            <div class="campaign-stats">
                <div class="stat-item">
                    <span class="stat-label">Progress</span>
                    <span class="stat-value">${progress.toFixed(1)}%</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Donors</span>
                    <span class="stat-value">${campaign.donation_count || 0}</span>
                </div>
                ${campaign.end_date ? `
                <div class="stat-item">
                    <span class="stat-label">Ends</span>
                    <span class="stat-value">${formatDate(campaign.end_date)}</span>
                </div>
                ` : ''}
            </div>
            
            <div class="campaign-actions">
                ${!isOwner ? `
                    <button onclick="showDonateModal(${campaign.id})" class="btn btn-primary">
                        💝 Donate
                    </button>
                ` : `
                    <button onclick="viewCampaignDetails(${campaign.id})" class="btn btn-secondary">
                        📊 View Details
                    </button>
                `}
            </div>
        `;
        
        container.appendChild(card);
    });
}

// ===============================
// CREAR CAMPAÑA
// ===============================

async function createCampaign(event) {
    event.preventDefault();
    
    const title = document.getElementById('campaign-title').value.trim();
    const description = document.getElementById('campaign-description').value.trim();
    const goalAmount = document.getElementById('campaign-goal').value;
    const endDate = document.getElementById('campaign-end-date').value;
    
    if (!title || !description || !goalAmount || goalAmount <= 0) {
        showToast('Please fill all required fields', 'error');
        return;
    }
    
    // Deshabilitar botón
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating...';
    
    try {
        const response = await fetch('/api/donations/create_campaign.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getJWT()}`
            },
            body: JSON.stringify({
                title: title,
                description: description,
                goal_amount: parseFloat(goalAmount),
                end_date: endDate || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Campaign created successfully! 🎉', 'success');
            hideCreateCampaign();
            event.target.reset();
            
            // Recargar campañas
            await loadCampaigns(currentFilter);
        } else {
            showToast(data.error || 'Failed to create campaign', 'error');
        }
    } catch (error) {
        console.error('Error creating campaign:', error);
        showToast('Network error. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// ===============================
// DONAR
// ===============================

async function donate(event) {
    event.preventDefault();
    
    if (!currentCampaignId) {
        showToast('No campaign selected', 'error');
        return;
    }
    
    const amount = document.getElementById('donate-amount').value;
    const message = document.getElementById('donate-message').value.trim();
    
    if (!amount || amount <= 0) {
        showToast('Please enter a valid amount', 'error');
        return;
    }
    
    if (parseFloat(amount) > userBalance) {
        showToast('Insufficient SPHE balance', 'error');
        return;
    }
    
    // Deshabilitar botón
    const submitBtn = document.getElementById('donate-btn-text');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Processing...';
    event.target.querySelector('button[type="submit"]').disabled = true;
    
    try {
        // 1. Verificar que tenemos wallet conectada
        if (!signer || !spheContract || !donationsContract) {
            throw new Error('Please connect your wallet first');
        }
        
        const userAddress = await signer.getAddress();
        const amountWei = ethers.parseEther(amount);
        
        // 2. Verificar balance
        const balance = await spheContract.balanceOf(userAddress);
        if (balance < amountWei) {
            throw new Error('Insufficient SPHE balance');
        }
        
        // 3. Verificar/Aprobar tokens
        submitBtn.textContent = 'Approving SPHE...';
        const allowance = await spheContract.allowance(userAddress, DONATIONS_CONTRACT_ADDRESS);
        
        if (allowance < amountWei) {
            console.log('Approving SPHE tokens...');
            const approveTx = await spheContract.approve(
                DONATIONS_CONTRACT_ADDRESS,
                ethers.MaxUint256 // Aprobar cantidad ilimitada
            );
            await approveTx.wait();
            console.log('✅ Tokens approved');
        }
        
        // 4. Donar
        submitBtn.textContent = 'Donating...';
        console.log('Sending donation...');
        
        const donateTx = await donationsContract.donate(
            currentCampaignId,
            amountWei,
            message || ''
        );
        
        console.log('Transaction sent:', donateTx.hash);
        showToast('Transaction sent! Waiting for confirmation...', 'info');
        
        // 5. Registrar en backend (mientras esperamos confirmación)
        await fetch('/api/donations/donate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getJWT()}`
            },
            body: JSON.stringify({
                campaign_id: currentCampaignId,
                amount: amount,
                tx_hash: donateTx.hash,
                donor_address: userAddress,
                message: message
            })
        });
        
        // 6. Esperar confirmación
        submitBtn.textContent = 'Confirming...';
        await donateTx.wait();
        
        console.log('✅ Donation confirmed!');
        showToast('Donation successful! Thank you! 🎉', 'success');
        
        hideDonateModal();
        event.target.reset();
        
        // Actualizar balance y recargar campañas
        await loadUserBalance();
        await loadCampaigns(currentFilter);
        
    } catch (error) {
        console.error('Error donating:', error);
        
        let errorMessage = 'Donation failed';
        if (error.message) {
            if (error.message.includes('insufficient funds')) {
                errorMessage = 'Insufficient funds for gas fees';
            } else if (error.message.includes('user rejected')) {
                errorMessage = 'Transaction rejected by user';
            } else {
                errorMessage = error.message;
            }
        }
        
        showToast(errorMessage, 'error');
    } finally {
        submitBtn.textContent = originalText;
        event.target.querySelector('button[type="submit"]').disabled = false;
    }
}

// ===============================
// MODALES
// ===============================

function showCreateCampaign() {
    const modal = document.getElementById('create-campaign-modal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideCreateCampaign() {
    const modal = document.getElementById('create-campaign-modal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

async function showDonateModal(campaignId) {
    currentCampaignId = campaignId;
    
    // Cargar info de la campaña
    try {
        const response = await fetch(`/api/donations/get_campaigns.php?campaign_id=${campaignId}`);
        const data = await response.json();
        
        if (data.success && data.campaigns && data.campaigns[0]) {
            const campaign = data.campaigns[0];
            
            const infoDiv = document.getElementById('donate-campaign-info');
            infoDiv.innerHTML = `
                <h3>${escapeHtml(campaign.title)}</h3>
                <p>${escapeHtml(campaign.description.substring(0, 150))}${campaign.description.length > 150 ? '...' : ''}</p>
                <div class="progress-info">
                    <strong>${campaign.raised_amount || 0} / ${campaign.goal_amount} SPHE</strong>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading campaign info:', error);
    }
    
    const modal = document.getElementById('donate-modal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Actualizar summary cuando cambia el amount
    const amountInput = document.getElementById('donate-amount');
    amountInput.addEventListener('input', updateDonationSummary);
}

function hideDonateModal() {
    const modal = document.getElementById('donate-modal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentCampaignId = null;
}

function updateDonationSummary() {
    const amount = document.getElementById('donate-amount').value;
    const summaryAmount = document.getElementById('summary-amount');
    if (summaryAmount && amount) {
        summaryAmount.textContent = `${amount} SPHE`;
    }
}

// ===============================
// FILTROS
// ===============================

function filterCampaigns(filter) {
    // Actualizar UI de filtros
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Cargar campañas con filtro
    loadCampaigns(filter);
}

// ===============================
// UTILIDADES
// ===============================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = date - now;
    const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
    
    if (days < 0) return 'Ended';
    if (days === 0) return 'Today';
    if (days === 1) return 'Tomorrow';
    return `${days} days`;
}

function getJWT() {
    return localStorage.getItem('jwt_token') || '';
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <span>${escapeHtml(message)}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function viewCampaignDetails(campaignId) {
    // TODO: Implementar página de detalles
    window.location.href = `/pages/campaign_details.php?id=${campaignId}`;
}

// ===============================
// INICIALIZACIÓN AL CARGAR
// ===============================

document.addEventListener('DOMContentLoaded', init);

// Exportar funciones para uso global
window.createCampaign = createCampaign;
window.donate = donate;
window.showCreateCampaign = showCreateCampaign;
window.hideCreateCampaign = hideCreateCampaign;
window.showDonateModal = showDonateModal;
window.hideDonateModal = hideDonateModal;
window.filterCampaigns = filterCampaigns;
window.viewCampaignDetails = viewCampaignDetails;
