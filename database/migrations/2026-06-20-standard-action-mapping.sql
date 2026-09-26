-- Connect registered controls to the standard page permissions.
-- Only true special actions continue to use independent action grants.

START TRANSACTION;

ALTER TABLE permissionActions
    ADD COLUMN IF NOT EXISTS permissionType
        ENUM('canAdd', 'canEdit', 'canDelete', 'canApprove', 'special')
        NOT NULL DEFAULT 'special'
        AFTER actionLabel;

UPDATE permissionActions
SET permissionType = 'canAdd'
WHERE actionKey IN (
    'add_lead',
    'add_lead_category',
    'add_lead_plan',
    'add_candidate',
    'add_event',
    'add_asset',
    'add_leave_type',
    'apply_leave',
    'add_break_type',
    'add_overtime',
    'add_deduction',
    'add_expense',
    'add_point_category',
    'add_point_transaction',
    'add_commission_category',
    'add_commission_transaction'
);

UPDATE permissionActions
SET permissionType = 'canEdit'
WHERE actionKey IN (
    'edit_lead',
    'add_lead_remark',
    'update_lead_status',
    'edit_lead_category',
    'edit_lead_plan',
    'save_lead_setup',
    'edit_candidate',
    'add_candidate_remark',
    'save_agreement_draft',
    'edit_employee',
    'change_employment_status',
    'edit_event',
    'edit_leave_type',
    'save_leave_setup',
    'edit_attendance',
    'edit_break_type',
    'save_attendance_setup',
    'edit_deduction',
    'edit_expense',
    'edit_point_category',
    'save_point_setup',
    'edit_point_transaction',
    'edit_commission_category',
    'save_commission_setup',
    'edit_commission_transaction',
    'edit_overtime',
    'edit_profile'
);

UPDATE permissionActions
SET permissionType = 'canDelete'
WHERE actionKey IN (
    'delete_lead',
    'delete_lead_category',
    'delete_lead_plan',
    'delete_candidate',
    'delete_event',
    'delete_asset',
    'delete_leave_type',
    'cancel_leave',
    'delete_break_type',
    'delete_deduction',
    'delete_expense',
    'delete_point_category',
    'delete_point_transaction',
    'delete_commission_category',
    'delete_commission_transaction',
    'delete_overtime'
);

UPDATE permissionActions
SET permissionType = 'canApprove'
WHERE actionKey IN (
    'submit_hr_review',
    'reject_hr_review',
    'review_agreement',
    'review_leave',
    'approve_overtime',
    'reject_overtime',
    'approve_expense',
    'reject_expense',
    'approve_commission_transaction',
    'reject_commission_transaction'
);

UPDATE permissionActions
SET permissionType = 'special'
WHERE actionKey IN (
    'import_leads',
    'upload_lead_document',
    'assign_asset',
    'return_asset',
    'final_verify_candidate',
    'send_agreement',
    'punch_in',
    'punch_out',
    'start_break',
    'end_break'
);

-- Standard mappings no longer use duplicate action grants.
DELETE rap
FROM roleActionPermissions rap
INNER JOIN permissionActions pa ON pa.id = rap.actionId
WHERE pa.permissionType <> 'special';

DELETE uap
FROM userActionPermissionOverrides uap
INNER JOIN permissionActions pa ON pa.id = uap.actionId
WHERE pa.permissionType <> 'special';

COMMIT;
