<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';


$employeeEngine = new EmployeeInfoEngine($con);
$currentEmployee = $employeeEngine->getCurrentEmployee();

// =============================
// CALCULATE AGE
// =============================
$age = '-';

if (!empty($currentEmployee['dateOfBirth'])) {

    $dob = new DateTime($currentEmployee['dateOfBirth']);

    $today = new DateTime();

    $age = $today->diff($dob)->y;
}

// =============================
// CALCULATE EXPERIENCE
// =============================
$experience = '-';

if (!empty($currentEmployee['joiningDate'])) {

    $joiningDate = new DateTime(
        $currentEmployee['joiningDate']
    );

    $today = new DateTime();

    $years = $today->diff($joiningDate)->y;

    $experience = $years . ' Years';
}


$profilePhotoUrl = $employeeEngine->getProfilePhotoUrl(
    $currentEmployee
);

// Fallback Image
if (empty($profilePhotoUrl)) {

    $profilePhotoUrl =
        ASSET_URL . '/assets/images/faces/team/7.png';
}



/*
|--------------------------------------------------------------------------
| Skills Array
|--------------------------------------------------------------------------
*/

$skills = [];

if (!empty($currentEmployee['skills'])) {

    $skills = array_filter(
        array_map(
            'trim',
            explode(',', $currentEmployee['skills'])
        )
    );
}

/*
|--------------------------------------------------------------------------
| Location
|--------------------------------------------------------------------------
*/

$location = trim(

    ($currentEmployee['cityName'] ?? '') .

    (
        !empty($currentEmployee['cityName']) &&
        !empty($currentEmployee['stateName'])
            ? ', '
            : ''
    ) .

    ($currentEmployee['stateName'] ?? '')

);


/*
|--------------------------------------------------------------------------
| Get Same Department Employees
|--------------------------------------------------------------------------
*/

$teamMembers = [];

