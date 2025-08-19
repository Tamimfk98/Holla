/**
 * eSports Tournament Management System - Main JavaScript
 */

// Global configurations
const CONFIG = {
    ajaxTimeout: 30000,
    uploadMaxSize: 5 * 1024 * 1024, // 5MB
    animationDuration: 300
};

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Initialize components
    initializeTooltips();
    initializeModals();
    initializeFormValidation();
    initializeFileUploads();
    initializeAjaxForms();
    initializeNotifications();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(hideAlerts, 5000);
    
    // Initialize page-specific functionality
    const page = getCurrentPage();
    switch(page) {
        case 'dashboard':
            initializeDashboard();
            break;
        case 'tournaments':
            initializeTournaments();
            break;
        case 'matches':
            initializeMatches();
            break;
        case 'payments':
            initializePayments();
            break;
        case 'upload':
            initializeUpload();
            break;
        case 'admin':
            initializeAdmin();
            break;
    }
}

/**
 * Get current page identifier
 */
function getCurrentPage() {
    const path = window.location.pathname;
    if (path.includes('dashboard')) return 'dashboard';
    if (path.includes('tournament')) return 'tournaments';
    if (path.includes('match')) return 'matches';
    if (path.includes('payment')) return 'payments';
    if (path.includes('upload')) return 'upload';
    if (path.includes('admin')) return 'admin';
    return 'home';
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize Bootstrap modals
 */
function initializeModals() {
    const modalElements = document.querySelectorAll('.modal');
    modalElements.forEach(modalElement => {
        modalElement.addEventListener('shown.bs.modal', function() {
            const firstInput = modalElement.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Focus on first invalid field
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    showNotification('Please correct the highlighted errors', 'error');
                }
            }
            
            form.classList.add('was-validated');
        });
    });
    
    // Real-time validation
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
}

/**
 * Validate individual field
 */
function validateField(field) {
    if (field.checkValidity()) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
    }
}

/**
 * Initialize file upload components
 */
function initializeFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            validateFile(this);
            previewFile(this);
        });
        
        // Drag and drop
        const dropZone = input.closest('.file-drop-zone');
        if (dropZone) {
            initializeDragDrop(dropZone, input);
        }
    });
}

/**
 * Validate file upload
 */
function validateFile(input) {
    const file = input.files[0];
    if (!file) return true;
    
    // Check file size
    if (file.size > CONFIG.uploadMaxSize) {
        showNotification(`File size exceeds ${formatFileSize(CONFIG.uploadMaxSize)} limit`, 'error');
        input.value = '';
        return false;
    }
    
    // Check file type for images
    if (input.accept && input.accept.includes('image/')) {
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showNotification('Please select a valid image file (JPG, PNG, GIF, WEBP)', 'error');
            input.value = '';
            return false;
        }
    }
    
    return true;
}

/**
 * Preview uploaded file
 */
function previewFile(input) {
    const file = input.files[0];
    if (!file) return;
    
    const previewContainer = document.getElementById('filePreview') || 
                           input.parentElement.querySelector('.file-preview');
    
    if (!previewContainer) return;
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.innerHTML = `
                <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="clearFilePreview('${input.id}')">
                    <i class="fas fa-trash"></i> Remove
                </button>
            `;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        previewContainer.innerHTML = `
            <div class="file-info">
                <i class="fas fa-file fa-2x mb-2"></i>
                <div>${file.name}</div>
                <small class="text-muted">${formatFileSize(file.size)}</small>
            </div>
        `;
        previewContainer.style.display = 'block';
    }
}

/**
 * Clear file preview
 */
function clearFilePreview(inputId) {
    const input = document.getElementById(inputId);
    const previewContainer = input.parentElement.querySelector('.file-preview');
    
    input.value = '';
    previewContainer.style.display = 'none';
    previewContainer.innerHTML = '';
}

/**
 * Initialize drag and drop
 */
function initializeDragDrop(dropZone, fileInput) {
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
}

/**
 * Initialize AJAX forms
 */
function initializeAjaxForms() {
    const ajaxForms = document.querySelectorAll('.ajax-form');
    
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAjaxForm(this);
        });
    });
}

/**
 * Submit AJAX form
 */
