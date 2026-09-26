<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">

                <!-- Start::page-header -->
                <div class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap gap-2">
                    <div>
                        <p class="fw-medium fs-20 mb-0">Hello there, Modlus Team</p>
                        <p class="fs-13 text-muted mb-0">Let's make today a productive one!</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-text bg-primary-transparent text-primary"> <i class="ri-calendar-line"></i> </div>
                                <input type="text" class="form-control breadcrumb-input" id="daterange" placeholder="Search By Date Range">
                            </div>
                        </div>
                        <div class="btn-list">
                            <button class="btn btn-secondary-light btn-wave">
                                <i class="ri-upload-cloud-line align-middle me-1 lh-1"></i> Export Report
                            </button>
                            <button class="btn btn-icon btn-success btn-wave me-0">
                                <i class="ri-filter-3-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- End::page-header -->

                <!-- Start:: row-1 -->
                <div class="row">
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <div>
                                            <span class="d-block mb-2">Sales</span>
                                            <h5 class="mb-4 fs-4">32,981</h5>
                                        </div>
                                        <span class="text-success me-2 fw-medium d-inline-block"><i class="ti ti-trending-up fs-5 align-middle me-1 d-inline-block"></i>0.45%</span><span class="text-muted">Since last month</span>
                                    </div>
                                    <div>
                                        <div class="main-card-icon primary">
                                            <div class="avatar avatar-lg bg-primary-transparent border border-primary border-opacity-10">
                                                <div class="avatar avatar-sm svg-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M216,64H176a48,48,0,0,0-96,0H40A16,16,0,0,0,24,80V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V80A16,16,0,0,0,216,64ZM128,32a32,32,0,0,1,32,32H96A32,32,0,0,1,128,32Zm88,168H40V80H80V96a8,8,0,0,0,16,0V80h64V96a8,8,0,0,0,16,0V80h40Z"></path></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card main-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <div>
                                            <span class="d-block mb-2">Profit</span>
                                            <h5 class="mb-4 fs-4">$645</h5>
                                        </div>
                                        <span class="text-success me-2 fw-medium d-inline-block"><i class="ti ti-trending-up fs-5 align-middle me-1 d-inline-block"></i>0.18%</span><span class="text-muted">than last month</span>
                                    </div>
                                    <div>
                                        <div class="main-card-icon secondary">
                                            <div class="avatar avatar-lg bg-secondary-transparent border border-secondary border-opacity-10">
                                                <div class="avatar avatar-sm svg-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M216,72H56a8,8,0,0,1,0-16H192a8,8,0,0,0,0-16H56A24,24,0,0,0,32,64V192a24,24,0,0,0,24,24H216a16,16,0,0,0,16-16V88A16,16,0,0,0,216,72Zm0,128H56a8,8,0,0,1-8-8V86.63A23.84,23.84,0,0,0,56,88H216Zm-48-60a12,12,0,1,1,12,12A12,12,0,0,1,168,140Z"></path></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card main-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <div>
                                            <span class="d-block mb-2">Revenue</span>
                                            <h5 class="mb-4 fs-4">$14,32,145</h5>
                                        </div>
                                        <span class="text-success me-2 fw-medium d-inline-block"><i class="ti ti-trending-up fs-5 align-middle me-1 d-inline-block"></i>0.29%</span><span class="text-muted">Since last month</span>
                                    </div>
                                    <div>
                                        <div class="main-card-icon success">
                                            <div class="avatar avatar-lg bg-success-transparent border border-success border-opacity-10">
                                                <div class="avatar avatar-sm svg-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M200,168a48.05,48.05,0,0,1-48,48H136v16a8,8,0,0,1-16,0V216H104a48.05,48.05,0,0,1-48-48,8,8,0,0,1,16,0,32,32,0,0,0,32,32h48a32,32,0,0,0,0-64H112a48,48,0,0,1,0-96h8V24a8,8,0,0,1,16,0V40h8a48.05,48.05,0,0,1,48,48,8,8,0,0,1-16,0,32,32,0,0,0-32-32H112a32,32,0,0,0,0,64h40A48.05,48.05,0,0,1,200,168Z"></path></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="card custom-card main-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <div>
                                            <span class="d-block mb-2">Views</span>
                                            <h5 class="mb-4 fs-4">4,678</h5>
                                        </div>
                                        <span class="text-danger me-2 fw-medium d-inline-block"><i class="ti ti-trending-down fs-5 align-middle me-1 d-inline-block"></i>0.05%</span><span class="text-muted">Since last month</span>
                                    </div>
                                    <div>
                                        <div class="main-card-icon orange">
                                            <div class="avatar avatar-lg bg-orange-transparent border border-orange border-opacity-10">
                                                <div class="avatar avatar-sm svg-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"></path></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-1 -->

                <!-- Start:: row-instagram-overview -->
                <div class="row" id="instagramOverviewRow" style="display:none;">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">Instagram Overview</div>
                                <a href="instagram-analytics" class="fs-12">View full analytics</a>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-xl-3 col-md-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="ti ti-brand-instagram fs-24 text-primary"></i>
                                            <div>
                                                <div class="fs-20 fw-semibold" id="igConnectedAccounts">—</div>
                                                <div class="text-muted fs-12">Connected Accounts</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="ti ti-users fs-24 text-primary"></i>
                                            <div>
                                                <div class="fs-20 fw-semibold" id="igTotalFollowers">—</div>
                                                <div class="text-muted fs-12">Total Followers</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="ti ti-eye fs-24 text-primary"></i>
                                            <div>
                                                <div class="fs-20 fw-semibold" id="igReachToday">—</div>
                                                <div class="text-muted fs-12">Reach (Today)</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="ti ti-click fs-24 text-primary"></i>
                                            <div>
                                                <div class="fs-20 fw-semibold" id="igInteractionsToday">—</div>
                                                <div class="text-muted fs-12">Interactions (Today)</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-instagram-overview -->

                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card custom-card overflow-hidden sales-statistics-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    SALES STATISTICS
                                </div>
                                <div class="dropdown"> 
                                    <a href="javascript:void(0);" class="p-2 fs-12 text-muted" data-bs-toggle="dropdown" aria-expanded="false"> Sort By <i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i> </a> 
                                    <ul class="dropdown-menu" role="menu"> 
                                        <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li> 
                                         <li><a class="dropdown-item" href="javascript:void(0);">This Month</a></li> 
                                    </ul> 
                                </div>
                            </div>
                            <div class="card-body position-relative p-0">
                                <div id="sales-statistics"></div>
                                <div id="sales-statistics1"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3">
                        <div class="card custom-card">
                            <div class="card-header">
                                <div class="card-title">
                                    TOP SELLING CATEGORIES
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="top-categories"></div>
                                <div class="row mt-0">
                                    <div class="col-6 border-end border-inline-end-dashed text-center">
                                        <p class="text-muted mb-1 fs-12">This Month</p>
                                        <h6 class="text-success">+74.83%</h6>
                                    </div>
                                    <div class="col-6 text-center">
                                        <p class="text-muted mb-1 fs-12">Last Month</p>
                                        <h6 class="text-primary">+56.90%</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-2 -->

                <!-- Start:: row-3 -->
                <div class="row">
                    <div class="col-xl-3">
                        <div class="card custom-card">
                            <div class="card-header">
                                <div class="card-title">
                                    RECENT ACTIVITY
                                </div>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled recent-activity-list">
                                    <li>
                                        <div>
                                            <h6 class="mb-1 fs-13">John Doe<span class="fs-11 text-muted float-end">12:47PM</span></h6>
                                            <span class="d-block fs-13 text-muted fw-normal">Updated profile picture</span>
                                        </div>
                                    </li>
                                    <li>
                                        <div>
                                            <h6 class="mb-1 fs-13">Jane Smith<span class="fs-11 text-muted float-end">10:22AM</span></h6>
                                            <span class="d-block fs-13 text-muted mb-1 fw-normal">Posted a <span class="text-warning fw-medium">new status</span></span>
                                            <div class="p-2 rounded-1 bg-light fs-13">
                                                Enjoying the weekend vibes &#127774;
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div>
                                            <h6 class="mb-1 fs-13">Alice Johnson<span class="fs-11 text-muted float-end">11:45AM</span></h6>
                                            <span class="d-block fs-13 text-muted fw-normal">Commented on a photo - <span class="fw-medium text-success">"Beautiful"</span></span>
                                        </div>
                                    </li>
                                    <li>
                                        <div>
                                            <h6 class="mb-1 fs-13">Charlie Brown<span class="fs-11 text-muted float-end">04:15PM</span></h6>
                                            <span class="d-block fs-13 text-muted fw-normal">Shared an article</span>
                                        </div>
                                    </li>
                                    <li>
                                        <div>
                                            <h6 class="mb-1 fs-13">Bob Anderson<span class="fs-11 text-muted float-end">10:54AM</span></h6>
                                            <span class="d-block fs-13 text-muted fw-normal">Liked a post from <span class="badge bg-secondary-transparent">John Doe</span></span>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    RECENT TRANSACTIONS
                                </div>
                                <a href="javascript:void(0);" class="fs-12 text-muted"> View All<i class="ti ti-arrow-narrow-right ms-1"></i> </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table text-nowrap">
                                        <thead>
                                            <tr>
                                                <th scope="col">Order ID</th>
                                                <th scope="col">Payment Mode</th>
                                                <th scope="col">Amount Paid</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead> 
                                        <tbody>
                                            <tr>
                                                <td><a href="javascript:void(0)" class="fw-medium fs-13">SPK-9ABC</a></td>
                                                <td>
                                                    <div class="d-flex align-items-start gap-2">
                                                        <div>
                                                            <span class="avatar avatar-sm bg-primary-transparent">
                                                                <i class="ri-paypal-line fs-18"></i> 
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span class="d-block fw-medium mb-1">Paypal ****2783</span>
                                                            <span class="d-block fs-11 text-muted">Online Transaction</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="d-block fw-medium mb-1">$1,234.78</span>
                                                        <span class="d-block fs-11 text-muted">Nov 22,2024</span>
                                                    </div>
                                                </td>
                                                <td><span class="text-primary">Completed</span></td>
                                            </tr>
                                            <tr>
                                                <td><a href="javascript:void(0)" class="fw-medium fs-13">SPK-3SFW</a></td>
                                                <td>
                                                    <div class="d-flex align-items-start gap-2">
                                                        <div>
                                                            <span class="avatar avatar-sm bg-secondary-transparent">
                                                                <i class="ri-wallet-3-line fs-18"></i>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span class="d-block fw-medium mb-1">Digital Wallet</span>
                                                            <span class="d-block fs-11 text-muted">Online Transaction</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="d-block fw-medium mb-1">$623.99</span>
                                                        <span class="d-block fs-11 text-muted">Nov 22,2024</span>
                                                    </div>
                                                </td>
                                                <td><span class="text-secondary">Pending</span></td>
                                            </tr>
                                            <tr>
                                                <td><a href="javascript:void(0)" class="fw-medium fs-13">SPK-6SKF</a></td>
                                                <td>
                                                    <div class="d-flex align-items-start gap-2">
                                                        <div>
                                                            <span class="avatar avatar-sm bg-success-transparent">
                                                                <i class="ri-mastercard-line fs-18"></i>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span class="d-block fw-medium mb-1">Mastro Card ****7893</span>
                                                            <span class="d-block fs-11 text-muted">Card Payment</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="d-block fw-medium mb-1">$1,324</span>
                                                        <span class="d-block fs-11 text-muted">Nov 21,2024</span>
                                                    </div>
                                                </td>
                                                <td><span class="text-success">Cancelled</span></td>
                                            </tr>
                                            <tr>
                                                <td><a href="javascript:void(0)" class="fw-medium fs-13">SPK-3ESD</a></td>
                                                <td>
                                                    <div class="d-flex align-items-start gap-2">
                                                        <div>
                                                            <span class="avatar avatar-sm bg-orange-transparent">
                                                                <i class="ti ti-currency-dollar fs-18"></i>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span class="d-block fw-medium mb-1">Cash On Delivery</span>
                                                            <span class="d-block fs-11 text-muted">Pay On Delivery</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="d-block fw-medium mb-1">$1,123.49</span>
                                                        <span class="d-block fs-11 text-muted">Nov 20,2024</span>
                                                    </div>
                                                </td>
                                                <td><span class="text-orange">Completed</span></td>
                                            </tr>
                                            <tr>
                                                <td class="border-bottom-0 fs-13"><a href="javascript:void(0)" class="fw-medium fs-13">SPK-3KSE</a></td>
                                                <td class="border-bottom-0">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <div>
                                                            <span class="avatar avatar-sm bg-info-transparent">
                                                                <i class="ri-visa-line fs-18"></i>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span class="d-block fw-medium mb-1">Visa Card ****2563</span>
                                                            <span class="d-block fs-11 text-muted">Card Payment</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="border-bottom-0">
                                                    <div>
                                                        <span class="d-block fw-medium mb-1">$1,289</span>
                                                        <span class="d-block fs-11 text-muted">Nov 18,2024</span>
                                                    </div>
                                                </td>
                                                <td class="border-bottom-0">
                                                    <span class="text-info">Cancelled</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>    
                    </div>
                    <div class="col-xl-3">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    TOP COUNTRY SALES
                                </div>
                                <div class="dropdown"> 
                                    <a href="javascript:void(0);" class="fs-12 text-muted p-2" data-bs-toggle="dropdown" aria-expanded="false"> Sort By <i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i> </a> 
                                    <ul class="dropdown-menu" role="menu"> 
                                        <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                         <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li> 
                                         <li><a class="dropdown-item" href="javascript:void(0);">This Month</a></li> 
                                    </ul> 
                                </div>
                            </div>
                            <div class="card-body"> 
                                <ul class="list-unstyled mb-0 top-country-sales">
                                    <li>
                                        <div class="d-flex align-items-start flex-wrap gap-2">
                                            <div class="lh-1">
                                                <span class="avatar avatar-sm avatar-rounded text-default p-1 bg-light border">
                                                    <img src="../assets/images/flags/us_flag.jpg" alt="">
                                                </span>
                                            </div>
                                            <div class="flex-fill lh-1">
                                                <span class="fw-medium mb-2 d-block">United States</span>
                                                <span class="d-block text-muted fs-11">32,190 Sales</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fs-10 text-success fw-medium"><i class="ti ti-arrow-narrow-up"></i>0.27%</span>
                                                <span class="text-default fs-13 fw-semibold ms-2">$32,190</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="d-flex align-items-start flex-wrap gap-2">
                                            <div class="lh-1">
                                                <span class="avatar avatar-sm avatar-rounded text-default p-1 bg-light border">
                                                    <img src="../assets/images/flags/germany_flag.jpg" alt="">
                                                </span>
                                            </div>
                                            <div class="flex-fill lh-1">
                                                <span class="fw-medium mb-2 d-block">Germany</span>
                                                <span class="d-block text-muted fs-11">8,798 Sales</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fs-10 text-success fw-medium"><i class="ti ti-arrow-narrow-up"></i>0.12%</span>
                                                <span class="text-default fs-13 fw-semibold ms-2">$29,234</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="d-flex align-items-start flex-wrap gap-2">
                                            <div class="lh-1">
                                                <span class="avatar avatar-sm avatar-rounded text-default p-1 bg-light border">
                                                    <img src="../assets/images/flags/mexico_flag.jpg" alt="">
                                                </span>
                                            </div>
                                            <div class="flex-fill lh-1">
                                                <span class="fw-medium mb-2 d-block">Mexico</span>
                                                <span class="d-block text-muted fs-11">16,885 Sales</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fs-10 text-danger fw-medium"><i class="ti ti-arrow-narrow-down"></i>0.75%</span>
                                                <span class="text-default fs-13 fw-semibold ms-2">$26,166</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="d-flex align-items-start flex-wrap gap-2">
                                            <div class="lh-1">
                                                <span class="avatar avatar-sm avatar-rounded text-default p-1 bg-light border">
                                                    <img src="../assets/images/flags/uae_flag.jpg" alt="">
                                                </span>
                                            </div>
                                            <div class="flex-fill lh-1">
                                                <span class="fw-medium mb-2 d-block">Uae</span>
                                                <span class="d-block text-muted fs-11">14,885 Sales</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fs-10 text-success fw-medium"><i class="ti ti-arrow-narrow-up"></i>1.45%</span>
                                                <span class="text-default fs-13 fw-semibold ms-2">$24,263</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="d-flex align-items-start flex-wrap gap-2">
                                            <div class="lh-1">
                                                <span class="avatar avatar-sm avatar-rounded text-default p-1 bg-light border">
                                                    <img src="../assets/images/flags/argentina_flag.jpg" alt="">
                                                </span>
                                            </div>
                                            <div class="flex-fill lh-1">
                                                <span class="fw-medium mb-2 d-block">Argentina</span>
                                                <span class="d-block text-muted fs-11">17,578 Sales</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fs-10 text-success fw-medium"><i class="ti ti-arrow-narrow-up"></i>0.36%</span>
                                                <span class="text-default fs-13 fw-semibold ms-2">$23,897</span>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="d-flex align-items-start flex-wrap gap-2">
                                            <div class="lh-1">
                                                <span class="avatar avatar-sm avatar-rounded text-default p-1 bg-light border">
                                                    <img src="../assets/images/flags/russia_flag.jpg" alt="">
                                                </span>
                                            </div>
                                            <div class="flex-fill lh-1">
                                                <span class="fw-medium mb-2 d-block">Russia</span>
                                                <span class="d-block text-muted fs-11">10,118 Sales</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="fs-10 text-danger fw-medium"><i class="ti ti-arrow-narrow-down"></i>0.68%</span>
                                                <span class="text-default fs-13 fw-semibold ms-2">$20,212</span>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
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
                                    RECENT ORDERS
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
                                                <th scope="row" class="ps-4"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob1" value="" aria-label="..."></th>
                                                <th scope="col">Product</th>
                                                <th scope="col">Category</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Customer</th>
                                                <th scope="col">Qty</th>
                                                <th scope="col">Date Ordered</th>
                                                <th scope="col" class="text-center">Total Sales</th>
                                                <th scope="col">Price</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ps-4"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob2" value="" aria-label="..."></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <span class="avatar avatar-md"><img src="../assets/images/ecommerce/jpg/1.jpg" class="" alt="..."></span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Flower Pot</a></p>
                                                            <p class="fs-12 text-muted mb-0">Accusam Brand</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    Furniture
                                                </td>
                                                <td><span class="badge bg-primary-transparent">Pending</span></td>
                                                <td>
                                                    <span class="d-block fw-semibold fs-13">Mayor Kelly</span>
                                                    <span class="d-block text-muted fs-12 fw-normal">mayorkelly213@gmail.com</span>
                                                </td>
                                                <td>
                                                   6
                                                </td>
                                                <td>03 Sep 2024</td>
                                                <td class="text-center">
                                                   10
                                                </td>
                                                <td class="fw-semibold">$15,000</td>
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
                                                <td class="ps-4"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob3" value="" aria-label="..." checked=""></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <span class="avatar avatar-md"><img src="../assets/images/ecommerce/jpg/2.jpg" class="" alt="..."></span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold fs-13 mb-0 d-flex align-items-center"><a href="javascript:void(0);">Head Phones</a></p>
                                                            <p class="fs-12 text-muted mb-0">Vellintn Brand</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    Electronics
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-transparent">Processing</span>
                                                </td>
                                                <td>
                                                    <span class="d-block fw-semibold fs-13">Andrew Garfield</span>
                                                    <span class="d-block text-muted fs-12 fw-normal">andrewgarfield1994@gmail.com</span>
                                                </td>
                                                <td>
                                                    1
                                                </td>
                                                <td>05 Oct 2024</td>
                                                <td class="text-center">
                                                    20
                                                </td>
                                                <td class="fw-semibold">$25,000</td>
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
                                                <td class="ps-4"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob4" value="" aria-label="..." checked=""></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <span class="avatar avatar-md"><img src="../assets/images/ecommerce/jpg/4.jpg" class="" alt="..."></span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold mb-0 fs-13 d-flex align-items-center"><a href="javascript:void(0);">Kiwi Fruit</a></p>
                                                            <p class="fs-12 text-muted mb-0">Top Brand</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                   Food
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-transparent">Shipped</span>
                                                </td>
                                                <td>
                                                    <span class="d-block fw-semibold fs-13">Simon Cowel</span>
                                                    <span class="d-block text-muted fs-12 fw-normal">simoncowel26@gmail.com</span>
                                                </td>
                                                <td>
                                                    2
                                                </td>
                                                <td>13 Nov 2024</td>
                                                <td class="text-center">
                                                    27
                                                </td>
                                                <td class="fw-semibold">$43,000</td>
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
                                                <td class="ps-4"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob5" value="" aria-label="..."></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <span class="avatar avatar-md"><img src="../assets/images/ecommerce/jpg/5.jpg" class="" alt="..."></span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold mb-0 fs-13 d-flex align-items-center"><a href="javascript:void(0);">Donut</a></p>
                                                            <p class="fs-12 text-muted mb-0">Erat Brand</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    Food
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning-transparent">On Hold</span>
                                                </td>
                                                <td>
                                                    <span class="d-block fw-semibold fs-13">Mirinda Hers</span>
                                                    <span class="d-block text-muted fs-12 fw-normal">mirindahers@hotmail.com</span>
                                                </td>
                                                <td>
                                                    2
                                                </td>
                                                <td>15 Dec 2024</td>
                                                <td class="text-center">
                                                    34
                                                </td>
                                                <td class="fw-semibold">$10,000</td>
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
                                                <td class="ps-4"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob6" value="" aria-label="..." checked=""></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <span class="avatar avatar-md"><img src="../assets/images/ecommerce/jpg/2.jpg" class="" alt="..."></span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold mb-0 fs-13 d-flex align-items-center"><a href="javascript:void(0);">Head Phones</a></p>
                                                            <p class="fs-12 text-muted mb-0">Boalt Audio</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    Electronics
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-transparent">Delivered</span>
                                                </td>
                                                <td>
                                                    <span class="d-block fw-semibold fs-13">Alicia Keys</span>
                                                    <span class="d-block text-muted fs-12 fw-normal">aliciakeys@gmail.com</span>
                                                </td>
                                                <td>
                                                    1
                                                </td>
                                                <td>28 Dec 2024</td>
                                                <td class="text-center">
                                                    77
                                                </td>
                                                <td class="fw-semibold">$4,000</td>
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
                                                <td class="ps-4 border-bottom-0"><input class="form-check-input" type="checkbox" id="checkboxNoLabeljob7" value="" aria-label="..."></td>
                                                <td class="border-bottom-0">
                                                    <div class="d-flex">
                                                        <span class="avatar avatar-md"><img src="../assets/images/ecommerce/jpg/3.jpg" class="" alt="..."></span>
                                                        <div class="ms-2">
                                                            <p class="fw-semibold mb-0 fs-13 d-flex align-items-center"><a href="javascript:void(0);">Camera</a></p>
                                                            <p class="fs-12 text-muted mb-0">Analog.Comp</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="border-bottom-0">
                                                    Electronics
                                                </td>
                                                <td class="border-bottom-0">
                                                    <span class="badge bg-danger-transparent">Cancelled</span>
                                                </td>
                                                <td class="border-bottom-0">
                                                    <span class="d-block fw-semibold fs-13">Jeremy Lewis</span>
                                                    <span class="d-block text-muted fs-12 fw-normal">jeremylewis2000@gmail.com</span>
                                                </td>
                                                <td class="border-bottom-0">
                                                    3
                                                </td>
                                                <td class="border-bottom-0">15 Dec 2024</td>
                                                <td class="border-bottom-0 text-center">
                                                    19
                                                </td>
                                                <td class="fw-semibold border-bottom-0">$16,000</td>
                                                <td class="border-bottom-0">
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
                            <div class="card-footer"> 
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

<script>
fetch(API_BASE + '/instagram/getInstagramDashboardSummary.php')
    .then(function(res) { return res.json(); })
    .then(function(res) {
        if (!res || !res.success || !res.data || !res.data.summary) {
            return;
        }

        var summary = res.data.summary;

        if (summary.connectedAccounts <= 0) {
            return;
        }

        document.getElementById('igConnectedAccounts').textContent = summary.connectedAccounts.toLocaleString();
        document.getElementById('igTotalFollowers').textContent = summary.totalFollowers.toLocaleString();
        document.getElementById('igReachToday').textContent = summary.totalReachToday.toLocaleString();
        document.getElementById('igInteractionsToday').textContent = summary.totalInteractionsToday.toLocaleString();
        document.getElementById('instagramOverviewRow').style.display = '';
    })
    .catch(function() {});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>