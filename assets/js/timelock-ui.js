/**
 * ============================================
 * TIMELOCK UI CONTROLLER
 * ============================================
 * Main controller for timelock operations
 */

class TimelockUI {
    constructor() {
        this.apiBase = '/api/governance';
        this.web3 = null;
        this.contract = null;
        this.userAddress = null;
    }

    /**
     * Initialize Web3 and contract
     */
    async init() {
        try {
            if (typeof window.smartWalletProvider !== 'undefined') {
                this.web3 = new Web3(window.smartWalletProvider);
                await window.smartWalletProvider.request({ method: 'eth_requestAccounts' });
                
                const accounts = await this.web3.eth.getAccounts();
                this.userAddress = accounts[0];

                // Load contract (replace with actual deployed address)
                const contractAddress = '0x...'; // TODO: Get from config
                const contractABI = []; // TODO: Load ABI
                this.contract = new this.web3.eth.Contract(contractABI, contractAddress);

                console.log('Timelock initialized:', this.userAddress);
                return true;
            } else {
                throw new Error('Smart Wallet not detected');
            }
        } catch (error) {
            console.error('Initialization error:', error);
            this.showError('Failed to initialize Web3');
            return false;
        }
    }

    /**
     * Queue proposal for timelock
     */
    async queueProposal(proposalId, targetAddress, callData, salt, description) {
        try {
            if (!this.contract) {
                await this.init();
            }

            this.showLoading('Queueing proposal...');

            // Call smart contract
            const tx = await this.contract.methods.queueProposal(
                proposalId,
                targetAddress,
                0, // value
                callData || '0x',
                '0x0000000000000000000000000000000000000000000000000000000000000000', // predecessor
                salt,
                description
            ).send({ from: this.userAddress });

            console.log('Queue transaction:', tx);

            // Get operation hash from event
            const operationHash = tx.events.ProposalQueued.returnValues.id;

            // Calculate ETA (now + 48 hours)
            const now = new Date();
            const eta = new Date(now.getTime() + (172800 * 1000));

            // Save to database
            const response = await fetch(`${this.apiBase}/timelock-queue.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    operation_hash: operationHash,
                    proposal_id: proposalId,
                    target_address: targetAddress,
                    call_data: callData,
                    salt: salt,
                    proposer: this.userAddress,
                    description: description,
                    execution_eta: eta.toISOString().slice(0, 19).replace('T', ' ')
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Proposal queued successfully!');
                
                // Start countdown
                window.timelockCountdown.init(
                    operationHash,
                    eta.toISOString(),
                    `countdown-${operationHash}`
                );

                return data.data;
            } else {
                throw new Error(data.error);
            }

        } catch (error) {
            console.error('Queue error:', error);
            this.showError('Failed to queue proposal: ' + error.message);
            return null;
        }
    }

    /**
     * Execute queued proposal
     */
    async executeProposal(operationHash, proposalId, targetAddress, callData, salt) {
        try {
            if (!this.contract) {
                await this.init();
            }

            this.showLoading('Executing proposal...');

            // Call smart contract
            const tx = await this.contract.methods.executeProposal(
                proposalId,
                targetAddress,
                0, // value
                callData || '0x',
                '0x0000000000000000000000000000000000000000000000000000000000000000', // predecessor
                salt
            ).send({ from: this.userAddress });

            console.log('Execute transaction:', tx);

            // Update database
            const response = await fetch(`${this.apiBase}/timelock-execute.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    operation_hash: operationHash,
                    executor: this.userAddress,
                    tx_hash: tx.transactionHash
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Proposal executed successfully!');
                
                // Stop countdown
                window.timelockCountdown.stop(operationHash);

                return data.data;
            } else {
                throw new Error(data.error);
            }

        } catch (error) {
            console.error('Execute error:', error);
            this.showError('Failed to execute proposal: ' + error.message);
            return null;
        }
    }

    /**
     * Cancel queued proposal
     */
    async cancelProposal(operationHash, reason = 'Cancelled by user') {
        try {
            if (!this.contract) {
                await this.init();
            }

            if (!confirm('Are you sure you want to cancel this proposal?')) {
                return null;
            }

            this.showLoading('Cancelling proposal...');

            // Call smart contract
            const tx = await this.contract.methods.cancelProposal(
                operationHash
            ).send({ from: this.userAddress });

            console.log('Cancel transaction:', tx);

            // Update database
            const response = await fetch(`${this.apiBase}/timelock-cancel.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    operation_hash: operationHash,
                    canceller: this.userAddress,
                    reason: reason
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Proposal cancelled successfully!');
                
                // Stop countdown
                window.timelockCountdown.stop(operationHash);

                return data.data;
            } else {
                throw new Error(data.error);
            }

        } catch (error) {
            console.error('Cancel error:', error);
            this.showError('Failed to cancel proposal: ' + error.message);
            return null;
        }
    }

    /**
     * Get timelock status
     */
    async getStatus(operationHash = null, proposalId = null) {
        try {
            let url = `${this.apiBase}/timelock-status.php?`;
            if (operationHash) url += `operation_hash=${operationHash}&`;
            if (proposalId) url += `proposal_id=${proposalId}&`;

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.error);
            }

        } catch (error) {
            console.error('Status error:', error);
            return null;
        }
    }

    /**
     * Show loading state
     */
    showLoading(message) {
        // TODO: Implement loading UI
        console.log('Loading:', message);
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        // TODO: Implement success UI
        console.log('Success:', message);
        alert(message);
    }

    /**
     * Show error message
     */
    showError(message) {
        // TODO: Implement error UI
        console.error('Error:', message);
        alert('Error: ' + message);
    }
}

// Export as global
window.TimelockUI = TimelockUI;

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    window.timelockUI = new TimelockUI();
});
