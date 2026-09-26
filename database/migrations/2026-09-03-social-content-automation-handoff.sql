-- Phase 4.1 — Social Automation Handoff foundation.
--
-- The explicit, auditable boundary between Production (socialContentProduction)
-- and Automation (socialPosts). One row per production task that has been
-- sent to Automation via the manager's explicit "Send to Automation" action
-- (a later phase — no API/UI exists yet). Production itself never writes to
-- this table; only the new SocialAutomationHandoffEngine does.
--
-- No clientId column: derived via productionId -> socialContentProduction
-- .clientSocialContentId -> clientSocialContent.clientId, exactly how
-- socialContentProduction itself avoids duplicating clientId.
--
-- instagramAccountId is NOT NULL by design (Architecture Lock, Decision #7)
-- even though account resolution is not implemented until Phase 4.2 — this
-- table is therefore not written to at all until a real, ownership-verified
-- account id exists. Phase 4.1's engine only reads/validates; it never
-- inserts a row with a fabricated account id.
--
-- UNIQUE(productionId) is the actual duplicate-handoff guard (a database
-- constraint, not an application-level check) — a production task can never
-- have more than one handoff row, race conditions included.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE-TABLE migrations,
-- e.g. 2026-09-02c-social-content-production.sql).

CREATE TABLE socialContentAutomationHandoff (
  id INT NOT NULL AUTO_INCREMENT,
  productionId INT NOT NULL,
  instagramAccountId INT UNSIGNED NOT NULL,
  socialPostId INT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  errorMessage TEXT NULL,
  createdBy INT NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqSocialContentAutomationHandoffProduction (productionId),
  KEY idxSocialContentAutomationHandoffStatus (status),
  CONSTRAINT fkSocialContentAutomationHandoffProduction FOREIGN KEY (productionId) REFERENCES socialContentProduction(id) ON DELETE CASCADE ON UPDATE CASCADE,
  -- RESTRICT (the implicit default, stated explicitly here): an
  -- instagramAccounts row referenced by an existing handoff record cannot be
  -- deleted out from under it. Not specified as CASCADE/SET NULL by the
  -- Architecture Lock, and the column is NOT NULL, so RESTRICT is the only
  -- non-speculative choice.
  CONSTRAINT fkSocialContentAutomationHandoffAccount FOREIGN KEY (instagramAccountId) REFERENCES instagramAccounts(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fkSocialContentAutomationHandoffPost FOREIGN KEY (socialPostId) REFERENCES socialPosts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
