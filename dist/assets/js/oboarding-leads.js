/**
 * ======================================================
 * AGREEMENT MANAGEMENT SYSTEM - COMPLETE FIX
 * All issues resolved and optimized
 * ======================================================
 */

// ======================================================
// 1. TOAST NOTIFICATION FUNCTION (FIXED)
// ======================================================

/**
 * Show toast notifications
 * This function was missing and causing errors
 */
function showToast(type, message, duration = 3000) {
    // Check if SweetAlert2 is available
    if (typeof Swal !== 'undefined') {
        const iconMap = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        Swal.fire({
            icon: iconMap[type] || 'info',
            title: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: duration,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    } else {
        // Fallback to alert if SweetAlert2 is not available
        alert(type.toUpperCase() + ': ' + message);
    }
}

// ======================================================
// 2. VARIABLES (FIXED - Added missing declarations)
// ======================================================

var agreementEditor = null;
var companySignaturePad = null;

// API URLs - Make sure API_BASE is defined globally
var saveAgreementDraftApiUrl = API_BASE + '/onboarding/saveAgreementDraft.php';
var getAgreementByLeadIdApiUrl = API_BASE + '/onboarding/getAgreementByLeadId.php';
var sendAgreementApiUrl = API_BASE + '/onboarding/sendAgreement.php';
var getAgreementSubmissionApiUrl = API_BASE + '/onboarding/getAgreementSubmission.php';
var saveAgreementReviewApiUrl = API_BASE + '/onboarding/saveAgreementReview.php';

// ======================================================
// 3. INITIALIZATION (FIXED - Better error handling)
// ======================================================

$(function() {
    try {
        // Initialize Quill Editor
        const editorElement = document.getElementById('agreementEditor');
        if (editorElement) {
            agreementEditor = new Quill('#agreementEditor', {
                theme: 'snow',
                placeholder: 'Write agreement content here...'
            });
            console.log('Quill editor initialized successfully');
        } else {
            console.warn('Quill editor element not found');
        }
    } catch (e) {
        console.error('Error initializing Quill editor:', e);
        showToast('error', 'Failed to initialize editor. Please refresh the page.');
    }
});

// ======================================================
// 4. SIGNATURE PAD INITIALIZATION (FIXED)
// ======================================================

// Initialize signature pad with proper error handling
(function initSignaturePad() {
    try {
        const companyCanvas = document.getElementById('companySignaturePad');
        if (!companyCanvas) {
            console.warn('Signature pad canvas not found');
            return;
        }

        function resizeCompanyCanvas() {
            const rect = companyCanvas.getBoundingClientRect();
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            
            companyCanvas.width = rect.width * ratio;
            companyCanvas.height = 180 * ratio;
            
            const ctx = companyCanvas.getContext('2d');
            ctx.scale(ratio, ratio);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, rect.width, 180);
        }

        // Initial resize
        resizeCompanyCanvas();

        // Resize on window resize with debounce
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(resizeCompanyCanvas, 200);
        });

        // Initialize signature pad
        companySignaturePad = new SignaturePad(companyCanvas, {
            backgroundColor: '#ffffff',
            penColor: '#000000',
            minWidth: 0.5,
            maxWidth: 1.5
        });

        console.log('Signature pad initialized successfully');
    } catch (e) {
        console.error('Error initializing signature pad:', e);
        showToast('error', 'Failed to initialize signature pad.');
    }
})();

// ======================================================
// 5. EVENT HANDLERS (FIXED - Proper event delegation)
// ======================================================

// Clear signature
$(document).on('click', '#clearCompanySignature', function() {
    if (companySignaturePad) {
        companySignaturePad.clear();
        console.log('Signature cleared');
    }
});

// Review action change - Show/hide company approval section
$(document).on('change', '#reviewAction', function() {
    const value = $(this).val();
    if (value === 'approved') {
        $('#companyApprovalSection').slideDown(300);
    } else {
        $('#companyApprovalSection').slideUp(300);
        // Clear signature and signatory name when not approved
        if (companySignaturePad) {
            companySignaturePad.clear();
        }
        $('#companySignatoryName').val('');
    }
});

// ======================================================
// 6. UTILITY FUNCTIONS (FIXED - Better error handling)
// ======================================================

function formatDateTime(dateString) {
    if (!dateString) {
        return '--';
    }
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return '--';
        }
        
        return (
            date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            }) +
            ' ' +
            date.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            })
        );
    } catch (e) {
        console.error('Error formatting date:', e);
        return '--';
    }
}

// ======================================================
// 7. AGREEMENT STATUS UI (FIXED - Added null checks)
// ======================================================