function submitAjaxForm(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action || window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Operation completed successfully', 'success');
            
            // Handle redirect
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
            
            // Handle modal close
            const modal = form.closest('.modal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                modalInstance.hide();
            }
            
            // Handle form reset
            if (data.reset_form) {
                form.reset();
            }
        } else {
            showNotification(data.error || 'An error occurred', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error occurred', 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    });
}

/**
 * Show notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.toast-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `toast-notification alert alert-${getBootstrapAlertClass(type)} alert-dismissible fade show`;
    notification.innerHTML = `
        ${getNotificationIcon(type)} ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Style notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-hide notification
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 150);
            }
        }, duration);
    }
}

/**
 * Get Bootstrap alert class for notification type
 */
function getBootstrapAlertClass(type) {
    const classMap = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    return classMap[type] || 'info';
}

/**
 * Get notification icon
 */
function getNotificationIcon(type) {
    const iconMap = {
        'success': '<i class="fas fa-check-circle"></i>',
        'error': '<i class="fas fa-exclamation-triangle"></i>',
        'warning': '<i class="fas fa-exclamation-circle"></i>',
        'info': '<i class="fas fa-info-circle"></i>'
    };
    return iconMap[type] || iconMap['info'];
}

/**
 * Initialize notifications
 */
function initializeNotifications() {
    // Auto-dismiss alerts with close button
    document.addEventListener('click', function(e) {
        if (e.target.matches('.alert .btn-close') || e.target.closest('.alert .btn-close')) {
            const alert = e.target.closest('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }
        }
    });
}

/**
 * Hide alerts automatically
 */
function hideAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        if (!alert.querySelector('.btn-close')) return;
        
        alert.classList.add('fade');
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 150);
    });
}

/**
 * Initialize dashboard functionality
 */
function initializeDashboard() {
    // Update statistics periodically
    updateStatistics();
    setInterval(updateStatistics, 60000); // Update every minute
    
    // Initialize chart if present
    initializeDashboardCharts();
}

/**
 * Update dashboard statistics
 */
function updateStatistics() {
    fetch('../api/dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatCards(data.stats);
            }
        })
        .catch(error => console.error('Error updating statistics:', error));
}

/**
 * Update statistic cards
 */
function updateStatCards(stats) {
    Object.keys(stats).forEach(key => {
        const element = document.querySelector(`[data-stat="${key}"]`);
        if (element) {
            animateNumber(element, stats[key]);
        }
    });
}

/**
 * Animate number change
 */
function animateNumber(element, newValue) {
    const currentValue = parseInt(element.textContent) || 0;
    const increment = (newValue - currentValue) / 20;
    let current = currentValue;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
            current = newValue;
            clearInterval(timer);
        }
        element.textContent = Math.round(current).toLocaleString();
    }, 50);
}

/**
 * Initialize dashboard charts
 */
function initializeDashboardCharts() {
    // This would integrate with Chart.js if charts are needed
    const chartElements = document.querySelectorAll('.chart-container');
    chartElements.forEach(element => {
        // Initialize chart based on data attributes
        const chartType = element.dataset.chartType;
        const chartData = JSON.parse(element.dataset.chartData || '{}');
        
        if (typeof Chart !== 'undefined') {
            createChart(element, chartType, chartData);
        }
    });
}

/**
 * Initialize tournaments page
 */
function initializeTournaments() {
    // Filter functionality
    initializeTournamentFilters();
    
    // Registration modal handling
    initializeTournamentRegistration();
}

/**
 * Initialize tournament filters
 */
function initializeTournamentFilters() {
    const filterForm = document.querySelector('.tournament-filters');
    if (!filterForm) return;
    
    const filterInputs = filterForm.querySelectorAll('select, input');
    filterInputs.forEach(input => {
        input.addEventListener('change', debounce(filterTournaments, 300));
    });
}

/**
 * Filter tournaments
 */
function filterTournaments() {
    const formData = new FormData(document.querySelector('.tournament-filters'));
    const params = new URLSearchParams(formData);
    
    fetch(`?${params.toString()}`)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            const newContent = newDoc.querySelector('.tournaments-container');
            const currentContent = document.querySelector('.tournaments-container');
            
            if (newContent && currentContent) {
                currentContent.innerHTML = newContent.innerHTML;
            }
        })
        .catch(error => console.error('Error filtering tournaments:', error));
}