if (!empty($currentEmployee['departmentName'])) {

    $stmt = mysqli_prepare(

        $con,

        "SELECT
            id,
            fullName,
            emailAddress,
            designationName,
            profilePhoto

         FROM employeeusers

         WHERE

            departmentName = ?
            AND id != ?

         ORDER BY fullName ASC

         LIMIT 5"

    );

    mysqli_stmt_bind_param(

        $stmt,

        "si",

        $currentEmployee['departmentName'],
        $currentEmployee['id']

    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {

        $teamMembers[] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<!-- Start::app-content -->
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Profile</h1>
                <div class="">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="javascript:void(0);">Employee</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start:: row-1 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card profile-card">

                    <?php
                                $daySeed = date('Ymd');
                                $coverImage = "https://picsum.photos/1207/238?random={$daySeed}?blur";
                                ?>

                    <img src="<?= $coverImage ?>" class="img-fluid w-100" class="card-img-top" alt="Cover">
                    <!-- <img src="<?= ASSET_URL ?>/assets/images/media/media-3.jpg" class="card-img-top" alt="..."> -->
                    <div class="card-body p-4 pb-0 position-relative">
                        <span class="avatar avatar-xxl avatar-rounded bg-info online">
                            <img src="<?php echo htmlspecialchars($profilePhotoUrl); ?>"
                                alt="<?php echo htmlspecialchars($currentEmployee['fullName'] ?? 'Employee'); ?>">
                        </span>
                        <div class="mt-4 mb-3 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                            <div>
                                <h5 class="fw-semibold mb-1">
                                    <?php echo htmlspecialchars($currentEmployee['fullName'] ?? '-'); ?>
                                </h5>
                                <span class="d-block fw-medium text-muted mb-1">
                                    <?php echo htmlspecialchars($currentEmployee['designationName'] ?? '-'); ?>
                                </span>
                                <p class="fs-12 mb-0 fw-medium text-muted">
                                    <span class="me-3">
                                        <i class="ri-building-line me-1 align-middle"></i>
                                        <?php echo htmlspecialchars($currentEmployee['departmentName'] ?? '-'); ?>
                                    </span>
                                    <span>
                                        <i class="ri-map-pin-line me-1 align-middle"></i>
                                        <?php
                                                    echo htmlspecialchars(
                                                        trim(
                                                            ($currentEmployee['cityName'] ?? '') .
                                                            ', ' .
                                                            ($currentEmployee['stateName'] ?? '')
                                                        )
                                                    );
                                                ?>
                                    </span>
                                </p>
                            </div>
                            <div class="d-flex mb-0 flex-wrap gap-4">

                                <!-- ATTENDANCE -->
                                <div class="p-3 bg-light rounded d-flex align-items-center border gap-3">

                                    <div class="main-card-icon danger">

                                        <div
                                            class="avatar avatar-lg bg-danger-transparent border border-danger border-opacity-10">

                                            <div class="avatar avatar-sm svg-white">

                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">

                                                    <circle cx="12" cy="12" r="10"></circle>

                                                    <polyline points="12 6 12 12 16 14"></polyline>

                                                </svg>

                                            </div>

                                        </div>

                                    </div>

                                    <div id="attendanceActionContainer">

                                        <button class="btn btn-light btn-sm" disabled>
                                            Loading...
                                        </button>

                                        <p class="mb-0 fs-12 text-muted fw-medium mt-1">
                                            Loading attendance...
                                        </p>

                                    </div>

                                </div>
                                <div class="p-3 bg-light rounded d-flex align-items-center border gap-3">
                                    <div class="main-card-icon primary">
                                        <div
                                            class="avatar avatar-lg bg-primary-transparent border border-primary border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
                                                    <rect width="256" height="256" fill="none" />
                                                    <path
                                                        d="M128,144a191.14,191.14,0,0,1-96-25.68h0V200a8,8,0,0,0,8,8H216a8,8,0,0,0,8-8V118.31A191.08,191.08,0,0,1,128,144Z"
                                                        opacity="0.2" />
                                                    <line x1="112" y1="112" x2="144" y2="112" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                    <rect x="32" y="64" width="192" height="144" rx="8" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                    <path d="M168,64V48a16,16,0,0,0-16-16H104A16,16,0,0,0,88,48V64"
                                                        fill="none" stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                    <path
                                                        d="M224,118.31A191.09,191.09,0,0,1,128,144a191.14,191.14,0,0,1-96-25.68"
                                                        fill="none" stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="fw-semibold fs-20 mb-0">0</p>
                                        <p class="mb-0 fs-12 text-muted fw-medium">Projects</p>
                                    </div>
                                </div>
                                <div class="p-3 bg-light rounded d-flex align-items-center border gap-3">
                                    <div class="main-card-icon secondary">
                                        <div
                                            class="avatar avatar-lg bg-secondary-transparent border border-secondary border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
                                                    <rect width="256" height="256" fill="none" />
                                                    <circle cx="84" cy="108" r="52" opacity="0.2" />
                                                    <path d="M10.23,200a88,88,0,0,1,147.54,0" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                    <path d="M172,160a87.93,87.93,0,0,1,73.77,40" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                    <circle cx="84" cy="108" r="52" fill="none" stroke="currentColor"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="16" />
                                                    <path d="M152.69,59.7A52,52,0,1,1,172,160" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="fw-semibold fs-20 mb-0">0</p>
                                        <p class="mb-0 fs-12 text-muted fw-medium">Followers</p>
                                    </div>
                                </div>
                                <div class="p-3 bg-light rounded d-flex align-items-center border gap-2">
                                    <div class="main-card-icon success">
                                        <div
                                            class="avatar avatar-lg bg-success-transparent border border-success border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
                                                    <rect width="256" height="256" fill="none" />
                                                    <path
                                                        d="M208,40H48a8,8,0,0,0-8,8V208a8,8,0,0,0,8,8H208a8,8,0,0,0,8-8V48A8,8,0,0,0,208,40ZM57.78,216A72,72,0,0,1,128,160a40,40,0,1,1,40-40,40,40,0,0,1-40,40,72,72,0,0,1,70.22,56Z"
                                                        opacity="0.2" />
                                                    <circle cx="128" cy="120" r="40" fill="none" stroke="currentColor"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="16" />
                                                    <rect x="40" y="40" width="176" height="176" rx="8" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                    <path d="M57.78,216a72,72,0,0,1,140.44,0" fill="none"
                                                        stroke="currentColor" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="16" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="fw-semibold fs-20 mb-0">0</p>
                                        <p class="mb-0 fs-12 text-muted fw-medium">Following</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <ul class="nav nav-tabs mb-0 tab-style-8 scaleX" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-about-tab" data-bs-toggle="tab"
                                    data-bs-target="#profile-about-tab-pane" type="button" role="tab"
                                    aria-controls="profile-about-tab-pane" aria-selected="true">About</button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="friends-tab" data-bs-toggle="tab"
                                    data-bs-target="#friends-tab-pane" type="button" role="tab"
                                    aria-controls="friends-tab-pane" aria-selected="false">Friends</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- End:: row-1 -->

        <!-- Start:: row-2 -->
        <div class="row">
            <div class="col-xl-9">
                <div class="tab-content" id="profile-tabs">
                    <div class="tab-pane show active p-0 border-0" id="profile-about-tab-pane" role="tabpanel"
                        aria-labelledby="profile-about-tab" tabindex="0">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <!-- ABOUT -->
                                    <li class="list-group-item p-4">
                                        <span class="fw-medium fs-15 d-block mb-3">
                                            <span class="me-1">&#10024;</span>
                                            ABOUT ME :
                                        </span>
                                        <p class="text-muted mb-0">
                                            <?php
                                                    echo !empty($currentEmployee['aboutMe'])
                            
                                                        ? nl2br(
                                                            htmlspecialchars(
                                                                $currentEmployee['aboutMe']
                                                            )
                                                        )
                            
                                                        : 'No about information added yet.';
                                                    ?>
                                        </p>
                                    </li>

                                    <!-- SKILLS -->
                                    <li class="list-group-item p-4">
                                        <span class="fw-medium fs-15 d-block mb-3">
                                            SKILLS :
                                        </span>
                                        <div class="w-75">
                                            <?php if (!empty($skills)) : ?>
                                            <?php foreach ($skills as $skill) : ?>
                                            <a href="javascript:void(0);">
                                                <span class="badge bg-light text-muted m-1 border">
                                                    <?php
                                                                    echo htmlspecialchars($skill);
                                                                    ?>
                                                </span>
                                            </a>
                                            <?php endforeach; ?>
                                            <?php else : ?>
                                            <span class="text-muted">
                                                No skills added yet.
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </li>

                                    <!-- CONTACT INFO -->
                                    <li class="list-group-item p-4">

                                        <span class="fw-medium fs-15 d-block mb-3">
                                            CONTACT INFORMATION :
                                        </span>

                                        <div class="text-muted">

                                            <p class="mb-2">

                                                <span class="avatar avatar-sm avatar-rounded text-primary">

                                                    <i class="ri-mail-line align-middle fs-15"></i>

                                                </span>

                                                <span class="fw-medium text-default">
                                                    Email :
                                                </span>

                                                <?php
                                                        echo htmlspecialchars(
                                                            $currentEmployee['emailAddress'] ?? '-'
                                                        );
                                                        ?>

                                            </p>
                                            <p class="mb-2">
                                                <span class="avatar avatar-sm avatar-rounded text-secondary">
                                                    <i class="ri-phone-line align-middle fs-15"></i>
                                                </span>
                                                <span class="fw-medium text-default">
                                                    Phone :
                                                </span>
                                                <?php
                                                        echo htmlspecialchars(
                                                            $currentEmployee['mobileNumber'] ?? '-'
                                                        );
                                                        ?>
                                            </p>
                                            <p class="mb-2">
                                                <span class="avatar avatar-sm avatar-rounded text-success">
                                                    <i class="ri-phone-lock-line align-middle fs-15"></i>
                                                </span>
                                                <span class="fw-medium text-default">
                                                    Emergency :
                                                </span>
                                                <?php
                                                        echo htmlspecialchars(
                                                            $currentEmployee['emergencyContactNumber'] ?? '-'
                                                        );
                                                        ?>

                                            </p>

                                            <p class="mb-0">
                                                <span class="avatar avatar-sm avatar-rounded text-orange">
                                                    <i class="ri-map-pin-line align-middle fs-15"></i>
                                                </span>
                                                <span class="fw-medium text-default">
                                                    Location :
                                                </span>
                                                <?php
                                                        echo !empty($location)
                                                            ? htmlspecialchars($location)
                                                            : '-';
                                                        ?>
                                            </p>
                                        </div>
                                    </li>
                                    <!-- SOCIAL INFO -->
                                    <li class="list-group-item p-4">

                                        <span class="fw-medium fs-15 d-block mb-3">
                                            SOCIAL MEDIA :
                                        </span>

                                        <div class="d-flex align-items-center gap-5 flex-wrap">

                                            <!-- LINKEDIN -->
                                            <div class="d-flex align-items-center gap-3">

                                                <div>

                                                    <?php if (!empty($currentEmployee['linkedInProfile'])) : ?>

                                                    <a href="<?=
                                                                        htmlspecialchars(
                                                                            $currentEmployee['linkedInProfile']
                                                                        );
                                                                    ?>" target="_blank" rel="noopener noreferrer">

                                                        <span class="avatar avatar-md bg-success-transparent">

                                                            <i class="ri-linkedin-box-fill fs-4"></i>

                                                        </span>

                                                    </a>

                                                    <?php else : ?>

                                                    <span class="avatar avatar-md bg-light text-muted">

                                                        <i class="ri-linkedin-box-fill fs-4"></i>

                                                    </span>

                                                    <?php endif; ?>

                                                </div>

                                                <div>

                                                    <span class="d-block fw-medium">
                                                        Linkedin
                                                    </span>

                                                    <span class="text-muted fw-medium">

                                                        <?php
                                                                echo !empty($currentEmployee['linkedInProfile'])
                                                                    ? 'Profile Available'
                                                                    : 'Not Added';
                                                                ?>

                                                    </span>

                                                </div>

                                            </div>

                                            <!-- INSTAGRAM -->
                                            <div class="d-flex align-items-center gap-3">

                                                <div>

                                                    <?php if (!empty($currentEmployee['instagramProfile'])) : ?>

                                                    <a href="<?= htmlspecialchars($currentEmployee['instagramProfile']); ?>"
                                                        target="_blank" rel="noopener noreferrer">

                                                        <span class="avatar avatar-md bg-danger-transparent">

                                                            <i class="ri-instagram-line fs-4"></i>

                                                        </span>

                                                    </a>

                                                    <?php else : ?>
                                                    <span class="avatar avatar-md bg-light text-muted">
                                                        <i class="ri-instagram-line fs-4"></i>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <span class="d-block fw-medium">
                                                        Instagram
                                                    </span>
                                                    <span class="text-muted fw-medium">

                                                        <?php
                                                                echo !empty($currentEmployee['instagramProfile'])
                                                                    ? 'Profile Available'
                                                                    : 'Not Added';
                                                                ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>



                    <div class="tab-pane p-0 border-0" id="friends-tab-pane" role="tabpanel"
                        aria-labelledby="friends-tab" tabindex="0">

                        <div class="card custom-card">

                            <div class="card-body">

                                <div class="row">

                                    <?php if (!empty($teamMembers)) : ?>

                                    <?php foreach ($teamMembers as $member) : ?>

                                    <?php
                            
                                                    $memberPhoto =
                                                        $employeeEngine->getProfilePhotoUrl(
                                                            $member
                                                        );
                            
                                                    if (empty($memberPhoto)) {
                            
                                                        $memberPhoto =
                                                            ASSET_URL .
                                                            '/assets/images/faces/team/7.png';
                                                    }
                            
                                                    ?>

                                    <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6 col-sm-12">

                                        <div class="card custom-card shadow-none border">

                                            <div class="card-body p-4">

                                                <div class="text-center">

                                                    <span class="avatar avatar-xl avatar-rounded">

                                                        <img src="<?=
                                                                                htmlspecialchars(
                                                                                    $memberPhoto
                                                                                );
                                                                            ?>" alt="<?=
                                                                                htmlspecialchars(
                                                                                    $member['fullName']
                                                                                );
                                                                            ?>">

                                                    </span>

                                                    <div class="mt-2">

                                                        <p class="mb-0 fw-semibold">

                                                            <?php
                                                                            echo htmlspecialchars(
                                                                                $member['fullName']
                                                                            );
                                                                            ?>

                                                        </p>

                                                        <p class="fs-12 op-7 mb-1 text-muted">

                                                            <?php
                                                                            echo htmlspecialchars(
                                                                                $member['emailAddress']
                                                                            );
                                                                            ?>

                                                        </p>

                                                        <span class="badge bg-info-transparent">

                                                            <?php
                                                                            echo htmlspecialchars(
                                                                                $member['designationName'] ?? 'Team Member'
                                                                            );
                                                                            ?>

                                                        </span>

                                                    </div>

                                                </div>

                                            </div>

                                            <div class="card-footer text-center">
                                                <div class="d-flex gap-2 flex-wrap justify-content-center">
                                                    <div class="btn-list">
                                                        <button
                                                            class="btn btn-sm btn-light btn-wave waves-effect waves-light">Block</button>
                                                        <button
                                                            class="btn btn-sm btn-primary btn-wave me-0 waves-effect waves-light">Unfollow</button>
                                                    </div>
                                                    <div class="dropdown">
                                                        <a aria-label="anchor"
                                                            class="btn btn-secondary btn-icon btn-sm btn-wave waves-effect waves-light"
                                                            href="javascript:void(0);" data-bs-toggle="dropdown">
                                                            <i class="ri-more-2-fill"></i>
                                                        </a>
                                                        <ul class="dropdown-menu" role="menu">
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="javascript:void(0);">Message</a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="javascript:void(0);">Edit</a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="javascript:void(0);">View</a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="javascript:void(0);">Delete</a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php endforeach; ?>

                                    <?php else : ?>
                                    <div class="col-xl-12">
                                        <div class="text-center text-muted py-5">
                                            No team mates found
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">

                <div class="card custom-card overflow-hidden">
                    <div class="card-header">
                        <div class="card-title">
                            PERSONAL INFO
                        </div>

                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <div>
                                    <span class="fw-medium me-2">
                                        Name :
                                    </span>
                                    <span class="text-muted">
                                        <?php
                                                echo htmlspecialchars(
                                                    $currentEmployee['fullName'] ?? '-'
                                                );
                                                ?>
                                    </span>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div>
                                    <span class="fw-medium me-2">
                                        Email :
                                    </span>
                                    <span class="text-muted">
                                        <?php
                                                echo htmlspecialchars(
                                                    $currentEmployee['emailAddress'] ?? '-'
                                                );
                                                ?>
                                    </span>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div>
                                    <span class="fw-medium me-2">
                                        Phone :
                                    </span>
                                    <span class="text-muted">
                                        <?php
                                                echo htmlspecialchars(
                                                    $currentEmployee['mobileNumber'] ?? '-'
                                                );
                                                ?>
                                    </span>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div>
                                    <span class="fw-medium me-2">
                                        Designation :
                                    </span>
                                    <span class="text-muted">
                                        <?php
                                                echo htmlspecialchars(
                                                    $currentEmployee['designationName'] ?? '-'
                                                );
                                                ?>
                                    </span>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div>
                                    <span class="fw-medium me-2">
                                        Age :
                                    </span>
                                    <span class="text-muted">
                                        <?php echo $age; ?>
                                    </span>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div>
                                    <span class="fw-medium me-2">
                                        Experience :
                                    </span>
                                    <span class="text-muted">
                                        <?php echo $experience; ?>
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card custom-card overflow-hidden">

                    <div class="card-header justify-content-between">

                        <div class="card-title">
                            TEAM MATES
                        </div>

                        <a href="javascript:void(0);" class="fs-12 text-muted">

                            Same Department

                            <i class="ti ti-users ms-1"></i>

                        </a>

                    </div>

                    <div class="card-body p-0">

                        <ul class="list-group list-group-flush">

                            <?php if (!empty($teamMembers)) : ?>

                            <?php foreach ($teamMembers as $member) : ?>

                            <?php
                        
                                            $memberPhoto =
                                                $employeeEngine->getProfilePhotoUrl(
                                                    $member
                                                );
                        
                                            if (empty($memberPhoto)) {
                        
                                                $memberPhoto =
                                                    ASSET_URL .
                                                    '/assets/images/faces/team/7.png';
                                            }
                        
                                            ?>

                            <li class="list-group-item">

                                <div class="d-flex align-items-center gap-2">

                                    <div class="lh-1">

                                        <span class="avatar avatar-sm avatar-rounded">

                                            <img src="<?=
                                                                    htmlspecialchars(
                                                                        $memberPhoto
                                                                    );
                                                                ?>" alt="<?=
                                                                    htmlspecialchars(
                                                                        $member['fullName']
                                                                    );
                                                                ?>">

                                        </span>

                                    </div>

                                    <div class="flex-fill">

                                        <span class="fw-medium d-block">

                                            <?php
                                                            echo htmlspecialchars(
                                                                $member['fullName']
                                                            );
                                                            ?>

                                        </span>

                                        <span class="fs-12 text-muted">

                                            <?php
                                                            echo htmlspecialchars(
                                                                $member['designationName'] ?? '-'
                                                            );
                                                            ?>

                                        </span>

                                    </div>

                                    <div>

                                        <span class="badge bg-primary-transparent">

                                            Team

                                        </span>

                                    </div>

                                </div>

                            </li>

                            <?php endforeach; ?>

                            <?php else : ?>

                            <li class="list-group-item text-center text-muted py-4">

                                No team mates found

                            </li>

                            <?php endif; ?>

                        </ul>

                    </div>

                </div>

            </div>
        </div>
        <!-- End:: row-2 -->

    </div>
</div>
<!-- End::app-content -->

<!-- Footer Start -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let attendanceInterval = null;

let scheduledReminderShown = false;

let remindLaterUntil = null;

$(function() {

    loadAttendanceState();

    setInterval(function() {

        loadAttendanceState();

    }, 30000);

});

// =============================
// LOAD ATTENDANCE STATE
// =============================
function loadAttendanceState() {
    $.ajax({

        url: API_BASE + '/attendance/getAttendanceState.php',

        type: 'GET',

        dataType: 'json',

        success: function(response) {

            if (
                !response ||
                !response.success
            ) {

                renderAttendanceError(
                    response.message ||
                    'Failed to load attendance'
                );

                return;
            }

            renderAttendanceUI(
                response.data
            );

            checkScheduledBreakReminder(
                response.data
            );
        },

        error: function() {

            renderAttendanceError(
                'Unable to load attendance'
            );
        }
    });
}

// =============================
// RENDER ERROR
// =============================
function renderAttendanceError(message) {
    $('#attendanceActionContainer').html(`
        
                <button
                    class="btn btn-danger btn-sm"
                    disabled
                >
                    ${message}
                </button>
        
            `);
}

// =============================
// RENDER ATTENDANCE UI
// =============================
function renderAttendanceUI(data) {
    let html = '';

    // =========================
    // NOT PUNCHED IN
    // =========================
    if (
        data.state === 'not_punched_in'
    ) {

        html = `
        
                    <button
                        class="btn btn-success btn-sm"
                        onclick="handlePunchIn()"
                    >
                        Punch In
                    </button>
        
                    <p class="mb-0 fs-12 text-muted fw-medium mt-1">
                        Start Attendance
                    </p>
                `;
    }

    // =========================
    // PUNCHED IN
    // =========================
    else if (
        data.state === 'punched_in'
    ) {

        html = `
        
                    <div class="d-flex gap-2">
        
                        <button
                            class="btn btn-danger btn-sm"
                            onclick="handlePunchOut()"
                        >
                            Punch Out
                        </button>
        
                        <button
                            class="btn btn-warning btn-sm"
                            onclick="handleStartBreak()"
                        >
                            Start Break
                        </button>
        
                    </div>
        
                    <p class="mb-0 fs-12 text-success fw-medium mt-1">
                        Working Time
                    </p>
                    
                    <div
                        id="attendanceTimer"
                        class="fw-semibold text-primary mt-1"
                    >
                        00:00:00
                    </div>
                `;
    }

    // =========================
    // ON BREAK
    // =========================
    else if (
        data.state === 'on_break'
    ) {

        html = `
        
                    <div class="d-flex gap-2">

                        <button
                            class="btn btn-danger btn-sm"
                            onclick="handlePunchOut()"
                        >
                            Punch Out
                        </button>
                    
                        <button
                            class="btn btn-primary btn-sm"
                            onclick="handleEndBreak()"
                        >
                            End Break
                        </button>
                    
                    </div>
        
                    <p class="mb-0 fs-12 text-warning fw-medium mt-1">
                        Break Duration
                    </p>
                    
                    <div
                        id="attendanceTimer"
                        class="fw-semibold text-warning mt-1"
                    >
                        00:00:00
                    </div>
                `;
    }

    // =========================
    // COMPLETED
    // =========================
    else if (
        data.state === 'completed'
    ) {

        html = `
        
                    <button
                        class="btn btn-secondary btn-sm"
                        disabled
                    >
                        Attendance Completed
                    </button>
        
                    <p class="mb-0 fs-12 text-muted fw-medium mt-1">
                        Today's attendance completed
                    </p>
                `;
    }

    $('#attendanceActionContainer')
        .html(html);


    // =========================
    // START TIMER
    // =========================
    if (
        data.state === 'punched_in'
    ) {

        startTimer(
            data.workingSeconds
        );
    } else if (
        data.state === 'on_break'
    ) {

        startTimer(
            data.breakSeconds
        );
    } else {

        clearInterval(
            attendanceInterval
        );

        attendanceInterval = null;
    }

}


function checkScheduledBreakReminder(data) {
    const reminder =
        data.scheduledBreakReminder;

    if (
        data.state === 'on_break'
    ) {
        return;
    }

    if (!reminder) {

        scheduledReminderShown =
            false;

        return;
    }

    if (
        remindLaterUntil &&
        Date.now() < remindLaterUntil
    ) {
        return;
    }

    if (
        scheduledReminderShown
    ) {
        return;
    }

    scheduledReminderShown =
        true;

    Swal.fire({

            title: reminder.breakName,

            html: `
                    <div class="text-center">
        
                        <p class="mb-0">
                            Your scheduled
                            break is due.
                        </p>
        
                        <small class="text-muted">
                            Preferred Time :
                            ${formatReminderTime(
                                reminder.preferredStartTime
                            )}
                        </small>
        
                    </div>
                `,

            icon: 'info',

            showCancelButton: true,

            confirmButtonText: 'Start Break',

            cancelButtonText: 'Remind Me Later',

            allowOutsideClick: false

        })

        .then(function(result) {

            scheduledReminderShown =
                false;

            if (
                result.isConfirmed
            ) {

                startBreakRequest(
                    reminder.breakTypeId
                );
            } else {

                remindLaterUntil =
                    Date.now() +
                    (5 * 60 * 1000);
            }
        });
}

// =============================
// Format Reminder Time
// =============================


function formatReminderTime(time) {
    if (!time) {
        return '--';
    }

    return new Date(
        '1970-01-01T' + time
    ).toLocaleTimeString(
        'en-IN', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }
    );
}

// =============================
// PUNCH IN
// =============================
function handlePunchIn() {
    $.ajax({

        url: API_BASE + '/attendance/punchIn.php',

        type: 'POST',

        dataType: 'json',

        success: function(response) {

            if (
                response.success
            ) {

                loadAttendanceState();
            }

            showToast(
                response.success ?
                'success' :
                'error',

                response.message
            );
        },

        error: function() {

            showToast(
                'error',
                'Punch in failed'
            );
        }
    });
}


// =============================
// PUNCH OUT
// =============================
function handlePunchOut() {
    Swal.fire({

            title: 'Punch Out?',

            text: 'Are you sure you want to complete your attendance for today?',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Yes, Punch Out',

            cancelButtonText: 'Cancel',

            reverseButtons: true

        })

        .then(function(result) {

            if (!result.isConfirmed) {
                return;
            }

            $.ajax({

                url: API_BASE + '/attendance/punchOut.php',

                type: 'POST',

                dataType: 'json',

                success: function(response) {

                    if (response.success) {
                
                        loadAttendanceState();
                
                        showToast(
                            'success',
                            response.message
                        );
                
                        return;
                    }
                
                    Swal.fire({
                
                        icon: 'warning',
                
                        title: 'Punch Out Not Allowed',
                
                        text: response.message,
                
                        confirmButtonText: 'OK',
                
                        confirmButtonColor: '#3085d6'
                
                    });
                
                },

                error: function() {

                    showToast(
                        'error',
                        'Punch out failed'
                    );
                }
            });

        });
}


// =============================
// START BREAK
// =============================
function handleStartBreak() {
    $.ajax({

        url: API_BASE + '/attendance/getBreakTypes.php',

        type: 'GET',

        dataType: 'json',

        success: function(response) {

            if (
                !response.success ||
                !response.data.length
            ) {

                showToast(
                    'error',
                    'No break types available'
                );

                return;
            }

            let options = '';

            response.data.forEach(function(item) {

                options += `
                            <option value="${item.id}">
                                ${item.breakName}
                                (${item.allowedMinutes} Min)
                            </option>
                        `;
            });

            Swal.fire({

                    title: 'Start Break',

                    html: `
                            <div class="text-start">
        
                                <label class="form-label mb-2">
                                    Select Break Type
                                </label>
        
                                <select
                                    id="swalBreakType"
                                    class="form-select"
                                >
                                    ${options}
                                </select>
        
                            </div>
                        `,

                    icon: 'question',

                    showCancelButton: true,

                    confirmButtonText: 'Start Break',

                    cancelButtonText: 'Cancel',

                    reverseButtons: true,

                    preConfirm: () => {

                        return $('#swalBreakType').val();
                    }

                })

                .then(function(result) {

                    if (!result.isConfirmed) {
                        return;
                    }

                    startBreakRequest(
                        result.value
                    );
                });
        },

        error: function() {

            showToast(
                'error',
                'Unable to load break types'
            );
        }
    });
}


// =============================
// START BREAK REQUEST
// =============================
function startBreakRequest(breakTypeId) {
    $.ajax({

        url: API_BASE + '/attendance/startBreak.php',

        type: 'POST',

        dataType: 'json',

        data: {
            breakTypeId: breakTypeId
        },

        success: function(response) {

            if (
                response.success
            ) {

                loadAttendanceState();
            }

            showToast(

                response.success ?
                'success' :
                'error',

                response.message
            );
        },

        error: function() {

            showToast(
                'error',
                'Failed to start break'
            );
        }
    });
}

// =============================
// END BREAK
// =============================
function handleEndBreak() {
    Swal.fire({

            title: 'End Break?',

            text: 'Your working hours will resume after ending this break.',

            icon: 'question',

            showCancelButton: true,

            confirmButtonText: 'End Break',

            cancelButtonText: 'Cancel',

            reverseButtons: true

        })

        .then(function(result) {

            if (!result.isConfirmed) {
                return;
            }

            $.ajax({

                url: API_BASE + '/attendance/endBreak.php',

                type: 'POST',

                dataType: 'json',

                success: function(response) {

                    if (response.success) {

                        loadAttendanceState();
                    }

                    showToast(
                        response.success ?
                        'success' :
                        'error',
                        response.message
                    );
                },

                error: function() {

                    showToast(
                        'error',
                        'Failed to end break'
                    );
                }
            });

        });
}


// =============================
// START TIMER
// =============================
function startTimer(seconds) {
    clearInterval(
        attendanceInterval
    );

    let totalSeconds =
        parseInt(
            seconds || 0
        );

    renderTimer();

    attendanceInterval =
        setInterval(function() {

            totalSeconds++;

            renderTimer();

        }, 1000);

    function renderTimer() {
        const hours =
            String(
                Math.floor(
                    totalSeconds / 3600
                )
            ).padStart(2, '0');

        const minutes =
            String(
                Math.floor(
                    (totalSeconds % 3600) / 60
                )
            ).padStart(2, '0');

        const secs =
            String(
                totalSeconds % 60
            ).padStart(2, '0');

        $('#attendanceTimer').text(
            hours +
            ':' +
            minutes +
            ':' +
            secs
        );
    }
}
</script>
<?php include __DIR__ . '/../includes/emp-footer.php';