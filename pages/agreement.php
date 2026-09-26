<?php


require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leadEngine.php';




$token =
    trim(
        $_GET['token']
        ?? ''
    );

if ($token === '') {

    die(
        'Invalid agreement link.'
    );
}

$leadEngine =
    new LeadEngine(
        $con
    );

$agreement =  $leadEngine->getAgreementByToken( $token );
if (!$agreement)
{
die( 'Agreement not found.' );
} 
/*
|-------------------------------------------------------------------------- |
Mark Viewed
|-------------------------------------------------------------------------- */

$leadEngine->markAgreementViewed(
(int)$agreement['id'] ); ?>

<!doctype html>

<html lang="en">
    <head>
        <meta charset="UTF-8" />

        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <title>Agreement Review</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    </head>

    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-5">
                            <!-- Welcome -->

                            <div class="text-center mb-5">
                                <h2 class="fw-bold">Welcome <?= htmlspecialchars( $agreement['fullName'] ) ?></h2>

                                <p class="text-muted">Please review your onboarding agreement carefully.</p>
                            </div>

                            <!-- Client Info -->

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <small class="text-muted"> Client Name </small>

                                        <div class="fw-semibold"><?= htmlspecialchars( $agreement['fullName'] ) ?></div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <small class="text-muted"> Email </small>

                                        <div class="fw-semibold"><?= htmlspecialchars( $agreement['email'] ) ?></div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <small class="text-muted"> Final Price </small>

                                        <div class="fw-semibold">
                                            ₹ <?= number_format( (float)$agreement['finalPrice'], 2 ) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Agreement Content -->

                            <div class="border rounded p-4 bg-white mb-4"><?= $agreement['agreementContent'] ?></div>
                            
                            <div class="alert alert-warning">
                                <strong>Verification Required</strong>
                                <hr>
                                Please upload a valid business document
                                (GST Certificate or any government-issued
                                business document) and provide your
                                authorized signature to complete the
                                onboarding process.
                            </div>
                            <!-- Business Document -->
                            <div class="mb-4">
                                <label class="form-label">Upload Business Document (GST or any valid document)</label>
                                <input type="file" class="form-control" id="businessDocument" accept=".pdf,.jpg,.jpeg,.png" />
                            </div>
                            
                            <!-- Authorized Signatory Name -->
                            <div class="mb-4">
                                <label class="form-label">Authorized Signatory Name</label>
                                <input type="text" class="form-control" id="signatoryName" placeholder="Enter name as per signature" />
                            </div>
                            
                            <!-- Digital Signature -->
                            <div class="mb-4">
                                <label class="form-label">Signature</label>
                                <canvas
                                    id="signaturePad"
                                    style="
                                        width:100%;
                                        height:180px;
                                        border:1px solid #dee2e6;
                                        border-radius:8px;
                                        background:#fff;
                                        cursor:crosshair;
                                    ">
                                </canvas>
                                <small class="text-muted">
                                    Please sign using your mouse, touch screen, or stylus.
                                </small><br>
                                <button type="button" class="btn btn-sm btn-secondary mt-2" id="clearSignature">Clear</button>
                                
                            </div>

                            <!-- Terms -->

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" />

                                <label class="form-check-label" for="agreeTerms">
                                    I have read and agree to all terms and conditions.
                                </label>
                            </div>

                            <!-- Hidden -->

                            <input type="hidden" id="agreementToken" value="<?= htmlspecialchars($token) ?>" />

                            <!-- Button -->

                            <button
                                type="button"
                                class="btn btn-primary"
                                id="acceptAgreementBtn">
                            
                                Submit Agreement
                            
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>

        <script>
        
        const canvas =
            document.getElementById(
                'signaturePad'
            );
        
        /*
        |--------------------------------------------------------------------------
        | Fix Canvas Size
        |--------------------------------------------------------------------------
        */
        
        function resizeCanvas() {
        
            const ratio =
                Math.max(
                    window.devicePixelRatio || 1,
                    1
                );
        
            canvas.width =
                canvas.offsetWidth * ratio;
        
            canvas.height =
                200 * ratio;
        
            canvas
                .getContext('2d')
                .scale(
                    ratio,
                    ratio
                );
        }
        
        resizeCanvas();
        
        window.addEventListener(
            'resize',
            resizeCanvas
        );
        
        /*
        |--------------------------------------------------------------------------
        | Signature Pad
        |--------------------------------------------------------------------------
        */
        
       const signaturePad =
        new SignaturePad(
            canvas,
            {
                backgroundColor:
                    'rgb(255,255,255)',
    
                penColor:
                    '#1f2937',
    
                minWidth:
                    0.3,
    
                maxWidth:
                    1.0,
    
                throttle:
                    8,
    
                velocityFilterWeight:
                    0.85
            }
        );
        /*
        |--------------------------------------------------------------------------
        | Clear Signature
        |--------------------------------------------------------------------------
        */
        
        $('#clearSignature').on(
            'click',
            function () {
        
                signaturePad.clear();
        
            }
        );
        
        /*
        |--------------------------------------------------------------------------
        | Submit Agreement
        |--------------------------------------------------------------------------
        */
        
        $('#acceptAgreementBtn').on(
            'click',
            function () {
        
                if (
                    !$('#agreeTerms')
                        .is(':checked')
                ) {
        
                    alert(
                        'Please accept the terms and conditions.'
                    );
        
                    return;
                }
        
                if (
                    !$('#businessDocument')[0]
                        .files.length
                ) {
        
                    alert(
                        'Please upload your business document.'
                    );
        
                    return;
                }
        
                if (
                    !$('#signatoryName')
                        .val()
                        .trim()
                ) {
        
                    alert(
                        'Please enter the authorized signatory name.'
                    );
        
                    return;
                }
        
                if (
                    signaturePad.isEmpty()
                ) {
        
                    alert(
                        'Please provide your signature.'
                    );
        
                    return;
                }
        
                const formData =
                    new FormData();
        
                formData.append(
                    'token',
                    $('#agreementToken')
                        .val()
                );
        
                formData.append(
                    'businessDocument',
                    $('#businessDocument')[0]
                        .files[0]
                );
        
                formData.append(
                    'signatoryName',
                    $('#signatoryName')
                        .val()
                        .trim()
                );
        
                formData.append(
                    'signature',
                    signaturePad.toDataURL(
                        'image/png'
                    )
                );
        
                $.ajax({
        
                    url:
                        'api/onboarding/acceptAgreement.php',
        
                    method:
                        'POST',
        
                    data:
                        formData,
        
                    processData:
                        false,
        
                    contentType:
                        false,
        
                    dataType:
                        'json',
        
                    beforeSend:
                        function () {
        
                            $('#acceptAgreementBtn')
                                .prop(
                                    'disabled',
                                    true
                                )
                                .text(
                                    'Submitting...'
                                );
                        },
        
                    success:
                        function (
                            response
                        ) {
        
                            if (
                                !response.success
                            ) {
        
                                alert(
                                    response.message
                                );
        
                                $('#acceptAgreementBtn')
                                    .prop(
                                        'disabled',
                                        false
                                    )
                                    .text(
                                        'Submit Agreement'
                                    );
        
                                return;
                            }
        
                            window.location =
                                'agreement-success';
                        },
        
                    error:
                        function () {
        
                            alert(
                                'Failed to submit agreement.'
                            );
        
                            $('#acceptAgreementBtn')
                                .prop(
                                    'disabled',
                                    false
                                )
                                .text(
                                    'Submit Agreement'
                                );
                        }
        
                });
        
            }
        );
        
        </script>

    </body>
</html>
