/* ==========================================================================
   WhatsApp CRM Chat (admin /whatsapp + employee /emp-whatsapp). Meta Cloud
   API via api/whatsapp/*.php + the webhook in api/integrations/whatsapp-
   webhook.php. Lightweight polling, no WebSockets: conversation list every
   8s, open conversation's messages every 4s (Section 13's documented
   interval choice).
   ========================================================================== */
$(function () {
    var api = {
        conversations: API_BASE + "/whatsapp/getConversations.php",
        messages: API_BASE + "/whatsapp/getMessages.php",
        send: API_BASE + "/whatsapp/send-message.php",
        markRead: API_BASE + "/whatsapp/mark-read.php",
        linkLead: API_BASE + "/whatsapp/linkLead.php",
    };

    var conversations = [];
    var activeConversationId = null;
    var permissions = { canSend: false, canLinkLead: false };
    var listTimer = null;
    var messageTimer = null;
    var lastMessageCount = 0;

    function esc(v) { return $("<div>").text(v == null ? "" : String(v)).html(); }
    function toast(t, m) { if (window.showToast) window.showToast(t, m); }
    function apiError(xhr, fallback) { return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback; }
    function initials(name, phone) {
        var s = (name || phone || "?").trim();
        return esc(s.substring(0, 1).toUpperCase());
    }
    function fmtTime(v) { return v ? new Date(v.replace(" ", "T")).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : ""; }
    function fmtWhen(v) {
        if (!v) return "";
        var d = new Date(v.replace(" ", "T"));
        var today = new Date();
        if (d.toDateString() === today.toDateString()) return fmtTime(v);
        return d.toLocaleDateString([], { day: "2-digit", month: "short" });
    }

    if (typeof SimpleBar !== "undefined") {
        document.getElementById("waConversationScroll") && new SimpleBar(document.getElementById("waConversationScroll"), { autoHide: true });
    }
    var messageScrollBar = null;

    // ---------- Conversation list ----------
    function renderConversationList() {
        var q = ($("#waSearch").val() || "").toLowerCase();
        var filter = $("#waListFilter").val();

        var visible = conversations.filter(function (c) {
            if (filter === "unread" && !c.unreadCount) return false;
            if (filter === "unlinked" && c.leadId) return false;
            if (q) {
                var hay = ((c.customerName || "") + " " + (c.leadFullName || "") + " " + (c.phoneNumber || "")).toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });

        $("#waConversationEmpty").toggleClass("d-none", visible.length > 0);

        var html = visible.map(function (c) {
            var name = c.leadFullName || c.customerName || c.phoneNumber;
            var active = String(c.id) === String(activeConversationId) ? " active" : "";
            var unread = c.unreadCount > 0 ? ' chat-msg-unread' : "";
            return '<li class="checkforactive' + active + unread + '" data-id="' + c.id + '" style="cursor:pointer;">' +
                '<div class="d-flex align-items-center gap-2">' +
                '<span class="avatar avatar-sm avatar-rounded bg-primary-transparent flex-shrink-0">' + initials(name, c.phoneNumber) + '</span>' +
                '<div class="flex-fill overflow-hidden">' +
                '<div class="d-flex justify-content-between"><span class="fw-semibold text-truncate">' + esc(name) + '</span>' +
                '<small class="text-muted flex-shrink-0 ms-1">' + esc(fmtWhen(c.lastMessageAt)) + '</small></div>' +
                '<div class="d-flex justify-content-between"><small class="chat-msg text-truncate">' + esc(c.lastMessagePreview || c.phoneNumber) + '</small>' +
                (c.unreadCount > 0 ? '<span class="badge bg-success rounded-pill flex-shrink-0 ms-1">' + c.unreadCount + '</span>' : '') +
                '</div></div></div></li>';
        }).join("");

        $("#waConversationList").html(html);
    }

    function loadConversations(onDone) {
        $.getJSON(api.conversations, { q: $("#waSearch").val() || "" })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to load conversations."); return; }
                conversations = res.data || [];
                permissions = res.permissions || permissions;
                renderConversationList();
                if (onDone) onDone();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to load conversations.")); });
    }

    $("#waSearch").on("keyup", renderConversationList);
    $("#waListFilter").on("change", renderConversationList);

    $("#waConversationList").on("click", "li", function () {
        openConversation($(this).data("id"));
    });

    // ---------- Active conversation ----------
    function renderMessages(messages) {
        var html = messages.map(function (m) {
            var side = m.direction === "outbound" ? "chat-item-end" : "chat-item-start";
            var body;

            if (m.messageType === "image" && m.mediaPath) {
                body = '<img src="' + API_BASE + "/whatsapp/media.php?conversationId=" + activeConversationId + "&file=" + encodeURIComponent(m.mediaPath) + '" class="chat-media-image" alt="image" />';
            } else if (m.messageType === "document" && m.mediaPath) {
                body = '<a href="' + API_BASE + "/whatsapp/media.php?conversationId=" + activeConversationId + "&file=" + encodeURIComponent(m.mediaPath) + '" target="_blank" rel="noopener"><i class="ri-file-line me-1"></i>' + esc(m.messageText || "Document") + '</a>';
            } else {
                body = "<p class=\"mb-0\">" + esc(m.messageText || "") + "</p>";
            }

            var statusIcon = "";
            if (m.direction === "outbound") {
                var icons = { pending: "ri-time-line", sent: "ri-check-line", delivered: "ri-check-double-line", read: "ri-check-double-line text-primary", failed: "ri-error-warning-line text-danger" };
                statusIcon = '<i class="' + (icons[m.status] || "ri-time-line") + ' ms-1"></i>';
            }

            return '<li class="' + side + '">' +
                '<div class="chat-list-inner">' +
                '<div><div class="main-chat-msg"><div>' + body + '</div></div>' +
                '<div class="chatting-user-info"><span class="msg-sent-time">' + esc(fmtTime(m.sentAt)) + statusIcon + '</span></div>' +
                (m.status === "failed" ? '<small class="text-danger d-block">' + esc(m.errorMessage || "Delivery failed") + '</small>' : '') +
                '</div></div></li>';
        }).join("");

        $("#waMessageList").html(html || '<li class="text-muted text-center">No messages yet.</li>');

        var scrollEl = document.getElementById("waMessageScroll");
        if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
    }

    function renderHeader(conv, windowOpen, windowOpenUntil, integrationEnabled) {
        var name = conv.leadFullName || conv.customerName || conv.phoneNumber;
        $("#waHeadAvatar").text(initials(name, conv.phoneNumber).replace(/<[^>]+>/g, ""));
        $("#waHeadName").text(name);

        var meta = [conv.phoneNumber];
        if (conv.leadStatus) meta.push("Status: " + conv.leadStatus);
        if (conv.projectName) meta.push(conv.projectName);
        if (conv.assignedToName) meta.push("Assigned: " + conv.assignedToName);
        $("#waHeadMeta").text(meta.join(" · "));

        var actions = "";
        if (conv.leadId) {
            actions += '<a href="' + esc(WHATSAPP_LEADS_URL) + "?leadId=" + conv.leadId + '" class="btn btn-sm btn-outline-primary">View Lead</a>';
        } else if (permissions.canLinkLead) {
            actions += '<button type="button" class="btn btn-sm btn-outline-primary" id="waOpenLinkModalBtn">Link / Create Lead</button>';
        }
        $("#waHeadActions").html(actions);

        if (!integrationEnabled) {
            $("#waWindowBanner").removeClass("alert-warning").addClass("alert-danger").text("WhatsApp integration is not enabled.").removeClass("d-none");
        } else if (!windowOpen) {
            $("#waWindowBanner").removeClass("alert-danger").addClass("alert-warning").text("Conversation window closed (24h passed since the customer's last message) — send an approved template.").removeClass("d-none");
        } else {
            $("#waWindowBanner").addClass("d-none");
        }

        $("#waMessageInput, #waAttachBtn, #waSendBtn").prop("disabled", !permissions.canSend || !integrationEnabled || !windowOpen);
    }

    function openConversation(conversationId, leadId) {
        activeConversationId = conversationId || null;
        var params = conversationId ? { conversationId: conversationId } : { leadId: leadId };

        $.getJSON(api.messages, params)
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to open conversation."); return; }

                var data = res.data;
                activeConversationId = data.conversation.id;
                window.history.replaceState(null, "", window.location.pathname);

                $("#waEmptyState").addClass("d-none");
                $("#waActiveConversation").removeClass("d-none").addClass("d-flex");
                renderHeader(data.conversation, data.windowOpen, data.windowOpenUntil, data.integrationEnabled);
                renderMessages(data.messages);
                lastMessageCount = data.messages.length;
                window.waTemplates = data.templates || [];

                $.post(api.markRead, { conversationId: activeConversationId });
                loadConversations();
                restartMessagePolling();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to open conversation.")); });
    }

    function refreshActiveConversation() {
        if (!activeConversationId) return;
        $.getJSON(api.messages, { conversationId: activeConversationId })
            .done(function (res) {
                if (!res || !res.success) return;
                var data = res.data;
                renderHeader(data.conversation, data.windowOpen, data.windowOpenUntil, data.integrationEnabled);
                if (data.messages.length !== lastMessageCount) {
                    renderMessages(data.messages);
                    lastMessageCount = data.messages.length;
                    $.post(api.markRead, { conversationId: activeConversationId });
                }
            });
    }

    function restartMessagePolling() {
        if (messageTimer) clearInterval(messageTimer);
        messageTimer = setInterval(refreshActiveConversation, 4000);
    }

    $(".responsive-chat-close").on("click", function () {
        $(".main-chart-wrapper").removeClass("responsive-chat-open");
    });

    // ---------- Composer: text ----------
    function sendPayload(formData) {
        $("#waSendBtn").prop("disabled", true);
        $.ajax({ url: api.send, type: "POST", dataType: "json", data: formData, processData: false, contentType: false })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Message could not be sent."); }
                refreshActiveConversation();
                loadConversations();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Message could not be sent.")); })
            .always(function () { $("#waSendBtn").prop("disabled", false); });
    }

    $("#waSendBtn").on("click", function () {
        var text = ($("#waMessageInput").val() || "").trim();
        if (!text || !activeConversationId) return;
        var fd = new FormData();
        fd.append("conversationId", activeConversationId);
        fd.append("messageType", "text");
        fd.append("message", text);
        $("#waMessageInput").val("");
        sendPayload(fd);
    });

    $("#waMessageInput").on("keydown", function (e) {
        if (e.key === "Enter") { e.preventDefault(); $("#waSendBtn").trigger("click"); }
    });

    // ---------- Composer: media ----------
    $("#waAttachBtn").on("click", function () { $("#waMediaInput").trigger("click"); });

    $("#waMediaInput").on("change", function () {
        var file = this.files[0];
        if (!file || !activeConversationId) return;
        var messageType = file.type === "application/pdf" ? "document" : "image";
        var fd = new FormData();
        fd.append("conversationId", activeConversationId);
        fd.append("messageType", messageType);
        fd.append("media", file);
        this.value = "";
        sendPayload(fd);
    });

    // ---------- Composer: template ----------
    $("#waTemplateBtn").on("click", function () {
        var templates = window.waTemplates || [];
        $("#waTemplateEmpty").toggleClass("d-none", templates.length > 0);
        $("#waTemplateSelect").toggleClass("d-none", templates.length === 0).html(templates.map(function (t) {
            return '<option value="' + esc(t.name) + '" data-language="' + esc(t.language || "en_US") + '" data-vars="' + (t.variableCount || 0) + '">' + esc(t.label || t.name) + '</option>';
        }).join(""));
        renderTemplateVariables();
        $("#waTemplateModal").modal("show");
    });

    function renderTemplateVariables() {
        var opt = $("#waTemplateSelect option:selected");
        var count = parseInt(opt.data("vars"), 10) || 0;
        var html = "";
        for (var i = 1; i <= count; i++) {
            html += '<div class="mb-2"><label class="form-label">Variable {{' + i + '}}</label><input type="text" class="form-control wa-template-var" /></div>';
        }
        $("#waTemplateVariables").html(html);
    }

    $("#waTemplateSelect").on("change", renderTemplateVariables);

    $("#waTemplateSendBtn").on("click", function () {
        var opt = $("#waTemplateSelect option:selected");
        if (!opt.length || !activeConversationId) { toast("warning", "Select a template."); return; }
        var fd = new FormData();
        fd.append("conversationId", activeConversationId);
        fd.append("messageType", "template");
        fd.append("templateName", opt.val());
        fd.append("templateLanguage", opt.data("language") || "en_US");
        $(".wa-template-var").each(function () { fd.append("templateVariables[]", $(this).val()); });
        $("#waTemplateModal").modal("hide");
        sendPayload(fd);
    });

    // ---------- Link / create lead ----------
    $(document).on("click", "#waOpenLinkModalBtn", function () { $("#waLinkLeadModal").modal("show"); });

    $("#waLinkExistingBtn").on("click", function () {
        var leadId = $("#waLinkLeadId").val();
        if (!leadId || !activeConversationId) return;
        $.post(api.linkLead, { conversationId: activeConversationId, action: "link", leadId: leadId })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to link lead."); return; }
                toast("success", res.message);
                $("#waLinkLeadModal").modal("hide");
                openConversation(activeConversationId);
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to link lead.")); });
    });

    $("#waLinkCreateBtn").on("click", function () {
        if (!activeConversationId) return;
        $.post(api.linkLead, { conversationId: activeConversationId, action: "create" })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to create lead."); return; }
                toast("success", res.message);
                $("#waLinkLeadModal").modal("hide");
                openConversation(activeConversationId);
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to create lead.")); });
    });

    // ---------- Boot ----------
    loadConversations(function () {
        var leadId = new URLSearchParams(window.location.search).get("leadId");
        if (leadId) {
            openConversation(null, leadId);
        }
    });
    listTimer = setInterval(function () { loadConversations(); }, 8000);
});