/**
 * Initialize tournament registration
 */
function initializeTournamentRegistration() {
    const registrationForms = document.querySelectorAll('.tournament-registration-form');
    registrationForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tournamentName = form.dataset.tournamentName;
            const entryFee = form.dataset.entryFee;
            
            if (entryFee > 0) {
                const confirmMsg = `This tournament requires an entry fee of ৳${entryFee}. Do you want to proceed?`;
                if (!confirm(confirmMsg)) {
                    return;
                }
            }
            
            submitAjaxForm(form);
        });
    });
}

/**
 * Initialize matches page
 */
function initializeMatches() {
    // Auto-refresh match status
    setInterval(refreshMatchStatus, 30000); // Refresh every 30 seconds
    
    // Initialize match screenshot modal
    initializeScreenshotModal();
}

/**
 * Refresh match status
 */
function refreshMatchStatus() {
    const matchCards = document.querySelectorAll('.match-card[data-match-id]');
    
    matchCards.forEach(card => {
        const matchId = card.dataset.matchId;
        
        fetch(`../api/match_status.php?match_id=${matchId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMatchCard(card, data.match);
                }
            })
            .catch(error => console.error('Error refreshing match status:', error));
    });
}

/**
 * Update match card with new data
 */
function updateMatchCard(card, matchData) {
    // Update status badge
    const statusBadge = card.querySelector('.match-status');
    if (statusBadge) {
        statusBadge.className = `badge bg-${getMatchStatusClass(matchData.status)}`;
        statusBadge.textContent = matchData.status.charAt(0).toUpperCase() + matchData.status.slice(1);
    }
    
    // Update scores if match is completed
    if (matchData.status === 'completed' && matchData.score1 !== null) {
        const score1Element = card.querySelector('.team1-score');
        const score2Element = card.querySelector('.team2-score');
        
        if (score1Element) score1Element.textContent = matchData.score1;
        if (score2Element) score2Element.textContent = matchData.score2;
    }
}

/**
 * Get match status CSS class
 */
function getMatchStatusClass(status) {
    const classMap = {
        'scheduled': 'info',
        'live': 'danger',
        'completed': 'success',
        'cancelled': 'secondary'
    };
    return classMap[status] || 'secondary';
}

/**
 * Initialize screenshot modal
 */
function initializeScreenshotModal() {
    const screenshotLinks = document.querySelectorAll('.screenshot-link');
    
    screenshotLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showScreenshotModal(this.href, this.dataset.title);
        });
    });
}

/**
 * Show screenshot in modal
 */
function showScreenshotModal(imageUrl, title) {
    const modalHtml = `
        <div class="modal fade" id="screenshotModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-secondary">
                    <div class="modal-header border-0">
                        <h5 class="modal-title text-accent">${title || 'Match Screenshot'}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${imageUrl}" class="img-fluid rounded" alt="Match Screenshot">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    const existingModal = document.getElementById('screenshotModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('screenshotModal'));
    modal.show();
    
    // Remove modal from DOM when hidden
    document.getElementById('screenshotModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

/**
 * Initialize payments page
 */
function initializePayments() {
    // Initialize payment method selection
    initializePaymentMethods();
    
    // Initialize receipt generation
    initializeReceiptGeneration();
}

/**
 * Initialize payment methods
 */
function initializePaymentMethods() {
    const methodSelect = document.getElementById('payment_method');
    if (!methodSelect) return;
    
    methodSelect.addEventListener('change', function() {
        updatePaymentInstructions(this.value);
    });
    
    // Initialize with current selection
    if (methodSelect.value) {
        updatePaymentInstructions(methodSelect.value);
    }
}

/**
 * Update payment instructions based on method
 */
function updatePaymentInstructions(method) {
    const instructionsContainer = document.getElementById('payment_instructions');
    if (!instructionsContainer) return;
    
    const instructions = {
        'bkash': {
            number: '01XXXXXXXXX',
            steps: [
                'Go to your bKash app',
                'Select "Send Money"',
                'Enter merchant number: 01XXXXXXXXX',
                'Enter exact amount',
                'Complete transaction',
                'Copy transaction ID from SMS'
            ]
        },
        'nagad': {
            number: '01XXXXXXXXX',
            steps: [
                'Go to your Nagad app',
                'Select "Send Money"',
                'Enter merchant number: 01XXXXXXXXX',
                'Enter exact amount',
                'Complete transaction',
                'Copy transaction ID from SMS'
            ]
        },
        'rocket': {
            number: '01XXXXXXXXX',
            steps: [
                'Dial *322# or use Rocket app',
                'Select "Send Money"',
                'Enter merchant number: 01XXXXXXXXX',
                'Enter exact amount',
                'Complete transaction',
                'Copy transaction ID from SMS'
            ]
        }
    };
    
    const methodData = instructions[method];
    if (!methodData) {
        instructionsContainer.innerHTML = '<p class="text-light-50">Select a payment method to see instructions</p>';
        return;
    }
    
    const instructionsHtml = `
        <div class="payment-instructions">
            <h6 class="text-accent">Merchant Number: ${methodData.number}</h6>
            <ol class="text-light-50">
                ${methodData.steps.map(step => `<li>${step}</li>`).join('')}
            </ol>
        </div>
    `;
    
    instructionsContainer.innerHTML = instructionsHtml;
}

/**
 * Initialize receipt generation
 */
function initializeReceiptGeneration() {
    window.generateReceipt = function(paymentId) {
        const receiptWindow = window.open('', '_blank');
        receiptWindow.document.write('<html><body><h1>Loading receipt...</h1></body></html>');
        
        fetch(`../api/receipt.php?payment_id=${paymentId}`)
            .then(response => response.text())
            .then(html => {
                receiptWindow.document.open();
                receiptWindow.document.write(html);
                receiptWindow.document.close();
            })
            .catch(error => {
                receiptWindow.document.write('<h1>Error loading receipt</h1>');
                console.error('Error generating receipt:', error);
            });
    };
}

/**
 * Initialize upload page
 */
function initializeUpload() {
    // Initialize drag and drop
    const uploadArea = document.querySelector('.upload-area');
    if (uploadArea) {
        initializeUploadDragDrop(uploadArea);
    }
    
    // Initialize file validation
    const fileInput = document.getElementById('screenshot');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            validateUploadFile(this);
        });
    }
    
    // Initialize progress tracking
    initializeUploadProgress();
}

/**
 * Initialize upload drag and drop
 */
function initializeUploadDragDrop(uploadArea) {
    const fileInput = uploadArea.querySelector('input[type="file"]');
    
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });
    
    uploadArea.addEventListener('dragleave', function() {
        uploadArea.classList.remove('drag-over');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && fileInput) {
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
}

/**
 * Validate upload file
 */
function validateUploadFile(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showNotification('Please select a valid image file (JPG, PNG, GIF, WEBP)', 'error');
        input.value = '';
        return false;
    }
    
    // Validate file size
    if (file.size > CONFIG.uploadMaxSize) {
        showNotification(`File size must be less than ${formatFileSize(CONFIG.uploadMaxSize)}`, 'error');
        input.value = '';
        return false;
    }
    
    return true;
}

/**
 * Initialize upload progress
 */
function initializeUploadProgress() {
    const uploadForms = document.querySelectorAll('.upload-form');
    
    uploadForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadWithProgress(form);
        });
    });
}

