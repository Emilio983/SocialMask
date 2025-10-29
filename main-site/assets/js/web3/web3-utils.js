/**
 * ============================================
 * WEB3 UTILITIES
 * ============================================
 * Funciones utilitarias para Web3
 * 
 * Features:
 * - Formateo de addresses
 * - Conversión de unidades
 * - Validaciones
 * - Manejo de errores
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

/**
 * Truncate Ethereum address
 * @param {string} address - Full address
 * @param {number} start - Characters to show at start
 * @param {number} end - Characters to show at end
 * @returns {string} Truncated address
 */
function truncateAddress(address, start = 6, end = 4) {
    if (!address) return '';
    if (address.length <= start + end) return address;
    
    return `${address.substring(0, start)}...${address.substring(address.length - end)}`;
}

/**
 * Convert address to checksum format
 * @param {string} address
 * @returns {string}
 */
function toChecksumAddress(address) {
    if (!address) return '';
    
    // Simple implementation (for full EIP-55, use ethers.js)
    address = address.toLowerCase().replace('0x', '');
    
    // For now, just return with 0x
    return '0x' + address;
}

/**
 * Validate Ethereum address
 * @param {string} address
 * @returns {boolean}
 */
function isValidAddress(address) {
    if (!address) return false;
    
    // Check format: 0x + 40 hex characters
    const regex = /^0x[a-fA-F0-9]{40}$/;
    return regex.test(address);
}

/**
 * Format balance from wei to readable format
 * @param {string|number} balance - Balance in wei
 * @param {number} decimals - Token decimals (default 18)
 * @param {number} displayDecimals - Decimals to display (default 4)
 * @returns {string}
 */
function formatBalance(balance, decimals = 18, displayDecimals = 4) {
    if (!balance) return '0';
    
    try {
        // Convert to number
        let balanceNum;
        if (typeof balance === 'string') {
            // Remove any non-numeric characters except decimal point
            balance = balance.replace(/[^0-9.]/g, '');
            balanceNum = parseFloat(balance);
        } else {
            balanceNum = balance;
        }
        
        // Divide by 10^decimals
        const divisor = Math.pow(10, decimals);
        const formattedBalance = balanceNum / divisor;
        
        // Format with comma separators and fixed decimals
        return formattedBalance.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: displayDecimals
        });
    } catch (error) {
        console.error('Error formatting balance:', error);
        return '0';
    }
}

/**
 * Parse balance from readable format to wei
 * @param {string|number} balance - Balance in readable format
 * @param {number} decimals - Token decimals (default 18)
 * @returns {string} Balance in wei as string
 */
function parseBalance(balance, decimals = 18) {
    if (!balance) return '0';
    
    try {
        // Convert to number
        let balanceNum = typeof balance === 'string' ? parseFloat(balance) : balance;
        
        // Multiply by 10^decimals
        const multiplier = Math.pow(10, decimals);
        const weiBalance = Math.floor(balanceNum * multiplier);
        
        return weiBalance.toString();
    } catch (error) {
        console.error('Error parsing balance:', error);
        return '0';
    }
}

/**
 * Format large numbers with K, M, B suffixes
 * @param {number} num
 * @param {number} decimals
 * @returns {string}
 */
function formatLargeNumber(num, decimals = 2) {
    if (num === 0) return '0';
    
    const k = 1000;
    const sizes = ['', 'K', 'M', 'B', 'T'];
    const i = Math.floor(Math.log(Math.abs(num)) / Math.log(k));
    
    if (i === 0) {
        return num.toFixed(decimals);
    }
    
    return (num / Math.pow(k, i)).toFixed(decimals) + sizes[i];
}

/**
 * Get network name from chain ID
 * @param {string|number} chainId - Chain ID (hex or decimal)
 * @returns {string}
 */
function getNetworkName(chainId) {
    // Convert to hex if decimal
    if (typeof chainId === 'number') {
        chainId = '0x' + chainId.toString(16);
    }
    
    const networks = {
        '0x1': 'Ethereum Mainnet',
        '0x89': 'Polygon',
        '0x13882': 'Polygon Amoy',
        '0xaa36a7': 'Sepolia',
        '0x5': 'Goerli',
        '0x539': 'Localhost'
    };
    
    return networks[chainId] || `Chain ${chainId}`;
}

/**
 * Get network color for UI
 * @param {string|number} chainId
 * @returns {string} Tailwind color class
 */
function getNetworkColor(chainId) {
    // Convert to hex if decimal
    if (typeof chainId === 'number') {
        chainId = '0x' + chainId.toString(16);
    }
    
    const colors = {
        '0x1': 'blue',      // Ethereum
        '0x89': 'purple',   // Polygon
        '0x13882': 'orange', // Amoy
        '0xaa36a7': 'green', // Sepolia
        '0x5': 'yellow',     // Goerli
        '0x539': 'gray'      // Localhost
    };
    
    return colors[chainId] || 'red';
}

/**
 * Get block explorer URL for address
 * @param {string} address
 * @param {string} chainId
 * @returns {string}
 */
