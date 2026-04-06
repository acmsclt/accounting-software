-- ================================================================
-- RBAC — Roles, Permissions, User Management
-- Run after schema.sql
-- ================================================================

-- ── Custom Roles (per company) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED   NOT NULL,
    `name`        VARCHAR(80)    NOT NULL,
    `slug`        VARCHAR(80)    NOT NULL,
    `description` VARCHAR(255)   NULL,
    `color`       VARCHAR(10)    NOT NULL DEFAULT '#6366f1',
    `is_system`   TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '1 = cannot be deleted',
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME       NULL,
    UNIQUE KEY `uniq_role_slug` (`company_id`, `slug`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Permission Definitions (per company) ─────────────────────
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED   NOT NULL,
    `module`      VARCHAR(60)    NOT NULL COMMENT 'e.g. invoices, reports',
    `action`      VARCHAR(30)    NOT NULL COMMENT 'view|create|edit|delete|export|approve',
    `name`        VARCHAR(100)   NOT NULL COMMENT 'Human label',
    `description` VARCHAR(255)   NULL,
    UNIQUE KEY `uniq_perm` (`company_id`, `module`, `action`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Role ↔ Permission pivot ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── User ↔ Role (within company) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id`    INT UNSIGNED NOT NULL,
    `role_id`    INT UNSIGNED NOT NULL,
    `company_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT UNSIGNED NULL,
    PRIMARY KEY (`user_id`, `role_id`, `company_id`),
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`role_id`)    REFERENCES `roles`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── User Direct Permissions (overrides role) ──────────────────
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `user_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `company_id`    INT UNSIGNED NOT NULL,
    `granted`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0=revoked, 1=granted',
    PRIMARY KEY (`user_id`, `permission_id`, `company_id`),
    FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Invitations ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_invitations` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED   NOT NULL,
    `invited_by`  INT UNSIGNED   NOT NULL,
    `email`       VARCHAR(191)   NOT NULL,
    `role_id`     INT UNSIGNED   NULL,
    `branch_ids`  JSON           NULL COMMENT 'Branch access list',
    `token`       VARCHAR(64)    NOT NULL UNIQUE,
    `status`      ENUM('pending','accepted','expired','cancelled') NOT NULL DEFAULT 'pending',
    `expires_at`  DATETIME       NOT NULL,
    `accepted_at` DATETIME       NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
