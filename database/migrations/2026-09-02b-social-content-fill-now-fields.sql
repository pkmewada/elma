-- Social Media Overview — "Fill Now" fields.
--
-- pages/social-overview.php's Fill Now modal collects a different field set
-- than social-data-entry.php's Add Entry modal (Social Media Handle, Post
-- Type, Raw Content, Song URL, Reference, Title, Content Description vs
-- Content Title/Status/Caption/Reference Link/Remarks). Both modals fill in
-- the same underlying "content for this planned slot" row, so they share
-- clientSocialContent rather than getting a second table — this just adds
-- the extra nullable columns Fill Now needs, and relaxes `title` since Fill
-- Now leaves it optional.
--
-- This file runs once per environment, same as this repo's other
-- ALTER-based migrations — re-running it will error on duplicate column
-- names, which is expected.

ALTER TABLE clientSocialContent
    MODIFY title VARCHAR(150) NULL,
    ADD COLUMN socialMediaHandle VARCHAR(150) NULL AFTER title,
    ADD COLUMN postType VARCHAR(50) NULL AFTER socialMediaHandle,
    ADD COLUMN rawContent TEXT NULL AFTER postType,
    ADD COLUMN songUrl VARCHAR(255) NULL AFTER rawContent,
    ADD COLUMN ideaReference VARCHAR(255) NULL AFTER songUrl,
    ADD COLUMN contentDescription TEXT NULL AFTER ideaReference;
