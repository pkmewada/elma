<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.company-logo-preview {
    width: 160px;
    height: 90px;
    border: 1px dashed rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    overflow: hidden;
}

.company-logo-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.company-logo-preview span {
    color: #8c9097;
    font-size: 12px;
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Company Setup</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Company Setup</li>
                </ol>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="setupWarning">
            Company profile changes affect salary slips and future company documents.
        </div>

        <form id="companySetupForm" autocomplete="off">
            <input type="hidden" id="existingCompanyLogo" name="existingCompanyLogo" value="">

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-0">Company Details</h5>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="companyName">Company Name</label>
                                    <input type="text" class="form-control" id="companyName" name="companyName" maxlength="180" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="legalName">Legal Name</label>
                                    <input type="text" class="form-control" id="legalName" name="legalName" maxlength="180">
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="addressLine1">Address Line 1</label>
                                    <input type="text" class="form-control" id="addressLine1" name="addressLine1" maxlength="255">
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="addressLine2">Address Line 2</label>
                                    <input type="text" class="form-control" id="addressLine2" name="addressLine2" maxlength="255">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="city">City</label>
                                    <input type="text" class="form-control" id="city" name="city" maxlength="120">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="stateName">State</label>
                                    <input type="text" class="form-control" id="stateName" name="stateName" maxlength="120">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="pincode">Pincode</label>
                                    <input type="text" class="form-control" id="pincode" name="pincode" maxlength="20">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="country">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" maxlength="120" value="India">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="phone">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" maxlength="40">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" maxlength="160">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="website">Website</label>
                                    <input type="url" class="form-control" id="website" name="website" maxlength="180">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="companyLogo">Company Logo</label>
                                    <input type="file" class="form-control" id="companyLogo" name="companyLogo" accept="image/png,image/jpeg,image/webp,image/gif">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Document Identity</h5>
                        </div>

                        <div class="card-body d-flex flex-column gap-3">
                            <div>
                                <label class="form-label" for="gstNumber">GST Number</label>
                                <input type="text" class="form-control text-uppercase" id="gstNumber" name="gstNumber" maxlength="15">
                            </div>

                            <div>
                                <label class="form-label" for="panNumber">PAN Number</label>
                                <input type="text" class="form-control text-uppercase" id="panNumber" name="panNumber" maxlength="10">
                            </div>

                            <div>
                                <label class="form-label" for="cinNumber">CIN Number</label>
                                <input type="text" class="form-control text-uppercase" id="cinNumber" name="cinNumber" maxlength="40">
                            </div>

                            <div>
                                <label class="form-label">Logo Preview</label>
                                <div class="company-logo-preview" id="companyLogoPreview">
                                    <span>No logo</span>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <button type="submit" class="btn btn-primary w-100" id="saveCompanySetupBtn">
                                    Save Company Setup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let hasUnsavedCompanyChanges = false;

$(function() {
    loadCompanySetup();

    $('#companySetupForm').on('change keyup', 'input', function() {
        hasUnsavedCompanyChanges = true;
        $('#setupWarning').removeClass('d-none');
    });

    $('#companySetupForm').on('submit', function(e) {
        e.preventDefault();
        saveCompanySetup();
    });

    $('#companyLogo').on('change', function() {
        previewSelectedLogo(this.files && this.files[0] ? this.files[0] : null);
    });

    window.addEventListener('beforeunload', function(e) {
        if (!hasUnsavedCompanyChanges) {
            return;
        }

        e.preventDefault();
        e.returnValue = '';
    });
});

function setLogoPreview(url) {
    if (!url) {
        $('#companyLogoPreview').html('<span>No logo</span>');
        return;
    }

    $('#companyLogoPreview').html(`<img src="${url}" alt="Company logo preview">`);
}

function previewSelectedLogo(file) {
    if (!file) {
        setLogoPreview($('#existingCompanyLogo').data('logo-url') || '');
        return;
    }

    if (!file.type.match(/^image\/(png|jpeg|webp|gif)$/)) {
        window.showToast && window.showToast('warning', 'Logo must be PNG, JPG, WEBP, or GIF.');
        $('#companyLogo').val('');
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        window.showToast && window.showToast('warning', 'Company logo must be 2 MB or smaller.');
        $('#companyLogo').val('');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        setLogoPreview(e.target.result);
    };
    reader.readAsDataURL(file);
}

function bindCompanySetup(settings) {
    $('#companyName').val(settings.companyName || '');
    $('#legalName').val(settings.legalName || '');
    $('#addressLine1').val(settings.addressLine1 || '');
    $('#addressLine2').val(settings.addressLine2 || '');
    $('#city').val(settings.city || '');
    $('#stateName').val(settings.stateName || '');
    $('#pincode').val(settings.pincode || '');
    $('#country').val(settings.country || 'India');
    $('#phone').val(settings.phone || '');
    $('#email').val(settings.email || '');
    $('#website').val(settings.website || '');
    $('#gstNumber').val(settings.gstNumber || '');
    $('#panNumber').val(settings.panNumber || '');
    $('#cinNumber').val(settings.cinNumber || '');
    $('#existingCompanyLogo')
        .val(settings.companyLogo || '')
        .data('logo-url', settings.companyLogoUrl || '');
    setLogoPreview(settings.companyLogoUrl || '');
}

function validateCompanySetup() {
    const companyName = $('#companyName').val().trim();
    const email = $('#email').val().trim();
    const gstNumber = $('#gstNumber').val().trim().toUpperCase();
    const panNumber = $('#panNumber').val().trim().toUpperCase();

    if (!companyName) {
        return 'Company name is required.';
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return 'Please enter a valid company email.';
    }

    if (gstNumber && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(gstNumber)) {
        return 'Please enter a valid GST number.';
    }

    if (panNumber && !/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(panNumber)) {
        return 'Please enter a valid PAN number.';
    }

    return '';
}

function loadCompanySetup() {
    $.getJSON(API_BASE + '/company/getCompanySetup.php')
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load company setup.');
                return;
            }

            bindCompanySetup(res.data.companySettings || {});
            hasUnsavedCompanyChanges = false;
            $('#setupWarning').addClass('d-none');
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load company setup.');
        });
}

function saveCompanySetup() {
    const error = validateCompanySetup();

    if (error) {
        window.showToast && window.showToast('warning', error);
        return;
    }

    const formData = new FormData(document.getElementById('companySetupForm'));
    const $btn = $('#saveCompanySetupBtn');

    $btn
        .prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
        url: API_BASE + '/company/saveCompanySetup.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Invalid server response.'
            );

            if (res && res.success) {
                bindCompanySetup(res.data.companySettings || {});
                $('#companyLogo').val('');
                hasUnsavedCompanyChanges = false;
                $('#setupWarning').addClass('d-none');
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to save company setup.');
        },
        complete: function() {
            $btn
                .prop('disabled', false)
                .text('Save Company Setup');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
