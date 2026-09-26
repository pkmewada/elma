<?php

require_once __DIR__ . '/../../includes/auth-functions.php';
require_once __DIR__ . '/../models/CandidateModel.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/config.php';

class CandidateProfileController
{
    private $candidateModel;

    public function __construct()
    {
        $this->candidateModel = new CandidateModel();
    }

    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        /*
        |--------------------------------------------------------------------------
        | Session Validation
        |--------------------------------------------------------------------------
        */

        if (empty($_SESSION['candidateId'])) {

            redirectTo('candidate-login');
        }

        if (!empty($_SESSION['candidateForceReset'])) {

            redirectTo('candidate-reset-password');
        }

        $candidateId = (int) $_SESSION['candidateId'];
        
        $candidate =
            $this->candidateModel
            ->getCandidateById($candidateId);
            
        $verificationRemarks =
            $this->candidateModel
            ->getRejectedVerificationRemarks(
                $candidateId
            ); 
            
        
        if (!$candidate) {
        
            redirectTo('candidate-login');
        }

        $error = '';

        $success = '';

        /*
        |--------------------------------------------------------------------------
        | Handle Form Submit
        |--------------------------------------------------------------------------
        */

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $errors = [];

            $validData = [];

            /*
            |--------------------------------------------------------------------------
            | Collect + Sanitize Inputs
            |--------------------------------------------------------------------------
            */

            $data = [

                'mobileNumber' =>
                    $this->digitsOnly(getPost('mobileNumber')),

                'alternativeNumber' =>
                    $this->digitsOnly(getPost('alternativeNumber')),

                'emergencyContactNumber' =>
                    $this->digitsOnly(getPost('emergencyContactNumber')),

                'dateOfBirth' =>
                    trim((string) getPost('dateOfBirth')),

                'gender' =>
                    $this->titleCase(getPost('gender')),

                'maritalStatus' =>
                    $this->titleCase(getPost('maritalStatus')),

                'linkedInProfile' =>
                    trim((string) getPost('linkedInProfile')),

                'instagramProfile' =>
                    trim((string) getPost('instagramProfile')),

                'permanentAddress' =>
                    $this->titleCase(getPost('permanentAddress')),

                'localAddress' =>
                    $this->titleCase(getPost('localAddress')),

                'cityName' =>
                    $this->titleCase(getPost('cityName')),

                'stateName' =>
                    $this->titleCase(getPost('stateName')),

                'pinCode' =>
                    $this->digitsOnly(getPost('pinCode')),

                'accountHolderName' =>
                    $this->titleCase(getPost('accountHolderName')),

                'bankName' =>
                    $this->titleCase(getPost('bankName')),

                'accountNumber' =>
                    $this->digitsOnly(getPost('accountNumber')),

                'ifscCode' =>
                    strtoupper(trim((string) getPost('ifscCode'))),

                'branchName' =>
                    $this->titleCase(getPost('branchName')),

                'aadhaarNumber' =>
                    $this->digitsOnly(getPost('aadhaarNumber')),

                'panNumber' =>
                    strtoupper(trim((string) getPost('panNumber')))
            ];

            /*
            |--------------------------------------------------------------------------
            | Mobile Number
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^[6-9]\d{9}$/',
                    $data['mobileNumber']
                )
            ) {

                $validData['mobileNumber'] =
                    $data['mobileNumber'];

            } else {

                $errors[] = 'Invalid mobile number.';
            }

            /*
            |--------------------------------------------------------------------------
            | Alternative Number
            |--------------------------------------------------------------------------
            */

            if (
                empty($data['alternativeNumber']) ||
                preg_match(
                    '/^[6-9]\d{9}$/',
                    $data['alternativeNumber']
                )
            ) {

                $validData['alternativeNumber'] =
                    $data['alternativeNumber'];

            } else {

                $errors[] = 'Invalid alternative number.';
            }

