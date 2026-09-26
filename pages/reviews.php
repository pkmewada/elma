<?php
include __DIR__ . "/../includes/auth.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Business Reviews</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="javascript:void(0);">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Reviews</li>
                    <li class="breadcrumb-item active">Business Reviews</li>
                </ol>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    onclick="openCreateModal()">

                    <i class="ri-add-line me-1"></i>
                    Create Business Review
                </button>
            </div>
        </div>

        <!-- FILTER -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">

                    <div class="col-xl-4 col-md-5">
                        <label class="filter-label">Search</label>

                        <div class="search-wrapper">
                            <i class="ri-search-line"></i>

                            <input
                                type="text"
                                id="searchBusiness"
                                class="form-control form-control-sm"
                                placeholder="Search business, client, category...">
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-3">
                        <label class="filter-label">Category</label>

                        <select id="filterCategory" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                        </select>
                    </div>

                    <div class="col-xl-3 col-md-3">
                        <label class="filter-label">Client Name</label>

                        <select id="filterOwner" class="form-select form-select-sm">
                            <option value="">All Clients</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-md-1">
                        <button
                            type="button"
                            class="btn btn-primary btn-sm w-100"
                            onclick="resetFilters()">

                            <i class="ri-refresh-line me-1"></i>

                        </button>
                    </div>

                </div>
            </div>
        </div>
        <!-- SUMMARY -->
        <div class="row g-2 mb-3">

            <div class="col-6 col-md-3">
                <div class="card custom-card summary-card mb-0">
                    <div class="card-body text-center py-3">
                        <div class="summary-number" id="totalCount">0</div>
                        <div class="summary-label">Total Businesses</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card custom-card summary-card mb-0">
                    <div class="card-body text-center py-3">
                        <div class="summary-number text-success" id="reviewCount">0</div>
                        <div class="summary-label">Total Reviews</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card custom-card summary-card mb-0">
                    <div class="card-body text-center py-3">
                        <div class="summary-number text-primary" id="ownerCount">0</div>
                        <div class="summary-label">Clients</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card custom-card summary-card mb-0">
                    <div class="card-body text-center py-3">
                        <div class="summary-number" id="categoryCount">0</div>
                        <div class="summary-label">Categories</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- BUSINESS TABLE -->
        <div class="card custom-card">

            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Business Reviews</h5>

                    <span class="text-muted fs-12">
                        Manage business review experiences
                    </span>
                </div>

                <span id="tableCount" class="badge bg-primary-subtle text-primary">
                    0 total
                </span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">

                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Business Name</th>
                                <th>Client Name</th>
                                <th>Category</th>
                                <th>Reviews</th>
                                <th>Review Data</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody id="businessTableBody"></tbody>
                    </table>

                </div>
            </div>
        </div>

    </div>
</div>


<!-- BUSINESS CREATE / EDIT MODAL -->
<div class="modal fade" id="businessModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="modalTitle">
                        Create Business Review
                    </h5>

                    <small class="text-muted">
                        Add business review details
                    </small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">

                <form id="businessForm">

                    <input type="hidden" id="businessId">

                    <div class="row g-3">

                        <!-- CLIENT NAME -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Client Name *
                            </label>

                            <select
                                id="businessOwner"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Client
                                </option>
                            </select>

                            <small class="text-muted">
                                Select an existing client.
                            </small>

                        </div>

                        <!-- BUSINESS NAME -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Business Name *
                            </label>

                            <input
                                type="text"
                                id="businessName"
                                class="form-control"
                                placeholder="CFS Gym"
                                required>

                        </div>

                        <!-- CATEGORY -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Category *
                            </label>

                            <select
                                id="businessCategory"
                                class="form-select"
                                required>

                                <option value="">Select Category</option>
                                <option>Fitness & Sports</option>
                                <option>Beauty & Personal Care</option>
                                <option>Healthcare & Medical</option>
                                <option>Professional Services</option>
                                <option>IT & Technology</option>
                                <option>Automobile</option>
                                <option>Education & Training</option>
                                <option>Travel & Hospitality</option>
                                <option>Events & Media</option>

                            </select>
                        </div>

                        <!-- GOOGLE URL -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Google Review URL *
                            </label>

                            <input
                                type="url"
                                id="googleReviewUrl"
                                class="form-control"
                                placeholder="https://g.page/r/xxxxx/review"
                                required>

                        </div>

                    </div>

                </form>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light btn-sm"
                    data-bs-dismiss="modal">
                    Cancel
                </button>

                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    onclick="saveBusiness()">

                    <i class="ri-save-line me-1"></i>

                    <span id="saveButtonText">
                        Save Business
                    </span>
                </button>

            </div>

        </div>
    </div>
</div>


<!-- REVIEWS LIST MODAL -->
<div class="modal fade" id="reviewsModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-xl">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title" id="reviewsModalTitle">
                        Reviews
                    </h5>

                    <small class="text-muted" id="reviewsModalSubtitle"></small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">

                <div class="d-flex align-items-center justify-content-between mb-3">

                    <span class="text-muted fs-12">
                        Review Data
                    </span>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        onclick="openAddReviewModal()">

                        <i class="ri-add-line me-1"></i>
                        Add Review
                    </button>

                </div>

                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>
                            <tr>
                                <th>Star</th>
                                <th>Review</th>
                                <th>Total</th>
                                <th>Used</th>
                                <th>Remaining</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody id="reviewsTableBody"></tbody>

                    </table>

                </div>

            </div>
        </div>
    </div>
</div>


