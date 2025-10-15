/**
 * PayPerView - Create Content Manager
 * Handles content creation flow and blockchain integration
 */

let currentStep = 1;
let selectedType = null;
let uploadedContentUrl = null;
let uploadedPreviewUrl = null;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializeContentTypeSelector();
    initializeUploadZone();
    initializeFormValidation();
    
    // Character counter
    const descInput = document.getElementById('description');
    if (descInput) {
        descInput.addEventListener('input', function() {
            document.getElementById('descCount').textContent = this.value.length;
        });
    }
});

/**
 * Content Type Selector
 */
function initializeContentTypeSelector() {
    const cards = document.querySelectorAll('.content-type-card');
    
    cards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove active from all
            cards.forEach(c => c.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Store selection
            selectedType = this.dataset.type;
            document.getElementById('contentType').value = selectedType;
        });
    });
}

/**
 * Upload Zone
 */
function initializeUploadZone() {
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('contentFile');
    
    if (!uploadZone || !fileInput) return;
    
    // Click to upload
    uploadZone.addEventListener('click', () => fileInput.click());
    
    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileUpload(files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileUpload(e.target.files[0]);
        }
    });
    
    // Preview file upload
    const previewInput = document.getElementById('previewFile');
    if (previewInput) {
        previewInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handlePreviewUpload(e.target.files[0]);
            }
        });
    }
}

/**
 * Handle main content file upload
 */
async function handleFileUpload(file) {
    // Validate file size (100MB)
    if (file.size > 100 * 1024 * 1024) {
        alert('File size must be less than 100MB');
        return;
    }
    
    console.log('Uploading file:', file.name, 'Size:', (file.size / 1024 / 1024).toFixed(2), 'MB');
    
    // Show progress
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadSuccess').style.display = 'none';
    
    try {
        // Upload to server (or IPFS)
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'paywall_content');
        
        const response = await fetch('/api/upload/upload_file.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            uploadedContentUrl = data.url;
            document.getElementById('contentUrl').value = data.url;
            document.getElementById('uploadedUrl').textContent = data.url;
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('uploadSuccess').style.display = 'block';
            
            console.log('Upload successful:', data.url);
        } else {
            throw new Error(data.message || 'Upload failed');
        }
        
    } catch (error) {
        console.error('Upload error:', error);
        document.getElementById('uploadProgress').style.display = 'none';
        alert('Upload failed: ' + error.message);
    }
}

/**
 * Handle preview file upload
 */
async function handlePreviewUpload(file) {
    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'paywall_preview');
        
        const response = await fetch('/api/upload/upload_file.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            uploadedPreviewUrl = data.url;
            document.getElementById('previewUrl').value = data.url;
            console.log('Preview uploaded:', data.url);
        }
        
    } catch (error) {
        console.error('Preview upload error:', error);
    }
}

/**
 * Form Validation
 */
function initializeFormValidation() {
    const form = document.getElementById('createContentForm');
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        await publishContent();
    });
}

/**
 * Step Navigation
 */