            /*
            |--------------------------------------------------------------------------
            | Emergency Contact Number
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^[6-9]\d{9}$/',
                    $data['emergencyContactNumber']
                )
            ) {

                $validData['emergencyContactNumber'] =
                    $data['emergencyContactNumber'];

            } else {

                $errors[] =
                    'Invalid emergency contact number.';
            }

            /*
            |--------------------------------------------------------------------------
            | PIN Code
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^\d{6}$/',
                    $data['pinCode']
                )
            ) {

                $validData['pinCode'] =
                    $data['pinCode'];

            } else {

                $errors[] = 'Invalid PIN code.';
            }

            /*
            |--------------------------------------------------------------------------
            | Aadhaar Number
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^\d{12}$/',
                    $data['aadhaarNumber']
                )
            ) {

                $validData['aadhaarNumber'] =
                    $data['aadhaarNumber'];

            } else {

                $errors[] = 'Invalid Aadhaar number.';
            }

            /*
            |--------------------------------------------------------------------------
            | PAN Number
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
                    $data['panNumber']
                )
            ) {

                $validData['panNumber'] =
                    $data['panNumber'];

            } else {

                $errors[] = 'Invalid PAN number.';
            }

            /*
            |--------------------------------------------------------------------------
            | IFSC Code
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^[A-Z]{4}0[A-Z0-9]{6}$/',
                    $data['ifscCode']
                )
            ) {

                $validData['ifscCode'] =
                    $data['ifscCode'];

            } else {

                $errors[] = 'Invalid IFSC code.';
            }

            /*
            |--------------------------------------------------------------------------
            | Safe Fields
            |--------------------------------------------------------------------------
            */

            $safeFields = [

                'dateOfBirth',

                'gender',
                'maritalStatus',

                'linkedInProfile',
                'instagramProfile',

                'permanentAddress',
                'localAddress',

                'cityName',
                'stateName',

                'accountHolderName',
                'bankName',
                'accountNumber',
                'branchName'
            ];

            foreach ($safeFields as $field) {

                $validData[$field] = $data[$field];
            }

            /*
            |--------------------------------------------------------------------------
            | Upload Files
            |--------------------------------------------------------------------------
            */

            $employeeName =
                $_SESSION['candidateName'] ?? 'candidate';

            $folderName =
                $this->camelFolderName($employeeName) .
                '_' .
                $candidateId;

            $uploadDir =
                __DIR__ .
                '/../../uploads/candidates/' .
                $folderName .
                '/';

            if (!is_dir($uploadDir)) {

                if (
                    !mkdir($uploadDir, 0777, true) &&
                    !is_dir($uploadDir)
                ) {

                    $errors[] =
                        'Unable to create upload directory.';
                }
            }