<!-- SIMPLE ONE-PAGE REVIEW MODAL -->
<div class="modal fade" id="reviewEditorModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title" id="reviewEditorTitle">
                        Add Review
                    </h5>

                    <small class="text-muted">
                        Add review manually or import Excel
                    </small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">

                <form id="reviewForm">

                    <input type="hidden" id="reviewId">

                    <div class="row g-3">

                        <!-- CLIENT NAME -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Client Name *
                            </label>

                            <select
                                id="reviewOwner"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Client
                                </option>
                            </select>

                        </div>

                        <!-- RATING -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Rating *
                            </label>

                            <select
                                id="reviewRating"
                                class="form-select"
                                required>

                                <option value="">Select Rating</option>
                                <option value="5">★★★★★ — 5 Star</option>
                                <option value="4">★★★★☆ — 4 Star</option>
                                <option value="3">★★★☆☆ — 3 Star</option>
                                <option value="2">★★☆☆☆ — 2 Star</option>
                                <option value="1">★☆☆☆☆ — 1 Star</option>

                            </select>

                        </div>

                        <!-- REVIEW -->
                        <div class="col-12">

                            <label class="form-label">
                                Review *
                            </label>

                            <textarea
                                id="reviewText"
                                class="form-control"
                                rows="5"
                                placeholder="Write customer review..."
                                required></textarea>

                        </div>

                        <!-- DATE -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Review Date *
                            </label>

                            <input
                                type="date"
                                id="reviewDate"
                                class="form-control"
                                required>

                        </div>

                        <!-- SAMPLE -->
                        <div class="col-md-6 d-flex align-items-end gap-2 flex-wrap">

                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                onclick="loadSampleReview()">

                                <i class="ri-file-copy-line me-1"></i>
                                Sample
                            </button>

                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                onclick="openExcelFilePicker()">

                                <i class="ri-file-excel-2-line me-1"></i>
                                Import Excel
                            </button>

                            <input
                                type="file"
                                id="excelFile"
                                accept=".xlsx,.xls,.csv"
                                hidden>

                        </div>

                    </div>

                </form>


                <!-- EXCEL PREVIEW ON SAME PAGE -->
                <div id="excelPreviewWrapper" class="d-none mt-4">

                    <div class="mb-2">
                        <strong>Import Preview</strong>

                        <span
                            class="text-muted fs-12"
                            id="excelPreviewCount">
                        </span>
                    </div>

                    <div class="table-responsive">

                        <table class="table table-sm align-middle">

                            <thead>
                                <tr>
                                    <th>Owner Name</th>
                                    <th>Rating</th>
                                    <th>Review</th>
                                    <th>Review Date</th>
                                </tr>
                            </thead>

                            <tbody id="excelPreviewBody"></tbody>

                        </table>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light btn-sm"
                    data-bs-dismiss="modal">
                    Cancel
                </button>

                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    id="saveReviewButton"
                    onclick="saveReview()">

                    <i class="ri-save-line me-1"></i>
                    Save Review
                </button>

            </div>

        </div>
    </div>
</div>


<style>
.custom-card {
    background: #fff;
    border: 1px solid #e9edf4;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(20,20,40,.025);
    margin-bottom: 16px;
}

.card-header {
    background: #fff;
    border-bottom: 1px solid #e9edf4;
    padding: 15px 18px;
}

.filter-label {
    display: block;
    font-size: 12px;
    color: #687080;
    font-weight: 500;
    margin-bottom: 5px;
}

.search-wrapper {
    position: relative;
}

.search-wrapper i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #9aa1af;
    z-index: 2;
}

.search-wrapper input {
    padding-left: 34px;
}

.form-control,
.form-select {
    border-color: #dfe3eb;
    border-radius: 7px;
    font-size: 13px;
    min-height: 36px;
}

.form-control:focus,
.form-select:focus {
    border-color: #845adf;
    box-shadow: 0 0 0 .15rem rgba(132,90,223,.10);
}

.summary-card {
    transition: .2s;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-number {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 6px;
}

.summary-label {
    color: #7b8191;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .3px;
}

.table {
    margin-bottom: 0;
    min-width: 950px;
}

.table thead th {
    background: #fafbfc;
    color: #687080;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .35px;
    white-space: nowrap;
}

.table tbody td {
    font-size: 13px;
    color: #4b5563;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #faf9ff;
}

.business-name {
    font-weight: 600;
    color: #273142;
}

.business-slug {
    color: #9aa1af;
    font-size: 11px;
    margin-top: 2px;
}

.review-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 500;
}

.badge-purple {
    color: #6f42c1;
    background: rgba(132,90,223,.10);
}

.badge-blue {
    color: #0d6efd;
    background: rgba(13,110,253,.10);
}

.badge-green {
    color: #198754;
    background: rgba(25,135,84,.10);
}

.action-group {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 5px;
}

.action-btn {
    width: 31px;
    height: 31px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e3e6ec;
    background: #fff;
    border-radius: 6px;
    color: #687080;
    cursor: pointer;
    text-decoration: none;
    transition: .15s;
}

.action-btn:hover {
    border-color: #845adf;
    color: #845adf;
    background: rgba(132,90,223,.08);
}

.action-btn.delete:hover {
    border-color: #dc3545;
    color: #dc3545;
    background: rgba(220,53,69,.08);
}

.storage-info {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #198754;
    background: rgba(25,135,84,.08);
    border: 1px solid rgba(25,135,84,.12);
    padding: 5px 9px;
    border-radius: 5px;
}

.empty-state {
    text-align: center;
    padding: 55px 20px !important;
}

.empty-icon {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    background: rgba(132,90,223,.10);
    color: #845adf;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    margin-bottom: 12px;
}

.review-text-cell {
    max-width: 480px;
    white-space: normal;
}

.star-text {
    white-space: nowrap;
    letter-spacing: 1px;
}

@media(max-width:768px) {
    .action-group {
        justify-content: flex-start;
    }

    .storage-info {
        display: none;
    }

    .table {
        min-width: 950px;
    }
}
</style>