function getExplorerUrl(address, chainId) {
    if (!address) return '#';
    
    // Convert to hex if decimal
    if (typeof chainId === 'number') {
        chainId = '0x' + chainId.toString(16);
    }
    
    const explorers = {
        '0x1': 'https://etherscan.io',
        '0x89': 'https://polygonscan.com',
        '0x13882': 'https://www.oklink.com/amoy',
        '0xaa36a7': 'https://sepolia.etherscan.io',
        '0x5': 'https://goerli.etherscan.io'
    };
    
    const baseUrl = explorers[chainId] || 'https://etherscan.io';
    return `${baseUrl}/address/${address}`;
}

/**
 * Get block explorer URL for transaction
 * @param {string} txHash
 * @param {string} chainId
 * @returns {string}
 */
function getTxExplorerUrl(txHash, chainId) {
    if (!txHash) return '#';
    
    // Convert to hex if decimal
    if (typeof chainId === 'number') {
        chainId = '0x' + chainId.toString(16);
    }
    
    const explorers = {
        '0x1': 'https://etherscan.io',
        '0x89': 'https://polygonscan.com',
        '0x13882': 'https://www.oklink.com/amoy',
        '0xaa36a7': 'https://sepolia.etherscan.io',
        '0x5': 'https://goerli.etherscan.io'
    };
    
    const baseUrl = explorers[chainId] || 'https://etherscan.io';
    return `${baseUrl}/tx/${txHash}`;
}

/**
 * Handle Web3 errors with user-friendly messages
 * @param {Error} error
 * @returns {string} User-friendly error message
 */
function handleWeb3Error(error) {
    console.error('Web3 Error:', error);
    
    // Smart Wallet specific errors
    if (error.code === 4001) {
        return 'Transaction rejected by user';
    }
    
    if (error.code === -32002) {
        return 'Request already pending. Please check Smart Wallet.';
    }
    
    if (error.code === -32603) {
        return 'Internal error. Please try again.';
    }
    
    // Network errors
    if (error.message && error.message.includes('network')) {
        return 'Network error. Please check your connection.';
    }
    
    // Gas errors
    if (error.message && error.message.includes('gas')) {
        return 'Insufficient gas. Please increase gas limit.';
    }
    
    // Balance errors
    if (error.message && error.message.includes('insufficient funds')) {
        return 'Insufficient funds to complete transaction.';
    }
    
    // Nonce errors
    if (error.message && error.message.includes('nonce')) {
        return 'Nonce error. Please reset your Smart Wallet account.';
    }
    
    // Generic error
    return error.message || 'An unknown error occurred';
}

/**
 * Copy text to clipboard
 * @param {string} text
 * @returns {Promise<boolean>}
 */
async function copyToClipboard(text) {
    try {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(text);
            return true;
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            return true;
        }
    } catch (error) {
        console.error('Error copying to clipboard:', error);
        return false;
    }
}

/**
 * Format timestamp to readable date
 * @param {number} timestamp - Unix timestamp
 * @returns {string}
 */
function formatTimestamp(timestamp) {
    if (!timestamp) return '';
    
    const date = new Date(timestamp * 1000);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Calculate time remaining
 * @param {number} endTimestamp - End timestamp
 * @returns {string}
 */
function timeRemaining(endTimestamp) {
    if (!endTimestamp) return '';
    
    const now = Math.floor(Date.now() / 1000);
    const diff = endTimestamp - now;
    
    if (diff <= 0) return 'Ended';
    
    const days = Math.floor(diff / 86400);
    const hours = Math.floor((diff % 86400) / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    
    if (days > 0) {
        return `${days}d ${hours}h remaining`;
    } else if (hours > 0) {
        return `${hours}h ${minutes}m remaining`;
    } else {
        return `${minutes}m remaining`;
    }
}

/**
 * Convert hex to decimal
 * @param {string} hex
 * @returns {number}
 */
function hexToDecimal(hex) {
    if (!hex) return 0;
    return parseInt(hex, 16);
}

/**
 * Convert decimal to hex
 * @param {number} decimal
 * @returns {string}
 */
function decimalToHex(decimal) {
    if (!decimal) return '0x0';
    return '0x' + decimal.toString(16);
}

/**
 * Show toast notification
 * @param {string} message
 * @param {string} type - success, error, info, warning
 */
function showToast(message, type = 'info') {
    // Simple toast implementation
    // TODO: Replace with proper toast library
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    
    const colors = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        warning: 'bg-yellow-500 text-white',
        info: 'bg-blue-500 text-white'
    };
    
    toast.className += ' ' + (colors[type] || colors.info);
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Export functions to global scope
window.Web3Utils = {
    truncateAddress,
    toChecksumAddress,
    isValidAddress,
    formatBalance,
    parseBalance,
    formatLargeNumber,
    getNetworkName,
    getNetworkColor,
    getExplorerUrl,
    getTxExplorerUrl,
    handleWeb3Error,
    copyToClipboard,
    formatTimestamp,
    timeRemaining,
    hexToDecimal,
    decimalToHex,
    showToast
};

// console.log('Web3Utils initialized');
