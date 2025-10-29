// ============================================
// thesocialmask MEMBERSHIP SYSTEM WITH STAKING
// ============================================
// New JavaScript for membership.php with staking support

const SPHE_CONTRACT = '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b';
const MEMBERSHIP_STAKING_CONTRACT = 'CONTRACT_ADDRESS_PLACEHOLDER'; // Se actualizará después del deploy
const TREASURY_WALLET = '0xa1052872c755B5B2192b54ABD5F08546eeE6aa20';

let web3;
let userAccount;
let currentPlan = null;

const PLAN_PRICES = {
    'free': 0,
    'platinum': 100,
    'gold': 250,
    'diamond': 500,
    'creator': 750
};

const PLAN_ORDER = ['free', 'platinum', 'gold', 'diamond', 'creator'];

// ABI del token SPHE (ERC20)
const SPHE_ABI = [
    {
        "inputs": [
            {"name": "spender", "type": "address"},
            {"name": "amount", "type": "uint256"}
        ],
        "name": "approve",
        "outputs": [{"name": "", "type": "bool"}],
        "stateMutability": "nonpayable",
        "type": "function"
    },
    {
        "inputs": [{"name": "account", "type": "address"}],
        "name": "balanceOf",
        "outputs": [{"name": "", "type": "uint256"}],
        "stateMutability": "view",
        "type": "function"
    },
    {
        "inputs": [
            {"name": "owner", "type": "address"},
            {"name": "spender", "type": "address"}
        ],
        "name": "allowance",
        "outputs": [{"name": "", "type": "uint256"}],
        "stateMutability": "view",
        "type": "function"
    }
];

// ABI del contrato de staking
const MEMBERSHIP_STAKING_ABI = [
    {
        "inputs": [
            {"name": "planType", "type": "string"}
        ],
        "name": "purchaseMembership",
        "outputs": [{"name": "", "type": "uint256"}],
        "stateMutability": "nonpayable",
        "type": "function"
    },
    {
        "inputs": [
            {"name": "planType", "type": "string"}
        ],
        "name": "getPlanPrice",
        "outputs": [{"name": "", "type": "uint256"}],
        "stateMutability": "view",
        "type": "function"
    },
    {
        "inputs": [
            {"name": "user", "type": "address"}
        ],
        "name": "getUserStakes",
        "outputs": [
            {
                "components": [
                    {"name": "amount", "type": "uint256"},
                    {"name": "unlockTime", "type": "uint256"},
                    {"name": "planType", "type": "string"},
                    {"name": "claimed", "type": "bool"},
                    {"name": "stakedAt", "type": "uint256"}
                ],
                "name": "",
                "type": "tuple[]"
            }
        ],
        "stateMutability": "view",
        "type": "function"
    },
    {
        "inputs": [
            {"name": "stakeId", "type": "uint256"}
        ],
        "name": "unstake",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
    }
];

async function initWeb3() {
    try {
        // Obtener smart account del usuario desde la sesión
        const response = await fetch('../api/wallet/get_smart_account.php');
        const data = await response.json();
        
        if (!data.success || !data.smart_account_address) {
            throw new Error('No se encontró una smart account. Por favor contacta a soporte.');
        }
        
        userAccount = data.smart_account_address;
        return true;
    } catch (error) {
        console.error('Error obteniendo smart account:', error);
        alert('Error al obtener tu wallet. Por favor recarga la página o contacta a soporte.');
        return false;
    }
}