function nextStep(step) {
    // Validate current step
    if (!validateStep(currentStep)) {
        return;
    }
    
    // Hide current step
    document.querySelector(`.progress-step[data-step="${currentStep}"]`).classList.remove('active');
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('completed');
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
    
    // Show next step
    currentStep = step;
    document.querySelector(`.progress-step[data-step="${step}"]`).classList.add('active');
    document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
    
    // Update review if on step 4
    if (step === 4) {
        updateReview();
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep(step) {
    // Hide current step
    document.querySelector(`.progress-step[data-step="${currentStep}"]`).classList.remove('active');
    document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
    
    // Show previous step
    currentStep = step;
    document.querySelector(`.progress-step[data-step="${step}"]`).classList.add('active');
    document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
    document.querySelector(`.step[data-step="${step}"]`).classList.remove('completed');
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Validate Step
 */
function validateStep(step) {
    switch(step) {
        case 1:
            if (!selectedType) {
                alert('Please select a content type');
                return false;
            }
            return true;
            
        case 2:
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            
            if (title.length < 3 || title.length > 255) {
                alert('Title must be between 3 and 255 characters');
                return false;
            }
            
            if (description.length < 10) {
                alert('Description must be at least 10 characters');
                return false;
            }
            
            if (isNaN(price) || price <= 0) {
                alert('Please enter a valid price');
                return false;
            }
            
            return true;
            
        case 3:
            if (!uploadedContentUrl) {
                alert('Please upload your content file');
                return false;
            }
            return true;
            
        default:
            return true;
    }
}

/**
 * Update Review
 */
function updateReview() {
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    const price = parseFloat(document.getElementById('price').value);
    const earnings = (price * 0.975).toFixed(2);
    
    document.getElementById('reviewType').textContent = selectedType.toUpperCase();
    document.getElementById('reviewTitle').textContent = title;
    document.getElementById('reviewDescription').textContent = description;
    document.getElementById('reviewPrice').textContent = price.toFixed(2);
    document.getElementById('reviewEarnings').textContent = earnings;
    document.getElementById('reviewContentUrl').textContent = uploadedContentUrl;
}

/**
 * Publish Content
 */
async function publishContent() {
    const modal = new bootstrap.Modal(document.getElementById('publishModal'));
    modal.show();
    
    try {
        // Step 1: Register on blockchain
        updatePublishStatus('Registering on blockchain...', '1 of 3');
        
        const price = document.getElementById('price').value;
        const priceWei = ethers.parseEther(price);
        
        // Get next content ID from contract
        const contract = await getPayPerViewContract();
        const contentId = await contract.nextContentId();
        
        console.log('Creating content on blockchain with ID:', contentId.toString());
        
        // Call contract (gasless)
        const callData = contract.interface.encodeFunctionData('createContent', [priceWei]);
        
        const taskId = await window.GelatoRelay.sponsoredCall(
            window.PAYWALL_CONTRACT_ADDRESS,
            callData,
            200000 // gas limit
        );
        
        console.log('Gasless transaction submitted:', taskId);
        
        // Step 2: Wait for confirmation
        updatePublishStatus('Confirming transaction...', '2 of 3');
        
        await window.GelatoRelay.waitForTask(taskId, (status) => {
            console.log('Task status:', status);
        });
        
        // Step 3: Save to database
        updatePublishStatus('Saving to database...', '3 of 3');
        
        const formData = {
            contract_content_id: contentId.toString(),
            title: document.getElementById('title').value,
            description: document.getElementById('description').value,
            price: price,
            content_type: selectedType,
            content_url: uploadedContentUrl,
            preview_url: uploadedPreviewUrl || null,
            preview_text: document.getElementById('previewText').value || null
        };
        
        const response = await fetch('/api/paywall/create_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Success!
            document.getElementById('publishProgress').style.display = 'none';
            document.getElementById('publishSuccess').style.display = 'block';
            
            console.log('Content published successfully:', result.content);
        } else {
            throw new Error(result.message || 'Failed to save content');
        }
        
    } catch (error) {
        console.error('Publishing error:', error);
        document.getElementById('publishProgress').style.display = 'none';
        document.getElementById('publishError').style.display = 'block';
        document.getElementById('errorMessage').textContent = error.message;
    }
}

/**
 * Update publish status
 */
function updatePublishStatus(status, step) {
    document.getElementById('publishStatus').textContent = status;
    document.getElementById('publishStep').textContent = step;
}

/**
 * Get PayPerView contract instance
 */
async function getPayPerViewContract() {
    if (!window.smartWalletProvider) {
        throw new Error('Smart Wallet not available');
    }
    
    const provider = new ethers.BrowserProvider(window.smartWalletProvider);
    const signer = await provider.getSigner();
    
    // Load ABI
    const response = await fetch('/escrow-system/abis/PayPerView.json');
    const artifact = await response.json();
    
    return new ethers.Contract(
        window.PAYWALL_CONTRACT_ADDRESS,
        artifact.abi,
        signer
    );
}
