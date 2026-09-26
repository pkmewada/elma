<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Setup</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Master Setup</li>
                </ol>
            </div>
        </div>

        <div class="page-content pb-4">
            <div class="container-fluid px-0">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Table</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table data-ui-table="mamix">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Customer</th>
                                        <th>Mobile</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#19876</td>
                                        <td>Flower Pot</td>
                                        <td>Aarav Sharma</td>
                                        <td>(555) 123-4567</td>
                                        <td>09 Apr 2026</td>
                                        <td><span class="badge bg-success-transparent">Shipped</span></td>
                                        <td>Online Payment</td>
                                        <td class="fw-semibold">INR 4,250</td>
                                        <td>
                                            <button type="button"
                                                class="btn btn-icon btn-sm btn-primary-light btn-wave waves-effect waves-light">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#19877</td>
                                        <td>Head Phones</td>
                                        <td>Riya Patel</td>
                                        <td>(555) 234-5678</td>
                                        <td>08 Apr 2026</td>
                                        <td><span class="badge bg-info-transparent">In Progress</span></td>
                                        <td>Cash On Delivery</td>
                                        <td class="fw-semibold">INR 2,180</td>
                                        <td>
                                            <button type="button"
                                                class="btn btn-icon btn-sm btn-primary-light btn-wave waves-effect waves-light">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#19878</td>
                                        <td>Camera</td>
                                        <td>Kunal Verma</td>
                                        <td>(555) 345-6789</td>
                                        <td>07 Apr 2026</td>
                                        <td><span class="badge bg-danger-transparent">Cancelled</span></td>
                                        <td>Online Payment</td>
                                        <td class="fw-semibold">INR 7,930</td>
                                        <td>
                                            <button type="button"
                                                class="btn btn-icon btn-sm btn-primary-light btn-wave waves-effect waves-light">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Checkbox & Radio</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="mb-3">Colored Checkboxes</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" style="accent-color:#198754;" type="checkbox"
                                        id="check-success" checked>
                                    <label class="form-check-label" for="check-success">Enable notifications</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" style="accent-color:#0d6efd;" type="checkbox"
                                        id="check-primary">
                                    <label class="form-check-label" for="check-primary">Allow API access</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" style="accent-color:#fd7e14;" type="checkbox"
                                        id="check-warning" checked>
                                    <label class="form-check-label" for="check-warning">Require approval for
                                        edits</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">Colored Radios</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" style="accent-color:#6f42c1;" type="radio"
                                        name="sync_mode" id="sync-live" checked>
                                    <label class="form-check-label" for="sync-live">Live sync</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" style="accent-color:#20c997;" type="radio"
                                        name="sync_mode" id="sync-hourly">
                                    <label class="form-check-label" for="sync-hourly">Hourly sync</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" style="accent-color:#dc3545;" type="radio"
                                        name="sync_mode" id="sync-manual">
                                    <label class="form-check-label" for="sync-manual">Manual only</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">File Upload</h5>
                    </div>
                    <div class="card-body">
                        <input type="file" id="setup-file-upload" class="filepond" name="setup_files[]"
                            data-ui-upload="filepond" data-allow-multiple="true" multiple>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Toast Notifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-list">
                            <button type="button" class="btn btn-primary-light me-2 btn-wave" id="primaryToastBtn"
                                data-toast-message="Primary toast message">Primary</button>
                            <button type="button" class="btn btn-secondary-light me-2 btn-wave" id="secondaryToastBtn"
                                data-toast-message="Secondary toast message">secondary</button>
                            <button type="button" class="btn btn-warning-light me-2 btn-wave" id="warningToastBtn"
                                data-toast-message="Warning toast message">warning</button>
                            <button type="button" class="btn btn-info-light me-2 btn-wave" id="infoToastBtn"
                                data-toast-message="Info toast message">info</button>
                            <button type="button" class="btn btn-success-light me-2 btn-wave" id="successToastBtn"
                                data-toast-message="Setup saved successfully.">success</button>
                            <button type="button" class="btn btn-danger-light me-2 btn-wave" id="dangerToastBtn"
                                data-toast-message="Unable to save setup right now.">danger</button>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Form Validation</h5>
                    </div>
                    <div class="card-body">
                        <form class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="validation-name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="validation-name" required>
                                    <div class="invalid-feedback">Name is required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="validation-email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="validation-email" required>
                                    <div class="invalid-feedback">Please enter a valid email.</div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Text Editor</h5>
                    </div>
                    <div class="card-body">
                        <div id="setup-quill-editor" data-ui-editor="quill"
                            data-placeholder="Write details for future setup modules..." style="height:220px;">
                            <p>Start writing reusable setup notes here...</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Floating Labels</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="floatingText" placeholder="Name">
                                    <label for="floatingText">Text Input</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="floatingEmail"
                                        placeholder="name@example.com">
                                    <label for="floatingEmail">Email Input</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Single dropdown buttons</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="btn-list">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a>
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Something else
                                                    here</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-secondary dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a>
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Something else
                                                    here</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-success dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a>
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Something else
                                                    here</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a>
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Something else
                                                    here</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-warning dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a>
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Something else
                                                    here</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-danger dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a>
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Something else
                                                    here</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>

            <div class="row g-4">
                <div class="col-xl-12">
                    <div class="card custom-card">
                        <div class="card-header justify-content-between">
                            <div class="card-title">
                                Colored Headers
                            </div>

                        </div>
                        <div class="card-body">
                            <div class="btn-list">
                                <button type="button" class="btn btn-outline-primary btn-wave waves-effect waves-light"
                                    data-bs-toggle="popover" data-bs-placement="top"
                                    data-bs-custom-class="header-primary" data-bs-content="Popover with primary header."
                                    data-bs-original-title="Color Header">
                                    Header Primary
                                </button>
                                <button type="button"
                                    class="btn btn-outline-secondary btn-wave waves-effect waves-light"
                                    data-bs-toggle="popover" data-bs-placement="right"
                                    data-bs-custom-class="header-secondary"
                                    data-bs-content="Popover with secondary header."
                                    data-bs-original-title="Color Header">
                                    Header Secondary
                                </button>
                                <button type="button" class="btn btn-outline-info btn-wave waves-effect waves-light"
                                    data-bs-toggle="popover" data-bs-placement="bottom"
                                    data-bs-custom-class="header-info" data-bs-content="Popover with info header."
                                    data-bs-original-title="Color Header">
                                    Header Info
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-wave waves-effect waves-light"
                                    data-bs-toggle="popover" data-bs-placement="left"
                                    data-bs-custom-class="header-warning" data-bs-content="Popover with warning header."
                                    data-bs-original-title="Color Header">
                                    Header Warning
                                </button>
                                <button type="button" class="btn btn-outline-success btn-wave waves-effect waves-light"
                                    data-bs-toggle="popover" data-bs-placement="top"
                                    data-bs-custom-class="header-success" data-bs-content="Popover with success header."
                                    data-bs-original-title="Color Header">
                                    Header Success
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-wave waves-effect waves-light"
                                    data-bs-toggle="popover" data-bs-placement="top"
                                    data-bs-custom-class="header-danger" data-bs-content="Popover with danger header."
                                    data-bs-original-title="Color Header">
                                    Header Danger
                                </button>
                            </div>
                        </div>
                        <div class="card-footer d-none border-top-0">
                            <!-- Prism Code -->
                            <pre class="language-html"
                                tabindex="0"><code class="language-html"><span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>div</span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn-list<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>button</span> <span class="token attr-name">type</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>button<span class="token punctuation">"</span></span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn btn-outline-primary btn-wave<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-toggle</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>popover<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-placement</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>top<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-custom-class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>header-primary<span class="token punctuation">"</span></span>
        <span class="token attr-name">title</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Color Header<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-content</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Popover with primary header.<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
        Header Primary
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>button</span><span class="token punctuation">&gt;</span></span>
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>button</span> <span class="token attr-name">type</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>button<span class="token punctuation">"</span></span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn btn-outline-secondary btn-wave<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-toggle</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>popover<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-placement</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>right<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-custom-class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>header-secondary<span class="token punctuation">"</span></span> <span class="token attr-name">title</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Color Header<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-content</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Popover with secondary header.<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
        Header Secondary
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>button</span><span class="token punctuation">&gt;</span></span>
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>button</span> <span class="token attr-name">type</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>button<span class="token punctuation">"</span></span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn btn-outline-info btn-wave<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-toggle</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>popover<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-placement</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>bottom<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-custom-class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>header-info<span class="token punctuation">"</span></span>
        <span class="token attr-name">title</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Color Header<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-content</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Popover with info header.<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
        Header Info
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>button</span><span class="token punctuation">&gt;</span></span>
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>button</span> <span class="token attr-name">type</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>button<span class="token punctuation">"</span></span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn btn-outline-warning btn-wave<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-toggle</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>popover<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-placement</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>left<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-custom-class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>header-warning<span class="token punctuation">"</span></span>
        <span class="token attr-name">title</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Color Header<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-content</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Popover with warning header.<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
        Header Warning
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>button</span><span class="token punctuation">&gt;</span></span>
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>button</span> <span class="token attr-name">type</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>button<span class="token punctuation">"</span></span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn btn-outline-success btn-wave<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-toggle</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>popover<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-placement</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>top<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-custom-class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>header-success<span class="token punctuation">"</span></span>
        <span class="token attr-name">title</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Color Header<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-content</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Popover with success header.<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
        Header Success
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>button</span><span class="token punctuation">&gt;</span></span>
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;</span>button</span> <span class="token attr-name">type</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>button<span class="token punctuation">"</span></span> <span class="token attr-name">class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>btn btn-outline-danger btn-wave<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-toggle</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>popover<span class="token punctuation">"</span></span>
        <span class="token attr-name">data-bs-placement</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>top<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-custom-class</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>header-danger<span class="token punctuation">"</span></span>
        <span class="token attr-name">title</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Color Header<span class="token punctuation">"</span></span> <span class="token attr-name">data-bs-content</span><span class="token attr-value"><span class="token punctuation attr-equals">=</span><span class="token punctuation">"</span>Popover with danger header.<span class="token punctuation">"</span></span><span class="token punctuation">&gt;</span></span>
        Header Danger
    <span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>button</span><span class="token punctuation">&gt;</span></span>
<span class="token tag"><span class="token tag"><span class="token punctuation">&lt;/</span>div</span><span class="token punctuation">&gt;</span></span></code></pre>
                            <!-- Prism Code -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>