function updateAgreementStatusUI(agreement) {
    if (!agreement) {
        console.warn('No agreement data provided');
        return;
    }

    let badgeClass = 'bg-secondary';
    let badgeText = 'Draft';

    switch (agreement.agreementStatus) {
        case 'sent':
            badgeClass = 'bg-primary';
            badgeText = 'Sent';
            break;
        case 'viewed':
            badgeClass = 'bg-warning';
            badgeText = 'Viewed';
            break;
        case 'submitted':
            badgeClass = 'bg-info';
            badgeText = 'Submitted';
            break;
        case 'approved':
            badgeClass = 'bg-success';
            badgeText = 'Approved';
            break;
        case 'rejected':
            badgeClass = 'bg-danger';
            badgeText = 'Rejected';
            break;
        default:
            badgeClass = 'bg-secondary';
            badgeText = 'Draft';
    }

    // Update badge
    $('#agreementStatusBadge')
        .removeClass('bg-secondary bg-primary bg-warning bg-info bg-success bg-danger')
        .addClass(badgeClass)
        .text(badgeText);

    // Locking Logic - with null checks
    if (agreementEditor) {
        if (['submitted', 'approved'].includes(agreement.agreementStatus)) {
            agreementEditor.enable(false);
            $('#saveAgreementBtn').hide();
            $('#sendAgreementBtn').hide();
        } else {
            agreementEditor.enable(true);
            $('#saveAgreementBtn').show();
            $('#sendAgreementBtn').show();
        }
    }
}

// ======================================================
// 8. OPEN AGREEMENT BUILDER (FIXED - Better error handling)
// ======================================================

$(document).on('click', '.agreement-btn', function() {
    const leadId = $(this).data('id');
    const leadName = $(this).data('name') || '';
    const leadEmail = $(this).data('email') || '';
    const leadPrice = parseFloat($(this).data('price')) || 0;

    if (!leadId) {
        showToast('error', 'Invalid lead ID');
        return;
    }

    // Set lead information
    $('#agreementLeadId').val(leadId);
    $('#agreementClientName').val(leadName);
    $('#agreementClientEmail').val(leadEmail);
    $('#agreementFinalPrice').val('₹ ' + leadPrice.toLocaleString('en-IN'));

    // Clear editor content
    if (agreementEditor) {
        agreementEditor.root.innerHTML = '';
    }

    // Reset timeline
    $('#agreementCreatedAt').text('--');
    $('#agreementSentAt').text('--');
    $('#agreementViewedAt').text('--');
    $('#agreementAcceptedAt').text('--');

    // Show loading state
    showToast('info', 'Loading agreement...');

    // Fetch agreement data
    $.get(getAgreementByLeadIdApiUrl, { leadId: leadId }, function(response) {
        if (!response.success) {
            showToast('error', response.message || 'Failed to load agreement');
            return;
        }

        const agreement = response.data;
        if (!agreement) {
            showToast('warning', 'No agreement found for this lead');
            return;
        }

        // Set content
        if (agreement.agreementContent && agreementEditor) {
            agreementEditor.root.innerHTML = agreement.agreementContent;
        }

        // Update timeline
        $('#agreementCreatedAt').text(formatDateTime(agreement.createdAt));
        $('#agreementSentAt').text(formatDateTime(agreement.sentAt));
        $('#agreementViewedAt').text(formatDateTime(agreement.agreementViewedAt));
        $('#agreementAcceptedAt').text(formatDateTime(agreement.agreementAcceptedAt));

        // Update status
        updateAgreementStatusUI(agreement);

    }, 'json').fail(function(jqXHR, textStatus, error) {
        console.error('Error loading agreement:', textStatus, error);
        showToast('error', 'Failed to load agreement data');
    });

    // Show modal
    $('#agreementModal').modal('show');
});

// ======================================================
// 9. SAVE AGREEMENT DRAFT (FIXED - Proper error handling)
// ======================================================

$(document).on('click', '#saveAgreementBtn', function() {
    const $button = $(this);
    const leadId = $('#agreementLeadId').val();
    const agreementContent = agreementEditor ? agreementEditor.root.innerHTML.trim() : '';

    if (!leadId) {
        showToast('error', 'Invalid lead.');
        return;
    }

    if (!agreementContent || agreementContent === '<p><br></p>' || agreementContent === '<p></p>') {
        showToast('warning', 'Please enter agreement content.');
        return;
    }

    // Disable button and show loading
    $button.prop('disabled', true).text('Saving...');

    $.post(saveAgreementDraftApiUrl, {
        leadId: leadId,
        agreementContent: agreementContent
    }, function(response) {
        if (!response.success) {
            showToast('error', response.message || 'Failed to save draft.');
            $button.prop('disabled', false).text('Save Draft');
            return;
        }

        showToast('success', response.message || 'Draft saved successfully.');
        $button.prop('disabled', false).text('Save Draft');

        // Reload after short delay
        setTimeout(function() {
            location.reload();
        }, 800);

    }, 'json').fail(function(jqXHR, textStatus, error) {
        console.error('Error saving draft:', textStatus, error);
        showToast('error', 'Server error while saving draft.');
        $button.prop('disabled', false).text('Save Draft');
    });
});