            if (is_dir($uploadDir)) {

                $fileFields = [
            
                    'profilePhoto',
                    'aadhaarFile',
                    'panFile',
            
                    'marksheet10File',
                    'marksheet12File',
            
                    'graduationFile',
            
                    'bankPassbookFile'
                ];
            
                /*
                |--------------------------------------------------------------------------
                | Track Only Reuploaded Documents
                |--------------------------------------------------------------------------
                */
                $updatedFields = [];
            
                foreach ($fileFields as $field) {
            
                    $uploaded = $this->uploadFile(
                        $field,
                        $candidateId,
                        $uploadDir
                    );
            
                    if ($uploaded === false) {
            
                        $errors[] =
                            'Invalid file uploaded for ' .
                            $field;
            
                        continue;
                    }
            
                    /*
                    |--------------------------------------------------------------------------
                    | New File Uploaded
                    |--------------------------------------------------------------------------
                    */
                    if ($uploaded !== '') {
            
                        $validData[$field] = $uploaded;
            
                        $updatedFields[] = $field;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Save Profile
            |--------------------------------------------------------------------------
            */

            if (!empty($validData)) {

                try {

                    $saved =
                        $this->candidateModel
                        ->updateCandidateProfile(
                            $candidateId,
                            $validData
                        );

                    if ($saved) {
                        
                           $this->candidateModel
                                ->resetVerificationForFields(
                                    $candidateId,
                                    array_keys($validData)
                                );
                        
                            $candidate =
                                $this->candidateModel
                                ->getCandidateById($candidateId);

                        /*
                        |--------------------------------------------------------------------------
                        | Send Email
                        |--------------------------------------------------------------------------
                        */

                        if (
                            empty($errors) &&
                            !empty($_SESSION['candidateEmail']) &&
                            function_exists(
                                'sendCandidateProfileReceivedEmail'
                            )
                        ) {

                            try {

                                sendCandidateProfileReceivedEmail(
                                    $_SESSION['candidateEmail'],
                                    $_SESSION['candidateName']
                                );

                            } catch (\Throwable $e) {

                                error_log(
                                    'Candidate mail error: ' .
                                    $e->getMessage()
                                );
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Final Response
                        |--------------------------------------------------------------------------
                        */

                        if (empty($errors)) {

                            redirectTo('candidate-waiting');
                        }

                        $success =
                            'Profile partially saved successfully.';

                        $error =
                            implode('<br>', $errors);

                    } else {

                        $error =
                            'Unable to save profile. Please try again.';
                    }

                } catch (\Throwable $e) {

                    error_log(
                        'Candidate profile save error: ' .
                        $e->getMessage()
                    );

                    $error =
                        'Unexpected server error occurred.';
                }

            } else {

                $error =
                    implode('<br>', $errors);
            }
        }

        include __DIR__ . '/../views/candidate-profile.php';
    }
    
    
   

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function digitsOnly($value): string
    {
        return preg_replace(
            '/\D/',
            '',
            (string) $value
        );
    }

    private function titleCase($value): string
    {
        return ucwords(
            strtolower(
                trim((string) $value)
            )
        );
    }

    private function camelFolderName($name): string
    {
        $name =
            preg_replace(
                '/[^a-zA-Z0-9 ]/',
                '',
                (string) $name
            );

        $name =
            ucwords(
                strtolower(
                    trim($name)
                )
            );

        $name =
            str_replace(' ', '', $name);

        return lcfirst($name);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload File
    |--------------------------------------------------------------------------
    */

    private function uploadFile(
        string $field,
        int $candidateId,
        string $uploadPath
    ) {

        if (
            !isset($_FILES[$field]) ||
            empty($_FILES[$field]['name'])
        ) {

            return '';
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {

            return false;
        }

        $allowedExt = [

            'jpg',
            'jpeg',
            'png',
            'pdf'
        ];

        $fileName =
            (string) $_FILES[$field]['name'];

        $tmpName =
            (string) $_FILES[$field]['tmp_name'];

        $fileSize =
            (int) $_FILES[$field]['size'];

        $extension =
            strtolower(
                pathinfo(
                    $fileName,
                    PATHINFO_EXTENSION
                )
            );

        if (
            !in_array(
                $extension,
                $allowedExt,
                true
            )
        ) {

            return false;
        }
        
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeType = finfo_file($finfo, $tmpName);
        
        finfo_close($finfo);
        
        $allowedMimeTypes = [
        
            'image/jpeg',
            'image/png',
            'application/pdf'
        ];
        
        if (
            !in_array(
                $mimeType,
                $allowedMimeTypes,
                true
            )
        ) {
        
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Max 5MB
        |--------------------------------------------------------------------------
        */

        if ($fileSize > (5 * 1024 * 1024)) {

            return false;
        }

        $newFileName =
            $field .
            '_' .
            $candidateId .
            '_' .
            time() .
            '_' .
            mt_rand(1000, 9999) .
            '.' .
            $extension;

        $destination =
            rtrim($uploadPath, '/') .
            '/' .
            $newFileName;

        if (
            !move_uploaded_file(
                $tmpName,
                $destination
            )
        ) {

            return false;
        }

        return $newFileName;
    }
}
