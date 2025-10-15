<?php
/**
 * Página: Crear Contenido de Pago
 * Ruta: /pages/paywall/create-content.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Create Paid Content';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - thesocialmask</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .preview-section {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 30px;
            margin-top: 20px;
            min-height: 200px;
        }
        
        .content-type-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .content-type-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .content-type-card.active {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        
        .price-input-group {
            position: relative;
        }
        
        .price-input-group .sphe-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #6c757d;
        }
        
        .price-input-group input {
            padding-left: 45px;
        }
        
        .progress-step {
            display: none;
        }
        
        .progress-step.active {
            display: block;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: #e9ecef;
            position: relative;
        }
        
        .step.completed {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .step.active {
            background: #0d6efd;
            color: white;
        }
        
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-zone:hover {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        
        .upload-zone.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <div class="container py-5">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="bi bi-lock-fill"></i> Create Paid Content</h1>
                        <p class="text-muted">Monetize your exclusive content with SPHE tokens</p>
                    </div>
                    <a href="/pages/paywall/my-earnings" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up"></i> My Earnings
                    </a>
                </div>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator mb-4">
            <div class="step active" data-step="1">
                <strong>1. Content Type</strong>
            </div>
            <div class="step" data-step="2">
                <strong>2. Details</strong>
            </div>
            <div class="step" data-step="3">
                <strong>3. Upload</strong>
            </div>
            <div class="step" data-step="4">
                <strong>4. Review</strong>
            </div>
        </div>

        <!-- Form -->
        <form id="createContentForm">
            <!-- Step 1: Content Type -->
            <div class="progress-step active" data-step="1">
                <h3 class="mb-4">Select Content Type</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card content-type-card" data-type="post">
                            <div class="card-body text-center py-4">
                                <i class="bi bi-file-text display-3 text-primary"></i>
                                <h5 class="mt-3">Post</h5>
                                <p class="text-muted small">Text-based content</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card content-type-card" data-type="article">
                            <div class="card-body text-center py-4">
                                <i class="bi bi-journal-text display-3 text-success"></i>
                                <h5 class="mt-3">Article</h5>
                                <p class="text-muted small">Long-form content</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card content-type-card" data-type="image">
                            <div class="card-body text-center py-4">
                                <i class="bi bi-image display-3 text-warning"></i>
                                <h5 class="mt-3">Image</h5>
                                <p class="text-muted small">Photo or artwork</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card content-type-card" data-type="video">
                            <div class="card-body text-center py-4">
                                <i class="bi bi-camera-video display-3 text-danger"></i>
                                <h5 class="mt-3">Video</h5>
                                <p class="text-muted small">Video content</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card content-type-card" data-type="audio">
                            <div class="card-body text-center py-4">
                                <i class="bi bi-music-note display-3 text-info"></i>
                                <h5 class="mt-3">Audio</h5>
                                <p class="text-muted small">Music or podcast</p>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="contentType" name="content_type" required>
                <div class="text-end mt-4">
                    <button type="button" class="btn btn-primary btn-lg" onclick="nextStep(2)">
                        Next <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Details -->
            <div class="progress-step" data-step="2">
                <h3 class="mb-4">Content Details</h3>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" class="form-control form-control-lg" 
                                           id="title" name="title" maxlength="255" required
                                           placeholder="Enter a catchy title...">
                                    <div class="form-text">3-255 characters</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="4" maxlength="1000" required
                                              placeholder="Describe your content..."></textarea>
                                    <div class="form-text"><span id="descCount">0</span>/1000 characters</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Price in SPHE *</label>
                                    <div class="price-input-group">
                                        <span class="sphe-icon">💎</span>
                                        <input type="number" class="form-control form-control-lg" 
                                               id="price" name="price" min="0.01" step="0.01" required
                                               placeholder="10.00">
                                    </div>
                                    <div class="form-text">
                                        Platform fee: 2.5% • You earn: 97.5%
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preview Text</label>
                                    <textarea class="form-control" id="previewText" name="preview_text" 
                                              rows="3" maxlength="500"
                                              placeholder="Free preview text (optional)"></textarea>
                                    <div class="form-text">Shown to users before purchase</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">💡 Tips for Success</h6>
                                <ul class="small mb-0">
                                    <li>Use descriptive titles</li>
                                    <li>Set competitive prices</li>
                                    <li>Provide good previews</li>
                                    <li>High-quality content sells</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="nextStep(3)">
                        Next <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Upload -->
            <div class="progress-step" data-step="3">
                <h3 class="mb-4">Upload Content</h3>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="upload-zone" id="uploadZone">
                                    <i class="bi bi-cloud-upload display-1 text-muted"></i>
                                    <h5 class="mt-3">Drop your file here or click to browse</h5>
                                    <p class="text-muted">Max size: 100MB</p>
                                    <input type="file" id="contentFile" class="d-none" accept="*">
                                </div>
                                
                                <div id="uploadProgress" class="mt-3" style="display: none;">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             id="progressBar" style="width: 0%"></div>
                                    </div>
                                    <p class="text-center mt-2" id="uploadStatus">Uploading...</p>
                                </div>

                                <div id="uploadSuccess" class="alert alert-success mt-3" style="display: none;">
                                    <i class="bi bi-check-circle"></i> File uploaded successfully!
                                    <div class="mt-2">
                                        <strong>URL:</strong> <span id="uploadedUrl"></span>
                                    </div>
                                </div>

                                <div class="mb-3 mt-4">
                                    <label class="form-label">Preview File (Optional)</label>
                                    <input type="file" class="form-control" id="previewFile" accept="image/*">
                                    <div class="form-text">Upload a preview image or thumbnail</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body">
                                <h6 class="card-title">📦 Upload Tips</h6>
                                <ul class="small mb-0">
                                    <li>Use high resolution</li>
                                    <li>Compress large files</li>
                                    <li>Add watermarks</li>
                                    <li>Test preview quality</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="contentUrl" name="content_url">
                <input type="hidden" id="previewUrl" name="preview_url">
                
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="nextStep(4)" id="btnNext3">
                        Next <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 4: Review & Publish -->
            <div class="progress-step" data-step="4">
                <h3 class="mb-4">Review & Publish</h3>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="mb-3">Content Summary</h5>
                                <table class="table">
                                    <tr>
                                        <th width="30%">Type:</th>
                                        <td id="reviewType"></td>
                                    </tr>
                                    <tr>
                                        <th>Title:</th>
                                        <td id="reviewTitle"></td>
                                    </tr>
                                    <tr>
                                        <th>Description:</th>
                                        <td id="reviewDescription"></td>
                                    </tr>
                                    <tr>
                                        <th>Price:</th>
                                        <td><strong id="reviewPrice"></strong> SPHE</td>
                                    </tr>
                                    <tr>
                                        <th>Your Earnings:</th>
                                        <td><strong id="reviewEarnings"></strong> SPHE (97.5%)</td>
                                    </tr>
                                    <tr>
                                        <th>Content URL:</th>
                                        <td class="text-break"><small id="reviewContentUrl"></small></td>
                                    </tr>
                                </table>

                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle"></i> Publishing Process:</h6>
                                    <ol class="mb-0 small">
                                        <li>Register on blockchain (gasless transaction)</li>
                                        <li>Save to database</li>
                                        <li>Content goes live immediately</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="text-success"><i class="bi bi-shield-check"></i> Ready to Publish</h6>
                                <p class="small mb-0">Your content will be protected by smart contracts on the Polygon network.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(3)">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn btn-success btn-lg" id="btnPublish">
                        <i class="bi bi-rocket-takeoff"></i> Publish Content
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Publishing Modal -->
    <div class="modal fade" id="publishModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div id="publishProgress">
                        <div class="spinner-border text-primary mb-3" style="width: 4rem; height: 4rem;"></div>
                        <h5 id="publishStatus">Publishing content...</h5>
                        <p class="text-muted" id="publishStep">Step 1 of 3</p>
                    </div>
                    <div id="publishSuccess" style="display: none;">
                        <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">Content Published!</h4>
                        <p class="text-muted">Your content is now live and earning.</p>
                        <div class="d-grid gap-2 mt-4">
                            <a href="/pages/paywall/my-earnings" class="btn btn-primary">View Earnings</a>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                Create Another
                            </button>
                        </div>
                    </div>
                    <div id="publishError" style="display: none;">
                        <i class="bi bi-x-circle text-danger" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">Publishing Failed</h4>
                        <p class="text-danger" id="errorMessage"></p>
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ethers@6.7.0/dist/ethers.umd.min.js"></script>
    <script src="/assets/js/wallet.js"></script>
    <script src="/assets/js/gelato-relay.js"></script>
    <script src="/assets/js/paywall-create.js"></script>
</body>
</html>
