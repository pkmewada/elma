/* ==========================================================================
   Advance Payment — reads/writes api/payroll/*AdvancePayment*.php
   (AdvancePaymentEngine). Employee dropdown reuses the existing
   api/employee/getEmployees.php source, same as Asset Management / Walk-in.

   Payment Adjustment: Not Adjusted / Upcoming Salary / Partial Payment.
   Partial Payment stores a manually-entered recovery plan (months + a
   monthly amount HR types in -- never auto-calculated) and its generated
   schedule is read via getAdvanceRepaymentSchedule.php once the advance is
   Approved, same read pattern as Employee Loan's schedule view.
   ========================================================================== */
$(function () {

    var getListUrl = API_BASE + '/payroll/getAdvancePayments.php';
    var saveUrl = API_BASE + '/payroll/saveAdvancePayment.php';
    var updateStatusUrl = API_BASE + '/payroll/updateAdvancePaymentStatus.php';
    var getScheduleUrl = API_BASE + '/payroll/getAdvanceRepaymentSchedule.php';
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

    function adjustmentBadgeClass(adjustment) {
        if (adjustment === 'Upcoming Salary') return 'upcoming';
        if (adjustment === 'Partial Payment') return 'partial';
        return 'not-adjusted';
    }

    function loadEmployees() {
        $.getJSON(getEmployeesUrl, function (res) {
            if (!res.success) return;
            var options = (res.data || []).map(function (e) {
                return '<option value="' + e.id + '">' + esc(e.fullName) + '</option>';
            }).join('');
            $('#apEmployee').append(options);
            $('#apFormEmployee').append(options);
        });
    }

    function currentFilters() {
        return {
            employeeId: $('#apEmployee').val(),
            status: $('#apStatus').val(),
            search: $('#apSearch').val().trim()
        };
    }

    function loadList() {
        $('#apTableBody').html('<tr><td colspan="7" class="text-center text-muted py-3">Loading...</td></tr>');

        $.getJSON(getListUrl, currentFilters(), function (response) {
            if (!response.success) {
                notify('danger', response.message || 'Unable to load advance payments.');
                rows = [];
                renderList();
                return;
            }
            rows = response.data || [];
            renderList();
        }).fail(function () {
            notify('danger', 'Unable to load advance payments.');
            rows = [];
            renderList();
        });
    }

    function renderAdjustmentCell(row) {
        var label = row.paymentAdjustment || 'Not Adjusted';
        var html = '<span class="ap-adj-badge ' + adjustmentBadgeClass(label) + '">' + esc(label) + '</span>';

        if (label === 'Partial Payment' && row.totalInstallments > 0) {
            var pct = Math.round((row.paidInstallments / row.totalInstallments) * 100);
            html += '<div class="mt-1">' +
                '<div class="ap-progress"><span style="width:' + pct + '%"></span></div>' +
                '<span class="fs-11 text-muted">' + money(row.amountRecovered) + ' / ' + money(row.amount) + '</span>' +
            '</div>';
        }

        return html;
    }

    function renderList() {
        if (!rows.length) {
            $('#apTableBody').html(
                '<tr><td colspan="7" class="text-center text-muted py-3">No advance payments match the current filters.</td></tr>'
            );
            return;
        }

        var html = '';
        rows.forEach(function (row) {
            var actions = '';

            if (row.status === 'Pending') {
                actions += '<button type="button" class="btn btn-sm btn-outline-primary edit-ap-btn" data-id="' + row.id + '" title="Edit"><i class="ri-pencil-line"></i></button> ';
                actions += '<button type="button" class="btn btn-sm btn-outline-success approve-ap-btn" data-id="' + row.id + '" title="Approve"><i class="ri-check-line"></i></button> ';
                actions += '<button type="button" class="btn btn-sm btn-outline-danger cancel-ap-btn" data-id="' + row.id + '" title="Cancel"><i class="ri-close-line"></i></button>';
            } else if (row.status === 'Approved') {
                actions += '<button type="button" class="btn btn-sm btn-outline-secondary view-ap-btn" data-id="' + row.id + '" title="View"><i class="ri-eye-line"></i></button> ';
                actions += '<button type="button" class="btn btn-sm btn-outline-danger cancel-ap-btn" data-id="' + row.id + '" title="Cancel"><i class="ri-close-line"></i></button>';
            } else {
                actions += '<button type="button" class="btn btn-sm btn-outline-secondary view-ap-btn" data-id="' + row.id + '" title="View"><i class="ri-eye-line"></i></button>';
            }

            html += '<tr>' +
                '<td>' + esc(row.employeeName) + '</td>' +
                '<td>' + money(row.amount) + '</td>' +
                '<td>' + fmtDate(row.paymentDate) + '</td>' +
                '<td>' + esc(row.transactionNo || '—') + '</td>' +
                '<td><span class="ap-badge ' + row.status + '">' + row.status + '</span></td>' +
                '<td>' + renderAdjustmentCell(row) + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        });

        $('#apTableBody').html(html);
    }

    function togglePartialFields() {
        var isPartial = $('#apAdjustment').val() === 'Partial Payment';
        $('.ap-partial-field').toggleClass('d-none', !isPartial);
    }

    $('#apAdjustment').on('change', togglePartialFields);

    function resetForm() {
        $('#apId').val('');
        $('#apFormEmployee').val('');
        $('#apAmount').val('');
        $('#apPaymentDate').val('');
        $('#apPaymentMode').val('');
        $('#apTransactionNo').val('');
        $('#apReferenceNo').val('');
        $('#apRemark').val('');
        $('#apAdjustment').val('Not Adjusted');
        $('#apPartialMonths').val('');
        $('#apPartialMonthlyAmount').val('');
        $('#apPartialFirstMonth').val('');
        togglePartialFields();

        $('#apFormWrap').removeClass('d-none');
        $('#apViewDetails').addClass('d-none');
        $('#saveApBtn').show();
    }

    $('#addAdvanceBtn').on('click', function () {
        $('#apModalTitle').text('Add Advance Payment');
        resetForm();
        $('#apModal').modal('show');
    });

    function fillForm(row) {
        $('#apId').val(row.id);
        $('#apFormEmployee').val(row.employeeId);
        $('#apAmount').val(row.amount);
        $('#apPaymentDate').val(row.paymentDate);
        $('#apPaymentMode').val(row.paymentMode || '');
        $('#apTransactionNo').val(row.transactionNo || '');
        $('#apReferenceNo').val(row.referenceNo || '');
        $('#apRemark').val(row.remark || '');
        $('#apAdjustment').val(row.paymentAdjustment || 'Not Adjusted');
        $('#apPartialMonths').val(row.partialMonths || '');
        $('#apPartialMonthlyAmount').val(row.partialMonthlyAmount || '');
        $('#apPartialFirstMonth').val(row.partialFirstSalaryMonth || '');
        togglePartialFields();
    }

    $('#apTableBody').on('click', '.edit-ap-btn', function () {
        var id = $(this).data('id');
        var row = rows.find(function (r) { return String(r.id) === String(id); });
        if (!row) return;

        $('#apModalTitle').text('Edit Advance Payment');
        resetForm();
        fillForm(row);
        $('#apModal').modal('show');
    });

    function renderSchedule(schedule) {
        if (!schedule.length) {
            $('#apScheduleBody').html('<tr><td colspan="4" class="text-center text-muted">No schedule generated yet.</td></tr>');
            return;
        }
        var html = schedule.map(function (s) {
            return '<tr>' +
                '<td>' + fmtMonth(s.salaryMonth) + '</td>' +
                '<td>' + money(s.scheduledAmount) + '</td>' +
                '<td>' + (s.deductedAmount != null ? money(s.deductedAmount) : '—') + '</td>' +
                '<td><span class="ap-badge ' + s.status + '">' + s.status + '</span></td>' +
            '</tr>';
        }).join('');
        $('#apScheduleBody').html(html);
    }

    function renderViewDetails(row) {
        $('#apViewEmployee').text(row.employeeName || '--');
        $('#apViewAmount').text(money(row.amount));
        $('#apViewPaymentDate').text(fmtDate(row.paymentDate));
        $('#apViewPaymentMode').text(row.paymentMode || '—');
        $('#apViewTransactionNo').text(row.transactionNo || '—');
        $('#apViewReferenceNo').text(row.referenceNo || '—');
        $('#apViewStatus').html('<span class="ap-badge ' + row.status + '">' + esc(row.status) + '</span>');
        $('#apViewRemark').text(row.remark || '—');

        var adjustment = row.paymentAdjustment || 'Not Adjusted';
        $('#apViewAdjustment').html('<span class="ap-adj-badge ' + adjustmentBadgeClass(adjustment) + '">' + esc(adjustment) + '</span>');

        var isPartial = adjustment === 'Partial Payment';
        $('.ap-partial-view').toggleClass('d-none', !isPartial);

        if (isPartial) {
            $('#apViewPartialSummary').text(
                (row.partialMonths || '—') + ' month(s) intended, ' +
                money(row.partialMonthlyAmount) + '/month starting ' + fmtMonth(row.partialFirstSalaryMonth) +
                ' — recovered ' + money(row.amountRecovered) + ' of ' + money(row.amount)
            );
            $('#apScheduleBody').html('<tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>');
            $.getJSON(getScheduleUrl, { advanceId: row.id }, function (res) {
                if (!res.success) {
                    notify('danger', res.message || 'Unable to load repayment schedule.');
                    return;
                }
                renderSchedule(res.data || []);
            });
        }
    }

    $('#apTableBody').on('click', '.view-ap-btn', function () {
        var id = $(this).data('id');
        var row = rows.find(function (r) { return String(r.id) === String(id); });
        if (!row) return;

        $('#apModalTitle').text('Advance Payment Details');
        $('#apFormWrap').addClass('d-none');
        $('#apViewDetails').removeClass('d-none');
        $('#saveApBtn').hide();
        renderViewDetails(row);
        $('#apModal').modal('show');
    });

    $('#saveApBtn').on('click', function () {
        var employeeId = $('#apFormEmployee').val();
        var amount = parseFloat($('#apAmount').val());
        var paymentDate = $('#apPaymentDate').val();
        var adjustment = $('#apAdjustment').val();

        if (!employeeId) { notify('danger', 'Please select an employee.'); return; }
        if (!amount || amount <= 0) { notify('danger', 'Enter a valid amount.'); return; }
        if (!paymentDate) { notify('danger', 'Payment date is required.'); return; }

        var payload = {
            employeeId: employeeId,
            amount: amount,
            paymentDate: paymentDate,
            paymentMode: $('#apPaymentMode').val(),
            transactionNo: $('#apTransactionNo').val().trim(),
            referenceNo: $('#apReferenceNo').val().trim(),
            remark: $('#apRemark').val().trim(),
            paymentAdjustment: adjustment
        };

        if (adjustment === 'Partial Payment') {
            var months = parseInt($('#apPartialMonths').val(), 10);
            var monthlyAmount = parseFloat($('#apPartialMonthlyAmount').val());
            var firstMonth = $('#apPartialFirstMonth').val();

            if (!months || months < 1 || months > 12) { notify('danger', 'Number of months must be between 1 and 12.'); return; }
            if (!monthlyAmount || monthlyAmount <= 0) { notify('danger', 'Enter a valid monthly deduction amount.'); return; }
            if (monthlyAmount > amount) { notify('danger', 'Monthly deduction amount cannot exceed the advance amount.'); return; }
            if (!firstMonth) { notify('danger', 'First salary month is required.'); return; }

            payload.partialMonths = months;
            payload.partialMonthlyAmount = monthlyAmount;
            payload.partialFirstSalaryMonth = firstMonth;
        }

        var id = $('#apId').val();
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
                notify('success', response.message || 'Advance payment saved successfully.');
                $('#apModal').modal('hide');
                loadList();
            } else {
                notify('danger', response.message || 'Failed to save advance payment.');
            }
        }).fail(function () {
            notify('danger', 'Failed to save advance payment.');
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

    $('#apTableBody').on('click', '.approve-ap-btn', function () {
        updateStatus($(this).data('id'), 'approve', 'Approve this advance payment? Its configured Payment Adjustment will take effect starting the next applicable payroll run.');
    });

    $('#apTableBody').on('click', '.cancel-ap-btn', function () {
        updateStatus($(this).data('id'), 'cancel', 'Cancel this advance payment? Any undeducted installments will be waived.');
    });

    $('#apEmployee, #apStatus').on('change', loadList);

    var searchTimer = null;
    $('#apSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadList, 300);
    });

    $('#apResetBtn').on('click', function () {
        $('#apEmployee').val('');
        $('#apStatus').val('');
        $('#apSearch').val('');
        loadList();
    });

    loadEmployees();
    loadList();

});