/**
 * Upload with progress tracking
 */
function uploadWithProgress(form) {
    const formData = new FormData(form);
    const progressBar = document.getElementById('upload_progress');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (progressBar) {
        progressBar.style.display = 'block';
        progressBar.querySelector('.progress-bar').style.width = '0%';
    }
    
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        submitBtn.disabled = true;
    }
    
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable && progressBar) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressBar.querySelector('.progress-bar').style.width = percentComplete + '%';
        }
    });
    
    xhr.addEventListener('load', function() {
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                showNotification(response.message || 'Upload successful!', 'success');
                if (response.redirect) {
                    setTimeout(() => window.location.href = response.redirect, 1500);
                }
            } else {
                showNotification(response.error || 'Upload failed', 'error');
            }
        } catch (e) {
            showNotification('Upload completed but response invalid', 'warning');
        }
    });
    
    xhr.addEventListener('error', function() {
        showNotification('Network error during upload', 'error');
    });
    
    xhr.addEventListener('loadend', function() {
        if (progressBar) {
            progressBar.style.display = 'none';
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Screenshot';
            submitBtn.disabled = false;
        }
    });
    
    xhr.open('POST', form.action);
    xhr.send(formData);
}

/**
 * Initialize admin functionality
 */
function initializeAdmin() {
    // Initialize data tables
    initializeDataTables();
    
    // Initialize admin modals
    initializeAdminModals();
    
    // Initialize bulk actions
    initializeBulkActions();
}