<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>
"use strict";

const BUSINESS_STORAGE_KEY = "revmeai_business_reviews";
const REVIEW_STORAGE_KEY = "revmeai_generated_reviews";
const CLIENT_STORAGE_KEY = "revmeai_clients";

let businesses = [];
let generatedReviews = {};
let currentBusinessId = null;
let currentReviewId = null;
let importedExcelReviews = [];

const modalInstances = {};

function $(id) {
    return document.getElementById(id);
}


/* MODAL HELPERS */
function getModalInstance(id) {
    const element = $(id);

    if (!element) {
        return null;
    }

    if (
        window.bootstrap &&
        typeof window.bootstrap.Modal === "function"
    ) {
        if (!modalInstances[id]) {
            modalInstances[id] =
                window.bootstrap.Modal.getOrCreateInstance(element);
        }

        return modalInstances[id];
    }

    return {
        show: () => openFallbackModal(element),
        hide: () => closeFallbackModal(element)
    };
}

function openFallbackModal(element) {
    element.classList.add("show");
    element.style.display = "block";
    element.removeAttribute("aria-hidden");
    element.setAttribute("aria-modal", "true");
    document.body.classList.add("modal-open");

    let backdrop = document.querySelector(
        ".modal-backdrop[data-fallback-modal='1']"
    );

    if (!backdrop) {
        backdrop = document.createElement("div");
        backdrop.className = "modal-backdrop fade show";
        backdrop.dataset.fallbackModal = "1";
        document.body.appendChild(backdrop);
    }
}

function closeFallbackModal(element) {
    element.classList.remove("show");
    element.style.display = "none";
    element.setAttribute("aria-hidden", "true");
    element.removeAttribute("aria-modal");
    document.body.classList.remove("modal-open");

    const backdrop = document.querySelector(
        ".modal-backdrop[data-fallback-modal='1']"
    );

    if (backdrop) {
        backdrop.remove();
    }
}

function showModal(id) {
    const modal = getModalInstance(id);

    if (modal) {
        modal.show();
    }
}

function hideModal(id) {
    const modal = getModalInstance(id);

    if (modal) {
        modal.hide();
    }
}


/* ALERT HELPERS */
function alertMessage(title, text = "", icon = "success") {
    if (window.Swal && typeof window.Swal.fire === "function") {
        return Swal.fire({
            title,
            text,
            icon
        });
    }

    alert(text ? title + "\n\n" + text : title);
}

async function confirmAction(title, text) {
    if (window.Swal && typeof window.Swal.fire === "function") {
        const result = await Swal.fire({
            title,
            text,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Delete",
            cancelButtonText: "Cancel"
        });

        return result.isConfirmed;
    }

    return confirm(title + "\n\n" + text);
}


/* INIT */
document.addEventListener("DOMContentLoaded", function () {
    loadStorage();
    normalizeStorage();
    populateClientDropdowns();
    populateFilters();
    renderTable();
    updateSummary();
    bindEvents();
    setupModalDismissFallback();
    setDefaultReviewDate();
});


function bindEvents() {
    $("searchBusiness").addEventListener("input", renderTable);
    $("filterCategory").addEventListener("change", renderTable);
    $("filterOwner").addEventListener("change", renderTable);

    $("excelFile").addEventListener("change", handleExcelImport);

    document.addEventListener("click", function (event) {
        const dismiss = event.target.closest(
            '[data-bs-dismiss="modal"]'
        );

        if (!dismiss) {
            return;
        }

        const modal = dismiss.closest(".modal");

        if (
            modal &&
            !(
                window.bootstrap &&
                typeof window.bootstrap.Modal === "function"
            )
        ) {
            closeFallbackModal(modal);
        }
    });
}

function setupModalDismissFallback() {
    document.addEventListener("click", function (event) {
        if (!event.target.classList.contains("modal")) {
            return;
        }

        if (
            event.target.classList.contains("show") &&
            !(
                window.bootstrap &&
                typeof window.bootstrap.Modal === "function"
            )
        ) {
            closeFallbackModal(event.target);
        }
    });
}


/* STORAGE */
function loadStorage() {
    try {
        businesses = JSON.parse(
            localStorage.getItem(BUSINESS_STORAGE_KEY) || "[]"
        );

        if (!Array.isArray(businesses)) {
            businesses = [];
        }
    } catch (error) {
        businesses = [];
    }

    try {
        generatedReviews = JSON.parse(
            localStorage.getItem(REVIEW_STORAGE_KEY) || "{}"
        );

        if (
            !generatedReviews ||
            typeof generatedReviews !== "object" ||
            Array.isArray(generatedReviews)
        ) {
            generatedReviews = {};
        }
    } catch (error) {
        generatedReviews = {};
    }
}

function normalizeStorage() {
    businesses = businesses.map(function (business) {
        const clientName =
            business.clientName ||
            business.ownerName ||
            business.reviewerName ||
            "";

        return {
            id: business.id || createId(),

            clientName,

            ownerName: clientName,

            businessName:
                business.businessName ||
                business.name ||
                "—",

            category: business.category || "",

            googleReviewUrl:
                business.googleReviewUrl ||
                business.google_url ||
                business.googleUrl ||
                "",

            reviewLimit: Number(business.reviewLimit || 100),

            createdAt:
                business.createdAt ||
                new Date().toISOString(),

            updatedAt:
                business.updatedAt ||
                business.createdAt ||
                new Date().toISOString()
        };
    });

    if (Array.isArray(generatedReviews)) {
        const grouped = {};

        generatedReviews.forEach(function (review) {
            if (!review.businessId) {
                return;
            }

            const key = String(review.businessId);

            if (!grouped[key]) {
                grouped[key] = [];
            }

            grouped[key].push(normalizeReview(review, key));
        });

        generatedReviews = grouped;
    }

    Object.keys(generatedReviews).forEach(function (businessId) {
        if (!Array.isArray(generatedReviews[businessId])) {
            generatedReviews[businessId] = [];
        } else {
            generatedReviews[businessId] =
                generatedReviews[businessId].map(function (review) {
                    return normalizeReview(review, businessId);
                });
        }
    });

    saveBusinessStorage();
    saveReviewStorage();
}

