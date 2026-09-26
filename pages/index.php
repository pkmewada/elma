<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">

                <!-- Start::page-header -->
                <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h1 class="page-title fw-medium fs-18 mb-2">Analytics</h1>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="javascript:void(0);">
                                    Dashboards
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Analytics</li>
                        </ol>
                    </div>
                    <div>
                        <button class="btn btn-primary-light btn-wave me-2 waves-effect waves-light">
                            <i class="bx bx-crown align-middle"></i> Plan Upgrade
                        </button>
                        <button class="btn btn-secondary-light btn-wave me-0 waves-effect waves-light">
                            <i class="ri-upload-cloud-line align-middle"></i> Export Report
                        </button>
                    </div>
                </div>
                <!-- End::page-header -->

                <!-- Start:: row-1 -->
                <div class="row">
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div class="main-card-icon primary">
                                        <div class="avatar avatar-lg bg-primary-transparent border border-primary border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><circle cx="84" cy="108" r="52" opacity="0.2"/><path d="M10.23,200a88,88,0,0,1,147.54,0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><path d="M172,160a87.93,87.93,0,0,1,73.77,40" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><circle cx="84" cy="108" r="52" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><path d="M152.69,59.7A52,52,0,1,1,172,160" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a aria-label="anchor" href="javascript:void(0);" data-bs-toggle="dropdown">
                                            <i class="ri-more-2-fill fs-5 text-muted"></i>
                                        </a>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Week</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Day</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Year</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <span class="d-block mt-3 mb-2 fw-medium">Total Users</span>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="fs-4 mb-0 lh-1">872</h5>
                                    <span class="badge bg-success-transparent">
                                        +0.45%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div class="main-card-icon secondary">
                                        <div class="avatar avatar-lg bg-secondary-transparent border border-secondary border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><circle cx="128" cy="136" r="88" opacity="0.2"/><circle cx="128" cy="136" r="88" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><line x1="128" y1="136" x2="168" y2="96" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><line x1="104" y1="16" x2="152" y2="16" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a aria-label="anchor" href="javascript:void(0);" data-bs-toggle="dropdown">
                                            <i class="ri-more-2-fill fs-5 text-muted"></i>
                                        </a>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Week</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Day</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Year</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <span class="d-block mt-3 mb-2 fw-medium">Sessions</span>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="fs-4 mb-0 lh-1">2,178</h5>
                                    <span class="badge bg-success-transparent">
                                        +1.02%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div class="main-card-icon success">
                                        <div class="avatar avatar-lg bg-success-transparent border border-success border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><circle cx="76" cy="76" r="28" opacity="0.2"/><circle cx="180" cy="180" r="28" opacity="0.2"/><line x1="200" y1="56" x2="56" y2="200" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><circle cx="76" cy="76" r="28" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><circle cx="180" cy="180" r="28" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a aria-label="anchor" href="javascript:void(0);" data-bs-toggle="dropdown">
                                            <i class="ri-more-2-fill fs-5 text-muted"></i>
                                        </a>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Week</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Day</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Year</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <span class="d-block mt-3 mb-2 fw-medium">Bounce Rate</span>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="fs-4 mb-0 lh-1">48.46%</h5>
                                    <span class="badge bg-danger-transparent">
                                        -0.89%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div class="main-card-icon orange">
                                        <div class="avatar avatar-lg bg-orange-transparent border border-orange border-opacity-10">
                                            <div class="avatar avatar-sm svg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M128,128,67.2,82.4A8,8,0,0,1,64,76V40a8,8,0,0,1,8-8H184a8,8,0,0,1,8,8V75.64A8,8,0,0,1,188.82,82L128,128h0" opacity="0.2"/><path d="M128,128,67.2,173.6A8,8,0,0,0,64,180v36a8,8,0,0,0,8,8H184a8,8,0,0,0,8-8V180.36a8,8,0,0,0-3.18-6.38L128,128h0" opacity="0.2"/><path d="M128,128,67.2,82.4A8,8,0,0,1,64,76V40a8,8,0,0,1,8-8H184a8,8,0,0,1,8,8V75.64A8,8,0,0,1,188.82,82L128,128h0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><path d="M128,128,67.2,173.6A8,8,0,0,0,64,180v36a8,8,0,0,0,8,8H184a8,8,0,0,0,8-8V180.36a8,8,0,0,0-3.18-6.38L128,128h0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a aria-label="anchor" href="javascript:void(0);" data-bs-toggle="dropdown">
                                            <i class="ri-more-2-fill fs-5 text-muted"></i>
                                        </a>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Week</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Day</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Year</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <span class="d-block mt-3 mb-2 fw-medium">Avg Session Duration</span>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="fs-4 mb-0 lh-1">4.5 min</h5>
                                    <span class="badge bg-success-transparent">
                                        +11.07%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-1 -->

                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-xl-8">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    SESSIONS OVERVIEW
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="p-2 fs-12 text-muted" data-bs-toggle="dropdown" aria-expanded="true"> Sort By <i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i> </a>
                                    <ul class="dropdown-menu" role="menu" style="position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate(0px, 28px);" data-popper-placement="bottom-end">
                                        <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">This Month</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="sessions-overview"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    EARNINGS REPORT
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="p-2 fs-12 text-muted" data-bs-toggle="dropdown" aria-expanded="true"> Sort By <i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i> </a>
                                    <ul class="dropdown-menu" role="menu" style="position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate(0px, 28px);" data-popper-placement="bottom-end">
                                        <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">This Month</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="analytics-earnings-report"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-2 -->

                <!-- Start:: row-3 -->
                <div class="row">
                    <div class="col-xl-4">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header justify-content-between">
                                <div class="card-title"> 
                                    SESSIONS DEVICE
                                </div>
                                <div class="dropdown"> 
                                    <a href="javascript:void(0);" class="p-2 fs-12 text-muted" data-bs-toggle="dropdown" aria-expanded="true"> Sort By <i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i> </a> 
                                    <ul class="dropdown-menu" role="menu" style="position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate(0px, 28px);" data-popper-placement="bottom-end"> 
                                        <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li> 
                                         <li><a class="dropdown-item" href="javascript:void(0);">This Month</a></li> 
                                    </ul> 
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <span class="d-block p-3 text-muted fs-12">Experience a remarkable surge in user engagement over the past week</span>
                                <div id="sessions-device"></div>
                                <ul class="list-group list-group-flush border-top mt-3">
                                    <li class="list-group-item">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="session-type lh-1 mobile">
                                                <div class="fw-medium mb-1">Mobile</div>
                                                <span class="fs-12 text-muted">Increased By<span class="text-success fs-12 ms-1 fw-medium d-inline-block">1.67%</span></span>
                                            </div>
                                            <div class="lh-1 text-end">
                                                <span class="d-block fs-12 text-muted mb-1">Total</span>
                                                <span class="d-block fw-semibold mb-0">1,754</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="session-type lh-1 tablet">
                                                <div class="fw-medium mb-1">Tablet</div>
                                                <span class="fs-12 text-muted">Increased By<span class="text-success fs-12 ms-1 fw-medium d-inline-block">0.46%</span></span>
                                            </div>
                                            <div class="lh-1 text-end">
                                                <span class="d-block fs-12 text-muted mb-1">Total</span>
                                                <span class="d-block fw-semibold mb-0">634</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="session-type desktop lh-1">
                                                <div class="fw-medium mb-1">Desktop</div>
                                                <span class="fs-12 text-muted">Decresed By<span class="text-danger fs-12 ms-1 fw-medium d-inline-block">3.43%</span></span>
                                            </div>
                                            <div class="lh-1 text-end">
                                                <span class="d-block fs-12 text-muted mb-1">Total</span>
                                                <span class="d-block fw-semibold mb-0">878</span>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="row">
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                <div class="card custom-card overflow-hidden">
                                    <div class="card-header">
                                        <div class="card-title">
                                            Browser Statistics
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table text-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th>Browser</th>
                                                        <th class="text-center">Sessions</th>
                                                        <th class="text-center">Bounce Rate</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="avatar avatar-rounded avatar-md p-1 bg-light border border-warning border-opacity-10">
                                                                        <img src="../assets/images/browsers/chrome.png" alt="">
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-medium">Google</span>
                                                                    <span class="d-block text-muted fs-12">Google,Inc</span>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td class="text-center">1215</td>
                                                        <td class="text-center">29.52%</td>
                                                    </tr>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="avatar avatar-rounded avatar-md p-1 bg-light border border-warning border-opacity-10">
                                                                        <img src="../assets/images/browsers/edge.png" alt="">
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-medium">Edge</span>
                                                                    <span class="d-block text-muted fs-12">Microsoft Corp,Inc</span>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td class="text-center">978</td>
                                                        <td class="text-center">24.79%</td>
                                                    </tr>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="avatar avatar-rounded avatar-md p-1 bg-light border border-warning border-opacity-10">
                                                                        <img src="../assets/images/browsers/firefox.png" alt="">
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-medium">Firefox</span>
                                                                    <span class="d-block text-muted fs-12">Mozilla,Inc</span>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td class="text-center">815</td>
                                                        <td class="text-center">35.06%</td>
                                                    </tr>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="avatar avatar-rounded avatar-md p-1 bg-light border border-warning border-opacity-10">
                                                                        <img src="../assets/images/browsers/opera.png" alt="">
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-medium">Opera</span>
                                                                    <span class="d-block text-muted fs-12">Opera,Inc</span>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td class="text-center">1347</td>
                                                        <td class="text-center">27.91%</td>
                                                    </tr>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="avatar avatar-rounded avatar-md p-1 bg-light border border-warning border-opacity-10">
                                                                        <img src="../assets/images/browsers/safari.png" alt="">
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-medium">Safari</span>
                                                                    <span class="d-block text-muted fs-12">Apple Corp,Inc</span>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td class="text-center">1123</td>
                                                        <td class="text-center">39.13%</td>
                                                    </tr>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="avatar avatar-rounded avatar-md p-1 bg-light border border-warning border-opacity-10">
                                                                        <img src="../assets/images/browsers/uc.png" alt="">
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-medium">Uc Browser</span>
                                                                    <span class="d-block text-muted fs-12">Uc Browser,Inc</span>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td class="text-center">1189</td>
                                                        <td class="text-center">28.94%</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                <div class="row">
                                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="card custom-card">
                                            <div class="card-body">
                                                <div class="mb-4 d-flex align-items-start justify-content-between flex-wrap">
                                                    <div>
                                                        <span class="fs-12 d-block mb-2 text-muted">Impressions</span>
                                                        <h4 class="mb-0 d-flex gap-2 flex-wrap align-items-center">9,763<span class="text-success fs-12"><i class="ti ti-trending-up align-middle me-1 d-inline-block"></i>0.14%</span></h4>
                                                    </div>
                                                    <div class="dropdown">
                                                        <a aria-label="anchor" href="javascript:void(0);" data-bs-toggle="dropdown">
                                                            <i class="ri-more-2-fill fs-5 text-muted"></i>
                                                        </a>
                                                        <ul class="dropdown-menu" role="menu">
                                                            <li><a class="dropdown-item" href="javascript:void(0);">Day</a></li>
                                                            <li><a class="dropdown-item" href="javascript:void(0);">Week</a></li>
                                                            <li><a class="dropdown-item" href="javascript:void(0);">Year</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div id="impressions"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="card custom-card">
                                            <div class="card-body">
                                                <div class="mb-4 d-flex align-items-start justify-content-between flex-wrap">
                                                    <div>
                                                        <span class="fs-12 d-block mb-2 text-muted">New Subscribers</span>
                                                        <h4 class="mb-0 d-flex gap-2 flex-wrap align-items-center">13,278<span class="text-success fs-12"><i class="ti ti-trending-up align-middle me-1 d-inline-block"></i>0.25%</span></h4>
                                                    </div>
                                                    <div class="dropdown">
                                                        <a aria-label="anchor" href="javascript:void(0);" data-bs-toggle="dropdown">
                                                            <i class="ri-more-2-fill fs-5 text-muted"></i>
                                                        </a>
                                                        <ul class="dropdown-menu" role="menu">
                                                            <li><a class="dropdown-item" href="javascript:void(0);">Day</a></li>
                                                            <li><a class="dropdown-item" href="javascript:void(0);">Week</a></li>
                                                            <li><a class="dropdown-item" href="javascript:void(0);">Year</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div id="new-subscribers"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                        <div class="card custom-card">
                                            <div class="card-header justify-content-between">
                                                <div class="card-title">VISITORS BY BROWSER</div>
                                                <div class="dropdown">
                                                    <a href="javascript:void(0);" class="p-2 fs-12 text-muted" data-bs-toggle="dropdown" aria-expanded="true"> Sort By <i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i> </a>
                                                    <ul class="dropdown-menu" role="menu" style="position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate(0px, 28px);" data-popper-placement="bottom-end">
                                                        <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                                         <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li>
                                                         <li><a class="dropdown-item" href="javascript:void(0);">This Month</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div id="views-browser"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-3 -->

                <!-- Start:: row-4 -->
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    CAMPAIGN ANALYTICS
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <div>
                                        <input class="form-control form-control-sm" type="text" placeholder="Search Here" aria-label=".form-control-sm example">
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="btn btn-primary btn-sm btn-wave waves-effect waves-light" data-bs-toggle="dropdown" aria-expanded="false"> Sort By<i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
                                        </a>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">New</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Popular</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Relevant</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table text-nowrap">
                                        <thead>
                                            <tr>
                                                <th class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob1" value="" aria-label="..."></th>
                                                <th scope="col">Website</th>
                                                <th scope="col">Clicks</th>
                                                <th scope="col">Impressions</th>
                                                <th scope="col">CTR</th>
                                                <th scope="col">Amount Spent</th>
                                                <th scope="col">Cost Per Click</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob2" value="" aria-label="..."></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-danger-transparent">
                                                            <i class="ti ti-brand-youtube fs-16"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Youtube</a></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    1.4K
                                                </td>
                                                <td>143.4K</td>
                                                <td>
                                                    <span class="badge bg-primary-transparent">9.21%</span>
                                                </td>
                                                <td>
                                                    $1.278K
                                                </td>
                                                <td>
                                                   $0.267
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button class="btn btn-sm btn-icon btn-primary-light btn-wave">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-secondary-light btn-wave">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob3" value="" aria-label="..." checked></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-primary-transparent">
                                                            <i class="ti ti-brand-facebook fs-16"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Facebook</a></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    2.68K
                                                </td>
                                                <td>121.8K</td>
                                                <td>
                                                    <span class="badge bg-success-transparent">5.24%</span>
                                                </td>
                                                <td>
                                                    $2.873K
                                                </td>
                                                <td>
                                                   $0.790
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button class="btn btn-sm btn-icon btn-primary-light btn-wave">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-secondary-light btn-wave">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob6" value="" aria-label="..."></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-success-transparent">
                                                            <i class="ti ti-brand-snapchat fs-16"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Snapchat</a></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    1.56K
                                                </td>
                                                <td>155.1K</td>
                                                <td>
                                                    <span class="badge bg-success-transparent">4.89%</span>
                                                </td>
                                                <td>
                                                    $3.344K
                                                </td>
                                                <td>
                                                   $1.656
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button class="btn btn-sm btn-icon btn-primary-light btn-wave">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-secondary-light btn-wave">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob4" value="" aria-label="..."></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-secondary-transparent">
                                                            <i class="ti ti-brand-instagram fs-16"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Instagram</a></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    6.67K
                                                </td>
                                                <td>178.3K</td>
                                                <td>
                                                    <span class="badge bg-secondary-transparent">7.46%</span>
                                                </td>
                                                <td>
                                                    $3.783K
                                                </td>
                                                <td>
                                                   $1.076
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button class="btn btn-sm btn-icon btn-primary-light btn-wave">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-secondary-light btn-wave">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob5" value="" aria-label="..." checked></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-info-transparent">
                                                            <i class="ti ti-brand-linkedin fs-16"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">LinkedIn</a></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    3.21K
                                                </td>
                                                <td>116.2K</td>
                                                <td>
                                                    <span class="badge bg-warning-transparent">1.75%</span>
                                                </td>
                                                <td>
                                                    $2.138K
                                                </td>
                                                <td>
                                                   $0.242
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button class="btn btn-sm btn-icon btn-primary-light btn-wave">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-secondary-light btn-wave">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob7" value="" aria-label="..."></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-warning-transparent">
                                                            <i class="ti ti-brand-reddit fs-16"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Reddit</a></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    1.63K
                                                </td>
                                                <td>157.2K</td>
                                                <td>
                                                    <span class="badge bg-warning-transparent">4.85%</span>
                                                </td>
                                                <td>
                                                    $4.532K
                                                </td>
                                                <td>
                                                   $1.435
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button class="btn btn-sm btn-icon btn-primary-light btn-wave">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-secondary-light btn-wave">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer border-top-0">
                                <div class="d-flex align-items-center">
                                    <div> Showing 6 Entries <i class="bi bi-arrow-right ms-2 fw-semibold"></i> </div>
                                    <div class="ms-auto">
                                    <nav aria-label="Page navigation" class="pagination-style-4">
                                        <ul class="pagination mb-0">
                                            <li class="page-item disabled"> <a class="page-link" href="javascript:void(0);"> Prev </a> </li>
                                                <li class="page-item active"><a class="page-link" href="javascript:void(0);">1</a></li>
                                                <li class="page-item"><a class="page-link" href="javascript:void(0);">2</a></li>
                                                <li class="page-item"> <a class="page-link text-primary" href="javascript:void(0);"> next </a> </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-4 -->

            </div>
        </div>
        <!-- End::app-content -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
