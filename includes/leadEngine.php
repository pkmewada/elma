<?php

/*
|--------------------------------------------------------------------------
| Lead Engine
|--------------------------------------------------------------------------
|
| Centralized Lead & Onboarding Management
|
| Responsibilities:
| - Lead Management
| - Lead Remarks
| - Lead Follow Ups
| - Lead Documents
| - Lead Conversions
| - Onboarding Agreements
| - Client Onboarding Workflow
|
*/

require_once __DIR__ . '/mailer.php';

class LeadEngine
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    private $con;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct($con)
    {
        $this->con = $con;
    }

    /*
    |--------------------------------------------------------------------------
    | Lead Methods
    |--------------------------------------------------------------------------
    */

    public function getLeadById($leadId)
    {
        $leadId = (int)$leadId;

        $stmt = mysqli_prepare(
            $this->con,
            "
            SELECT
                l.*,
                c.categoryName,
                p.planName,
                e.fullName AS employeeName
            FROM leads l

            LEFT JOIN leadCategories c
                ON c.id = l.categoryId

            LEFT JOIN leadPlans p
                ON p.id = l.planId

            LEFT JOIN employeeusers e
                ON e.id = l.createdByCandidateId

            WHERE l.id = ?
            LIMIT 1
            "
        );

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $leadId
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        $lead =
            $result
                ? mysqli_fetch_assoc($result)
                : null;

        mysqli_stmt_close($stmt);

        return $lead;
    }

    /*
    |--------------------------------------------------------------------------
    | Lead Conversion
    |--------------------------------------------------------------------------
    */

    public function getConversionByLeadId($leadId)
    {
        $leadId = (int)$leadId;

        $stmt = mysqli_prepare(
            $this->con,
            "
            SELECT *
            FROM leadConversions
            WHERE leadId = ?
            ORDER BY id DESC
            LIMIT 1
            "
        );

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $leadId
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        $conversion =
            $result
                ? mysqli_fetch_assoc($result)
                : null;

        mysqli_stmt_close($stmt);

        return $conversion;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Agreement By Lead Id 
    |--------------------------------------------------------------------------
    */

    public function getAgreementByLeadId($leadId)
    {
        $leadId = (int)$leadId;

        $stmt = mysqli_prepare(
            $this->con,
            "
            SELECT *
            FROM onboardingAgreements
            WHERE leadId = ?
            LIMIT 1
            "
        );

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $leadId
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        $agreement =
            $result
                ? mysqli_fetch_assoc($result)
                : null;

        mysqli_stmt_close($stmt);

        return $agreement;
    }

    /*
    |--------------------------------------------------------------------------
    | Create / Update Agreement Draft
    |--------------------------------------------------------------------------
    */

    public function saveAgreementDraft(
        $leadId,
        $agreementContent,
        $createdByUserId
    ) {

        $existingAgreement =
            $this->getAgreementByLeadId(
                $leadId
            );

        if ($existingAgreement) {

            $stmt = mysqli_prepare(
                $this->con,
                "
                UPDATE onboardingAgreements
                SET
                    agreementContent = ?
                WHERE leadId = ?
                "
            );

            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param(
                $stmt,
                'si',
                $agreementContent,
                $leadId
            );

            $saved =
                mysqli_stmt_execute(
                    $stmt
                );

            mysqli_stmt_close(
                $stmt
            );

            return $saved;
        }

        $token =
            bin2hex(
                random_bytes(16)
            );

        $stmt = mysqli_prepare(
            $this->con,
            "
            INSERT INTO onboardingAgreements
            (
                leadId,
                agreementContent,
                agreementToken,
                createdByUserId
            )
            VALUES
            (
                ?, ?, ?, ?
            )
            "
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'issi',
            $leadId,
            $agreementContent,
            $token,
            $createdByUserId
        );

        $saved =
            mysqli_stmt_execute(
                $stmt
            );

        mysqli_stmt_close(
            $stmt
        );

        return $saved;
    }

    /*
    |--------------------------------------------------------------------------
    | Agreement Status
    |--------------------------------------------------------------------------
    */

    public function updateAgreementStatus(
        $leadId,
        $status
    ) {

        $stmt = mysqli_prepare(
            $this->con,
            "
            UPDATE onboardingAgreements
            SET
                agreementStatus = ?
            WHERE leadId = ?
            "
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $status,
            $leadId
        );

        $updated =
            mysqli_stmt_execute(
                $stmt
            );

        mysqli_stmt_close(
            $stmt
        );

        return $updated;
    }

   /*
    |--------------------------------------------------------------------------
    | Send Agreement Mail
    |--------------------------------------------------------------------------
    */
    
    public function sendAgreementMail(
        int $leadId
    ) {
    
        $lead =
            $this->getLeadById(
                $leadId
            );
    
        $agreement =
            $this->getAgreementByLeadId(
                $leadId
            );
    
        if (
            !$lead ||
            !$agreement
        ) {
    
            return [
    
                'success' => false,
    
                'message' =>
                    'Agreement not found.'
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Agreement Link
        |--------------------------------------------------------------------------
        */
    
        $agreementLink =
            SITE_URL .
            '/agreement?token=' .
            $agreement['agreementToken'];
    
        /*
        |--------------------------------------------------------------------------
        | Send Mail
        |--------------------------------------------------------------------------
        */
    
        $mailSent =
            sendAgreementEmail(
    
                (int)$leadId,
    
                $lead['email'],
    
                $lead['fullName'],
    
                $agreementLink
    
            );
    
        if (!$mailSent) {
    
            return [
    
                'success' => false,
    
                'message' =>
                    'Failed to send agreement email.'
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Update Agreement Status
        |--------------------------------------------------------------------------
        */
    
        $stmt =
            mysqli_prepare(
                $this->con,
                "
                UPDATE onboardingAgreements
    
                SET
    
                    agreementStatus = 'sent',
    
                    sentAt = NOW()
    
                WHERE leadId = ?
                "
            );
    
        if (!$stmt) {
    
            return [
    
                'success' => false,
    
                'message' =>
                    'Agreement email sent but status update failed.'
            ];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $leadId
        );
    
        $updated =
            mysqli_stmt_execute(
                $stmt
            );
    
        mysqli_stmt_close(
            $stmt
        );
    
        if (!$updated) {
    
            return [
    
                'success' => false,
    
                'message' =>
                    'Agreement email sent but status update failed.'
            ];
        }
    
        return [
    
            'success' => true,
    
            'message' =>
                'Agreement sent successfully.'
        ];
    }
    
    
    
    /*
    |--------------------------------------------------------------------------
    | Send Agreement
    |--------------------------------------------------------------------------
    */
    
    public function sendAgreement(
        $leadId
    ) {
    
        $lead =
            $this->getLeadById(
                $leadId
            );
    
        $agreement =
            $this->getAgreementByLeadId(
                $leadId
            );
    
        if (
            !$lead ||
            !$agreement
        ) {
            return [
                'success' => false,
                'message' => 'Agreement not found.'
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Agreement Link
        |--------------------------------------------------------------------------
        */
    
        $agreementLink =
            SITE_URL .
            '/client-agreement?token=' .
            $agreement['agreementToken'];
    
        /*
        |--------------------------------------------------------------------------
        | Mail Send
        |--------------------------------------------------------------------------
        |
        | Replace this with your actual mailer function
        |
        */
    
        sendAgreementEmail(

            (int)$leadId,
        
            $lead['email'],
        
            $lead['fullName'],
        
            $agreementLink
        
        );
    
        /*
        |--------------------------------------------------------------------------
        | Update Status
        |--------------------------------------------------------------------------
        */
    
        $stmt = mysqli_prepare(
            $this->con,
            "
            UPDATE onboardingAgreements
            SET
    
                agreementStatus = 'sent',
    
                sentAt = NOW()
    
            WHERE leadId = ?
            "
        );
    
        if (!$stmt) {
    
            return [
                'success' => false,
                'message' => 'Failed to update agreement.'
            ];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $leadId
        );
    
        $updated =
            mysqli_stmt_execute(
                $stmt
            );
    
        mysqli_stmt_close(
            $stmt
        );
    
        if (!$updated) {
    
            return [
                'success' => false,
                'message' => 'Failed to update agreement.'
            ];
        }
    
        return [
    
            'success' => true,
    
            'message' =>
                'Agreement sent successfully.'
        ];
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Mark Agreement Viewed
    |--------------------------------------------------------------------------
    */
    
    public function markAgreementViewed(
        int $agreementId
    ): bool {
    
        global $con;
    
        $ip =
            $_SERVER['REMOTE_ADDR']
            ?? '';
    
        $userAgent =
            $_SERVER['HTTP_USER_AGENT']
            ?? '';
    
        $stmt = mysqli_prepare(
            $this->con,
            "
            UPDATE onboardingAgreements
            SET
    
                agreementStatus =
                    CASE
                        WHEN agreementStatus = 'sent'
                        THEN 'viewed'
                        ELSE agreementStatus
                    END,
    
                agreementViewedAt =
                    COALESCE(
                        agreementViewedAt,
                        NOW()
                    ),
    
                viewedIp =
                    COALESCE(
                        viewedIp,
                        ?
                    ),
    
                viewedUserAgent =
                    COALESCE(
                        viewedUserAgent,
                        ?
                    ),
    
                updatedAt = NOW()
    
            WHERE id = ?
            "
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $ip,
            $userAgent,
            $agreementId
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Mark Agreement Accepted
    |--------------------------------------------------------------------------
    */
    
    public function markAgreementAccepted(
        int $agreementId
    ): bool {
    
        global $con;
    
        $ip =
            $_SERVER['REMOTE_ADDR']
            ?? '';
    
        $userAgent =
            $_SERVER['HTTP_USER_AGENT']
            ?? '';
    
        $stmt = mysqli_prepare(
            $this->con,
            "
            UPDATE onboardingAgreements
            SET
    
                agreementStatus = 'submitted',
    
                agreementAcceptedAt = NOW(),
    
                acceptedIp = ?,
    
                acceptedUserAgent = ?,
    
                updatedAt = NOW()
    
            WHERE id = ?
            "
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $ip,
            $userAgent,
            $agreementId
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Get Agreement By Token
    |--------------------------------------------------------------------------
    */
    
    public function getAgreementByToken(
        $token
    )
    {
        $stmt = mysqli_prepare(
            $this->con,
            "
            SELECT
    
                oa.*,
    
                l.fullName,
    
                l.email,
    
                lc.finalPrice
    
            FROM onboardingAgreements oa
    
            INNER JOIN leads l
                ON l.id = oa.leadId
    
            LEFT JOIN leadConversions lc
                ON lc.leadId = l.id
    
            WHERE oa.agreementToken = ?
    
            LIMIT 1
            "
        );
    
        if (!$stmt) {
            return null;
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            's',
            $token
        );
    
        mysqli_stmt_execute(
            $stmt
        );
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $agreement =
            $result
            ? mysqli_fetch_assoc(
                $result
            )
            : null;
    
        mysqli_stmt_close(
            $stmt
        );
    
        return $agreement;
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Get Agreement By Token
    |--------------------------------------------------------------------------
    */
    
    
    
    /*
    |--------------------------------------------------------------------------
    | Save Client Onboarding Submission
    |--------------------------------------------------------------------------
    */
    public function saveAgreementSubmission(
        int $agreementId,
        string $signatoryName,
        string $businessDocumentPath,
        string $signaturePath
    ): bool {
    
        global $con;
    
        $stmt = mysqli_prepare($con, "
            INSERT INTO onboardingAgreementSubmissions
            (agreementId, signatoryName, businessDocument, signatureFile)
            VALUES (?, ?, ?, ?)
        ");
    
        if (!$stmt) {
            return false;
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'isss',
            $agreementId,
            $signatoryName,
            $businessDocumentPath,
            $signaturePath
        );
    
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    
        return $result;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Mark Agreement Submitted (Locks the agreement)
    |--------------------------------------------------------------------------
    */
    public function markAgreementSubmitted(
        int $agreementId
    ): bool {
    
        global $con;
    
        $stmt = mysqli_prepare($con, "
            UPDATE onboardingAgreements
            SET
                agreementStatus = 'submitted',
                updatedAt = NOW()
            WHERE id = ?
        ");
    
        if (!$stmt) {
            return false;
        }
    
        mysqli_stmt_bind_param($stmt, 'i', $agreementId);
    
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    
        return $result;
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Save Agreement Review (HR/Admin)
    |--------------------------------------------------------------------------
    */
    public function saveAgreementReview(
        int $agreementId,
        string $action,
        string $remark,
        int $reviewedByUserId
    ): bool {
        global $con;
    
        $stmt = mysqli_prepare(
            $this->con,
            "
            UPDATE onboardingAgreementSubmissions
            SET
                reviewStatus = ?,
                reviewRemark = ?,
                reviewedByUserId = ?,
                reviewedAt = NOW()
            WHERE agreementId = ?
              AND reviewStatus = 'pending'
            ORDER BY id DESC
            LIMIT 1
            "
        );
    
        if (!$stmt) return false;
    
        mysqli_stmt_bind_param($stmt, 'ssii', $action, $remark, $reviewedByUserId, $agreementId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    
        return $result;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Update Agreement Status after Review
    |--------------------------------------------------------------------------
    */
    public function updateAgreementReviewStatus(
        int $agreementId,
        string $action
    ): bool {
        global $con;
    
        $newStatus =  $action === 'approved' ? 'approved' : 'rejected';
    
        $stmt = mysqli_prepare($con, "
            UPDATE onboardingAgreements
            SET 
                agreementStatus = ?,
                updatedAt = NOW()
            WHERE id = ?
        ");
    
        if (!$stmt) return false;
    
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $agreementId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    
        return $result;
    }
    
    
    /*
    |----------------------------------------------------------------------------|
    | Get Submission By Agreement Id                                             |
    | -------------------------------------------------------------------------- |
    | */                                                                         
    
    public function getSubmissionByAgreementId(
        int $agreementId
        ): ?array {
        
        $agreementId =
            (int)$agreementId;
        
        $stmt =
            mysqli_prepare(
        
                $this->con,
        
                "
                SELECT
        
                    s.*,
        
                    a.id AS agreementId,
        
                    a.leadId,
                    
                    a.agreementToken,
        
                    a.agreementStatus,
                    
                    a.agreementContent,
        
                    l.fullName,
        
                    l.email,
        
                    l.phone,
        
                    lc.finalPrice,
                    
                    a.signedAgreementFile,
                    
                    a.companySignatoryName,
                    
                    a.companySignatureFile
        
                FROM onboardingAgreementSubmissions s
        
                INNER JOIN onboardingAgreements a
                    ON a.id = s.agreementId
        
                INNER JOIN leads l
                    ON l.id = a.leadId
        
                LEFT JOIN leadConversions lc
                    ON lc.leadId = l.id
                    WHERE s.agreementId = ?
            
                ORDER BY s.id DESC
                
                LIMIT 1
                "
            );
        
        if (
            !$stmt
        ) {
        
            return null;
        }
        
        mysqli_stmt_bind_param(
        
            $stmt,
        
            'i',
        
            $agreementId
        );
        
        mysqli_stmt_execute(
            $stmt
        );
        
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
        
        $submission =
            $result
                ? mysqli_fetch_assoc(
                    $result
                )
                : null;
        
        mysqli_stmt_close(
            $stmt
        );
        
        return $submission;
        
        }
        
        
       /*
    |--------------------------------------------------------------------------
    | Save Company Signature
    |--------------------------------------------------------------------------
    */
    
    public function saveCompanySignature(
        int $agreementId,
        string $companySignatoryName,
        string $companySignatureFile
    ): bool {
    
        $stmt = mysqli_prepare(
            $this->con,
            "
            UPDATE onboardingAgreements
            SET
                companySignatoryName = ?,
                companySignatureFile = ?,
                updatedAt = NOW()
            WHERE id = ?
            "
        );
    
        if (!$stmt) {
            return false;
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $companySignatoryName,
            $companySignatureFile,
            $agreementId
        );
    
        $saved = mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        return $saved;
    } 
        
        
        
    /*
    |----------------------------------------------------------------------------|
    | Get Submission By Agreement Id                                             |
    | -------------------------------------------------------------------------- |
    | */     
        
        public function generateSignedAgreementPdf( int $agreementId ): ?string
            {
                $submission =
                    $this->getSubmissionByAgreementId($agreementId);
            
                if (!$submission) {
                    return null;
                }
            
                require_once __DIR__ . '/../vendor/autoload.php';
            
                $uploadDir =
                    __DIR__ . '/../uploads/onboarding/agreements/';
            
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
            
                $fileName =
                    'signed_agreement_' .
                    $agreementId .
                    '_' .
                    time() .
                    '.pdf';
            
                $filePath =
                    $uploadDir .
                    $fileName;
            
                $signaturePath =
                    __DIR__ .
                    '/../uploads/onboarding/signatures/' .
                    $submission['signatureFile'];
            
                $signatureSrc = '';
            
                if (file_exists($signaturePath)) {
                    $signatureSrc =
                        'data:image/png;base64,' .
                        base64_encode(file_get_contents($signaturePath));
                }
                
                $companySignaturePath =
                    __DIR__ .
                    '/../uploads/onboarding/company-signatures/' .
                    $submission['companySignatureFile'];
                
                $companySignatureSrc = '';
                
                if (
                    !empty($submission['companySignatureFile']) &&
                    file_exists($companySignaturePath)
                ) {
                
                    $companySignatureSrc =
                        'data:image/png;base64,' .
                        base64_encode(
                            file_get_contents($companySignaturePath)
                        );
                
                }
                
                
                
                
                
                
                
                
            
                $html = '
                <html>
                <head>
                    <style>
                        body {
                            font-family: DejaVu Sans, sans-serif;
                            font-size: 13px;
                            line-height: 1.6;
                            color: #222;
                        }
            
                        h2 {
                            text-align: center;
                            margin-bottom: 10px;
                        }
            
                        .section {
                            margin-top: 20px;
                        }
            
                        .box {
                            border: 1px solid #ddd;
                            padding: 12px;
                            margin-top: 10px;
                        }
            
                        .signature {
                            width: 220px;
                            border: 1px solid #ccc;
                            padding: 8px;
                            margin-top: 8px;
                        }
            
                        table {
                            width: 100%;
                            border-collapse: collapse;
                        }
            
                        td {
                            padding: 6px;
                            vertical-align: top;
                        }
                    </style>
                </head>
            
                <body>

                        <h2>Signed Client Onboarding Agreement</h2>
                    
                        <table>
                            <tr>
                                <td><strong>Agreement Ref:</strong></td>
                                <td>AGR-' . (int)$agreementId . '</td>
                            </tr>
                    
                            <tr>
                                <td><strong>Client Name:</strong></td>
                                <td>' . htmlspecialchars($submission['fullName']) . '</td>
                            </tr>
                    
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>' . htmlspecialchars($submission['email']) . '</td>
                            </tr>
                    
                            <tr>
                                <td><strong>Final Price:</strong></td>
                                <td>Rs. ' . number_format((float)$submission['finalPrice'], 2) . '</td>
                            </tr>
                    
                            <tr>
                                <td><strong>Submitted At:</strong></td>
                                <td>' . htmlspecialchars($submission['submittedAt']) . '</td>
                            </tr>
                        </table>
                    
                        <div class="section">
                            <h3>Agreement Content</h3>
                    
                            <div class="box">
                                ' . $submission['agreementContent'] . '
                            </div>
                        </div>
                    
                        <div class="section">
                    
                            <h3>Client Acceptance</h3>
                    
                            <p>
                                I,
                                <strong>' . htmlspecialchars($submission['signatoryName']) . '</strong>,
                                confirm that I have read, understood, and accepted this agreement.
                            </p>
                    
                            <p><strong>Digital Signature:</strong></p>';
                    
                    if ($signatureSrc) {
                    
                        $html .= '
                            <img src="' . $signatureSrc . '" class="signature">';
                    }
                    
                    $html .= '
                    
                            <p>
                                <strong>Signatory Name:</strong>
                                ' . htmlspecialchars($submission['signatoryName']) . '
                            </p>
                    
                            <p>
                                <strong>Accepted Date:</strong>
                                ' . htmlspecialchars($submission['submittedAt']) . '
                            </p>
                    
                        </div>
                    
                        <div class="section">
                    
                            <h3>Company Authorization</h3>
                    
                            <p>
                                This agreement has been reviewed and approved by
                                <strong>MQlus Business Solutions</strong>.
                            </p>';
                    
                    if ($companySignatureSrc) {
                    
                        $html .= '
                            <p><strong>Digital Signature:</strong></p>
                    
                            <img src="' . $companySignatureSrc . '" class="signature">';
                    }
                    
                    $html .= '
                    
                            <p>
                                <strong>Authorized Signatory:</strong>
                                ' . htmlspecialchars($submission['companySignatoryName']) . '
                            </p>
                    
                        </div>
                        <p>
                        <strong>Approved Date:</strong>
                            ' .  date('d M Y H:i'). '
                        </p>
                    
                    </body>
                    
                </html>
                ';
            
                $dompdf =
                    new \Dompdf\Dompdf();
            
                $dompdf->loadHtml($html);
            
                $dompdf->setPaper(
                    'A4',
                    'portrait'
                );
            
                $dompdf->render();
            
                file_put_contents(
                    $filePath,
                    $dompdf->output()
                );
            
                $stmt =
                    mysqli_prepare(
                        $this->con,
                        "
                        UPDATE onboardingAgreements
                        SET
                            signedAgreementFile = ?,
                            updatedAt = NOW()
                        WHERE id = ?
                        "
                    );
            
                if (!$stmt) {
                    return null;
                }
            
                mysqli_stmt_bind_param(
                    $stmt,
                    'si',
                    $fileName,
                    $agreementId
                );
            
                $saved =
                    mysqli_stmt_execute($stmt);
            
                mysqli_stmt_close($stmt);
            
                return $saved
                    ? $fileName
                    : null;
            }
            
            
            
            
            
    /*
    |--------------------------------------------------------------------------
    | Create Client Master
    |--------------------------------------------------------------------------
    */
    
    public function createClientMaster(int $agreementId): bool
    {
        // Load Agreement
        $submission = $this->getSubmissionByAgreementId($agreementId);
    
        if (!$submission) {
            return false;
        }
    
        // Prevent Duplicate Client
        $checkStmt = mysqli_prepare(
            $this->con,
            "
            SELECT id
            FROM clientMaster
            WHERE agreementId = ?
            LIMIT 1
            "
        );
    
        mysqli_stmt_bind_param(
            $checkStmt,
            'i',
            $agreementId
        );
    
        mysqli_stmt_execute($checkStmt);
    
        mysqli_stmt_store_result($checkStmt);
    
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
    
            mysqli_stmt_close($checkStmt);
    
            return true;
        }
    
        mysqli_stmt_close($checkStmt);
    
        // Get Conversion Id
        $conversionId = null;
    
        $conversionStmt = mysqli_prepare(
            $this->con,
            "
            SELECT id
            FROM leadConversions
            WHERE leadId = ?
            LIMIT 1
            "
        );
    
        mysqli_stmt_bind_param(
            $conversionStmt,
            'i',
            $submission['leadId']
        );
    
        mysqli_stmt_execute($conversionStmt);
    
        $conversionResult = mysqli_stmt_get_result($conversionStmt);
    
        if ($row = mysqli_fetch_assoc($conversionResult)) {
            $conversionId = (int)$row['id'];
        }
    
        mysqli_stmt_close($conversionStmt);
    
        // Generate Client Code
        $clientCode = str_pad((string)$agreementId,6,'0',STR_PAD_LEFT);
    
        // Insert Client Master
        $stmt = mysqli_prepare(
            $this->con,
            "
            INSERT INTO clientMaster
            (
                leadId,
                agreementId,
                conversionId,
                clientCode,
                onboardingStatus,
                signedAgreementFile,
                onboardedByUserId,
                onboardedAt
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, NOW()
            )
            "
        );
    
        if (!$stmt) {
            return false;
        }
    
        $status = 'completed';
    
        mysqli_stmt_bind_param(
            $stmt,
            'iiisssi',
            $submission['leadId'],
            $agreementId,
            $conversionId,
            $clientCode,
            $status,
            $submission['signedAgreementFile'],
            $_SESSION['userId']
        );
    
        $saved = mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        return $saved;
    }
    
  
    /*
    |--------------------------------------------------------------------------
    | Render Client Data table with new columns
    |--------------------------------------------------------------------------
    */
    
    public function getClientMasterList($status = '', $search = '')
    {
        $data = [];
        
        $sql = "
            SELECT
                cm.id,
                cm.clientCode,
                cm.onboardingStatus,
                cm.onboardedAt,
                cm.serviceDetails,
                cm.planDetails,
                
                l.id AS leadId,
                l.fullName,
                l.email,
                l.phone,
                l.orgName,
                
                oa.signedAgreementFile,
                lc.finalPrice,
                
                -- Get services from clientOnboardingForms
                (SELECT selectedServices 
                 FROM clientOnboardingForms 
                 WHERE clientMasterId = cm.id 
                 ORDER BY id DESC LIMIT 1) as selectedServices
                
            FROM clientMaster cm
            
            INNER JOIN leads l
                ON l.id = cm.leadId
    
            LEFT JOIN onboardingAgreements oa
                ON oa.id = cm.agreementId
    
            LEFT JOIN leadConversions lc
                ON lc.leadId = cm.leadId
                
            WHERE 1=1
        ";
        
        // Apply filters
        if (!empty($status)) {
            $sql .= " AND cm.onboardingStatus = '" . mysqli_real_escape_string($this->con, $status) . "'";
        }
        
        if (!empty($search)) {
            $search = mysqli_real_escape_string($this->con, $search);
            $sql .= " AND (l.fullName LIKE '%$search%' 
                           OR l.email LIKE '%$search%' 
                           OR l.phone LIKE '%$search%' 
                           OR cm.clientCode LIKE '%$search%')";
        }
        
        $sql .= " ORDER BY cm.id DESC";
        
        $result = mysqli_query($this->con, $sql);
        
        if (!$result) {
            return $data;
        }
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Decode services
            $row['selectedServices'] = !empty($row['selectedServices']) 
                ? json_decode($row['selectedServices'], true) 
                : [];
                
            $data[] = $row;
        }
        
        return $data;
    }
    
    
   /*
    |--------------------------------------------------------------------------
    | Get Complete Client Onboarding Details
    |--------------------------------------------------------------------------
    */
    
    public function getClientOnboardingDetails($clientId)
    {
        $clientId = (int)$clientId;
        
        // Get client master details
        $stmt = mysqli_prepare($this->con, "
            SELECT
                cm.*,
                l.fullName,
                l.email,
                l.phone,
                l.orgName,
                l.categoryId,
                l.planId,
                lc.finalPrice,
                oa.signedAgreementFile,
                oa.agreementStatus
            FROM clientMaster cm
            INNER JOIN leads l ON l.id = cm.leadId
            LEFT JOIN leadConversions lc ON lc.leadId = cm.leadId
            LEFT JOIN onboardingAgreements oa ON oa.id = cm.agreementId
            WHERE cm.id = ?
            LIMIT 1
        ");
        
        if (!$stmt) {
            return null;
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $client = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$client) {
            return null;
        }
        
        // Get onboarding form data
        $stmt = mysqli_prepare($this->con, "
            SELECT *
            FROM clientOnboardingForms
            WHERE clientMasterId = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $clientId);
            mysqli_stmt_execute($stmt);
            $formResult = mysqli_stmt_get_result($stmt);
            $formData = mysqli_fetch_assoc($formResult);
            mysqli_stmt_close($stmt);
            
            // Decode JSON fields
            if ($formData) {
                $formData['brandLogos'] = !empty($formData['brandLogos']) 
                    ? json_decode($formData['brandLogos'], true) 
                    : [];
                $formData['mediaLinks'] = !empty($formData['mediaLinks']) 
                    ? json_decode($formData['mediaLinks'], true) 
                    : [];
                $formData['competitors'] = !empty($formData['competitors']) 
                    ? json_decode($formData['competitors'], true) 
                    : [];
                $formData['selectedServices'] = !empty($formData['selectedServices']) 
                    ? json_decode($formData['selectedServices'], true) 
                    : [];
            }
            
            $client['onboardingForm'] = $formData;
        }
        
        // Get service credentials (if submitted by client)
        $stmt = mysqli_prepare($this->con, "
            SELECT *
            FROM clientServiceCredentials
            WHERE clientMasterId = ?
            ORDER BY id DESC
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $clientId);
            mysqli_stmt_execute($stmt);
            $credResult = mysqli_stmt_get_result($stmt);
            $credentials = [];
            
            while ($row = mysqli_fetch_assoc($credResult)) {
                // Decrypt sensitive data
                if (!empty($row['password'])) {
                    $row['password'] = $this->decryptData($row['password']);
                }
                if (!empty($row['fbPassword'])) {
                    $row['fbPassword'] = $this->decryptData($row['fbPassword']);
                }
                if (!empty($row['igPassword'])) {
                    $row['igPassword'] = $this->decryptData($row['igPassword']);
                }
                $credentials[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            $client['serviceCredentials'] = $credentials;
        }
        
        return $client;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Save Admin Onboarding Form
    |--------------------------------------------------------------------------
    */
    
  
    public function saveOnboardingForm($data, $userId)
    {
        $clientId = (int)$data['clientId'];
        
        // Validate required fields
        if (empty($data['selectedServices']) || !is_array($data['selectedServices'])) {
            return ['success' => false, 'message' => 'Please select at least one service'];
        }
        
        // Prepare JSON fields - only services
        $selectedServices = json_encode($data['selectedServices']);
        
        // Check if form exists
        $stmt = mysqli_prepare($this->con, "
            SELECT id FROM clientOnboardingForms 
            WHERE clientMasterId = ? 
            ORDER BY id DESC LIMIT 1
        ");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($existing) {
            // Update existing - only services
            $stmt = mysqli_prepare($this->con, "
                UPDATE clientOnboardingForms 
                SET 
                    selectedServices = ?,
                    updatedAt = NOW()
                WHERE clientMasterId = ?
            ");
            
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error'];
            }
            
            mysqli_stmt_bind_param($stmt, 'si', $selectedServices, $clientId);
        } else {
            // Insert new - only services
            $stmt = mysqli_prepare($this->con, "
                INSERT INTO clientOnboardingForms 
                (clientMasterId, leadId, selectedServices, formStatus)
                SELECT 
                    cm.id, cm.leadId, ?, 'draft'
                FROM clientMaster cm
                WHERE cm.id = ?
            ");
            
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error'];
            }
            
            mysqli_stmt_bind_param($stmt, 'si', $selectedServices, $clientId);
        }
        
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$saved) {
            return ['success' => false, 'message' => 'Failed to save form'];
        }
        
        return ['success' => true, 'message' => 'Services saved successfully'];
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Send Onboarding Form to Client
    |--------------------------------------------------------------------------
    */
    
    public function sendOnboardingFormToClient($formId, $userId)
    {
        error_log("=== sendOnboardingFormToClient called ===");
        error_log("Form ID: " . $formId);
        error_log("User ID: " . $userId);
        
        $formId = (int)$formId;
        
        // Get form and client details
        $stmt = mysqli_prepare($this->con, "
            SELECT 
                cof.*,
                cm.clientCode,
                cm.leadId,
                l.fullName,
                l.email,
                l.phone
            FROM clientOnboardingForms cof
            INNER JOIN clientMaster cm ON cm.id = cof.clientMasterId
            INNER JOIN leads l ON l.id = cm.leadId
            WHERE cof.id = ?
            LIMIT 1
        ");
        
        if (!$stmt) {
            error_log("sendOnboardingFormToClient: Database prepare failed");
            return ['success' => false, 'message' => 'Database error'];
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $formId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $form = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$form) {
            error_log("sendOnboardingFormToClient: Form not found for ID: " . $formId);
            return ['success' => false, 'message' => 'Form not found'];
        }
        
        error_log("Form data found: " . print_r($form, true));
        
        // ============================================================
        // CASE 1: Mail never sent (formStatus = 'draft')
        // ============================================================
        if ($form['formStatus'] === 'draft') {
            return $this->sendFullOnboardingForm($formId, $userId, $form);
        }
        
        // ============================================================
        // CASE 2 & 3: Mail already sent
        // ============================================================
        // Get existing selected services from the form
        $existingServices = !empty($form['selectedServices']) 
            ? json_decode($form['selectedServices'], true) 
            : [];
        
        // Get the current selected services from the form (most recent)
        $currentServices = $existingServices;
        
        // Compare with newly selected services (passed in request)
        // We need to check if new services were added
        
        // For this, we'll compare with the services stored in the form
        // The admin would have updated selectedServices before calling this
        // So we need to check if any new services were added
        
        // Since we're using the same form, let's check if there are any 
        // services that the client hasn't submitted credentials for yet
        
        // Get already submitted services
        $stmt = mysqli_prepare($this->con, "
            SELECT DISTINCT serviceType 
            FROM clientServiceCredentials 
            WHERE onboardingFormId = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $formId);
            mysqli_stmt_execute($stmt);
            $credResult = mysqli_stmt_get_result($stmt);
            $submittedServices = [];
            while ($row = mysqli_fetch_assoc($credResult)) {
                $submittedServices[] = $row['serviceType'];
            }
            mysqli_stmt_close($stmt);
        } else {
            $submittedServices = [];
        }
        
        // Get all selected services from the form
        $allSelectedServices = !empty($form['selectedServices']) 
            ? json_decode($form['selectedServices'], true) 
            : [];
        
        // Find NEW services (selected but not yet submitted)
        $newServices = array_diff($allSelectedServices, $submittedServices);
        
        error_log("Submitted services: " . print_r($submittedServices, true));
        error_log("All selected services: " . print_r($allSelectedServices, true));
        error_log("New services to send: " . print_r($newServices, true));
        
        // ============================================================
        // CASE 2: No new services added
        // ============================================================
        if (empty($newServices)) {
            error_log("sendOnboardingFormToClient: No new services to add");
            return [
                'success' => false, 
                'message' => 'No new services to add. All selected services have already been submitted by the client.'
            ];
        }
        
        // ============================================================
        // CASE 4: New service has NO fields (e.g., Videos)
        // ============================================================
        $servicesWithNoFields = ['videos'];
        $servicesNeedingForm = array_diff($newServices, $servicesWithNoFields);
        
        // ============================================================
        // Send mail based on what needs to be sent
        // ============================================================
        
        if (empty($servicesNeedingForm)) {
            // CASE 4: Only services with no fields (just send notification)
            return $this->sendServiceNotificationEmail($form, $newServices);
        }
        
        // CASE 3: Send form with ONLY new services
        return $this->sendPartialOnboardingForm($formId, $userId, $form, $servicesNeedingForm, $newServices);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Send Full Onboarding Form (First Time)
    |--------------------------------------------------------------------------
    */
    
    private function sendFullOnboardingForm($formId, $userId, $form)
    {
        error_log("sendFullOnboardingForm: Sending full form for the first time");
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        error_log("Generated token: " . $token);
        
        // Save token
        $stmt = mysqli_prepare($this->con, "
            INSERT INTO clientFormAccessTokens 
            (onboardingFormId, clientMasterId, token, expiresAt)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        
        if (!$stmt) {
            error_log("sendFullOnboardingForm: Token insert prepare failed");
            return ['success' => false, 'message' => 'Database error'];
        }
        
        mysqli_stmt_bind_param($stmt, 'iis', $formId, $form['clientMasterId'], $token);
        $tokenSaved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$tokenSaved) {
            error_log("sendFullOnboardingForm: Failed to save token");
            return ['success' => false, 'message' => 'Failed to generate access token'];
        }
        
        // Update form status
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientOnboardingForms 
            SET formStatus = 'sent', sentAt = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $formId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Send email
        $formLink = SITE_URL . '/client-onboarding-form?token=' . $token;
        error_log("Form link: " . $formLink);
        
        require_once __DIR__ . '/mailer.php';
        
        // Get all selected services
        $allServices = !empty($form['selectedServices']) 
            ? json_decode($form['selectedServices'], true) 
            : [];
        
        $emailSent = sendOnboardingFormEmail(
            $form['leadId'],
            $form['email'],
            $form['fullName'],
            $formLink,
            $form['clientCode'],
            $allServices,           // All services
            'full'                  // Full form
        );
        
        error_log("Email send result: " . ($emailSent ? 'TRUE' : 'FALSE'));
        
        if (!$emailSent) {
            error_log("Failed to send onboarding form email for form ID: " . $formId);
        }
        
        return [
            'success' => true, 
            'message' => 'Form sent to client successfully',
            'formLink' => $formLink,
            'emailSent' => $emailSent,
            'type' => 'full'
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Send Partial Onboarding Form (New Services Only)
    |--------------------------------------------------------------------------
    */
    
    private function sendPartialOnboardingForm($formId, $userId, $form, $servicesNeedingForm, $allNewServices)
    {
        error_log("sendPartialOnboardingForm: Sending form for new services only");
        error_log("Services needing form: " . print_r($servicesNeedingForm, true));
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        error_log("Generated token: " . $token);
        
        // Save token
        $stmt = mysqli_prepare($this->con, "
            INSERT INTO clientFormAccessTokens 
            (onboardingFormId, clientMasterId, token, expiresAt)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        
        if (!$stmt) {
            error_log("sendPartialOnboardingForm: Token insert prepare failed");
            return ['success' => false, 'message' => 'Database error'];
        }
        
        mysqli_stmt_bind_param($stmt, 'iis', $formId, $form['clientMasterId'], $token);
        $tokenSaved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$tokenSaved) {
            error_log("sendPartialOnboardingForm: Failed to save token");
            return ['success' => false, 'message' => 'Failed to generate access token'];
        }
        
        // Update form status (keep as sent)
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientOnboardingForms 
            SET formStatus = 'sent', sentAt = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $formId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Send email with only new services
        $formLink = SITE_URL . '/client-onboarding-form?token=' . $token;
        error_log("Form link: " . $formLink);
        
        require_once __DIR__ . '/mailer.php';
        
        $emailSent = sendOnboardingFormEmail(
            $form['leadId'],
            $form['email'],
            $form['fullName'],
            $formLink,
            $form['clientCode'],
            $servicesNeedingForm,   // Only new services
            'partial'               // Partial form
        );
        
        error_log("Email send result: " . ($emailSent ? 'TRUE' : 'FALSE'));
        
        if (!$emailSent) {
            error_log("Failed to send onboarding form email for form ID: " . $formId);
        }
        
        return [
            'success' => true, 
            'message' => 'Form for new services sent to client successfully',
            'formLink' => $formLink,
            'emailSent' => $emailSent,
            'type' => 'partial',
            'newServices' => $servicesNeedingForm
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | Send Service Notification Email (No Form Needed)
    |--------------------------------------------------------------------------
    */
    
    private function sendServiceNotificationEmail($form, $newServices)
    {
        error_log("sendServiceNotificationEmail: Sending notification for services with no fields");
        
        require_once __DIR__ . '/mailer.php';
        
        $emailSent = sendServiceAddedNotification(
            $form['leadId'],
            $form['email'],
            $form['fullName'],
            $form['clientCode'],
            $newServices
        );
        
        // Update form status
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientOnboardingForms 
            SET formStatus = 'sent', sentAt = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $form['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        return [
            'success' => true,
            'message' => 'New service notification sent to client',
            'emailSent' => $emailSent,
            'type' => 'notification',
            'newServices' => $newServices
        ];
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Get Onboarding Form by Token
    |--------------------------------------------------------------------------
    */
    
    public function getOnboardingFormByToken($token)
    {
        $stmt = mysqli_prepare($this->con, "
            SELECT 
                cof.*,
                cm.clientCode,
                l.fullName,
                l.email,
                l.phone
            FROM clientFormAccessTokens cft
            INNER JOIN clientOnboardingForms cof ON cof.id = cft.onboardingFormId
            INNER JOIN clientMaster cm ON cm.id = cof.clientMasterId
            INNER JOIN leads l ON l.id = cm.leadId
            WHERE cft.token = ?
            AND cft.expiresAt > NOW()
            AND cft.isUsed = 0
            LIMIT 1
        ");
        
        if (!$stmt) {
            return null;
        }
        
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$data) {
            return null;
        }
        
        // Decode JSON fields
        $data['selectedServices'] = !empty($data['selectedServices']) 
            ? json_decode($data['selectedServices'], true) 
            : [];
        $data['brandLogos'] = !empty($data['brandLogos']) 
            ? json_decode($data['brandLogos'], true) 
            : [];
        $data['mediaLinks'] = !empty($data['mediaLinks']) 
            ? json_decode($data['mediaLinks'], true) 
            : [];
        $data['competitors'] = !empty($data['competitors']) 
            ? json_decode($data['competitors'], true) 
            : [];
        
        return $data;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Mark Form as Viewed by Client
    |--------------------------------------------------------------------------
    */
    
    public function markFormAsViewed($formId)
    {
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientOnboardingForms 
            SET formStatus = 'clientViewed', clientViewedAt = NOW() 
            WHERE id = ? AND formStatus = 'sent'
        ");
        
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $formId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
   /*
    |--------------------------------------------------------------------------
    | Submit Client Form Data
    |--------------------------------------------------------------------------
    */
    
    public function submitClientFormData($data)
    {
        $formId = (int)$data['formId'];
        $token = $data['token'];
        
        // Verify token
        $stmt = mysqli_prepare($this->con, "
            SELECT id, onboardingFormId, clientMasterId 
            FROM clientFormAccessTokens 
            WHERE token = ? AND isUsed = 0 AND expiresAt > NOW()
            LIMIT 1
        ");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tokenData = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        // ============================================================
        // 1. UPDATE clientOnboardingForms (Common Fields)
        // ============================================================
        $officialEmail = $data['officialEmail'] ?? '';
        $contactPhone = $data['contactPhone'] ?? '';
        $mediaLinks = !empty($data['mediaLinks']) ? json_encode($data['mediaLinks']) : '[]';
        $competitors = !empty($data['competitors']) ? json_encode($data['competitors']) : '[]';
        
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientOnboardingForms 
            SET 
                officialEmail = ?,
                contactPhone = ?,
                mediaLinks = ?,
                competitors = ?
            WHERE id = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssi', 
                $officialEmail, 
                $contactPhone, 
                $mediaLinks, 
                $competitors, 
                $formId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // ============================================================
        // 2. INSERT INTO clientServiceCredentials (Service Credentials)
        // ============================================================
        foreach ($data['services'] as $service) {
            $serviceType = $service['serviceType'];
            $platform = $service['platform'];
            
            // ✅ Fix: Convert youtubeScreenshot to string if it's an array/object
            $youtubeScreenshot = '';
            if (!empty($service['youtubeScreenshot'])) {
                if (is_array($service['youtubeScreenshot']) || is_object($service['youtubeScreenshot'])) {
                    $youtubeScreenshot = '';
                } else {
                    $youtubeScreenshot = (string)$service['youtubeScreenshot'];
                }
            }
            
            // Encrypt passwords
            $password = !empty($service['password']) ? $this->encryptData($service['password']) : '';
            $fbPassword = !empty($service['fbPassword']) ? $this->encryptData($service['fbPassword']) : '';
            $igPassword = !empty($service['igPassword']) ? $this->encryptData($service['igPassword']) : '';
            
            // ✅ Store all values in variables first (no expressions in bind_param)
            $onboardingFormId = $formId;
            $clientMasterId = $tokenData['clientMasterId'];
            $username = $service['username'] ?? '';
            $youtubeGrantAccess = isset($service['youtubeGrantAccess']) && $service['youtubeGrantAccess'] ? 1 : 0;
            $fbUsername = $service['fbUsername'] ?? '';
            $igUsername = $service['igUsername'] ?? '';
            
            $stmt = mysqli_prepare($this->con, "
                INSERT INTO clientServiceCredentials 
                (onboardingFormId, clientMasterId, serviceType, platform, 
                 username, password, youtubeGrantAccess, youtubeScreenshot,
                 fbUsername, fbPassword, igUsername, igPassword)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                continue;
            }
            
            // ✅ All parameters are now variables, not expressions
            mysqli_stmt_bind_param(
                $stmt,
                'iisssssissss',
                $onboardingFormId,      // 1. onboardingFormId
                $clientMasterId,        // 2. clientMasterId
                $serviceType,           // 3. serviceType
                $platform,              // 4. platform
                $username,              // 5. username
                $password,              // 6. password
                $youtubeGrantAccess,    // 7. youtubeGrantAccess ✅ FIXED
                $youtubeScreenshot,     // 8. youtubeScreenshot ✅ FIXED
                $fbUsername,            // 9. fbUsername
                $fbPassword,            // 10. fbPassword
                $igUsername,            // 11. igUsername
                $igPassword             // 12. igPassword
            );
            
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // ============================================================
        // 3. UPDATE clientOnboardingForms - Status
        // ============================================================
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientOnboardingForms 
            SET formStatus = 'clientSubmitted', clientSubmittedAt = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $formId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // ============================================================
        // 4. UPDATE clientFormAccessTokens - Mark as Used
        // ============================================================
        $stmt = mysqli_prepare($this->con, "
            UPDATE clientFormAccessTokens 
            SET isUsed = 1 
            WHERE token = ?
        ");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $token);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        return [
            'success' => true,
            'message' => 'Form submitted successfully'
        ];
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Upload Client Files
    |--------------------------------------------------------------------------
    */
    
    public function uploadClientFiles($formId, $token, $files)
    {
        $formId = (int)$formId;
        
        // ✅ Allow token that is already used (for file upload after submission)
        $stmt = mysqli_prepare($this->con, "
            SELECT id, onboardingFormId, clientMasterId 
            FROM clientFormAccessTokens 
            WHERE token = ? AND expiresAt > NOW()
            LIMIT 1
        ");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tokenData = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        // ============================================================
        // 1. Handle Brand Logo Uploads
        // ============================================================
        $brandLogos = [];
        if (isset($files['brandLogo']) && !empty($files['brandLogo']['name'][0])) {
            for ($i = 0; $i < count($files['brandLogo']['name']); $i++) {
                $file = [
                    'name' => $files['brandLogo']['name'][$i],
                    'type' => $files['brandLogo']['type'][$i],
                    'tmp_name' => $files['brandLogo']['tmp_name'][$i],
                    'error' => $files['brandLogo']['error'][$i],
                    'size' => $files['brandLogo']['size'][$i]
                ];
                $uploadedFile = $this->uploadFile($file, 'brand-logos');
                if ($uploadedFile) {
                    $brandLogos[] = $uploadedFile;
                }
            }
        }
        
        // ============================================================
        // 2. Handle YouTube Screenshots
        // ============================================================
        $youtubeScreenshots = [];
        foreach ($files as $key => $fileData) {
            if (strpos($key, 'youtubeScreenshot') !== false && !empty($fileData['name'])) {
                if ($fileData['error'] === UPLOAD_ERR_OK) {
                    $uploadedFile = $this->uploadFile($fileData, 'youtube-screenshots');
                    if ($uploadedFile) {
                        $service = str_replace('_youtubeScreenshot', '', $key);
                        $youtubeScreenshots[$service] = $uploadedFile;
                    }
                }
            }
        }
        
        // ============================================================
        // 3. Update Brand Logos in clientOnboardingForms
        // ============================================================
        if (!empty($brandLogos)) {
            // Get existing logos
            $stmt = mysqli_prepare($this->con, "
                SELECT brandLogos FROM clientOnboardingForms WHERE id = ?
            ");
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $formId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                $existingLogos = [];
                if ($row && !empty($row['brandLogos'])) {
                    $existingLogos = json_decode($row['brandLogos'], true) ?: [];
                }
                
                // Merge with new logos
                $allLogos = array_merge($existingLogos, $brandLogos);
                $brandLogosJson = json_encode($allLogos);
                
                // Update database
                $stmt = mysqli_prepare($this->con, "
                    UPDATE clientOnboardingForms 
                    SET brandLogos = ? 
                    WHERE id = ?
                ");
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'si', $brandLogosJson, $formId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        // ============================================================
        // 4. Update YouTube Screenshots in clientServiceCredentials
        // ============================================================
        foreach ($youtubeScreenshots as $service => $filename) {
            $stmt = mysqli_prepare($this->con, "
                UPDATE clientServiceCredentials 
                SET youtubeScreenshot = ? 
                WHERE onboardingFormId = ? AND serviceType = ?
                ORDER BY id DESC LIMIT 1
            ");
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sis', $filename, $formId, $service);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Files uploaded successfully',
            'uploaded' => [
                'brandLogos' => $brandLogos,
                'youtubeScreenshots' => $youtubeScreenshots
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Upload File Helper
    |--------------------------------------------------------------------------
    */
    
    private function uploadFile($file, $targetDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return null;
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return null;
        }
        
        // Create directory if not exists
        $uploadPath = __DIR__ . '/../uploads/onboarding/' . $targetDir . '/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $targetFile = $uploadPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return $filename;
        }
        
        return null;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Encryption Helper Methods
    |--------------------------------------------------------------------------
    */
    
    private function encryptData($data)
    {
        if (empty($data)) return '';
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key_123';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    private function decryptData($data)
    {
        if (empty($data)) return '';
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key_123';
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
    
    
    
}