async function purchasePlan(planType, amount) {
    const isLoggedIn = await checkUserSession();
    if (!isLoggedIn) {
        alert('Debes iniciar sesión para comprar un plan');
        window.location.href = '../pages/login.php';
        return;
    }

    // Check if user already has this plan
    if (currentPlan && currentPlan.type === planType) {
        alert('Ya tienes este plan activo');
        return;
    }

    // Calculate price difference for upgrades
    const currentPrice = currentPlan ? PLAN_PRICES[currentPlan.type] : 0;
    const newPrice = PLAN_PRICES[planType];
    const priceToPay = Math.max(0, newPrice - currentPrice);

    // Check if it's a downgrade
    const currentIndex = currentPlan ? PLAN_ORDER.indexOf(currentPlan.type) : 0;
    const newIndex = PLAN_ORDER.indexOf(planType);

    if (newIndex < currentIndex) {
        alert('No puedes hacer downgrade de tu plan. Contacta a soporte.');
        return;
    }

    // Show upgrade message if applicable
    if (currentPlan && currentPlan.type !== 'free' && priceToPay < newPrice) {
        const paymentAmount = priceToPay / 2;
        const stakeAmount = priceToPay / 2;
        const confirmUpgrade = confirm(
            `Upgrade de ${currentPlan.name} a ${planType.toUpperCase()}.\n\n` +
            `Precio total: ${priceToPay} SPHE\n` +
            `· 50% pago al treasury: ${paymentAmount} SPHE\n` +
            `· 50% stake (30 días): ${stakeAmount} SPHE\n\n` +
            `¿Deseas continuar?`
        );
        if (!confirmUpgrade) return;
    } else {
        const paymentAmount = priceToPay / 2;
        const stakeAmount = priceToPay / 2;
        const confirmPurchase = confirm(
            `Comprar membresía ${planType.toUpperCase()}\n\n` +
            `Precio total: ${priceToPay} SPHE\n` +
            `· 50% pago: ${paymentAmount} SPHE\n` +
            `· 50% stake (30 días): ${stakeAmount} SPHE\n\n` +
            `Los ${stakeAmount} SPHE en stake estarán disponibles para reclamar en 30 días.\n\n` +
            `¿Confirmar compra?`
        );
        if (!confirmPurchase) return;
    }

    const web3Ready = await initWeb3();
    if (!web3Ready) return;

    try {
        showLoadingState(planType, 'Preparando pago...');

        // Verificar balance de SPHE usando API del backend
        const balanceResponse = await fetch(`../api/wallet/balances.php`);
        const balanceData = await balanceResponse.json();
        
        if (!balanceData.success) {
            throw new Error('Error al verificar balance');
        }

        const spheBalance = parseFloat(balanceData.balances.sphe?.formatted || '0');
        
        if (spheBalance < priceToPay) {
            throw new Error(`Balance insuficiente. Necesitas ${priceToPay} SPHE pero tienes ${spheBalance} SPHE`);
        }

        showLoadingState(planType, 'Procesando pago con tu Smart Wallet...');

        // Procesar pago usando el sistema de pagos con Smart Wallet
        const paymentResponse = await fetch('../api/payments/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: 'MEMBERSHIP',
                amount: priceToPay,
                token: 'SPHE',
                metadata: {
                    plan: planType,
                    previous_plan: currentPlan ? currentPlan.type : 'free',
                    is_upgrade: currentPlan && currentPlan.type !== 'free'
                }
            })
        });

        const paymentResult = await paymentResponse.json();
        
        if (!paymentResult.success) {
            throw new Error(paymentResult.message || 'Error procesando el pago');
        }

        showLoadingState(planType, 'Pago exitoso, activando plan...');

        // El backend ya procesó el plan, solo mostrar éxito
        showSuccessState(planType);
        
        setTimeout(() => {
            window.location.reload();
        }, 2000);

    } catch (error) {
        console.error('Error purchasing plan:', error);
        let errorMsg = error.message || 'Error al comprar el plan';
        alert(errorMsg);
        showErrorState(planType);
    }
}

async function updateUserPlanWithStake(planType, txHash, stakeId) {
    try {
        const response = await fetch('../api/membership/purchase_with_stake.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan: planType,
                wallet_address: userAccount,
                stake_tx_hash: txHash,
                stake_id_on_contract: stakeId
            })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Error al actualizar plan');
        }

        return true;
    } catch (error) {
        console.error('Error updating plan with stake:', error);
        throw error;
    }
}