function normalizeReview(review, businessId) {
    const clientName =
        review.clientName ||
        review.ownerName ||
        review.reviewerName ||
        "";

    return {
        id: review.id || createId(),

        businessId: review.businessId || businessId,

        clientName,

        ownerName: clientName,

        rating: Math.min(
            5,
            Math.max(1, Number(review.rating || 5))
        ),

        reviewText:
            review.reviewText ||
            review.review ||
            review.comment ||
            "",

        reviewDate:
            review.reviewDate ||
            review.date ||
            todayDate(),

        createdAt:
            review.createdAt ||
            new Date().toISOString(),

        updatedAt:
            review.updatedAt ||
            review.createdAt ||
            new Date().toISOString()
    };
}

function saveBusinessStorage() {
    localStorage.setItem(
        BUSINESS_STORAGE_KEY,
        JSON.stringify(businesses)
    );
}

function saveReviewStorage() {
    localStorage.setItem(
        REVIEW_STORAGE_KEY,
        JSON.stringify(generatedReviews)
    );
}

function createId() {
    return Date.now().toString() +
        Math.random().toString(36).substring(2, 9);
}


/* CLIENT DROPDOWNS */
function getClientNames() {
    const clients = [];

    businesses.forEach(function (business) {
        const name =
            business.clientName ||
            business.ownerName ||
            "";

        if (name.trim()) {
            clients.push(name.trim());
        }
    });

    /*
     * Optional existing client list.
     * No add-client button is displayed.
     */
    try {
        const savedClients = JSON.parse(
            localStorage.getItem(CLIENT_STORAGE_KEY) || "[]"
        );

        if (Array.isArray(savedClients)) {
            savedClients.forEach(function (client) {
                const name =
                    typeof client === "string"
                        ? client
                        : client.name || client.clientName || "";

                if (String(name).trim()) {
                    clients.push(String(name).trim());
                }
            });
        }
    } catch (error) {
        console.warn("Client list could not be loaded.");
    }

    return [...new Set(clients)].sort(function (a, b) {
        return a.localeCompare(b);
    });
}

function populateClientDropdowns() {
    const clients = getClientNames();

    const businessSelect = $("businessOwner");
    const reviewSelect = $("reviewOwner");

    const currentBusinessValue = businessSelect.value;
    const currentReviewValue = reviewSelect.value;

    businessSelect.innerHTML =
        '<option value="">Select Client</option>';

    reviewSelect.innerHTML =
        '<option value="">Select Client</option>';

    clients.forEach(function (client) {
        addOption(businessSelect, client, client);
        addOption(reviewSelect, client, client);
    });

    if (currentBusinessValue) {
        businessSelect.value = currentBusinessValue;
    }

    if (currentReviewValue) {
        reviewSelect.value = currentReviewValue;
    }
}

function addOption(select, value, text) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = text;
    select.appendChild(option);
}

function selectHasValue(select, value) {
    return Array.from(select.options).some(function (option) {
        return option.value === value;
    });
}


/* BUSINESS CREATE */
function openCreateModal() {
    $("businessForm").reset();
    $("businessId").value = "";

    $("modalTitle").textContent = "Create Business Review";
    $("saveButtonText").textContent = "Save Business";

    populateClientDropdowns();

    $("businessOwner").value = "";

    showModal("businessModal");
}


/* BUSINESS EDIT */
function editBusiness(id) {
    const business = businesses.find(function (item) {
        return String(item.id) === String(id);
    });

    if (!business) {
        alertMessage("Business not found", "", "error");
        return;
    }

    populateClientDropdowns();

    const clientName =
        business.clientName ||
        business.ownerName ||
        "";

    if (
        clientName &&
        !selectHasValue($("businessOwner"), clientName)
    ) {
        addOption($("businessOwner"), clientName, clientName);
    }

    $("businessId").value = business.id;
    $("businessOwner").value = clientName;
    $("businessName").value = business.businessName || "";
    $("businessCategory").value = business.category || "";
    $("googleReviewUrl").value = business.googleReviewUrl || "";

    $("modalTitle").textContent = "Edit Business Review";
    $("saveButtonText").textContent = "Save Changes";

    showModal("businessModal");
}


/* SAVE BUSINESS */
function saveBusiness() {
    const form = $("businessForm");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = $("businessId").value.trim();
    const clientName = $("businessOwner").value.trim();
    const businessName = $("businessName").value.trim();
    const category = $("businessCategory").value.trim();
    const googleReviewUrl = $("googleReviewUrl").value.trim();

    if (!clientName) {
        alertMessage(
            "Client required",
            "Please select an existing client.",
            "warning"
        );
        return;
    }

    const now = new Date().toISOString();

    if (id) {
        const index = businesses.findIndex(function (item) {
            return String(item.id) === String(id);
        });

        if (index !== -1) {
            businesses[index] = {
                ...businesses[index],
                clientName,
                ownerName: clientName,
                businessName,
                category,
                googleReviewUrl,
                updatedAt: now
            };
        }
    } else {
        businesses.unshift({
            id: createId(),
            clientName,
            ownerName: clientName,
            businessName,
            category,
            googleReviewUrl,
            reviewLimit: 100,
            createdAt: now,
            updatedAt: now
        });
    }

    saveBusinessStorage();

    populateClientDropdowns();
    populateFilters();
    renderTable();
    updateSummary();

    hideModal("businessModal");

    alertMessage(
        id ? "Business updated" : "Business created",
        id
            ? "Business details have been updated."
            : "Business review business has been created.",
        "success"
    );
}


