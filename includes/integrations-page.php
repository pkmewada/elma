<?php
/*
|--------------------------------------------------------------------------
| Integrations settings page (admin only, /integrations)
|--------------------------------------------------------------------------
| Meta Lead Ads / Google Lead Forms / Website lead capture: enable/configure,
| see Connected/Not Configured + Enabled/Disabled + last success/error, map
| forms to projects/assignees (Meta/Google), and review the integration log.
| All data via AJAX (api/integrations/*.php); every mutating API re-checks
| the 'manage_integrations' special action + CSRF server-side.
*/
$inEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Integrations</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Integrations</li>
                </ol>
            </div>
        </div>

        <div class="row g-4 mb-1" id="integrationCards"></div>

        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="card-title mb-0">Form Mapping</div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="mappingProviderFilter" class="form-select form-select-sm" style="width:auto">
                        <option value="meta">Meta Lead Ads</option>
                        <option value="google">Google Lead Forms</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" id="addMappingBtn"><i class="ri-add-line me-1"></i>Add Mapping</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="mappingTable" data-ui-table="mamix" class="table table-hover text-wrap">
                        <thead>
                            <tr><th>Form ID</th><th>Page ID</th><th>Label</th><th>Project</th><th>Default Assignee</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Integration Log</div></div>
            <div class="card-body">
                <div class="crm-filter-bar mb-3">
                    <select id="logProviderFilter" class="form-select">
                        <option value="">All Providers</option>
                        <option value="meta">Meta Lead Ads</option>
                        <option value="google">Google Lead Forms</option>
                        <option value="website">Website</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                    <select id="logStatusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="created">Created</option>
                        <option value="duplicate">Duplicate</option>
                        <option value="rejected">Rejected</option>
                        <option value="error">Error</option>
                    </select>
                    <input id="logSearch" class="form-control crm-filter-search" placeholder="Search logs..." autocomplete="off" />
                </div>
                <div class="table-responsive">
                    <table id="logsTable" data-ui-table="mamix" class="table table-hover text-wrap">
                        <thead>
                            <tr><th>Received</th><th>Provider</th><th>Event</th><th>External ID</th><th>Status</th><th>Message</th><th>Lead</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Configure provider -->
        <div class="modal fade" id="integrationConfigModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="integrationConfigTitle">Configure Integration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="integrationConfigForm" novalidate>
                        <input type="hidden" name="provider" id="configProvider" />
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Webhook / Endpoint URL</label>
                                <input type="text" class="form-control" id="configEndpointUrl" readonly />
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="configIsEnabled" name="isEnabled" value="1">
                                <label class="form-check-label" for="configIsEnabled">Enabled</label>
                            </div>

                            <div id="metaFields" class="provider-fields d-none">
                                <div class="mb-3">
                                    <label class="form-label">Verify Token</label>
                                    <input type="text" class="form-control" name="secrets[verifyToken]" id="metaVerifyToken" placeholder="Used for Meta's webhook handshake" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">App Secret</label>
                                    <input type="password" class="form-control" name="secrets[appSecret]" id="metaAppSecret" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Page Access Token</label>
                                    <input type="password" class="form-control" name="secrets[pageAccessToken]" id="metaPageAccessToken" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                </div>
                            </div>

                            <div id="googleFields" class="provider-fields d-none">
                                <div class="mb-3">
                                    <label class="form-label">Shared Key (google_key)</label>
                                    <input type="password" class="form-control" name="secrets[sharedKey]" id="googleSharedKey" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                </div>
                            </div>

                            <div id="websiteFields" class="provider-fields d-none">
                                <div class="mb-3">
                                    <label class="form-label">API Key</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="secrets[apiKey]" id="websiteApiKey" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                        <button type="button" class="btn btn-outline-secondary" id="generateWebsiteKeyBtn">Generate</button>
                                    </div>
                                    <small class="text-muted">Give this key to whoever wires the website form; it must be sent as the X-Integration-Key header.</small>
                                </div>
                            </div>

                            <div id="whatsappFields" class="provider-fields d-none">
                                <div class="mb-3">
                                    <label class="form-label">Verify Token</label>
                                    <input type="text" class="form-control" name="secrets[verifyToken]" id="whatsappVerifyToken" placeholder="Used for Meta's webhook handshake" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Access Token</label>
                                    <input type="password" class="form-control" name="secrets[accessToken]" id="whatsappAccessToken" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">App Secret</label>
                                    <input type="password" class="form-control" name="secrets[appSecret]" id="whatsappAppSecret" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number ID</label>
                                        <input type="text" class="form-control" name="config[phoneNumberId]" id="whatsappPhoneNumberId" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">WhatsApp Business Account ID</label>
                                        <input type="text" class="form-control" name="config[wabaId]" id="whatsappWabaId" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">API Version</label>
                                        <input type="text" class="form-control" name="config[apiVersion]" id="whatsappApiVersion" placeholder="v21.0" />
                                    </div>
                                </div>
                                <div class="mb-1 mt-3">
                                    <label class="form-label">Approved Templates</label>
                                    <textarea class="form-control" name="config[templatesRaw]" id="whatsappTemplatesRaw" rows="3" placeholder="name|language|variableCount|Display label (one per line)"></textarea>
                                    <small class="text-muted">Templates themselves are created/approved in Meta; list them here (one per line: name|language|variable count|label) so the chat composer can offer them.</small>
                                </div>
                            </div>

                            <div class="row g-3" id="configSourceAssigneeRow">
                                <div class="col-md-6">
                                    <label class="form-label">Default Source (optional override)</label>
                                    <select class="form-select" name="config[defaultSourceId]" id="configDefaultSourceId"><option value="">Use provider's own source</option></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Default Assignee (optional)</label>
                                    <select class="form-select" name="config[defaultAssigneeId]" id="configDefaultAssigneeId"><option value="">Unassigned</option></select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary integration-save-btn" id="saveIntegrationBtn">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add / edit mapping -->
        <div class="modal fade" id="mappingModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Form Mapping</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="mappingForm" novalidate>
                        <input type="hidden" name="provider" id="mappingProvider" />
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Form ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="externalFormId" id="mappingExternalFormId" maxlength="100" required />
                                <div class="invalid-feedback">Form ID is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Page / Account ID</label>
                                <input type="text" class="form-control" name="externalPageId" id="mappingExternalPageId" maxlength="100" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Label</label>
                                <input type="text" class="form-control" name="formLabel" id="mappingFormLabel" maxlength="150" placeholder="e.g. campaign or form name" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Project</label>
                                <select class="form-select" name="projectId" id="mappingProjectId"><option value="">No project (still captured)</option></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Default Assignee</label>
                                <select class="form-select" name="defaultAssigneeId" id="mappingDefaultAssigneeId"><option value="">Unassigned</option></select>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="mappingIsActive" name="isActive" value="1" checked>
                                <label class="form-check-label" for="mappingIsActive">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary integration-save-btn">Save Mapping</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/integrations.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/integrations.js') ?>"></script>