/**
 * Initialize data tables
 */
function initializeDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        // Add sorting functionality
        addTableSorting(table);
        
        // Add search functionality
        addTableSearch(table);
    });
}

/**
 * Add table sorting
 */
function addTableSorting(table) {
    const headers = table.querySelectorAll('th[data-sort]');
    
    headers.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.dataset.sort;
            const currentSort = this.dataset.sortDirection || 'asc';
            const newSort = currentSort === 'asc' ? 'desc' : 'asc';
            
            sortTable(table, column, newSort);
            
            // Update sort indicators
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
                delete h.dataset.sortDirection;
            });
            
            this.dataset.sortDirection = newSort;
            this.classList.add(`sort-${newSort}`);
        });
    });
}

/**
 * Sort table by column
 */
function sortTable(table, column, direction) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aValue = a.querySelector(`[data-sort-value="${column}"]`)?.textContent || 
                      a.cells[parseInt(column)]?.textContent || '';
        const bValue = b.querySelector(`[data-sort-value="${column}"]`)?.textContent || 
                      b.cells[parseInt(column)]?.textContent || '';
        
        const comparison = aValue.localeCompare(bValue, undefined, { numeric: true });
        return direction === 'asc' ? comparison : -comparison;
    });
    
    // Re-append rows in sorted order
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Add table search
 */
function addTableSearch(table) {
    const searchInput = document.querySelector(`[data-table-search="${table.id}"]`);
    if (!searchInput) return;
    
    searchInput.addEventListener('input', debounce(function() {
        filterTable(table, this.value);
    }, 300));
}

/**
 * Filter table by search term
 */
function filterTable(table, searchTerm) {
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

/**
 * Initialize admin modals
 */
function initializeAdminModals() {
    // Confirmation modals
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const message = this.dataset.confirm;
            const href = this.href;
            
            if (confirm(message)) {
                window.location.href = href;
            }
        });
    });
}

/**
 * Initialize bulk actions
 */
function initializeBulkActions() {
    const bulkActionForm = document.querySelector('.bulk-action-form');
    if (!bulkActionForm) return;
    
    const selectAllCheckbox = bulkActionForm.querySelector('.select-all');
    const itemCheckboxes = bulkActionForm.querySelectorAll('.select-item');
    const actionSelect = bulkActionForm.querySelector('.bulk-action-select');
    const submitBtn = bulkActionForm.querySelector('.bulk-action-submit');
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButton();
        });
    }
    
    // Update button state when individual items are selected
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButton);
    });
    
    function updateBulkActionButton() {
        const selectedCount = bulkActionForm.querySelectorAll('.select-item:checked').length;
        
        if (submitBtn) {
            submitBtn.disabled = selectedCount === 0 || !actionSelect?.value;
            submitBtn.textContent = selectedCount > 0 ? 
                `Apply to ${selectedCount} item(s)` : 
                'Select items';
        }
    }
    
    // Handle action select change
    if (actionSelect) {
        actionSelect.addEventListener('change', updateBulkActionButton);
    }
}

/**
 * Utility Functions
 */

/**
 * Debounce function
 */
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func.apply(this, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(this, args);
    };
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    if (bytes === 0) return '0 Bytes';
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '৳' + parseFloat(amount).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('Copied to clipboard!', 'success');
    }
}

/**
 * Smooth scroll to element
 */
function scrollToElement(element, offset = 0) {
    const targetElement = typeof element === 'string' ? 
        document.querySelector(element) : element;
    
    if (targetElement) {
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - offset;
        
        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });
    }
}

/**
 * Check if element is in viewport
 */
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Export functions for global use
 */
window.eSports = {
    showNotification,
    copyToClipboard,
    scrollToElement,
    formatCurrency,
    formatFileSize,
    debounce
};