/* DELETE BUSINESS */
async function deleteBusiness(id) {
    const business = businesses.find(function (item) {
        return String(item.id) === String(id);
    });

    if (!business) {
        return;
    }

    const confirmed = await confirmAction(
        "Delete business?",
        '"' + business.businessName +
        '" and its saved reviews will be deleted.'
    );

    if (!confirmed) {
        return;
    }

    businesses = businesses.filter(function (item) {
        return String(item.id) !== String(id);
    });

    delete generatedReviews[String(id)];

    saveBusinessStorage();
    saveReviewStorage();

    populateClientDropdowns();
    populateFilters();
    renderTable();
    updateSummary();

    alertMessage(
        "Deleted",
        "Business and its reviews were deleted.",
        "success"
    );
}


/* FILTERS */
function populateFilters() {
    const categorySelect = $("filterCategory");
    const ownerSelect = $("filterOwner");

    const currentCategory = categorySelect.value;
    const currentOwner = ownerSelect.value;

    const categories = [
        ...new Set(
            businesses
                .map(function (item) {
                    return item.category;
                })
                .filter(Boolean)
        )
    ].sort();

    const clients = getClientNames();

    categorySelect.innerHTML =
        '<option value="">All Categories</option>';

    clients.forEach(function (client) {
        addOption(ownerSelect, client, client);
    });

    ownerSelect.innerHTML =
        '<option value="">All Clients</option>';

    clients.forEach(function (client) {
        addOption(ownerSelect, client, client);
    });

    categories.forEach(function (category) {
        addOption(categorySelect, category, category);
    });

    if (categories.includes(currentCategory)) {
        categorySelect.value = currentCategory;
    }

    if (clients.includes(currentOwner)) {
        ownerSelect.value = currentOwner;
    }
}


/* FILTERED BUSINESSES */
function getFilteredBusinesses() {
    const search = $("searchBusiness").value.toLowerCase().trim();
    const category = $("filterCategory").value;
    const owner = $("filterOwner").value;

    return businesses.filter(function (business) {
        const clientName =
            business.clientName ||
            business.ownerName ||
            "";

        const text = (
            business.businessName +
            " " +
            clientName +
            " " +
            business.category
        ).toLowerCase();

        return (
            (!search || text.includes(search)) &&
            (!category || business.category === category) &&
            (!owner || clientName === owner)
        );
    });
}


/* REVIEWS */
function getReviews(businessId) {
    const key = String(businessId);

    if (
        !generatedReviews[key] ||
        !Array.isArray(generatedReviews[key])
    ) {
        generatedReviews[key] = [];
    }

    return generatedReviews[key];
}


