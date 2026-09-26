ALTER TABLE clientCalendarPlans
    ADD COLUMN removedDates LONGTEXT DEFAULT NULL AFTER selectedDates;
