// Shared status/label/color source for the Social Content Production
// workflow — used by pages/social-content-production.php (manager),
// employee/emp-content-production.php, and employee/emp-content-board.php
// so the same task shows the same badge everywhere, instead of each page
// keeping its own copy that can silently drift out of sync.
//
// Board-only visual extras (accent hex colors, section icons) stay local to
// emp-content-board.php — this file only covers what all three pages share.
window.SOCIAL_PRODUCTION_STATUS = {
    STATUS_LABEL: {
        NEW: 'New', ASSIGNED: 'Assigned', IN_PROGRESS: 'In Progress', SUBMITTED: 'Submitted',
        CORRECTION: 'Correction', APPROVED: 'Approved', PRODUCTION_READY: 'Production Ready'
    },
    STATUS_COLOR: {
        NEW: 'secondary', ASSIGNED: 'info', IN_PROGRESS: 'primary', SUBMITTED: 'warning',
        CORRECTION: 'danger', APPROVED: 'success', PRODUCTION_READY: 'dark'
    },
    REVIEW_STATUS_COLOR: {
        'Open': 'secondary',
        'Approved By Team': 'success',
        'Approved By Client': 'success',
        'Approval Pending From Client': 'warning',
        'Not Approved': 'danger',
        'Not For Use': 'dark'
    }
};