/* TABLE */
function renderTable() {
    const tbody = $("businessTableBody");
    const data = getFilteredBusinesses();

    $("tableCount").textContent = data.length + " total";

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <div class="empty-icon">
                        <i class="ri-store-2-line"></i>
                    </div>

                    <h6>No business reviews found</h6>

                    <p class="text-muted">
                        Create your first business review.
                    </p>

                    <button
                        class="btn btn-primary btn-sm"
                        onclick="openCreateModal()">

                        <i class="ri-add-line me-1"></i>
                        Create Business Review
                    </button>
                </td>
            </tr>
        `;

        return;
    }

    tbody.innerHTML = data.map(function (business, index) {
        const reviews = getReviews(business.id);
        const total = Number(business.reviewLimit || 100);
        const used = reviews.length;
        const clientName =
            business.clientName ||
            business.ownerName ||
            "";

        return `
            <tr>
                <td>${index + 1}</td>

                <td>
                    <div class="business-name">
                        ${escapeHtml(business.businessName)}
                    </div>

                    <div class="business-slug">
                        ${escapeHtml(createSlug(business.businessName))}
                    </div>
                </td>

                <td>
                    <span class="review-badge badge-blue">
                        <i class="ri-user-line"></i>
                        ${escapeHtml(clientName)}
                    </span>
                </td>

                <td>
                    <span class="review-badge badge-purple">
                        <i class="ri-price-tag-3-line"></i>
                        ${escapeHtml(business.category)}
                    </span>
                </td>

                <td>
                    <button
                        type="button"
                        class="btn btn-light btn-sm"
                        onclick="viewReviews('${escapeJs(business.id)}')">

                        <i class="ri-eye-line me-1"></i>
                        View

                        <span class="badge bg-primary ms-1">
                            ${used}
                        </span>
                    </button>
                </td>

                <td>
                    <span class="review-badge badge-green">
                        ${total} / ${used}
                    </span>
                </td>

                <td>
                    <div class="action-group">

                        <a
                            href="${escapeAttribute(buildQRUrl(business))}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="action-btn"
                            title="QR Code">

                            <i class="ri-qr-code-line"></i>
                        </a>

                        <a
                            href="${escapeAttribute(buildChatbotUrl(business))}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="action-btn"
                            title="Chatbot">

                            <i class="ri-robot-line"></i>
                        </a>

                        <button
                            type="button"
                            class="action-btn"
                            title="Add Review"
                            onclick="openAddReviewForBusiness('${escapeJs(business.id)}')">

                            <i class="ri-add-line"></i>
                        </button>

                        <button
                            type="button"
                            class="action-btn"
                            title="Edit"
                            onclick="editBusiness('${escapeJs(business.id)}')">

                            <i class="ri-edit-line"></i>
                        </button>

                        <button
                            type="button"
                            class="action-btn delete"
                            title="Delete"
                            onclick="deleteBusiness('${escapeJs(business.id)}')">

                            <i class="ri-delete-bin-line"></i>
                        </button>

                    </div>
                </td>
            </tr>
        `;
    }).join("");
}


/* URLS */
function buildQRUrl(business) {
    const clientName =
        business.clientName ||
        business.ownerName ||
        "";

    const params = new URLSearchParams({
        businessId: String(business.id || ""),
        business: business.businessName || "",
        ownerName: clientName,
        clientName,
        category: business.category || "",
        googleReviewUrl: business.googleReviewUrl || "",
        google_url: business.googleReviewUrl || ""
    });

    return "http://localhost/modlus/modlus/qrcode?" + params.toString();
}

function buildChatbotUrl(business) {
    const clientName =
        business.clientName ||
        business.ownerName ||
        "";

    const params = new URLSearchParams({
        businessId: String(business.id || ""),
        business: business.businessName || "",
        ownerName: clientName,
        clientName,
        category: business.category || "",
        googleReviewUrl: business.googleReviewUrl || "",
        google_url: business.googleReviewUrl || ""
    });

    return "http://localhost/modlus/modlus/generate-review?" + params.toString();
}


/* VIEW REVIEWS */
function viewReviews(id) {
    const business = businesses.find(function (item) {
        return String(item.id) === String(id);
    });

    if (!business) {
        return;
    }

    currentBusinessId = business.id;

    const clientName =
        business.clientName ||
        business.ownerName ||
        "";

    $("reviewsModalTitle").textContent =
        business.businessName;

    $("reviewsModalSubtitle").textContent =
        clientName + " • " + business.category;

    renderReviewDetails();
    showModal("reviewsModal");
}

function renderReviewDetails() {
    const tbody = $("reviewsTableBody");

    if (!currentBusinessId) {
        tbody.innerHTML = "";
        return;
    }

    const business = businesses.find(function (item) {
        return String(item.id) === String(currentBusinessId);
    });

    if (!business) {
        tbody.innerHTML = "";
        return;
    }

    const reviews = getReviews(currentBusinessId);
    const total = Number(business.reviewLimit || 100);

    if (!reviews.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                    <i class="ri-chat-3-line fs-24 d-block mb-2"></i>
                    No reviews added yet.
                </td>
            </tr>
        `;

        return;
    }

    const used = reviews.length;
    const remaining = Math.max(0, total - used);

    tbody.innerHTML = reviews.map(function (review) {
        return `
            <tr>
                <td>
                    <span class="star-text">
                        ${renderStars(review.rating)}
                    </span>
                </td>

                <td class="review-text-cell">
                    <div>
                        ${escapeHtml(review.reviewText)}
                    </div>

                    <small class="text-muted">
                        ${formatDate(review.reviewDate)}
                    </small>
                </td>

                <td>${total}</td>
                <td>${used}</td>
                <td>${remaining}</td>

                <td>
                    <div class="action-group">

                        <button
                            type="button"
                            class="action-btn"
                            title="Edit Review"
                            onclick="editReview('${escapeJs(review.id)}')">

                            <i class="ri-edit-line"></i>
                        </button>

                        <button
                            type="button"
                            class="action-btn delete"
                            title="Delete Review"
                            onclick="deleteReview('${escapeJs(review.id)}')">

                            <i class="ri-delete-bin-line"></i>
                        </button>

                    </div>
                </td>
            </tr>
        `;
    }).join("");
}


/* ADD REVIEW */
function openAddReviewForBusiness(businessId) {
    currentBusinessId = businessId;
    openAddReviewModal();
}

function openAddReviewModal() {
    if (!currentBusinessId) {
        alertMessage(
            "Select business",
            "Please select a business first.",
            "warning"
        );
        return;
    }

    currentReviewId = null;
    importedExcelReviews = [];

    $("reviewForm").reset();
    $("reviewId").value = "";

    $("excelFile").value = "";
    $("excelPreviewWrapper").classList.add("d-none");
    $("excelPreviewBody").innerHTML = "";

    populateClientDropdowns();

    const business = businesses.find(function (item) {
        return String(item.id) === String(currentBusinessId);
    });

    if (business) {
        const clientName =
            business.clientName ||
            business.ownerName ||
            "";

        if (
            clientName &&
            !selectHasValue($("reviewOwner"), clientName)
        ) {
            addOption($("reviewOwner"), clientName, clientName);
        }

        $("reviewOwner").value = clientName;
    }

    $("reviewEditorTitle").textContent = "Add Review";

    $("saveReviewButton").innerHTML =
        '<i class="ri-save-line me-1"></i> Save Review';

    setDefaultReviewDate();

    /*
     * Auto-download the Excel import template as soon as the
     * "Add Review" modal opens, so the user always has the
     * correct column headers on hand for a bulk import.
     */
    downloadExcelTemplate();

    showModal("reviewEditorModal");
}


/* EDIT REVIEW */
function editReview(id) {
    const reviews = getReviews(currentBusinessId);

    const review = reviews.find(function (item) {
        return String(item.id) === String(id);
    });

    if (!review) {
        return;
    }

    currentReviewId = review.id;

    $("reviewId").value = review.id;

    populateClientDropdowns();

    const clientName =
        review.clientName ||
        review.ownerName ||
        "";

    if (
        clientName &&
        !selectHasValue($("reviewOwner"), clientName)
    ) {
        addOption($("reviewOwner"), clientName, clientName);
    }

    $("reviewOwner").value = clientName;
    $("reviewRating").value = String(review.rating || 5);
    $("reviewText").value = review.reviewText || "";
    $("reviewDate").value = review.reviewDate || todayDate();

    $("reviewEditorTitle").textContent = "Edit Review";

    $("saveReviewButton").innerHTML =
        '<i class="ri-save-line me-1"></i> Save Changes';

    importedExcelReviews = [];

    $("excelFile").value = "";
    $("excelPreviewWrapper").classList.add("d-none");
    $("excelPreviewBody").innerHTML = "";

    showModal("reviewEditorModal");
}


