<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';

$employeeEngine = new EmployeeInfoEngine($con);

$currentEmployee = $employeeEngine->getCurrentEmployee(); /*
|-------------------------------------------------------------------------- | 
Profile Photo
|-------------------------------------------------------------------------- */ 

$profilePhotoUrl = $employeeEngine->getProfilePhotoUrl( $currentEmployee );

/*
|-------------------------------------------------------------------------- | 
Fallback
|-------------------------------------------------------------------------- */ 

if (empty($profilePhotoUrl)) {
    
$profilePhotoUrl = ASSET_URL . '/assets/images/faces/team/7.png'; } ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- PAGE HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Employee Profile</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard"> Dashboard </a>
                    </li>

                    <li class="breadcrumb-item active">Employee Profile</li>
                </ol>
            </div>
        </div>

        <!-- PROFILE CARD -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body p-0">
                        <form id="employeeProfileForm" enctype="multipart/form-data">
                            <ul class="list-group list-group-flush">
                                <!-- PROFILE HEADER -->
                                <li class="list-group-item p-4">
                                    <div class="d-flex align-items-center gap-4 flex-wrap">
                                        <span class="avatar avatar-xxl avatar-rounded">
                                            <img
                                                src="<?= htmlspecialchars($profilePhotoUrl); ?>"
                                                alt="<?= htmlspecialchars($currentEmployee['fullName'] ?? 'Employee'); ?>"
                                            />
                                        </span>

                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                <?= htmlspecialchars($currentEmployee['fullName'] ?? '-'); ?>
                                            </h5>

                                            <p class="text-muted mb-0">
                                                <?= htmlspecialchars($currentEmployee['designationName'] ?? '-'); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label fw-medium mb-1"> Update Profile Photo </label>

                                        <input
                                            type="file"
                                            class="form-control"
                                            name="profilePhoto"
                                            id="profilePhotoInput"
                                            accept="image/jpeg,image/png,image/webp"
                                        />

                                        <small class="text-muted"> Allowed: JPG, PNG, WEBP. Max size: 2MB. </small>
                                    </div>
                                </li>

                                <!-- PERSONAL INFO -->
                                <li class="list-group-item p-4">
                                    <span class="fw-medium fs-15 d-block mb-3"> PERSONAL INFO : </span>

                                    <div class="row gy-4 align-items-center">
                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Full Name : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                value="<?= htmlspecialchars($currentEmployee['fullName'] ?? ''); ?>"
                                                readonly
                                            />
                                        </div>

                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Employee Code : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                value="<?= htmlspecialchars($currentEmployee['employeeCode'] ?? ''); ?>"
                                                readonly
                                            />
                                        </div>

                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Designation : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                value="<?= htmlspecialchars($currentEmployee['designationName'] ?? ''); ?>"
                                                readonly
                                            />
                                        </div>

                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Department : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                value="<?= htmlspecialchars($currentEmployee['departmentName'] ?? ''); ?>"
                                                readonly
                                            />
                                        </div>
                                    </div>
                                </li>

                                <!-- CONTACT INFO -->
                                <li class="list-group-item p-4">
                                    <span class="fw-medium fs-15 d-block mb-3"> CONTACT INFO : </span>

                                    <div class="row gy-4 align-items-center">
                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Email : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="email"
                                                class="form-control"
                                                value="<?= htmlspecialchars($currentEmployee['emailAddress'] ?? ''); ?>"
                                                readonly
                                            />
                                        </div>

                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Primary Mobile : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                value="<?= htmlspecialchars($currentEmployee['mobileNumber'] ?? ''); ?>"
                                                readonly
                                            />
                                        </div>

                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Alternative Number : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="alternativeNumber"
                                                value="<?= htmlspecialchars($currentEmployee['alternativeNumber'] ?? ''); ?>"
                                            />
                                        </div>

                                        <div class="col-xl-3">
                                            <span class="fw-medium"> Emergency Contact : </span>
                                        </div>

                                        <div class="col-xl-9">
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="emergencyContactNumber"
                                                value="<?= htmlspecialchars($currentEmployee['emergencyContactNumber'] ?? ''); ?>"
                                            />
                                        </div>
                                    </div>
                                </li>

                                <!-- ABOUT -->
                                <li class="list-group-item p-4">
                                    <span class="fw-medium fs-15 d-block mb-3"> ABOUT : </span>

                                    <textarea class="form-control" name="aboutMe" rows="5">
                                    <?= htmlspecialchars($currentEmployee['aboutMe'] ?? ''); ?></textarea
                                    >
                                </li>

                                <!-- SKILLS -->
                                <li class="list-group-item p-4">
                                    <span class="fw-medium fs-15 d-block mb-3"> SKILLS : </span>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="skills"
                                        value="<?= htmlspecialchars($currentEmployee['skills'] ?? ''); ?>"
                                    />
                                </li>
                            </ul>

                            <div class="p-4 border-top">
                                <button type="button" class="btn btn-primary" id="saveEmployeeProfileBtn">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function () {

    const API = {
        updateProfile: API_BASE + "/employee/updateEmployeeProfile.php"
    };

    $("#saveEmployeeProfileBtn").on("click", function () {

        let btn = $(this);

        let alternativeNumber =
            $('input[name="alternativeNumber"]').val().trim();

        let emergencyContactNumber =
            $('input[name="emergencyContactNumber"]').val().trim();

        let aboutMe =
            $('textarea[name="aboutMe"]').val().trim();

        let skills =
            $('input[name="skills"]').val().trim();

        let profilePhotoInput =
            $("#profilePhotoInput")[0];

        let profilePhoto =
            profilePhotoInput && profilePhotoInput.files.length
                ? profilePhotoInput.files[0]
                : null;

        if (
            alternativeNumber &&
            !/^[0-9]{10}$/.test(alternativeNumber)
        ) {
            return showToast(
                "warning",
                "Alternative number must be 10 digits"
            );
        }

        if (
            emergencyContactNumber &&
            !/^[0-9]{10}$/.test(emergencyContactNumber)
        ) {
            return showToast(
                "warning",
                "Emergency contact number must be 10 digits"
            );
        }

        if (aboutMe.length > 3000) {
            return showToast(
                "warning",
                "About me content is too long"
            );
        }

        if (skills.length > 1000) {
            return showToast(
                "warning",
                "Skills content is too long"
            );
        }

        if (profilePhoto) {

            let allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!allowedTypes.includes(profilePhoto.type)) {
                return showToast(
                    "warning",
                    "Only JPG, PNG and WEBP images are allowed"
                );
            }

            if (profilePhoto.size > 2 * 1024 * 1024) {
                return showToast(
                    "warning",
                    "Profile photo size must be less than 2MB"
                );
            }
        }

        let formData = new FormData();

        formData.append(
            "alternativeNumber",
            alternativeNumber
        );

        formData.append(
            "emergencyContactNumber",
            emergencyContactNumber
        );

        formData.append(
            "aboutMe",
            aboutMe
        );

        formData.append(
            "skills",
            skills
        );

        if (profilePhoto) {
            formData.append(
                "profilePhoto",
                profilePhoto
            );
        }

        btn.prop("disabled", true);
        btn.text("Saving...");

        $.ajax({
            url: API.updateProfile,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",

            success: function (res) {

                showToast(
                    res.success ? "success" : "danger",
                    res.message
                );

                if (res.success) {
                    setTimeout(function () {
                        location.reload();
                    }, 700);
                }
            },

            error: function () {
                showToast(
                    "danger",
                    "Server error occurred"
                );
            },

            complete: function () {
                btn.prop("disabled", false);
                btn.text("Save Changes");
            }
        });

    });

});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
