/* ==========================================================================
   Employee Loan — reads/writes api/payroll/*EmployeeLoan*.php /
   getLoanRepaymentSchedule.php (EmployeeLoanEngine). Employee dropdown
   reuses api/employee/getEmployees.php, same as Advance Payment / Assets.
   ========================================================================== */
$(function () {

    var getListUrl = API_BASE + '/payroll/getEmployeeLoans.php';
    var saveUrl = API_BASE + '/payroll/saveEmployeeLoan.php';
    var updateStatusUrl = API_BASE + '/payroll/updateEmployeeLoanStatus.php';
    var getScheduleUrl = API_BASE + '/payroll/getLoanRepaymentSchedule.php';
    var getEmployeesUrl = API_BASE + '/employee/getEmployees.php';

    var rows = [];

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function notify(type, message) {
        if (typeof showToast === 'function') showToast(type, message);
        else if (window.Swal) Swal.fire({ icon: type === 'danger' ? 'error' : type, text: message });
    }

    function money(value) {
        return 'Rs. ' + Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function fmtMonth(salaryMonth) {
        if (!salaryMonth) return '—';
        var d = new Date(salaryMonth + '-01T00:00:00');
        if (isNaN(d.getTime())) return salaryMonth;
        return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    }

    function loadEmployees() {
        $.getJSON(getEmployeesUrl, function (res) {
            if (!res.success) return;
            var options = (res.data || []).map(function (e) {
                return '<option value="' + e.id + '">' + esc(e.fullName) + '</option>';
            }).join('');
            $('#elEmployee').append(options);
            $('#elFormEmployee').html('<option value="">Select Employee</option>' + options);
        });
    }

    function currentFilters() {
        return {
            employeeId: $('#elEmployee').val(),
            status: $('#elStatus').val(),
            search: $('#elSearch').val().trim()
        };
    }

    function loadList() {
        $('#elTableBody').html('<tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>');

        $.getJSON(getListUrl, currentFilters(), function (response) {
            if (!response.success) {
                notify('danger', response.message || 'Unable to load loans.');
                rows = [];
                renderList();
                return;
            }
            rows = response.data || [];
            renderList();
        }).fail(function () {
            notify('danger', 'Unable to load loans.');
            rows = [];
            renderList();
        });
    }

    function renderList() {
        if (!rows.length) {
            $('#elTableBody').html(
                '<tr><td colspan="8" class="text-center text-muted py-3">No loans match the current filters.</td></tr>'
            );
            return;
        }

        var html = '';
        rows.forEach(function (row) {
            var pct = row.totalInstallments > 0 ? Math.round((row.paidInstallments / row.totalInstallments) * 100) : 0;
            var actions = '';

            if (row.status === 'Pending') {
                actions += '<button type="button" class="btn btn-sm btn-outline-primary edit-el-btn" data-id="' + row.id + '" title="Edit"><i class="ri-pencil-line"></i></button> ';
                actions += '<button type="button" class="btn btn-sm btn-outline-success approve-el-btn" data-id="' + row.id + '" title="Approve"><i class="ri-check-line"></i></button> ';
                actions += '<button type="button" class="btn btn-sm btn-outline-danger cancel-el-btn" data-id="' + row.id + '" title="Cancel"><i class="ri-close-line"></i></button>';
            } else if (row.status === 'Approved') {
                actions += '<button type="button" class="btn btn-sm btn-outline-secondary view-el-btn" data-id="' + row.id + '" title="View Schedule"><i class="ri-eye-line"></i></button> ';
                actions += '<button type="button" class="btn btn-sm btn-outline-danger cancel-el-btn" data-id="' + row.id + '" title="Cancel"><i class="ri-close-line"></i></button>';
            } else {
                actions += '<button type="button" class="btn btn-sm btn-outline-secondary view-el-btn" data-id="' + row.id + '" title="View Schedule"><i class="ri-eye-line"></i></button>';
            }

            html += '<tr>' +
                '<td>' + esc(row.employeeName) + '</td>' +
                '<td>' + money(row.loanAmount) + '</td>' +
                '<td>' + row.duration + ' mo</td>' +
                '<td>' + money(row.monthlyDeduction) + '</td>' +
                '<td>' + money(row.amountRecovered) + ' <span class="text-muted fs-12">/ ' + money(row.loanAmount) + '</span></td>' +
                '<td>' +
                    '<div class="el-progress"><span style="width:' + pct + '%"></span></div>' +
                    '<span class="fs-11 text-muted">' + row.paidInstallments + '/' + row.totalInstallments + '</span>' +
                '</td>' +
                '<td><span class="el-badge ' + row.status + '">' + row.status + '</span></td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        });

        $('#elTableBody').html(html);
    }

    function resetForm() {
        $('#elId').val('');
        $('#elFormEmployee').val('').prop('disabled', false);
        $('#elLoanAmount').val('').prop('disabled', false);
        $('#elStartDate').val('').prop('disabled', false);
        $('#elDuration').val('').prop('disabled', false);
        $('#elMonthlyDeduction').val('').prop('disabled', false);
        $('#elRemark').val('').prop('disabled', false);
        $('#elScheduleWrap').addClass('d-none');
        $('#elScheduleBody').html('');
        $('#saveElBtn').show();
    }

    $('#addLoanBtn').on('click', function () {
        $('#elModalTitle').text('Add Loan');
        resetForm();
        $('#elModal').modal('show');
    });

    function fillForm(row) {
        $('#elId').val(row.id);
        $('#elFormEmployee').val(row.employeeId);
        $('#elLoanAmount').val(row.loanAmount);
        $('#elStartDate').val(row.startDate);
        $('#elDuration').val(row.duration);
        $('#elMonthlyDeduction').val(row.monthlyDeduction);
        $('#elRemark').val(row.remark || '');
    }

    function renderSchedule(schedule) {
        if (!schedule.length) {
            $('#elScheduleBody').html('<tr><td colspan="3" class="text-center text-muted">No schedule generated yet.</td></tr>');
            return;
        }
        var html = schedule.map(function (s) {
            return '<tr>' +
                '<td>' + fmtMonth(s.salaryMonth) + '</td>' +
                '<td>' + money(s.deductionAmount) + '</td>' +
                '<td><span class="el-badge ' + s.status + '">' + s.status + '</span></td>' +
            '</tr>';
        }).join('');
        $('#elScheduleBody').html(html);
    }

    $('#elTableBody').on('click', '.edit-el-btn', function () {
        var id = $(this).data('id');
        var row = rows.find(function (r) { return String(r.id) === String(id); });
        if (!row) return;

        $('#elModalTitle').text('Edit Loan');
        resetForm();
        fillForm(row);
        $('#elModal').modal('show');
    });

    $('#elTableBody').on('click', '.view-el-btn', function () {
        var id = $(this).data('id');
        var row = rows.find(function (r) { return String(r.id) === String(id); });
        if (!row) return;

        $('#elModalTitle').text('View Loan');
        resetForm();
        fillForm(row);
        $('#elFormEmployee, #elLoanAmount, #elStartDate, #elDuration, #elMonthlyDeduction, #elRemark').prop('disabled', true);
        $('#saveElBtn').hide();
        $('#elScheduleWrap').removeClass('d-none');
        $('#elScheduleBody').html('<tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>');
        $('#elModal').modal('show');

        $.getJSON(getScheduleUrl, { loanId: id }, function (res) {
            if (!res.success) {
                notify('danger', res.message || 'Unable to load repayment schedule.');
                return;
            }
            renderSchedule(res.data || []);
        });
    });

    $('#saveElBtn').on('click', function () {
        var employeeId = $('#elFormEmployee').val();
        var loanAmount = parseFloat($('#elLoanAmount').val());
        var startDate = $('#elStartDate').val();
        var duration = parseInt($('#elDuration').val(), 10);

        if (!employeeId) { notify('danger', 'Please select an employee.'); return; }
        if (!loanAmount || loanAmount <= 0) { notify('danger', 'Enter a valid loan amount.'); return; }
        if (!startDate) { notify('danger', 'Loan start date is required.'); return; }
        if (!duration || duration < 1 || duration > 12) { notify('danger', 'Duration must be between 1 and 12 months.'); return; }

        var payload = {
            employeeId: employeeId,
            loanAmount: loanAmount,
            startDate: startDate,
            duration: duration,
            monthlyDeduction: $('#elMonthlyDeduction').val() || 0,
            remark: $('#elRemark').val().trim()
        };

        var id = $('#elId').val();
        if (id) payload.id = id;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: saveUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload)
        }).done(function (response) {
            if (response.success) {
                notify('success', response.message || 'Loan saved successfully.');
                $('#elModal').modal('hide');
                loadList();
            } else {
                notify('danger', response.message || 'Failed to save loan.');
            }
        }).fail(function () {
            notify('danger', 'Failed to save loan.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    function updateStatus(id, action, confirmText) {
        if (!confirm(confirmText)) return;

        $.ajax({
            url: updateStatusUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ id: id, action: action })
        }).done(function (response) {
            if (response.success) {
                notify('success', response.message || 'Updated successfully.');
                loadList();
            } else {
                notify('danger', response.message || 'Failed to update.');
            }
        }).fail(function () {
            notify('danger', 'Failed to update.');
        });
    }

    $('#elTableBody').on('click', '.approve-el-btn', function () {
        updateStatus($(this).data('id'), 'approve', 'Approve this loan and generate its repayment schedule?');
    });

    $('#elTableBody').on('click', '.cancel-el-btn', function () {
        updateStatus($(this).data('id'), 'cancel', 'Cancel this loan? Any undeducted installments will be waived.');
    });

    $('#elEmployee, #elStatus').on('change', loadList);

    var searchTimer = null;
    $('#elSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadList, 300);
    });

    $('#elResetBtn').on('click', function () {
        $('#elEmployee').val('');
        $('#elStatus').val('');
        $('#elSearch').val('');
        loadList();
    });

    loadEmployees();
    loadList();

});