/* SAVE REVIEW */
function saveReview() {
    if (importedExcelReviews.length) {
        saveImportedReviews();
        return;
    }

    const form = $("reviewForm");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (!currentBusinessId) {
        alertMessage(
            "Business not selected",
            "Please select a business first.",
            "warning"
        );
        return;
    }

    const clientName = $("reviewOwner").value.trim();
    const rating = Number($("reviewRating").value);
    const reviewText = $("reviewText").value.trim();
    const reviewDate = $("reviewDate").value;

    if (!clientName) {
        alertMessage(
            "Client required",
            "Please select a client.",
            "warning"
        );
        return;
    }

    if (!rating || rating < 1 || rating > 5) {
        alertMessage(
            "Rating required",
            "Please select a rating.",
            "warning"
        );
        return;
    }

    if (!reviewText) {
        alertMessage(
            "Review required",
            "Please enter review text.",
            "warning"
        );
        return;
    }

    const reviews = getReviews(currentBusinessId);
    const now = new Date().toISOString();

    if (currentReviewId) {
        const index = reviews.findIndex(function (item) {
            return String(item.id) === String(currentReviewId);
        });

        if (index !== -1) {
            reviews[index] = {
                ...reviews[index],
                clientName,
                ownerName: clientName,
                rating,
                reviewText,
                reviewDate,
                updatedAt: now
            };
        }
    } else {
        reviews.push({
            id: createId(),
            businessId: currentBusinessId,
            clientName,
            ownerName: clientName,
            rating,
            reviewText,
            reviewDate,
            createdAt: now,
            updatedAt: now
        });
    }

    generatedReviews[String(currentBusinessId)] = reviews;

    saveReviewStorage();

    renderTable();
    updateSummary();

    if (currentBusinessId) {
        renderReviewDetails();
    }

    hideModal("reviewEditorModal");

    alertMessage(
        currentReviewId ? "Review updated" : "Review saved",
        currentReviewId
            ? "Review has been updated successfully."
            : "Review has been saved successfully.",
        "success"
    );

    currentReviewId = null;
}


/* DELETE REVIEW */
async function deleteReview(id) {
    if (!currentBusinessId) {
        return;
    }

    const reviews = getReviews(currentBusinessId);

    const review = reviews.find(function (item) {
        return String(item.id) === String(id);
    });

    if (!review) {
        return;
    }

    const confirmed = await confirmAction(
        "Delete review?",
        "This review will be permanently removed from LocalStorage."
    );

    if (!confirmed) {
        return;
    }

    generatedReviews[String(currentBusinessId)] =
        reviews.filter(function (item) {
            return String(item.id) !== String(id);
        });

    saveReviewStorage();

    renderReviewDetails();
    renderTable();
    updateSummary();

    alertMessage(
        "Deleted",
        "Review has been deleted.",
        "success"
    );
}


/* SAMPLE */
function loadSampleReview() {
    /*
     * "Sample" now downloads the sample/template Excel file
     * (same file as the import template) instead of filling
     * the manual review fields.
     */
    downloadExcelTemplate();
}


/* DATE */
function setDefaultReviewDate() {
    if ($("reviewDate") && !$("reviewDate").value) {
        $("reviewDate").value = todayDate();
    }
}

function todayDate() {
    const date = new Date();

    return date.getFullYear() + "-" +
        String(date.getMonth() + 1).padStart(2, "0") + "-" +
        String(date.getDate()).padStart(2, "0");
}


/* EXCEL */
function openExcelFilePicker() {
    $("excelFile").click();
}

function handleExcelImport(event) {
    const file = event.target.files[0];

    if (!file) {
        return;
    }

    if (typeof XLSX === "undefined") {
        alertMessage(
            "Excel library unavailable",
            "Please check your internet connection.",
            "error"
        );
        return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
        try {
            const data = new Uint8Array(e.target.result);

            const workbook = XLSX.read(data, {
                type: "array"
            });

            const firstSheet =
                workbook.Sheets[workbook.SheetNames[0]];

            const rows = XLSX.utils.sheet_to_json(firstSheet, {
                defval: ""
            });

            processExcelRows(rows);
        } catch (error) {
            console.error(error);

            alertMessage(
                "Import failed",
                "Could not read this Excel file.",
                "error"
            );
        }
    };

    reader.readAsArrayBuffer(file);
}

function processExcelRows(rows) {
    if (!rows.length) {
        alertMessage(
            "Empty Excel",
            "No rows were found in the selected file.",
            "warning"
        );
        return;
    }

    const imported = [];

    rows.forEach(function (row) {
        const clientName = getExcelValue(row, [
            "Owner Name",
            "Owner",
            "Client Name",
            "Client",
            "Name",
            "Reviewer Name",
            "Reviewer"
        ]);

        const rating = getExcelValue(row, [
            "Rating",
            "Star",
            "Stars"
        ]);

        const reviewText = getExcelValue(row, [
            "Review",
            "Review Text",
            "Comment",
            "Feedback"
        ]);

        const reviewDate = getExcelValue(row, [
            "Review Date",
            "Date"
        ]);

        if (!clientName && !rating && !reviewText) {
            return;
        }

        imported.push({
            clientName: String(clientName || "").trim(),
            ownerName: String(clientName || "").trim(),
            rating: Math.min(
                5,
                Math.max(1, Number(rating || 5))
            ),
            reviewText: String(reviewText || "").trim(),
            reviewDate: normalizeExcelDate(reviewDate)
        });
    });

    importedExcelReviews = imported;

    renderExcelPreview();
}

