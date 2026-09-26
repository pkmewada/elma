<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../includes/config.php';

// Use ASSET_URL instead of hardcoded path
$base = ASSET_URL . '/';
?>

<?php
function showRemark( array $remarks, string $fieldName): string {

    if (
        empty($remarks[$fieldName])
    ) {
        return '';
    }

    return '
        <div class="hr-remark-box mt-2">
            <strong>HR Remark:</strong><br>
            ' . htmlspecialchars($remarks[$fieldName]) . '
        </div>';
}

function reqLabel(string $text): string
{
    return $text . " <span class='text-danger'>*</span>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - Modlus</title>

    <link rel="icon" href="<?= $base ?>assets/images/brand-logos/favicon.ico">

    <link href="<?= $base ?>assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base ?>assets/css/styles.css" rel="stylesheet">
    <link href="<?= $base ?>assets/css/icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <style>
    body {
        background: #f8f9fb;
    }

    .section-title {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .card-header-custom {
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
    }

    .form-label {
        font-weight: 600;
        font-size: 14px;
    }

    .form-control,
    .form-select {
        min-height: 46px;
    }

    textarea.form-control {
        min-height: auto;
    }

    .upload-note {
        font-size: 12px;
        color: #6b7280;
    }

    .readonly-box {
        background: #f3f4f6 !important;
    }

    .sticky-submit {
        position: sticky;
        bottom: 0;
        background: #fff;
        padding-top: 15px;
    }
    
    .alert-danger.hr-remark,
    .hr-remark-box {
        background: #fff5f5;
        border: 1px solid #FF0000;
        color: #FF0000;
        border-radius: 8px;
        font-size: 13px;
        padding:15px;
    }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">

                <div class="card shadow-sm border-0 rounded-3">

                    <div class="card-header card-header-custom p-4">
                        <h3 class="mb-1 fw-bold">Complete Your Profile</h3>
                        <p class="text-muted mb-0">
                            Fill all mandatory details carefully for HR verification and onboarding process.
                        </p>
                    </div>

                    <div class="card-body p-4 p-lg-5">

                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($verificationRemarks)): ?>

                        <div class="alert alert-warning mb-4">
                        
                            <strong>
                                Action Required
                            </strong>
                        
                            <br>
                        
                            HR has requested corrections in some details/documents.
                            Please review the remarks highlighted below and update them.
                        
                        </div>
                        
                        <?php endif; ?>

                        <div class="alert alert-light border mb-4">
                            Fields marked with <span class="text-danger">*</span> are mandatory.
                            Please upload clear documents only.
                        </div>

                        <form method="post" action="candidate-profile" enctype="multipart/form-data">

                            <div class="row gy-4">
                        
                                <!-- PERSONAL -->
                                <div class="col-12">
                                    <div class="section-title">Personal Details</div>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                        
                                    <input type="text"
                                        class="form-control readonly-box"
                                        value="<?= htmlspecialchars($_SESSION['candidateName']) ?>"
                                        readonly>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                        
                                    <input type="email"
                                        class="form-control readonly-box"
                                        value="<?= htmlspecialchars($_SESSION['candidateEmail']) ?>"
                                        readonly>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Mobile Number') ?></label>
                        
                                    <input type="text"
                                        name="mobileNumber"
                                        class="form-control"
                                        placeholder="Enter mobile number"
                                        value="<?= htmlspecialchars($candidate['mobileNumber'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label">Alternative Number</label>
                        
                                    <input type="text"
                                        name="alternativeNumber"
                                        class="form-control"
                                        placeholder="Enter alternate number"
                                        value="<?= htmlspecialchars($candidate['alternativeNumber'] ?? '') ?>">
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Emergency Contact') ?></label>
                        
                                    <input type="text"
                                        name="emergencyContactNumber"
                                        class="form-control"
                                        placeholder="Enter emergency contact number"
                                        value="<?= htmlspecialchars($candidate['emergencyContactNumber'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Date of Birth') ?></label>
                        
                                    <input type="date"
                                        name="dateOfBirth"
                                        class="form-control"
                                        value="<?= htmlspecialchars($candidate['dateOfBirth'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Gender') ?></label>
                        
                                    <select name="gender" class="form-select" required>
                        
                                        <option value="">Select gender</option>
                        
                                        <option value="Male"
                                            <?= ($candidate['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>
                                            Male
                                        </option>
                        
                                        <option value="Female"
                                            <?= ($candidate['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>
                                            Female
                                        </option>
                        
                                        <option value="Other"
                                            <?= ($candidate['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>
                                            Other
                                        </option>
                        
                                    </select>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Marital Status') ?></label>
                        
                                    <select name="maritalStatus" class="form-select" required>
                        
                                        <option value="">Select status</option>
                        
                                        <option value="Single"
                                            <?= ($candidate['maritalStatus'] ?? '') === 'Single' ? 'selected' : '' ?>>
                                            Single
                                        </option>
                        
                                        <option value="Married"
                                            <?= ($candidate['maritalStatus'] ?? '') === 'Married' ? 'selected' : '' ?>>
                                            Married
                                        </option>
                        
                                    </select>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label">LinkedIn Profile</label>
                        
                                    <input type="url"
                                        name="linkedInProfile"
                                        class="form-control"
                                        placeholder="https://linkedin.com/in/username"
                                        value="<?= htmlspecialchars($candidate['linkedInProfile'] ?? '') ?>">
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label">Instagram Profile</label>
                        
                                    <input type="url"
                                        name="instagramProfile"
                                        class="form-control"
                                        placeholder="https://instagram.com/username"
                                        value="<?= htmlspecialchars($candidate['instagramProfile'] ?? '') ?>">
                                </div>
                        
                                <!-- ADDRESS -->
                                <div class="col-12 pt-2">
                                    <div class="section-title">Address Details</div>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Permanent Address') ?></label>
                        
                                    <textarea name="permanentAddress"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Enter permanent address"
                                        required><?= htmlspecialchars($candidate['permanentAddress'] ?? '') ?></textarea>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Local Address') ?></label>
                        
                                    <textarea name="localAddress"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Enter local/current address"
                                        required><?= htmlspecialchars($candidate['localAddress'] ?? '') ?></textarea>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('City') ?></label>
                        
                                    <input type="text"
                                        name="cityName"
                                        class="form-control"
                                        placeholder="Enter city name"
                                        value="<?= htmlspecialchars($candidate['cityName'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('State') ?></label>
                        
                                    <input type="text"
                                        name="stateName"
                                        class="form-control"
                                        placeholder="Enter state name"
                                        value="<?= htmlspecialchars($candidate['stateName'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Pin Code') ?></label>
                        
                                    <input type="text"
                                        name="pinCode"
                                        class="form-control"
                                        placeholder="Enter PIN code"
                                        value="<?= htmlspecialchars($candidate['pinCode'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <!-- BANK -->
                                <div class="col-12 pt-2">
                                    <div class="section-title">Bank Details</div>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Account Holder Name') ?></label>
                        
                                    <input type="text"
                                        name="accountHolderName"
                                        class="form-control"
                                        placeholder="Enter account holder name"
                                        value="<?= htmlspecialchars($candidate['accountHolderName'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Bank Name') ?></label>
                        
                                    <input type="text"
                                        name="bankName"
                                        class="form-control"
                                        placeholder="Enter bank name"
                                        value="<?= htmlspecialchars($candidate['bankName'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Account Number') ?></label>
                        
                                    <input type="text"
                                        name="accountNumber"
                                        class="form-control"
                                        placeholder="Enter account number"
                                        value="<?= htmlspecialchars($candidate['accountNumber'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('IFSC Code') ?></label>
                        
                                    <input type="text"
                                        name="ifscCode"
                                        class="form-control"
                                        placeholder="Enter IFSC code"
                                        value="<?= htmlspecialchars($candidate['ifscCode'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-4">
                                    <label class="form-label"><?= reqLabel('Branch Name') ?></label>
                        
                                    <input type="text"
                                        name="branchName"
                                        class="form-control"
                                        placeholder="Enter branch name"
                                        value="<?= htmlspecialchars($candidate['branchName'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <!-- KYC -->
                                <div class="col-12 pt-2">
                                    <div class="section-title">KYC Details</div>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Aadhaar Number') ?></label>
                        
                                    <input type="text"
                                        name="aadhaarNumber"
                                        class="form-control"
                                        placeholder="Enter 12-digit Aadhaar number"
                                        value="<?= htmlspecialchars($candidate['aadhaarNumber'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('PAN Number') ?></label>
                        
                                    <input type="text"
                                        name="panNumber"
                                        class="form-control"
                                        placeholder="Enter PAN number"
                                        value="<?= htmlspecialchars($candidate['panNumber'] ?? '') ?>"
                                        required>
                                </div>
                        
                                <!-- DOCUMENTS -->
                                <div class="col-12 pt-2">
                                    <div class="section-title">Upload Documents</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Profile Photo') ?></label>
                                
                                    <input type="file"
                                        name="profilePhoto"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png">
                                
                                    <div class="upload-note">
                                        JPG / PNG only
                                    </div>
                                
                                    <?php if (!empty($candidate['profilePhoto'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'profilePhoto') ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Aadhaar Card') ?></label>
                                
                                    <input type="file"
                                        name="aadhaarFile"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                
                                    <?php if (!empty($candidate['aadhaarFile'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'aadhaarFile') ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('PAN Card') ?></label>
                                
                                    <input type="file"
                                        name="panFile"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                
                                    <?php if (!empty($candidate['panFile'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'panFile') ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">12th Marksheet</label>
                                
                                    <input type="file"
                                        name="marksheet12File"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                
                                    <?php if (!empty($candidate['marksheet12File'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'marksheet12File') ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Graduation Certificate</label>
                                
                                    <input type="file"
                                        name="graduationFile"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                
                                    <?php if (!empty($candidate['graduationFile'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'graduationFile') ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label"><?= reqLabel('Previews Company Document') ?></label>
                                
                                    <input type="file"
                                        name="marksheet10File"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                
                                    <?php if (!empty($candidate['marksheet10File'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'marksheet10File') ?>
                                </div>
                                
                                <div class="col-md-12">
                                    <label class="form-label">
                                        <?= reqLabel('Bank Passbook / Cancelled Cheque') ?>
                                    </label>
                                
                                    <input type="file"
                                        name="bankPassbookFile"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                
                                    <?php if (!empty($candidate['bankPassbookFile'])): ?>
                                    <small class="text-success d-block mt-1">
                                        File already uploaded
                                    </small>
                                    <?php endif; ?>
                                
                                    <?= showRemark($verificationRemarks, 'bankPassbookFile') ?>
                                </div>
                                
                        
                                <!-- SUBMIT -->
                                <div class="col-12 sticky-submit">
                        
                                    <button type="submit"
                                        class="btn btn-primary btn-lg w-100">
                        
                                        Submit Profile for Verification
                        
                                    </button>
                        
                                </div>
                        
                            </div>
                        
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= $base ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        const form = document.querySelector('form');

        if (!form) return;

        /*
        |--------------------------------------------------------------------------
        | Helpers
        |--------------------------------------------------------------------------
        */

        function setError(input, message) {
            clearState(input);

            input.classList.add('is-invalid');

            let div = document.createElement('div');
            div.className = 'invalid-feedback d-block';
            div.innerText = message;

            input.parentNode.appendChild(div);
        }

        function setSuccess(input) {
            clearState(input);
            input.classList.add('is-valid');
        }

        function clearState(input) {
            input.classList.remove('is-invalid');
            input.classList.remove('is-valid');

            const msg = input.parentNode.querySelectorAll('.invalid-feedback');
            msg.forEach(el => el.remove());
        }

        function regex(v, r) {
            return r.test(v.trim());
        }

        function digitsOnly(el, max = null) {
            el.value = el.value.replace(/\D/g, '');
            if (max) el.value = el.value.slice(0, max);
        }

        function showToast(message) {
            if (typeof toastr !== 'undefined') {
                toastr.error(message);
            }
        }

        function scrollFirstError() {
            const first = form.querySelector('.is-invalid');
            if (first) {
                first.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                first.focus();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Auto Format
        |--------------------------------------------------------------------------
        */

        const mobile = form.mobileNumber;
        const alt = form.alternativeNumber;
        const emergency = form.emergencyContactNumber;
        const aadhaar = form.aadhaarNumber;
        const pan = form.panNumber;
        const ifsc = form.ifscCode;

        [mobile, alt, emergency].forEach(el => {
            if (!el) return;
            el.addEventListener('input', function() {
                digitsOnly(this, 10);
            });
        });

        if (aadhaar) {
            aadhaar.addEventListener('input', function() {

                let value = this.value.replace(/\D/g, '').slice(0, 12);

                let parts = value.match(/.{1,4}/g);
                this.value = parts ? parts.join(' ') : '';
            });
        }

        if (pan) {
            pan.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
            });
        }

        if (ifsc) {
            ifsc.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Live Validation Functions
        |--------------------------------------------------------------------------
        */

        function validateMobile(el, required = true) {

            const val = el.value.trim();

            if (val === '' && !required) {
                clearState(el);
                return true;
            }

            if (!regex(val, /^[6-9]\d{9}$/)) {
                setError(el, 'Enter valid 10 digit mobile number.');
                return false;
            }

            setSuccess(el);
            return true;
        }

        function validateRequired(el, msg = 'Required field.') {
            if (el.value.trim() === '') {
                setError(el, msg);
                return false;
            }

            setSuccess(el);
            return true;
        }

        function validatePin(el) {
            if (!regex(el.value, /^\d{6}$/)) {
                setError(el, 'Enter valid 6 digit PIN code.');
                return false;
            }
            setSuccess(el);
            return true;
        }

        function validatePan(el) {
            if (!regex(el.value, /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/)) {
                setError(el, 'Enter valid PAN number.');
                return false;
            }
            setSuccess(el);
            return true;
        }

        function validateAadhaar(el) {

            const raw = el.value.replace(/\s/g, '');

            if (!regex(raw, /^\d{12}$/)) {
                setError(el, 'Enter valid 12 digit Aadhaar.');
                return false;
            }

            setSuccess(el);
            return true;
        }

        function validateIfsc(el) {
            if (!regex(el.value, /^[A-Z]{4}0[A-Z0-9]{6}$/)) {
                setError(el, 'Enter valid IFSC code.');
                return false;
            }
            setSuccess(el);
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Live Bind
        |--------------------------------------------------------------------------
        */

        mobile.addEventListener('keyup', () => validateMobile(mobile));
        alt.addEventListener('keyup', () => validateMobile(alt, false));
        emergency.addEventListener('keyup', () => validateMobile(emergency));

        form.pinCode.addEventListener('keyup', () => validatePin(form.pinCode));
        pan.addEventListener('keyup', () => validatePan(pan));
        aadhaar.addEventListener('keyup', () => validateAadhaar(aadhaar));
        ifsc.addEventListener('keyup', () => validateIfsc(ifsc));

        /*
        |--------------------------------------------------------------------------
        | Submit Validation
        |--------------------------------------------------------------------------
        */

        form.addEventListener('submit', function(e) {

            let ok = true;

            if (!validateMobile(mobile)) ok = false;
            if (!validateMobile(alt, false)) ok = false;
            if (!validateMobile(emergency)) ok = false;

            if (!validateRequired(form.dateOfBirth)) ok = false;
            if (!validateRequired(form.gender)) ok = false;
            if (!validateRequired(form.maritalStatus)) ok = false;

            if (!validateRequired(form.cityName)) ok = false;
            if (!validateRequired(form.stateName)) ok = false;

            if (!validatePin(form.pinCode)) ok = false;
            if (!validateAadhaar(aadhaar)) ok = false;
            if (!validatePan(pan)) ok = false;
            if (!validateIfsc(ifsc)) ok = false;

            if (!ok) {
                e.preventDefault();
                showToast('Please correct highlighted fields.');
                scrollFirstError();
            }

        });

    });
    </script>

</body>

</html>