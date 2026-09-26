-- Leave Management: date-wise approval support.
--
-- Parent leaveApplications rows remain the master request. This child table
-- stores only the counted leave dates for per-date review.

ALTER TABLE leaveApplications
    MODIFY status ENUM('pending', 'approved', 'rejected', 'partial', 'cancelled') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS leaveApplicationDays (
    id INT NOT NULL AUTO_INCREMENT,
    leaveApplicationId INT NOT NULL,
    leaveDate DATE NOT NULL,
    dayType ENUM('full', 'half') NOT NULL DEFAULT 'full',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewedBy INT NULL,
    reviewedAt DATETIME NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_leave_application_day (leaveApplicationId, leaveDate),
    KEY idx_lad_leave_status (leaveApplicationId, status),
    KEY idx_lad_date_status (leaveDate, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
