<?php
/*
|--------------------------------------------------------------------------
| Shared Projects page body (admin /projects, employee /emp-projects)
|--------------------------------------------------------------------------
| Expects $projectHomeUrl. Management controls (add, edit, activate,
| upload/delete documents) are rendered by dist/assets/js/projects.js only
| when api/projects/getProjects.php reports the permission; every API
| re-checks it. Employees see active projects read-only.
*/
$prEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<style>
    .project-thumb { width: 96px; height: 72px; object-fit: cover; border-radius: 6px; }
    .project-view-label { font-size: 12px; color: var(--text-muted, #8c9097); margin-bottom: 2px; }
</style>
<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Projects</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= $prEsc($projectHomeUrl) ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Projects</li>
                </ol>
            </div>
            <button type="button" class="btn btn-primary btn-wave d-none" id="addProjectBtn"><i class="ri-add-line me-1"></i> Add Project</button>
        </div>

        <div class="card custom-card">
            <div class="card-body p-3">
                <div class="crm-filter-bar">
                    <select id="projectTypeFilter" class="form-select"><option value="">Property Type</option></select>
                    <select id="projectStatusFilter" class="form-select d-none">
                        <option value="">All Status</option><option value="Active">Active</option><option value="Inactive">Inactive</option>
                    </select>
                    <input id="projectSearch" class="form-control crm-filter-search" placeholder="Search projects..." autocomplete="off" />
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Project Portfolio</div></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="projectsTable" data-ui-table="mamix" class="table table-hover text-wrap">
                        <thead>
                            <tr><th>Project</th><th>Developer</th><th>Location</th><th>Type</th><th>Configuration</th><th>Pricing</th><th>Docs</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add / Edit Project -->
        <div class="modal fade" id="projectModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="projectModalTitle">Add Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="projectForm" novalidate>
                        <input type="hidden" name="id" id="projectId" />
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="projectName">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="projectName" name="projectName" maxlength="150" required />
                                    <div class="invalid-feedback">Project name is required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="developerName">Developer / Builder</label>
                                    <input type="text" class="form-control" id="developerName" name="developerName" maxlength="150" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="location">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" maxlength="255" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="propertyType">Property Type</label>
                                    <select class="form-select" id="propertyType" name="propertyType"><option value="">Select</option></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="configuration">Configuration</label>
                                    <input type="text" class="form-control" id="configuration" name="configuration" maxlength="255" placeholder="e.g. 2 BHK, 3 BHK" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="pricing">Pricing</label>
                                    <input type="text" class="form-control" id="pricing" name="pricing" maxlength="255" placeholder="e.g. ₹45 L – ₹1.2 Cr" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="amenities">Amenities</label>
                                    <input type="text" class="form-control" id="amenities" name="amenities" maxlength="2000" placeholder="Comma separated, e.g. Clubhouse, Pool, Gym" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveProjectBtn">Save Project</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Project + Documents -->
        <div class="modal fade" id="projectViewModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="projectViewTitle">Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3" id="projectViewDetails"></div>
                        <h6 class="fw-semibold">Documents</h6>
                        <div class="row g-2 mb-3 d-none" id="projectUploadRow">
                            <div class="col-md-3"><select class="form-select" id="projectDocumentType"></select></div>
                            <div class="col-md-6"><input type="file" class="form-control" id="projectDocumentFile" accept=".pdf,.jpg,.jpeg,.png,.webp" /></div>
                            <div class="col-md-3"><button type="button" class="btn btn-primary w-100" id="uploadProjectDocumentBtn">Upload</button></div>
                            <div class="col-12"><small class="text-muted">Project images: JPG / PNG / WEBP. Brochures, floor plans, price lists and other documents: PDF or image.</small></div>
                        </div>
                        <div id="projectDocuments"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm (activate / deactivate / delete document) -->
        <div class="modal fade" id="projectConfirmModal" data-bs-effect="effect-super-scaled">
            <div class="modal-dialog modal-dialog-centered text-center" role="document">
                <div class="modal-content modal-content-demo">
                    <div class="modal-header">
                        <h6 class="modal-title" id="projectConfirmTitle">Confirm</h6>
                        <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start"><h6 id="projectConfirmText"></h6></div>
                    <div class="modal-footer">
                        <button class="btn btn-danger" id="projectConfirmBtn">Confirm</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/projects.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/projects.js') ?>"></script>