function getExcelValue(row, aliases) {
    const keys = Object.keys(row);

    for (let i = 0; i < aliases.length; i++) {
        const alias = aliases[i].toLowerCase().trim();

        const found = keys.find(function (key) {
            return key.toLowerCase().trim() === alias;
        });

        if (found !== undefined) {
            return row[found];
        }
    }

    return "";
}

function normalizeExcelDate(value) {
    if (!value) {
        return todayDate();
    }

    if (typeof value === "number" && XLSX.SSF) {
        const excelDate = XLSX.SSF.parse_date_code(value);

        if (excelDate) {
            return excelDate.y + "-" +
                String(excelDate.m).padStart(2, "0") + "-" +
                String(excelDate.d).padStart(2, "0");
        }
    }

    const date = new Date(value);

    if (!isNaN(date.getTime())) {
        return date.getFullYear() + "-" +
            String(date.getMonth() + 1).padStart(2, "0") + "-" +
            String(date.getDate()).padStart(2, "0");
    }

    return todayDate();
}

function renderExcelPreview() {
    const wrapper = $("excelPreviewWrapper");
    const tbody = $("excelPreviewBody");

    $("excelPreviewCount").textContent =
        "(" + importedExcelReviews.length + " rows)";

    if (!importedExcelReviews.length) {
        wrapper.classList.add("d-none");
        tbody.innerHTML = "";
        return;
    }

    wrapper.classList.remove("d-none");

    tbody.innerHTML = importedExcelReviews.map(function (item) {
        return `
            <tr>
                <td>${escapeHtml(item.clientName)}</td>
                <td>${renderStars(item.rating)}</td>
                <td class="review-text-cell">
                    ${escapeHtml(item.reviewText)}
                </td>
                <td>${formatDate(item.reviewDate)}</td>
            </tr>
        `;
    }).join("");
}

function saveImportedReviews() {
    if (!currentBusinessId) {
        alertMessage(
            "Business not selected",
            "Please select a business first.",
            "warning"
        );
        return;
    }

    if (!importedExcelReviews.length) {
        alertMessage(
            "No reviews",
            "Please choose an Excel file containing reviews.",
            "warning"
        );
        return;
    }

    const reviews = getReviews(currentBusinessId);
    const now = new Date().toISOString();

    importedExcelReviews.forEach(function (item) {
        const clientName =
            item.clientName ||
            getBusinessClient(currentBusinessId);

        reviews.push({
            id: createId(),
            businessId: currentBusinessId,
            clientName,
            ownerName: clientName,
            rating: item.rating,
            reviewText: item.reviewText,
            reviewDate: item.reviewDate,
            createdAt: now,
            updatedAt: now
        });
    });

    generatedReviews[String(currentBusinessId)] = reviews;

    saveReviewStorage();

    renderTable();
    renderReviewDetails();
    updateSummary();

    hideModal("reviewEditorModal");

    alertMessage(
        "Reviews imported",
        importedExcelReviews.length + " reviews have been saved.",
        "success"
    );

    importedExcelReviews = [];
}

function getBusinessClient(businessId) {
    const business = businesses.find(function (item) {
        return String(item.id) === String(businessId);
    });

    if (!business) {
        return "";
    }

    return business.clientName || business.ownerName || "";
}


/* EXCEL TEMPLATE */
function downloadExcelTemplate() {
    if (typeof XLSX === "undefined") {
        alertMessage(
            "Excel library unavailable",
            "SheetJS could not be loaded.",
            "error"
        );
        return;
    }

    const data = [
        {
            "Owner Name": "John Doe",
            "Rating": 5,
            "Review": "Excellent service and highly recommended.",
            "Review Date": todayDate()
        },
        {
            "Owner Name": "Jane Doe",
            "Rating": 4,
            "Review": "Very good experience and friendly staff.",
            "Review Date": todayDate()
        }
    ];

    const worksheet = XLSX.utils.json_to_sheet(data);
    const workbook = XLSX.utils.book_new();

    XLSX.utils.book_append_sheet(
        workbook,
        worksheet,
        "Reviews"
    );

    XLSX.writeFile(
        workbook,
        "review-import-template.xlsx"
    );
}


/* FILTER RESET */
function resetFilters() {
    $("searchBusiness").value = "";
    $("filterCategory").value = "";
    $("filterOwner").value = "";

    renderTable();
}


/* SUMMARY */
function updateSummary() {
    $("totalCount").textContent = businesses.length;

    const totalReviews = businesses.reduce(function (total, business) {
        return total + getReviews(business.id).length;
    }, 0);

    $("reviewCount").textContent = totalReviews;

    $("ownerCount").textContent = getClientNames().length;

    $("categoryCount").textContent = new Set(
        businesses
            .map(function (item) {
                return item.category;
            })
            .filter(Boolean)
    ).size;
}


/* HELPERS */
function renderStars(rating) {
    const value = Number(rating || 0);
    let html = "";

    for (let i = 1; i <= 5; i++) {
        html += i <= value ? "★" : "☆";
    }

    return html;
}

function formatDate(value) {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (isNaN(date.getTime())) {
        return escapeHtml(value);
    }

    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });
}

function createSlug(value) {
    return String(value || "")
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escapeAttribute(value) {
    return escapeHtml(value);
}

function escapeJs(value) {
    return String(value ?? "")
        .replace(/\\/g, "\\\\")
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, "\\n")
        .replace(/\r/g, "\\r");
}

console.log("Business Reviews loaded successfully.");
</script>


<?php
include __DIR__ . '/../includes/footer.php';
?>