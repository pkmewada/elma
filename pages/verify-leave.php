<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

<style>
.table-responsive {
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.table-responsive::-webkit-scrollbar {
    display: none;
}

.dataTables_filter {
    display: none !important;
}

.custom-search input {
    border: 1px solid #dee2e6 !important;
    border-radius: 6px !important;
    padding: 8px 12px !important;
    min-width: 250px;
}

.dataTables_paginate .pagination {
    margin-bottom: 0 !important;
}

.dataTables_info {
    padding-top: 8px;
}

#statusFilter {
    min-width: 220px;
}

#customSearch {
    min-width: 260px;
}

@media (max-width: 768px) {

    #customSearch,
    #statusFilter {
        width: 100% !important;
        min-width: 100%;
    }

}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Leave Management</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">
                        All Leaves
                    </li>
                </ol>
            </div>
        </div>

        <!-- TABLE -->
        <!-- FILTER CARD -->
        <div class="card custom-card mb-3">
        
            <div class="card-body p-3">
        
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        
                    <!-- LEFT -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
        
                        <!-- EXPORT -->
                        <div style="min-width:160px;">
        
                            <div class="btn-group w-100">
        
                                <button type="button"
                                    class="btn btn-outline-primary dropdown-toggle w-100"
                                    data-bs-toggle="dropdown">
        
                                    Export
        
                                </button>
        
                                <ul class="dropdown-menu">
        
                                    <li>
        
                                        <a class="dropdown-item export-btn"
                                            data-type="csv"
                                            href="javascript:void(0);">
        
                                            CSV
        
                                        </a>
        
                                    </li>
        
                                    <li>
        
                                        <a class="dropdown-item export-btn"
                                            data-type="pdf"
                                            href="javascript:void(0);">
        
                                            PDF
        
                                        </a>
        
                                    </li>
        
                                </ul>
        
                            </div>
        
                        </div>
        
                        <!-- FILTER -->
                        <div style="min-width:220px;">
        
                            <select id="statusFilter"
                                class="form-select">
        
                                <option value="">
                                    All Status
                                </option>
        
                                <option value="pending">
                                    Pending
                                </option>
        
                                <option value="approved">
                                    Approved
                                </option>
        
                                <option value="rejected">
                                    Rejected
                                </option>
        
                                <option value="cancelled">
                                    Cancelled
                                </option>
        
                            </select>
        
                        </div>
        
                    </div>
        
                    <!-- CENTER -->
                    <div class="flex-fill"></div>
        
                    <!-- RIGHT -->
                    <div class="custom-search">
        
                        <input type="text"
                            id="customSearch"
                            class="form-control"
                            placeholder="Search leaves..."
                            autocomplete="off">
        
                    </div>
        
                </div>
        
            </div>
        
        </div>
        
        <!-- TABLE CARD -->
        <div class="card custom-card">
        
            <div class="card-header">
        
                <div class="card-title">
                    All Leaves
                </div>
        
            </div>
        
            <div class="card-body">
        
                <!-- TABLE -->
                <div class="table-responsive">
        
                    <table class="table table-hover text-nowrap"
                        id="adminLeaveTable">
        
                        <thead>
        
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
        
                        </thead>
        
                        <tbody id="adminLeaveBody"></tbody>
        
                    </table>
        
                </div>
        
            </div>
        
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function() {

    const API = {
        list: API_BASE + '/leave/getAllLeaves.php',
        update: API_BASE + '/leave/updateLeaveStatus.php'
    };

    let table;

    function loadLeaves() {

        $.getJSON(API.list, function(res) {

            let html = '';

            if (!res.success || !res.data.length) {

                html = `
                    <tr>
                        <td colspan="7" class="text-center">
                            No data found
                        </td>
                    </tr>
                `;

            } else {

                res.data.forEach((row, i) => {

                    html += `
                        <tr>
                            <td>${i + 1}</td>

                            <td>
                                ${row.employeeName || '-'}
                            </td>

                            <td>
                                ${row.leaveType}
                            </td>

                            <td>
                                ${row.fromDate} → ${row.toDate}
                            </td>

                            <td>
                                ${row.totalDays}
                            </td>

                            <td data-status="${row.status}">
                                <span class="btn btn-sm btn-outline-${getColor(row.status)}">
                                    ${row.status}
                                </span>
                            </td>
                            
                            <td>
                                ${row.status === 'pending' ? `
                            
                                    <a href="javascript:void(0);"
                                        class="btn btn-icon btn-sm btn-outline-success btn-wave waves-effect waves-light approveBtn"
                                        data-id="${row.id}"
                                        title="Approve">
                                        <i class="ri-check-line"></i>
                                    </a>
                            
                                    <a href="javascript:void(0);"
                                        class="btn btn-icon btn-sm btn-outline-danger btn-wave waves-effect waves-light rejectBtn"
                                        data-id="${row.id}"
                                        title="Reject">
                                        <i class="ri-close-line"></i>
                                    </a>
                            
                                ` : '-'}
                            </td>
                        </tr>
                    `;
                });
            }

            $('#adminLeaveBody').html(html);

            if ($.fn.DataTable.isDataTable('#adminLeaveTable')) {
                $('#adminLeaveTable').DataTable().destroy();
            }

            table = $('#adminLeaveTable').DataTable({

                dom:
                    "rt" +
                    "<'d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2'<'datatable-info'i><'datatable-pagination'p>>",

                pageLength: 10,

                ordering: true,

                order: [
                    [0, 'asc']
                ],

                lengthChange: false,

                autoWidth: false,

                responsive: false,

                searching: true,

                language: {
                    emptyTable: "No leave records found",
                    zeroRecords: "No matching leave records found",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries available",

                    paginate: {
                        previous: "Prev",
                        next: "Next"
                    }
                },

                buttons: [{
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],

                drawCallback: function() {

                    var api = this.api();

                    api.rows({
                        page: 'current'
                    }).every(function(rowIdx) {

                        $(this.node())
                            .find('td:eq(0)')
                            .html(rowIdx + 1);
                    });
                }
            });

            $('#customSearch').off().on('keyup input', function() {
                table.search($.trim($(this).val())).draw();
            });

            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

                if (settings.nTable.id !== 'adminLeaveTable') {
                    return true;
                }

                let status = $('#statusFilter').val();

                let row = table.row(dataIndex).node();

                if (!row) return true;

                let rowStatus = $(row)
                    .find('td[data-status]')
                    .attr('data-status');

                if (status && rowStatus !== status) {
                    return false;
                }

                return true;
            });

            $('#statusFilter').off().on('change', function() {
                table.draw();
            });

        });
    }

    $(document).on('click', '.export-btn', function() {

        let type = $(this).data('type');

        if (type === 'csv') {
            table.buttons('.buttons-csv').trigger();
        }

        if (type === 'pdf') {
            table.buttons('.buttons-pdf').trigger();
        }
    });

    function getColor(status) {

        if (status === 'approved') return 'success';

        if (status === 'rejected') return 'danger';

        if (status === 'cancelled') return 'secondary';

        return 'warning';
    }

    function updateStatus(id, status) {

        $.post(API.update, {
            id,
            status
        }, function(res) {

            if (res.success) {

                showToast('success', res.message);

                loadLeaves();

            } else {

                showToast('danger', res.message);
            }

        }, 'json');
    }

    $(document).on('click', '.approveBtn', function() {

        let id = $(this).data('id');

        Swal.fire({
            title: 'Approve this leave?',
            icon: 'question',
            showCancelButton: true
        }).then(r => {

            if (r.isConfirmed) {
                updateStatus(id, 'approved');
            }
        });
    });

    $(document).on('click', '.rejectBtn', function() {

        let id = $(this).data('id');

        Swal.fire({
            title: 'Reject this leave?',
            icon: 'warning',
            showCancelButton: true
        }).then(r => {

            if (r.isConfirmed) {
                updateStatus(id, 'rejected');
            }
        });
    });

    loadLeaves();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>