async function checkUserSession() {
    try {
        const response = await fetch('../api/check_session.php');
        const data = await response.json();
        return data.success;
    } catch (error) {
        return false;
    }
}

async function loadCurrentPlan() {
    try {
        const response = await fetch('../api/get_current_plan.php');
        const data = await response.json();

        if (data.success) {
            currentPlan = data.plan;
            updateUIWithCurrentPlan();
        }
    } catch (error) {
        console.error('Error loading current plan:', error);
    }
}

function updateUIWithCurrentPlan() {
    if (!currentPlan) return;

    const planType = currentPlan.type;

    // Update all buttons based on current plan
    document.querySelectorAll('button[onclick*="purchasePlan"]').forEach(button => {
        const buttonPlanType = button.getAttribute('onclick').match(/purchasePlan\('(\w+)'/)?.[1];

        if (buttonPlanType === planType) {
            // Current plan
            button.innerHTML = 'Plan Actual';
            button.classList.remove('bg-brand-accent', 'bg-purple-500', 'hover:scale-105');
            button.classList.add('bg-brand-border', 'text-brand-text-secondary', 'cursor-not-allowed');
            button.disabled = true;
        } else {
            const currentIndex = PLAN_ORDER.indexOf(planType);
            const buttonIndex = PLAN_ORDER.indexOf(buttonPlanType);

            if (buttonIndex > currentIndex) {
                // Upgrade - calculate difference
                const currentPrice = PLAN_PRICES[planType];
                const newPrice = PLAN_PRICES[buttonPlanType];
                const difference = newPrice - currentPrice;

                // Update button text to show upgrade price
                if (difference < newPrice) {
                    button.innerHTML = `Upgrade (${difference} SPHE)`;
                }
            } else if (buttonIndex < currentIndex) {
                // Downgrade - disable
                button.innerHTML = 'No disponible';
                button.classList.remove('bg-brand-accent', 'bg-purple-500', 'hover:scale-105');
                button.classList.add('bg-gray-700', 'text-gray-500', 'cursor-not-allowed');
                button.disabled = true;
            }
        }
    });

    // Show current plan indicator at top
    const heroSection = document.querySelector('section.text-center');
    if (heroSection && planType !== 'free') {
        const indicator = document.createElement('div');
        indicator.className = 'bg-brand-bg-secondary border border-brand-accent rounded-xl p-4 max-w-md mx-auto mb-8';
        indicator.innerHTML = `
            <p class="text-sm text-brand-text-secondary mb-1">Tu plan actual</p>
            <p class="text-2xl font-bold" style="color: ${currentPlan.color}">${currentPlan.name}</p>
            ${currentPlan.expires ? `<p class="text-xs text-brand-text-secondary mt-1">Expira: ${new Date(currentPlan.expires).toLocaleDateString()}</p>` : ''}
        `;
        heroSection.appendChild(indicator);
    }
}

function showLoadingState(planType, message = 'Procesando...') {
    const button = document.querySelector(`button[onclick*="${planType}"]`);
    if(button) {
        button.disabled = true;
        button.innerHTML = message;
    }
}

function showSuccessState(planType) {
    const button = document.querySelector(`button[onclick*="${planType}"]`);
    if(button) {
        button.innerHTML = '✅ ¡Plan Activado!';
        button.classList.remove('bg-brand-accent', 'bg-purple-500');
        button.classList.add('bg-green-600');
    }
}

function showErrorState(planType) {
    const button = document.querySelector(`button[onclick*="${planType}"]`);
    if(button) {
        button.disabled = false;
        button.innerHTML = '❌ Error - Reintentar';
        button.classList.remove('bg-brand-accent', 'bg-purple-500');
        button.classList.add('bg-red-600');
        setTimeout(() => location.reload(), 3000);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load current plan
    loadCurrentPlan();

    // Scroll animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.js-scroll-animation').forEach(el => {
        el.style.opacity = '0';
        observer.observe(el);
    });
});
