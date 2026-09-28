<?php
/*
 * Project-wide UI rules shared by the admin and employee layouts
 * (included from header.php / emp-header.php).
 *
 * FILTER BAR RULE (every list page): all filters, the search box and the
 * filter buttons sit on ONE row on desktop; the row wraps only on small
 * screens (< 992px). Use <div class="crm-filter-bar"> ... </div> and give
 * the search input the class "crm-filter-search". Never put a bare
 * .form-select/.form-control in a flex-wrap row (Bootstrap makes it 100%
 * wide, which stacks every filter vertically).
 *
 * STATUS BADGE RULE: the pipeline badge colors (.lead-status-<status>,
 * matching includes/leadAccess.php's LEAD_STATUSES keys) live here so every
 * page that shows a lead's status -- Leads, Reports, anywhere else -- gets
 * the same colors without redefining them.
 */
?>
<style>
    .crm-filter-bar {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 0.5rem;
        width: 100%;
    }

    .crm-filter-bar > * {
        flex: 0 1 auto;
        min-width: 0;
    }

    .crm-filter-bar > .form-select,
    .crm-filter-bar > .form-control,
    .crm-filter-bar > .crm-filter-field {
        width: auto;
        flex: 1 1 150px;
        min-width: 110px;
        max-width: 220px;
    }

    .crm-filter-bar > .crm-filter-search {
        margin-left: auto;
        flex: 0 1 220px;
        max-width: 240px;
    }

    .crm-filter-bar > .btn,
    .crm-filter-bar > .btn-group {
        flex: 0 0 auto;
    }

    @media (max-width: 991.98px) {
        .crm-filter-bar {
            flex-wrap: wrap;
        }

        .crm-filter-bar > .form-select,
        .crm-filter-bar > .form-control,
        .crm-filter-bar > .crm-filter-field {
            max-width: none;
            flex: 1 1 140px;
        }

        .crm-filter-bar > .crm-filter-search {
            margin-left: 0;
            max-width: none;
            flex: 1 1 100%;
        }
    }

    .lead-status-new { background: rgba(13, 202, 240, 0.15); color: #0aa2c0; }
    .lead-status-contacted { background: rgba(108, 117, 125, 0.15); color: #5c636a; }
    .lead-status-interested { background: rgba(255, 193, 7, 0.18); color: #b58900; }
    .lead-status-follow_up { background: rgba(253, 126, 20, 0.15); color: #d9660b; }
    .lead-status-site_visit { background: rgba(111, 66, 193, 0.15); color: #6f42c1; }
    .lead-status-negotiation { background: rgba(13, 110, 253, 0.15); color: #0d6efd; }
    .lead-status-converted { background: rgba(25, 135, 84, 0.15); color: #198754; }
    .lead-status-lost { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
</style>