// ======================================================
// 10. SEND AGREEMENT (FIXED - Proper confirmation)
// ======================================================

$(document).on('click', '#sendAgreementBtn', function() {
    const leadId = $('#agreementLeadId').val();

    if (!leadId) {
        showToast('error', 'Invalid lead.');
        return;
    }

    // Check if content exists
    const content = agreementEditor ? agreementEditor.root.innerHTML.trim() : '';
    if (!content || content === '<p><br></p>') {
        showToast('warning', 'Please create agreement content before sending.');
        return;
    }

    Swal.fire({
        title: 'Send Agreement?',
        text: 'Agreement will be sent to the client via email.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Send It!'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        // Show loading
        showToast('info', 'Sending agreement...');

        $.post(sendAgreementApiUrl, { leadId: leadId }, function(response) {
            if (!response.success) {
                showToast('error', response.message || 'Failed to send agreement');
                return;
            }

            showToast('success', response.message || 'Agreement sent successfully');
            setTimeout(function() {
                location.reload();
            }, 1000);

        }, 'json').fail(function(jqXHR, textStatus, error) {
            console.error('Error sending agreement:', textStatus, error);
            showToast('error', 'Server error while sending agreement');
        });
    });
});

// ======================================================
// 11. OPEN REVIEW MODAL (FIXED - Better UI handling)
// ======================================================

$(document).on('click', '.review-submission-btn', function() {
    const agreementId = $(this).data('id');
    console.log('Review Clicked:', agreementId);

    if (!agreementId) {
        showToast('error', 'Invalid agreement ID');
        return;
    }

    loadReviewModal(agreementId);
});

function loadReviewModal(agreementId) {
    // Show loading
    showToast('info', 'Loading submission details...');

    $.get(getAgreementSubmissionApiUrl, { agreementId: agreementId }, function(response) {
        console.log('Review Response:', response);

        if (!response.success) {
            showToast('error', response.message || 'Failed to load submission');
            return;
        }

        const data = response.data;
        if (!data) {
            showToast('error', 'No data found');
            return;
        }

        // Set review data
        $('#reviewAgreementId').val(agreementId);
        $('#reviewClientName').val(data.fullName || '');
        $('#reviewClientEmail').val(data.email || '');
        
        const finalPrice = Number(data.finalPrice || 0);
        $('#reviewFinalPrice').val('₹ ' + finalPrice.toLocaleString('en-IN'));
        
        // Set agreement content
        if (data.agreementContent) {
            $('#reviewAgreementContent').html(data.agreementContent);
        }
        
        // Set form fields
        $('#reviewRemark').val(data.reviewRemark || '');
        $('#reviewSignatoryName').val(data.signatoryName || '');
        
        // Set document link
        if (data.businessDocument) {
            $('#reviewBusinessDoc').attr('href', data.businessDocument);
        }
        
        // Set signature image
        if (data.signatureFile) {
            $('#reviewSignatureImg').attr('src', data.signatureFile);
        }

        // Reset company approval section
        $('#companySignatoryName').val('');
        if (companySignaturePad) {
            companySignaturePad.clear();
        }
        $('#companyApprovalSection').hide();

        // Trigger change event for review action
        $('#reviewAction').trigger('change');

        // Check if already reviewed
        const isReviewed = data.agreementStatus === 'approved' || 
                          data.reviewStatus === 'approved';

        if (isReviewed) {
            $('#saveReviewBtn').hide();
            $('#reviewAction').prop('disabled', true);
            $('#reviewRemark').prop('readonly', true);
        } else {
            $('#saveReviewBtn').show();
            $('#reviewAction').prop('disabled', false);
            $('#reviewRemark').prop('readonly', false);
        }

        // Show modal
        $('#reviewAgreementModal').modal('show');

    }, 'json').fail(function(jqXHR, textStatus, error) {
        console.error('Error loading review:', textStatus, error);
        showToast('error', 'Failed to load submission details');
    });
}

// ======================================================
// 12. SAVE REVIEW (FIXED - Complete validation)
// ======================================================

