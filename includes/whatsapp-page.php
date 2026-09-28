<?php
/*
|--------------------------------------------------------------------------
| Shared WhatsApp CRM Chat page body (admin /whatsapp, employee /emp-whatsapp)
|--------------------------------------------------------------------------
| Reuses the Mamix theme's own chat CSS (dist/assets/css/styles.css --
| .main-chart-wrapper/.chat-info/.main-chat-area/.chat-content/.chat-footer
| etc; the theme's demo chat.html was removed in Phase 1 cleanup but the
| compiled CSS for it is still here) instead of inventing new chat markup.
| Data/permissions/polling all via dist/assets/js/whatsapp.js.
*/
$waHomeUrl = getLoggedInUserType() === 'employee' ? 'emp-dashboard' : 'dashboard';
$waLeadsUrl = getLoggedInUserType() === 'employee' ? 'emp-leads' : 'leads';
?>
<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">WhatsApp</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= htmlspecialchars($waHomeUrl, ENT_QUOTES, 'UTF-8') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">WhatsApp</li>
                </ol>
            </div>
        </div>

        <div id="waDisabledNotice" class="alert alert-warning d-none">WhatsApp integration is not enabled yet. An admin can configure it under <a href="integrations">Integrations</a>.</div>

        <div class="main-chart-wrapper">
            <div class="row">
                <div class="col-xl-12">
                    <div class="d-flex" style="gap: 0;">

                        <!-- Conversation list -->
                        <div class="chat-info flex-shrink-0">
                            <div class="p-3 border-bottom">
                                <div class="crm-filter-bar">
                                    <select id="waListFilter" class="form-select">
                                        <option value="">All</option>
                                        <option value="unread">Unread</option>
                                        <option value="unlinked">Unlinked</option>
                                    </select>
                                    <input id="waSearch" class="form-control crm-filter-search" placeholder="Search name or phone..." autocomplete="off" />
                                </div>
                            </div>
                            <div id="waConversationScroll" style="height: calc(100vh - 14.5rem); overflow: auto;">
                                <ul class="list-unstyled chat-users-tab mb-0" id="waConversationList"></ul>
                                <div id="waConversationEmpty" class="text-muted text-center p-4 d-none">No conversations yet.</div>
                            </div>
                        </div>

                        <!-- Active conversation -->
                        <div class="main-chat-area flex-fill">
                            <div id="waEmptyState" class="d-flex align-items-center justify-content-center flex-column text-muted" style="height: calc(100vh - 8rem);">
                                <i class="ri-whatsapp-line fs-1 mb-2"></i>
                                <div>Select a conversation to start chatting.</div>
                            </div>

                            <div id="waActiveConversation" class="d-none h-100 d-flex flex-column">
                                <div class="main-chat-head d-flex align-items-center justify-content-between border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-icon btn-sm responsive-chat-close"><i class="ri-arrow-left-line"></i></button>
                                        <span class="avatar avatar-sm avatar-rounded bg-primary-transparent" id="waHeadAvatar"></span>
                                        <div>
                                            <div class="chatnameperson" id="waHeadName"></div>
                                            <small class="text-muted" id="waHeadMeta"></small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2" id="waHeadActions"></div>
                                </div>

                                <div id="waWindowBanner" class="alert alert-warning m-2 py-2 px-3 mb-0 d-none"></div>

                                <div class="chat-content flex-fill" id="waMessageScroll" style="overflow: auto;">
                                    <ul class="list-unstyled mb-0" id="waMessageList"></ul>
                                </div>

                                <div class="chat-footer" id="waComposerBar">
                                    <div class="d-flex align-items-center gap-2 w-100 py-2">
                                        <button type="button" class="btn btn-icon btn-sm btn-light" id="waAttachBtn" title="Attach image/document"><i class="ri-attachment-2"></i></button>
                                        <input type="file" id="waMediaInput" class="d-none" accept=".jpg,.jpeg,.png,.webp,.pdf" />
                                        <button type="button" class="btn btn-icon btn-sm btn-light" id="waTemplateBtn" title="Send template"><i class="ri-file-list-3-line"></i></button>
                                        <input type="text" class="form-control chat-message-space" id="waMessageInput" placeholder="Type a message..." maxlength="4096" />
                                        <button type="button" class="btn btn-icon btn-primary" id="waSendBtn" title="Send"><i class="ri-send-plane-2-line"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Link / Create lead (unlinked conversation only) -->
<div class="modal fade" id="waLinkLeadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link WhatsApp Conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#waLinkExisting" type="button">Link to Existing Lead</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#waLinkCreate" type="button">Create New Lead</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="waLinkExisting">
                        <label class="form-label">Lead ID</label>
                        <input type="number" class="form-control" id="waLinkLeadId" placeholder="Enter the lead's ID" />
                        <small class="text-muted">Find the lead's ID from the Leads page, then link it here.</small>
                        <button type="button" class="btn btn-primary w-100 mt-3" id="waLinkExistingBtn">Link</button>
                    </div>
                    <div class="tab-pane fade" id="waLinkCreate">
                        <p class="text-muted">Creates a new lead from this conversation's name/phone (source: WhatsApp).</p>
                        <button type="button" class="btn btn-primary w-100" id="waLinkCreateBtn">Create Lead</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send template -->
<div class="modal fade" id="waTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Template</label>
                    <select class="form-select" id="waTemplateSelect"></select>
                </div>
                <div id="waTemplateVariables"></div>
                <div id="waTemplateEmpty" class="text-muted d-none">No templates are configured yet. An admin can add approved template names under Integrations → WhatsApp Cloud API.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="waTemplateSendBtn">Send</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    var WHATSAPP_LEADS_URL = <?= json_encode($waLeadsUrl) ?>;
</script>
<script src="<?= ASSET_URL ?>/assets/js/whatsapp.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/whatsapp.js') ?>"></script>
