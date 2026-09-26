
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title> Modlus - Admin Setup </title>
    <meta name="Description" content="Bootstrap Responsive Admin Web Dashboard HTML5 Template">
    <meta name="Author" content="Modlus Technologies Private Limited">
<meta name="keywords" content="dashboard template,dashboard html,bootstrap admin,dashboard admin,admin template,sales dashboard,crypto dashboard,projects dashboard,html template,html,html css,admin dashboard template,html css bootstrap,dashboard html css,pos system,bootstrap dashboard">
    <!-- Favicon -->
    <link rel="icon" href="https://modlus.in/dist/assets/images/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script>window.AUTH_ASSET_BASE = "https://modlus.in/dist/assets/";</script>
    <script src="https://modlus.in/dist/assets/js/authentication-main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap Css -->
    <link id="style" href="https://modlus.in/dist/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet" >

    <!-- Style Css -->
    <link href="https://modlus.in/dist/assets/css/styles.css" rel="stylesheet" >

    <!-- Icons Css -->
    <link href="https://modlus.in/dist/assets/css/icons.css" rel="stylesheet" >

   
</head>

<body class="authentication-background">
    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
            <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                <div class="my-5 d-flex justify-content-center"> 
                    <a href="index.html"> 
                        <img src="https://modlus.in/uploads/company/company-logo-20260624135537-4a9edcda.webp" alt="logo" class="desktop-light"> 
                    </a> 
                </div>
                <div class="card custom-card my-4">
                    <div class="card-body p-5">
                        <p class="h4 mb-2 fw-semibold">Hello, </p>
                        <p class="mb-4 text-muted fw-normal">Welcome to Modlus!</p>
                            <form id="addCandidateForm" enctype="multipart/form-data" novalidate="">
                                <div style="display:none;">
                                    <input type="hidden" id="candidateId" name="id" value="">
                                    <input type="text" name="website" autocomplete="off">
                                </div>
                                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                    <div class="row g-3">
                                        <!-- Basic Details -->
                                        <div class="col-12 col-md-6">
                                            <label for="modal-fullName" class="form-label text-default">Full Name</label>
                                            <input type="text" class="form-control" id="modal-fullName" name="fullName" placeholder="Enter Full Name" required="">
                                            <div class="invalid-feedback">Full name is required.</div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="modal-email" class="form-label text-default">Email Address</label>
                                            <input type="email" class="form-control" id="modal-email" name="email" placeholder="Enter Email Address" required="">
                                            <div class="invalid-feedback">A valid email is required.</div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="modal-phoneNumber" class="form-label text-default">Phone Number</label>
                                            <div class="input-group">
                                                <span class="input-group-text">+91</span>
                                                <input type="text" class="form-control" id="modal-phoneNumber" name="phoneNumber" placeholder="Enter Phone Number" required="" pattern="[0-9]{10}">
                                            </div>
                                            <div class="invalid-feedback">A valid 10-digit phone number is required.</div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="modal-currentLocation" class="form-label text-default">Current
                                                Location</label>
                                            <input type="text" class="form-control" id="modal-currentLocation" name="currentLocation" placeholder="Enter Current Location" required="">
                                            <div class="invalid-feedback">Current location is required.</div>
                                        </div>
        
                                        <!-- Application Details -->
                                        <div class="col-12 col-md-6">
                                            <label for="modal-appliedRole" class="form-label text-default">
                                                Role Applied For
                                            </label>
                                            
                                            <input type="text" class="form-control" id="modal-appliedRole" name="appliedRole" 
                                                   value="<?php echo isset($_GET['position']) ? htmlspecialchars($_GET['position'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                                   readonly required>
                                            
                                            <div class="invalid-feedback">
                                                Role applied for is required.
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 col-md-6 d-none" id="portfolioUrlWrapper">
                                            <label for="modal-portfolioUrl" class="form-label text-default">
                                                Portfolio URL
                                                <span class="text-danger">*</span>
                                            </label>
                                        
                                            <input
                                                type="url"
                                                class="form-control"
                                                id="modal-portfolioUrl"
                                                name="portfolioUrl"
                                                placeholder="https://behance.net/username or Drive Link">
                                        
                                            <div class="form-text">
                                                Behance, Dribbble, Adobe Portfolio, YouTube, Vimeo, Google Drive, Dropbox etc.
                                            </div>
                                        
                                            <div class="invalid-feedback">
                                                Please enter a valid portfolio URL.
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="modal-experienceYears" class="form-label text-default">Total Experience
                                                (Years)</label>
                                            <select class="form-select" id="modal-experienceYears" name="experienceYears" required="">
                                                <option value="">Select Experience</option>
                                                <option value="0">Fresher</option>
                                                <option value="1">1 Year</option>
                                                <option value="2">2 Years</option>
                                                <option value="3">3 Years</option>
                                                <option value="4">4 Years</option>
                                                <option value="5">5 Years</option>
                                                <option value="6">6 Years</option>
                                                <option value="7">7 Years</option>
                                                <option value="8">8 Years</option>
                                                <option value="9">9 Years</option>
                                                <option value="10">10+ Years</option>
                                            </select>
                                            <div class="invalid-feedback">Experience is required.</div>
                                        </div>
        
                                        <!-- Compensation -->
                                        <div class="col-12 col-md-6">
                                            <label for="modal-expectedSalary" class="form-label text-default">Expected Salary
                                                (CTC)</label>
                                            <input type="text" class="form-control" id="modal-expectedSalary" name="expectedSalary" placeholder="e.g. 4,50,000 - 6,00,000" required="">
                                            <div class="invalid-feedback">Expected salary is required.</div>
                                        </div>
        
                                        <!-- Documents -->
                                        <div class="col-12 col-md-6">
                                            <label for="modal-resumeFile" class="form-label text-default">Resume Upload</label>
                                            <input type="file" class="form-control" id="modal-resumeFile" name="resumeFile" accept=".pdf,.doc,.docx" required="">
                                            <div class="form-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                            <div class="invalid-feedback">Resume upload is required.</div>
                                        </div>
                                        <!-- Additional Info -->
                                        <div class="col-12">
                                            <label for="modal-internalNotes" class="form-label text-default">Notes /
                                                Remarks</label>
                                            <textarea class="form-control" id="modal-internalNotes" name="internalNotes" rows="3" placeholder="Enter any additional notes or remarks"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="addCandidateSubmitBtn">
                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="addCandidateSubmitSpinner" role="status" aria-hidden="true"></span>
                                        <span id="addCandidateSubmitText">Save Candidate</span>
                                    </button>
                                </div>
                            </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://modlus.in/dist/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Page JS -->
   <script src="https://modlus.in/dist/assets/js/public-candidate.js"></script>

</body>

</html>