$(document).on('click', '#saveReviewBtn', function() {
    const $button = $(this);
    const agreementId = $('#reviewAgreementId').val();
    const action = $('#reviewAction').val();
    const remark = $('#reviewRemark').val().trim();

    // Validate agreement ID
    if (!agreementId) {
        showToast('error', 'Invalid submission.');
        return;
    }

    // Validate rejection remark
    if (action === 'rejected' && !remark) {
        showToast('warning', 'Please enter rejection remark.');
        $('#reviewRemark').focus();
        return;
    }

    // Validate approval details
    let companySignatoryName = '';
    let companySignature = '';

    if (action === 'approved') {
        companySignatoryName = $('#companySignatoryName').val().trim();
        
        if (!companySignatoryName) {
            showToast('warning', 'Please enter company signatory name.');
            $('#companySignatoryName').focus();
            return;
        }

        if (!companySignaturePad || companySignaturePad.isEmpty()) {
            showToast('warning', 'Please add company signature.');
            return;
        }

        companySignature = companySignaturePad.toDataURL('image/png');
        
        if (!companySignature) {
            showToast('error', 'Failed to capture signature.');
            return;
        }
    }

    // Confirm action
    let confirmMessage = 'Are you sure you want to ' + action + ' this agreement?';
    if (action === 'rejected') {
        confirmMessage = 'Are you sure you want to reject this agreement? This action cannot be undone.';
    }

    Swal.fire({
        title: 'Confirm ' + action.charAt(0).toUpperCase() + action.slice(1),
        text: confirmMessage,
        icon: action === 'rejected' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'rejected' ? '#d33' : '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, ' + action + ' it!'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        // Disable button and show loading
        $button.prop('disabled', true).text('Saving...');

        // Submit review
        $.post(saveAgreementReviewApiUrl, {
            agreementId: agreementId,
            action: action,
            remark: remark,
            companySignatoryName: companySignatoryName,
            companySignature: companySignature
        }, function(response) {
            if (!response.success) {
                showToast('error', response.message || 'Failed to save review');
                $button.prop('disabled', false).text('Save Review');
                return;
            }

            showToast('success', response.message || 'Review saved successfully');
            
            // Close modal
            $('#reviewAgreementModal').modal('hide');

            // Reload after delay
            setTimeout(function() {
                location.reload();
            }, 1000);

        }, 'json').fail(function(jqXHR, textStatus, error) {
            console.error('Error saving review:', textStatus, error);
            showToast('error', 'Server error while saving review');
            $button.prop('disabled', false).text('Save Review');
        });
    });
});

// ======================================================
// 13. ADDITIONAL HELPER FUNCTIONS (NEW)
// ======================================================

/**
 * Reset review modal fields
 */
function resetReviewModal() {
    $('#reviewRemark').val('');
    $('#reviewSignatoryName').val('');
    $('#companySignatoryName').val('');
    if (companySignaturePad) {
        companySignaturePad.clear();
    }
    $('#reviewAction').val('').trigger('change');
    $('#companyApprovalSection').hide();
}

/**
 * Check if agreement can be edited
 */
function canEditAgreement(status) {
    return !['submitted', 'approved'].includes(status);
}

/**
 * Get agreement status badge class
 */
function getStatusBadgeClass(status) {
    const statusMap = {
        'draft': 'bg-secondary',
        'sent': 'bg-primary',
        'viewed': 'bg-warning',
        'submitted': 'bg-info',
        'approved': 'bg-success',
        'rejected': 'bg-danger'
    };
    return statusMap[status] || 'bg-secondary';
}

// ======================================================
// 14. EXPOSE PUBLIC API
// ======================================================

// Make functions available globally
window.AgreementSystem = {
    showToast: showToast,
    formatDateTime: formatDateTime,
    updateAgreementStatusUI: updateAgreementStatusUI,
    loadReviewModal: loadReviewModal,
    resetReviewModal: resetReviewModal,
    canEditAgreement: canEditAgreement,
    getStatusBadgeClass: getStatusBadgeClass,
    clearSignature: function() {
        if (companySignaturePad) {
            companySignaturePad.clear();
        }
    },
    getSignatureData: function() {
        if (companySignaturePad && !companySignaturePad.isEmpty()) {
            return companySignaturePad.toDataURL('image/png');
        }
        return null;
    }
};

// ======================================================
// 15. CONSOLE LOGGING
// ======================================================

console.log('Agreement Management System loaded successfully');
console.log('Available API:', window.AgreementSystem);

// ======================================================
// 16. ERROR HANDLING FOR MODAL CLOSE
// ======================================================

$(document).on('hidden.bs.modal', '#reviewAgreementModal', function() {
    // Reset form when modal is closed
    resetReviewModal();
});

$(document).on('hidden.bs.modal', '#agreementModal', function() {
    // Clean up editor if needed
    if (agreementEditor) {
        // Save current state if needed
    }
});

// ======================================================
// 17. KEYBOARD SHORTCUTS (NEW)
// ======================================================

$(document).on('keydown', function(e) {
    // Ctrl+S to save draft
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        if ($('#agreementModal').hasClass('show')) {
            $('#saveAgreementBtn').click();
        }
    }
});