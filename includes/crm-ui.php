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
</style>
