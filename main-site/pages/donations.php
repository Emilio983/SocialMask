<?php
session_start();
require_once '../config/connection.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = "Donations - thesocialmask";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/donations.css">
    <script src="https://cdn.jsdelivr.net/npm/ethers@6.15.0/dist/ethers.umd.min.js"></script>
</head>
<body>
    <?php include '../components/navbar.php'; ?>
    
    <div class="container">
        <div class="donations-header">
            <h1>🎁 Donation Campaigns</h1>
            <button onclick="showCreateCampaign()" class="btn btn-primary">
                <span>+</span> Create Campaign
            </button>
        </div>
        
        <!-- Filtros -->
        <div class="donations-filters">
            <button class="filter-btn active" data-filter="all" onclick="filterCampaigns('all')">
                All Campaigns
            </button>
            <button class="filter-btn" data-filter="active" onclick="filterCampaigns('active')">
                Active
            </button>
            <button class="filter-btn" data-filter="completed" onclick="filterCampaigns('completed')">
                Completed
            </button>
            <button class="filter-btn" data-filter="my" onclick="filterCampaigns('my')">
                My Campaigns
            </button>
        </div>
        
        <!-- Loading -->
        <div id="campaigns-loading" class="loading">
            <div class="spinner"></div>
            <p>Loading campaigns...</p>
        </div>
        
        <!-- Lista de campañas -->
        <div id="campaigns-list" class="campaigns-grid" style="display: none;">
            <!-- Se llenan con JS -->
        </div>
        
        <!-- Empty state -->
        <div id="campaigns-empty" class="empty-state" style="display: none;">
            <div class="empty-icon">📭</div>
            <h3>No campaigns found</h3>
            <p>Be the first to create a donation campaign!</p>
            <button onclick="showCreateCampaign()" class="btn btn-primary">
                Create Campaign
            </button>
        </div>
    </div>
    
    <!-- Modal: Crear Campaña -->
    <div id="create-campaign-modal" class="modal">
        <div class="modal-overlay" onclick="hideCreateCampaign()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Donation Campaign</h2>
                <button class="modal-close" onclick="hideCreateCampaign()">&times;</button>
            </div>
            <form id="create-campaign-form" onsubmit="createCampaign(event)">
                <div class="form-group">
                    <label for="campaign-title">Campaign Title *</label>
                    <input 
                        type="text" 
                        id="campaign-title" 
                        placeholder="e.g., Help fund my documentary" 
                        maxlength="255"
                        required>
                </div>
                
                <div class="form-group">
                    <label for="campaign-description">Description *</label>
                    <textarea 
                        id="campaign-description" 
                        placeholder="Describe your campaign and how the funds will be used..."
                        rows="5"
                        required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="campaign-goal">Goal Amount (SPHE) *</label>
                        <input 
                            type="number" 
                            id="campaign-goal" 
                            placeholder="1000" 
                            step="0.01"
                            min="1"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label for="campaign-end-date">End Date (Optional)</label>
                        <input 
                            type="date" 
                            id="campaign-end-date"
                            min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="hideCreateCampaign()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Create Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal: Donar -->
    <div id="donate-modal" class="modal">
        <div class="modal-overlay" onclick="hideDonateModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Make a Donation</h2>
                <button class="modal-close" onclick="hideDonateModal()">&times;</button>
            </div>
            
            <div id="donate-campaign-info" class="campaign-info">
                <!-- Se llena con JS -->
            </div>
            
            <form id="donate-form" onsubmit="donate(event)">
                <div class="form-group">
                    <label for="donate-amount">Amount (SPHE) *</label>
                    <input 
                        type="number" 
                        id="donate-amount" 
                        placeholder="10" 
                        step="0.01"
                        min="0.01"
                        required>
                    <small class="form-hint">Your balance: <span id="user-balance">Loading...</span> SPHE</small>
                </div>
                
                <div class="form-group">
                    <label for="donate-message">Message (Optional)</label>
                    <textarea 
                        id="donate-message" 
                        placeholder="Leave a message of support..."
                        rows="3"
                        maxlength="500"></textarea>
                </div>
                
                <div class="donation-summary">
                    <div class="summary-row">
                        <span>Donation Amount:</span>
                        <span id="summary-amount">0 SPHE</span>
                    </div>
                    <div class="summary-row">
                        <span>Network Fee:</span>
                        <span class="text-muted">~0.001 MATIC</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="hideDonateModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span id="donate-btn-text">Donate Now</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Notifications -->
    <div id="toast-container"></div>
    
    <script>
        // Variables globales desde PHP
        const USER_ID = <?php echo $_SESSION['user_id']; ?>;
        const USER_WALLET = '<?php echo $_SESSION['wallet_address'] ?? ''; ?>';
    </script>
    <script src="../assets/js/donations.js"></script>
</body>
</html>
