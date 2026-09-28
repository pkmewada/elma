-- Real Estate CRM — baseline schema (Phase 1).
--
-- The CRM runs on its own database (`elma_realestate_crm`), NOT the Modlus
-- database. This file creates the reused foundation tables that the kept
-- code needs (auth, employees, routes/permissions, Lead Management, mail
-- log) with no data. Structure was taken read-only from the Modlus schema
-- the code was written against; Modlus-only tables (HRMS, payroll, social,
-- agreements, recruitment) are intentionally absent.
--
-- Table names use the casing the code uses (Linux MySQL is case-sensitive):
-- `employeeusers` is lower-case in the code, everything else camelCase.
-- Idempotent: CREATE TABLE IF NOT EXISTS only.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otpExpiresAt` datetime DEFAULT NULL,
  `isVerified` tinyint(1) DEFAULT '0',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `employeeusers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employeeCode` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fullName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userName` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emailAddress` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passwordHash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tempPassword` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isTempPassword` tinyint(1) DEFAULT '1',
  `lastLoginAt` datetime DEFAULT NULL,
  `mobileNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alternativeNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergencyContactNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dateOfBirth` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maritalStatus` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedInProfile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagramProfile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanentAddress` text COLLATE utf8mb4_unicode_ci,
  `localAddress` text COLLATE utf8mb4_unicode_ci,
  `cityName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stateName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pinCode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departmentName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designationName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `joiningDate` date DEFAULT NULL,
  `employmentStatus` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `employeeType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reportingManager` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `basicSalary` decimal(12,2) DEFAULT '0.00',
  `hraAmount` decimal(12,2) DEFAULT '0.00',
  `allowanceAmount` decimal(12,2) DEFAULT '0.00',
  `deductionAmount` decimal(12,2) DEFAULT '0.00',
  `netSalary` decimal(12,2) DEFAULT '0.00',
  `otherEarning1Amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `otherEarning2Amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `otherEarning3Amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `otherAllowanceAmount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paymentFrequency` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Monthly',
  `nextIncrementDate` date DEFAULT NULL,
  `accountHolderName` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bankName` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accountNumber` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ifscCode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branchName` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aadhaarNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panNumber` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accountStatus` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `profileStatus` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Incomplete',
  `hrRemark` text COLLATE utf8mb4_unicode_ci,
  `aboutMe` text COLLATE utf8mb4_unicode_ci,
  `skills` text COLLATE utf8mb4_unicode_ci,
  `verifiedBy` int DEFAULT NULL,
  `verifiedAt` datetime DEFAULT NULL,
  `joiningStatus` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `candidateRecordId` int DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profilePhoto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aadhaarFile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panFile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marksheet10File` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marksheet12File` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graduationFile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bankPassbookFile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otpExpiresAt` datetime DEFAULT NULL,
  `defaultRouteId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emailAddress` (`emailAddress`),
  UNIQUE KEY `employeeCode` (`employeeCode`),
  UNIQUE KEY `userName` (`userName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `routesMaster` (
  `id` int NOT NULL AUTO_INCREMENT,
  `routePath` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pageFile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `routeTitle` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `moduleName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `layoutType` enum('admin','employee','public') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `iconClass` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parentRouteId` int DEFAULT NULL,
  `isPublic` tinyint(1) DEFAULT '0',
  `isMenuVisible` tinyint(1) DEFAULT '1',
  `isActive` tinyint(1) DEFAULT '1',
  `sortOrder` int DEFAULT '0',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueRoutePath` (`routePath`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `rolePermissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `roleName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `routeId` int NOT NULL,
  `canView` tinyint(1) DEFAULT '0',
  `canAdd` tinyint(1) DEFAULT '0',
  `canEdit` tinyint(1) DEFAULT '0',
  `canDelete` tinyint(1) DEFAULT '0',
  `canApprove` tinyint(1) DEFAULT '0',
  `canExport` tinyint(1) DEFAULT '0',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueRoleRoute` (`roleName`,`routeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `userPermissionOverrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userId` int NOT NULL,
  `routeId` int NOT NULL,
  `overrideType` enum('grant','revoke') COLLATE utf8mb4_unicode_ci NOT NULL,
  `canView` tinyint(1) DEFAULT '0',
  `canAdd` tinyint(1) DEFAULT '0',
  `canEdit` tinyint(1) DEFAULT '0',
  `canDelete` tinyint(1) DEFAULT '0',
  `canApprove` tinyint(1) DEFAULT '0',
  `canExport` tinyint(1) DEFAULT '0',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueUserRoute` (`userId`,`routeId`),
  UNIQUE KEY `unique_employee_route` (`userId`,`routeId`),
  UNIQUE KEY `unique_user_route` (`userId`,`routeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `permissionActions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `routeId` int NOT NULL,
  `actionKey` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actionLabel` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissionType` enum('canAdd','canEdit','canDelete','canApprove','special') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'special',
  `buttonSelector` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apiEndpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `httpMethod` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `sortOrder` int NOT NULL DEFAULT '0',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueRouteAction` (`routeId`,`actionKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `roleActionPermissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `roleName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actionId` int NOT NULL,
  `canAccess` tinyint(1) NOT NULL DEFAULT '0',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueRoleAction` (`roleName`,`actionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `userActionPermissionOverrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userId` int NOT NULL,
  `actionId` int NOT NULL,
  `canAccess` tinyint(1) NOT NULL DEFAULT '0',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueUserAction` (`userId`,`actionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `countryCode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoryId` int unsigned DEFAULT NULL,
  `planId` int unsigned DEFAULT NULL,
  `createdByCandidateId` int DEFAULT NULL,
  `status` enum('open','interested','connected','converted','not_interested','not_connected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `orgName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_leads_category` (`categoryId`),
  KEY `fk_leads_plan` (`planId`),
  KEY `idx_leads_status` (`status`),
  KEY `idx_leads_created_at` (`createdAt`),
  KEY `idx_leads_category` (`categoryId`),
  KEY `idx_leads_plan` (`planId`),
  KEY `idx_leads_employee` (`createdByCandidateId`),
  KEY `idx_leads_source` (`source`),
  KEY `idx_leads_org_name` (`orgName`),
  KEY `idx_leads_email` (`email`),
  KEY `idx_leads_phone` (`phone`),
  CONSTRAINT `fk_leads_category` FOREIGN KEY (`categoryId`) REFERENCES `leadCategories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leads_plan` FOREIGN KEY (`planId`) REFERENCES `leadPlans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadRemarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `leadId` int NOT NULL,
  `remark` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `followUpDateTime` datetime DEFAULT NULL,
  `createdByCandidateId` int unsigned NOT NULL,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT NULL,
  `followUpremark` enum('open','close') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `idx_leadId` (`leadId`),
  CONSTRAINT `fk_leadRemarks_lead` FOREIGN KEY (`leadId`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadStatusRemarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `leadId` int NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remark` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdByCandidateId` int NOT NULL,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxLeadId` (`leadId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadsActivityLogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `moduleName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recordId` int DEFAULT NULL,
  `actionType` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `oldData` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `newData` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `createdBy` int NOT NULL,
  `ipAddress` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `leadsActivityLogs_chk_1` CHECK (json_valid(`oldData`)),
  CONSTRAINT `leadsActivityLogs_chk_2` CHECK (json_valid(`newData`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadFollowUps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `leadId` int NOT NULL,
  `settingId` int DEFAULT NULL,
  `followUpSequence` int NOT NULL,
  `followUpType` varchar(30) NOT NULL,
  `dueDate` date NOT NULL,
  `status` enum('Pending','Completed','Skipped') NOT NULL DEFAULT 'Pending',
  `resolvedAt` datetime DEFAULT NULL,
  `resolvedByCandidateId` int DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lfu_lead_setting` (`leadId`,`settingId`),
  KEY `idx_lfu_lead` (`leadId`),
  KEY `idx_lfu_due` (`dueDate`),
  KEY `idx_lfu_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadFollowUpSettings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dayNumber` int NOT NULL,
  `followUpSequence` int NOT NULL,
  `followUpType` varchar(30) NOT NULL DEFAULT 'Call',
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdBy` int NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lfus_active` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadDocuments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `leadId` int NOT NULL,
  `fileName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `originalFileName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploadedByCandidateId` int unsigned NOT NULL,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxLeadId` (`leadId`),
  CONSTRAINT `fkLeadDocumentsLead` FOREIGN KEY (`leadId`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadCategories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categoryName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoryCode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categoryCode` (`categoryCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadPlans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categoryId` int unsigned NOT NULL,
  `planName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `planCode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_planCode` (`planCode`),
  KEY `idx_categoryId` (`categoryId`),
  CONSTRAINT `fk_leadPlans_categoryId` FOREIGN KEY (`categoryId`) REFERENCES `leadCategories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leadConversions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `leadId` int NOT NULL,
  `finalPrice` decimal(12,2) NOT NULL,
  `statusRemark` text COLLATE utf8mb4_unicode_ci,
  `nextPriceIncrementDate` date DEFAULT NULL,
  `quotationFile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdByCandidateId` int NOT NULL,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxLeadId` (`leadId`),
  CONSTRAINT `fkLeadConversionLead` FOREIGN KEY (`leadId`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `companySettings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `companyName` varchar(180) NOT NULL DEFAULT '',
  `legalName` varchar(180) NOT NULL DEFAULT '',
  `addressLine1` varchar(255) NOT NULL DEFAULT '',
  `addressLine2` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(120) NOT NULL DEFAULT '',
  `stateName` varchar(120) NOT NULL DEFAULT '',
  `pincode` varchar(20) NOT NULL DEFAULT '',
  `country` varchar(120) NOT NULL DEFAULT 'India',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `email` varchar(160) NOT NULL DEFAULT '',
  `website` varchar(180) NOT NULL DEFAULT '',
  `gstNumber` varchar(30) NOT NULL DEFAULT '',
  `panNumber` varchar(20) NOT NULL DEFAULT '',
  `cinNumber` varchar(40) NOT NULL DEFAULT '',
  `companyLogo` varchar(255) NOT NULL DEFAULT '',
  `setupCompleted` tinyint(1) NOT NULL DEFAULT '0',
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `eventMailLog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `moduleName` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referenceId` int DEFAULT NULL,
  `mailType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipientEmail` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipientName` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subjectLine` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `retryCount` int DEFAULT '0',
  `errorMessage` text COLLATE utf8mb4_unicode_ci,
  `sentAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxRecipientEmail` (`recipientEmail`),
  KEY `idxMailType` (`mailType`),
  KEY `idxSentAt` (`sentAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `employeeProfileVerification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employeeUserId` int NOT NULL,
  `fieldName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verifyStatus` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `reviewRemark` text COLLATE utf8mb4_unicode_ci,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_field` (`employeeUserId`,`fieldName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
