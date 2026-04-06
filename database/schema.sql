-- ================================================================
-- AccountingPro ERP SaaS — Complete MySQL Schema
-- Version: 1.0 | Engine: InnoDB | Charset: utf8mb4
-- ================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `accounting_saas`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `accounting_saas`;

-- ──────────────────────────────────────────
-- 1. SUBSCRIPTIONS & PLANS
-- ──────────────────────────────────────────
CREATE TABLE `plans` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(50)    NOT NULL,
    `slug`            VARCHAR(50)    NOT NULL UNIQUE,
    `price_monthly`   DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `price_yearly`    DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `max_companies`   INT            NOT NULL DEFAULT 1,
    `max_users`       INT            NOT NULL DEFAULT 3,
    `max_invoices`    INT            NOT NULL DEFAULT 50,
    `features`        JSON,
    `is_active`       TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 2. USERS (Multi-tenant)
-- ──────────────────────────────────────────
CREATE TABLE `users` (
    `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_id`               INT UNSIGNED    NULL,
    `name`                  VARCHAR(150)    NOT NULL,
    `email`                 VARCHAR(191)    NOT NULL UNIQUE,
    `password`              VARCHAR(255)    NOT NULL,
    `role`                  ENUM('super_admin','admin','accountant','staff') NOT NULL DEFAULT 'staff',
    `avatar`                VARCHAR(255)    NULL,
    `phone`                 VARCHAR(30)     NULL,
    `timezone`              VARCHAR(80)     NOT NULL DEFAULT 'UTC',
    `locale`                VARCHAR(10)     NOT NULL DEFAULT 'en',
    `date_format`           VARCHAR(30)     NOT NULL DEFAULT 'Y-m-d',
    `currency`              VARCHAR(3)      NOT NULL DEFAULT 'USD',
    `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
    `email_verified_at`     DATETIME        NULL,
    `remember_token`        VARCHAR(100)    NULL,
    `subscription_status`   ENUM('active','trialing','past_due','canceled') DEFAULT 'trialing',
    `trial_ends_at`         DATE            NULL,
    `active_company_id`     INT UNSIGNED    NULL,
    `last_login_at`         DATETIME        NULL,
    `two_factor_secret`     VARCHAR(255)    NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            DATETIME        NULL,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role`  (`role`),
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 3. COMPANIES
-- ──────────────────────────────────────────
CREATE TABLE `companies` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `owner_id`         INT UNSIGNED    NOT NULL,
    `name`             VARCHAR(191)    NOT NULL,
    `legal_name`       VARCHAR(191)    NULL,
    `email`            VARCHAR(191)    NULL,
    `phone`            VARCHAR(50)     NULL,
    `website`          VARCHAR(255)    NULL,
    `address`          TEXT            NULL,
    `city`             VARCHAR(100)    NULL,
    `state`            VARCHAR(100)    NULL,
    `country`          VARCHAR(100)    NULL DEFAULT 'US',
    `postal_code`      VARCHAR(20)     NULL,
    `logo`             VARCHAR(255)    NULL,
    `currency`         VARCHAR(3)      NOT NULL DEFAULT 'USD',
    `timezone`         VARCHAR(80)     NOT NULL DEFAULT 'UTC',
    `fiscal_year_start` TINYINT       NOT NULL DEFAULT 1  COMMENT '1=Jan, 4=Apr',
    `tax_id`           VARCHAR(50)     NULL,
    `vat_number`       VARCHAR(50)     NULL,
    `invoice_prefix`   VARCHAR(10)     NOT NULL DEFAULT 'INV-',
    `invoice_counter`  INT UNSIGNED    NOT NULL DEFAULT 1000,
    `settings`         JSON            NULL,
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME        NULL,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_companies_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 4. COMPANY USERS (pivot, per-company roles)
-- ──────────────────────────────────────────
CREATE TABLE `company_users` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `role`       ENUM('admin','accountant','staff') NOT NULL DEFAULT 'staff',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_company_user` (`company_id`, `user_id`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 5. BRANCHES (per company)
-- ──────────────────────────────────────────
CREATE TABLE `branches` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED   NOT NULL,
    `name`        VARCHAR(150)   NOT NULL,
    `code`        VARCHAR(20)    NULL,
    `address`     TEXT           NULL,
    `city`        VARCHAR(100)   NULL,
    `state`       VARCHAR(100)   NULL,
    `country`     VARCHAR(100)   NULL,
    `phone`       VARCHAR(50)    NULL,
    `email`       VARCHAR(191)   NULL,
    `manager_id`  INT UNSIGNED   NULL COMMENT 'User managing this branch',
    `is_default`  TINYINT(1)     NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME       NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX `idx_branches_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Branch-User assignments (which users can access which branches)
CREATE TABLE `branch_users` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `role`        ENUM('manager','staff') NOT NULL DEFAULT 'staff',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_branch_user` (`branch_id`, `user_id`),
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 6. API KEYS
-- ──────────────────────────────────────────
CREATE TABLE `api_keys` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED    NOT NULL,
    `user_id`     INT UNSIGNED    NOT NULL,
    `name`        VARCHAR(100)    NOT NULL,
    `key_value`   VARCHAR(64)     NOT NULL UNIQUE,
    `permissions` JSON            NULL,
    `last_used_at` DATETIME       NULL,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `expires_at`  DATE            NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME        NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    INDEX `idx_api_keys_key` (`key_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 6. API REQUEST LOGS (rate limiting)
-- ──────────────────────────────────────────
CREATE TABLE `api_request_logs` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `api_key_id`  INT UNSIGNED    NOT NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `endpoint`    VARCHAR(255)    NULL,
    `method`      VARCHAR(10)     NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys`(`id`) ON DELETE CASCADE,
    INDEX `idx_api_req_key_time` (`api_key_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 7. CURRENCIES & EXCHANGE RATES
-- ──────────────────────────────────────────
CREATE TABLE `currencies` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`      VARCHAR(3)   NOT NULL UNIQUE,
    `name`      VARCHAR(80)  NOT NULL,
    `symbol`    VARCHAR(10)  NOT NULL,
    `position`  ENUM('before','after') NOT NULL DEFAULT 'before',
    `decimals`  TINYINT      NOT NULL DEFAULT 2,
    `is_active` TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exchange_rates` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `from_currency` VARCHAR(3) NOT NULL,
    `to_currency`   VARCHAR(3) NOT NULL,
    `rate`          DECIMAL(18,8) NOT NULL,
    `source`        VARCHAR(50) NULL DEFAULT 'manual',
    `date`          DATE NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exrate_from_to_date` (`from_currency`, `to_currency`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 8. TAXES
-- ──────────────────────────────────────────
CREATE TABLE `taxes` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED  NOT NULL,
    `name`        VARCHAR(100)  NOT NULL,
    `type`        ENUM('GST','VAT','Sales Tax','Custom') NOT NULL DEFAULT 'Custom',
    `rate`        DECIMAL(5,2)  NOT NULL,
    `is_inclusive` TINYINT(1)  NOT NULL DEFAULT 0,
    `is_compound`  TINYINT(1)  NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX `idx_taxes_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 9. CHART OF ACCOUNTS
-- ──────────────────────────────────────────
CREATE TABLE `accounts` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`   INT UNSIGNED   NOT NULL,
    `parent_id`    INT UNSIGNED   NULL,
    `code`         VARCHAR(20)    NOT NULL,
    `name`         VARCHAR(191)   NOT NULL,
    `type`         ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    `sub_type`     VARCHAR(80)    NULL,
    `description`  TEXT           NULL,
    `is_system`    TINYINT(1)     NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)     NOT NULL DEFAULT 1,
    `balance`      DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME       NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`)  REFERENCES `accounts`(`id`)  ON DELETE SET NULL,
    UNIQUE KEY `uniq_account_code_company` (`company_id`, `code`),
    INDEX `idx_accounts_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 10. JOURNAL ENTRIES & TRANSACTIONS
-- ──────────────────────────────────────────
CREATE TABLE `journal_entries` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`   INT UNSIGNED  NOT NULL,
    `user_id`      INT UNSIGNED  NOT NULL,
    `reference`    VARCHAR(50)   NULL,
    `description`  TEXT          NULL,
    `currency`     VARCHAR(3)    NOT NULL DEFAULT 'USD',
    `exchange_rate` DECIMAL(18,8) NOT NULL DEFAULT 1,
    `entry_date`   DATE          NOT NULL,
    `status`       ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE RESTRICT,
    INDEX `idx_je_company_date` (`company_id`, `entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `journal_entry_lines` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `journal_entry_id` INT UNSIGNED   NOT NULL,
    `account_id`       INT UNSIGNED   NOT NULL,
    `description`      VARCHAR(255)   NULL,
    `debit`            DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `credit`           DECIMAL(18,4)  NOT NULL DEFAULT 0,
    FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`)       REFERENCES `accounts`(`id`)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 11. CUSTOMERS
-- ──────────────────────────────────────────
CREATE TABLE `customers` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`    INT UNSIGNED   NOT NULL,
    `name`          VARCHAR(191)   NOT NULL,
    `email`         VARCHAR(191)   NULL,
    `phone`         VARCHAR(50)    NULL,
    `company_name`  VARCHAR(191)   NULL,
    `tax_id`        VARCHAR(50)    NULL,
    `address`       TEXT           NULL,
    `city`          VARCHAR(100)   NULL,
    `state`         VARCHAR(100)   NULL,
    `country`       VARCHAR(100)   NULL,
    `postal_code`   VARCHAR(20)    NULL,
    `currency`      VARCHAR(3)     NOT NULL DEFAULT 'USD',
    `credit_limit`  DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `balance`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `notes`         TEXT           NULL,
    `is_active`     TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME       NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX `idx_customers_company` (`company_id`),
    INDEX `idx_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 12. VENDORS
-- ──────────────────────────────────────────
CREATE TABLE `vendors` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`    INT UNSIGNED   NOT NULL,
    `name`          VARCHAR(191)   NOT NULL,
    `email`         VARCHAR(191)   NULL,
    `phone`         VARCHAR(50)    NULL,
    `company_name`  VARCHAR(191)   NULL,
    `tax_id`        VARCHAR(50)    NULL,
    `address`       TEXT           NULL,
    `city`          VARCHAR(100)   NULL,
    `country`       VARCHAR(100)   NULL,
    `currency`      VARCHAR(3)     NOT NULL DEFAULT 'USD',
    `balance`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `notes`         TEXT           NULL,
    `is_active`     TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME       NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX `idx_vendors_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 13. WAREHOUSES
-- ──────────────────────────────────────────
CREATE TABLE `warehouses` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED  NOT NULL,
    `name`        VARCHAR(100)  NOT NULL,
    `location`    VARCHAR(255)  NULL,
    `is_default`  TINYINT(1)    NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME      NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 14. PRODUCT CATEGORIES
-- ──────────────────────────────────────────
CREATE TABLE `categories` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED  NOT NULL,
    `parent_id`   INT UNSIGNED  NULL,
    `name`        VARCHAR(100)  NOT NULL,
    `slug`        VARCHAR(100)  NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME      NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`)  REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 15. PRODUCTS
-- ──────────────────────────────────────────
CREATE TABLE `products` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`       INT UNSIGNED   NOT NULL,
    `category_id`      INT UNSIGNED   NULL,
    `tax_id`           INT UNSIGNED   NULL,
    `account_id`       INT UNSIGNED   NULL COMMENT 'Sales revenue account',
    `name`             VARCHAR(191)   NOT NULL,
    `sku`              VARCHAR(80)    NOT NULL,
    `description`      TEXT           NULL,
    `type`             ENUM('product','service') NOT NULL DEFAULT 'product',
    `unit`             VARCHAR(30)    NULL DEFAULT 'unit',
    `sale_price`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `purchase_price`   DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `tax_type`         ENUM('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
    `track_inventory`  TINYINT(1)     NOT NULL DEFAULT 1,
    `stock_alert_qty`  INT            NOT NULL DEFAULT 10,
    `is_active`        TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME       NULL,
    FOREIGN KEY (`company_id`)  REFERENCES `companies`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`tax_id`)      REFERENCES `taxes`(`id`)      ON DELETE SET NULL,
    UNIQUE KEY `uniq_product_sku` (`company_id`, `sku`),
    INDEX `idx_products_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 16. INVENTORY (per warehouse)
-- ──────────────────────────────────────────
CREATE TABLE `inventory` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id`   INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `quantity`     DECIMAL(18,4) NOT NULL DEFAULT 0,
    `reserved_qty` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `updated_at`   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_product_warehouse` (`product_id`, `warehouse_id`),
    FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 17. INVOICES (Sales)
-- ──────────────────────────────────────────
CREATE TABLE `invoices` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`       INT UNSIGNED   NOT NULL,
    `branch_id`        INT UNSIGNED   NULL COMMENT 'Originating branch',
    `customer_id`      INT UNSIGNED   NOT NULL,
    `user_id`          INT UNSIGNED   NOT NULL,
    `invoice_number`   VARCHAR(30)    NOT NULL,
    `reference`        VARCHAR(100)   NULL,
    `currency`         VARCHAR(3)     NOT NULL DEFAULT 'USD',
    `exchange_rate`    DECIMAL(18,8)  NOT NULL DEFAULT 1,
    `invoice_date`     DATE           NOT NULL,
    `due_date`         DATE           NULL,
    `status`           ENUM('draft','sent','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `sub_total`        DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `discount_type`    ENUM('fixed','percent') NULL,
    `discount_value`   DECIMAL(10,4)  NOT NULL DEFAULT 0,
    `discount_amount`  DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `tax_amount`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `total`            DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `amount_paid`      DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `amount_due`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `notes`            TEXT           NULL,
    `terms`            TEXT           NULL,
    `is_recurring`     TINYINT(1)     NOT NULL DEFAULT 0,
    `recurring_interval` ENUM('weekly','monthly','quarterly','yearly') NULL,
    `next_recurring_date` DATE        NULL,
    `sent_at`          DATETIME       NULL,
    `paid_at`          DATETIME       NULL,
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME       NULL,
    FOREIGN KEY (`company_id`)  REFERENCES `companies`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`)   ON DELETE SET NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)  ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE RESTRICT,
    UNIQUE KEY `uniq_invoice_no` (`company_id`, `invoice_number`),
    INDEX `idx_invoices_company_status` (`company_id`, `status`),
    INDEX `idx_invoices_branch` (`branch_id`),
    INDEX `idx_invoices_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `invoice_items` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`   INT UNSIGNED   NOT NULL,
    `product_id`   INT UNSIGNED   NULL,
    `description`  VARCHAR(255)   NOT NULL,
    `quantity`     DECIMAL(18,4)  NOT NULL DEFAULT 1,
    `unit_price`   DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `discount`     DECIMAL(10,4)  NOT NULL DEFAULT 0,
    `tax_rate`     DECIMAL(5,2)   NOT NULL DEFAULT 0,
    `tax_amount`   DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `total`        DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `sort_order`   TINYINT        NOT NULL DEFAULT 0,
    FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 18. PURCHASE ORDERS / VENDOR BILLS
-- ──────────────────────────────────────────
CREATE TABLE `purchase_orders` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`    INT UNSIGNED   NOT NULL,
    `branch_id`     INT UNSIGNED   NULL COMMENT 'Originating branch',
    `vendor_id`     INT UNSIGNED   NOT NULL,
    `user_id`       INT UNSIGNED   NOT NULL,
    `po_number`     VARCHAR(30)    NOT NULL,
    `status`        ENUM('draft','ordered','received','partial','cancelled') NOT NULL DEFAULT 'draft',
    `currency`      VARCHAR(3)     NOT NULL DEFAULT 'USD',
    `sub_total`     DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `tax_amount`    DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `total`         DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `amount_paid`   DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `amount_due`    DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `order_date`    DATE           NOT NULL,
    `expected_date` DATE           NULL,
    `notes`         TEXT           NULL,
    `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME       NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`)  REFERENCES `branches`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`vendor_id`)  REFERENCES `vendors`(`id`)   ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)     ON DELETE RESTRICT,
    INDEX `idx_po_company` (`company_id`),
    INDEX `idx_po_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchase_order_items` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT UNSIGNED  NOT NULL,
    `product_id`       INT UNSIGNED   NULL,
    `description`      VARCHAR(255)   NOT NULL,
    `quantity`         DECIMAL(18,4)  NOT NULL DEFAULT 1,
    `received_qty`     DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `unit_price`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `tax_rate`         DECIMAL(5,2)   NOT NULL DEFAULT 0,
    `tax_amount`       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `total`            DECIMAL(18,4)  NOT NULL DEFAULT 0,
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`)        REFERENCES `products`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 19. PAYMENTS
-- ──────────────────────────────────────────
CREATE TABLE `payments` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`       INT UNSIGNED   NOT NULL,
    `branch_id`        INT UNSIGNED   NULL,
    `invoice_id`       INT UNSIGNED   NULL,
    `purchase_order_id` INT UNSIGNED  NULL,
    `customer_id`      INT UNSIGNED   NULL,
    `vendor_id`        INT UNSIGNED   NULL,
    `user_id`          INT UNSIGNED   NOT NULL,
    `account_id`       INT UNSIGNED   NULL COMMENT 'Bank/cash account',
    `type`             ENUM('received','sent') NOT NULL DEFAULT 'received',
    `amount`           DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `currency`         VARCHAR(3)     NOT NULL DEFAULT 'USD',
    `exchange_rate`    DECIMAL(18,8)  NOT NULL DEFAULT 1,
    `payment_date`     DATE           NOT NULL,
    `method`           ENUM('cash','bank_transfer','card','stripe','razorpay','paypal','cheque','other') NOT NULL DEFAULT 'bank_transfer',
    `reference`        VARCHAR(100)   NULL,
    `gateway_id`       VARCHAR(100)   NULL COMMENT 'Stripe/Razorpay transaction ID',
    `status`           ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
    `notes`            TEXT           NULL,
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME       NULL,
    FOREIGN KEY (`company_id`)        REFERENCES `companies`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`)         REFERENCES `branches`(`id`)         ON DELETE SET NULL,
    FOREIGN KEY (`invoice_id`)        REFERENCES `invoices`(`id`)         ON DELETE SET NULL,
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`customer_id`)       REFERENCES `customers`(`id`)        ON DELETE SET NULL,
    FOREIGN KEY (`vendor_id`)         REFERENCES `vendors`(`id`)          ON DELETE SET NULL,
    INDEX `idx_payments_company` (`company_id`),
    INDEX `idx_payments_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 20. EXPENSE CATEGORIES
-- ──────────────────────────────────────────
CREATE TABLE `expense_categories` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED  NOT NULL,
    `name`        VARCHAR(100)  NOT NULL,
    `color`       VARCHAR(10)   NULL DEFAULT '#6c757d',
    `account_id`  INT UNSIGNED  NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME      NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 21. EXPENSES
-- ──────────────────────────────────────────
CREATE TABLE `expenses` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED   NOT NULL,
    `branch_id`   INT UNSIGNED   NULL,
    `category_id` INT UNSIGNED   NULL,
    `vendor_id`   INT UNSIGNED   NULL,
    `account_id`  INT UNSIGNED   NULL,
    `user_id`     INT UNSIGNED   NOT NULL,
    `title`       VARCHAR(191)   NOT NULL,
    `amount`      DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `tax_amount`  DECIMAL(18,4)  NOT NULL DEFAULT 0,
    `currency`    VARCHAR(3)     NOT NULL DEFAULT 'USD',
    `expense_date` DATE          NOT NULL,
    `payment_method` VARCHAR(50) NULL,
    `reference`   VARCHAR(100)   NULL,
    `receipt`     VARCHAR(255)   NULL,
    `notes`       TEXT           NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME       NULL,
    FOREIGN KEY (`company_id`)  REFERENCES `companies`(`id`)          ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`)           ON DELETE SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`vendor_id`)   REFERENCES `vendors`(`id`)            ON DELETE SET NULL,
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)              ON DELETE RESTRICT,
    INDEX `idx_expenses_company` (`company_id`),
    INDEX `idx_expenses_branch` (`branch_id`),
    INDEX `idx_expenses_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 22. WEBHOOK ENDPOINTS
-- ──────────────────────────────────────────
CREATE TABLE `webhook_endpoints` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`  INT UNSIGNED  NOT NULL,
    `url`         VARCHAR(500)  NOT NULL,
    `event`       VARCHAR(80)   NOT NULL,
    `secret`      VARCHAR(128)  NOT NULL,
    `description` VARCHAR(255)  NULL,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME      NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX `idx_wh_company_event` (`company_id`, `event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 23. WEBHOOK LOGS
-- ──────────────────────────────────────────
CREATE TABLE `webhook_logs` (
    `id`                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `webhook_endpoint_id`  INT UNSIGNED  NOT NULL,
    `event`                VARCHAR(80)   NOT NULL,
    `payload`              TEXT          NULL,
    `response_code`        SMALLINT      NULL,
    `response_body`        TEXT          NULL,
    `status`               ENUM('success','failed') NOT NULL DEFAULT 'failed',
    `attempts`             TINYINT       NOT NULL DEFAULT 1,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`webhook_endpoint_id`) REFERENCES `webhook_endpoints`(`id`) ON DELETE CASCADE,
    INDEX `idx_whlogs_endpoint` (`webhook_endpoint_id`),
    INDEX `idx_whlogs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 24. NOTIFICATIONS
-- ──────────────────────────────────────────
CREATE TABLE `notifications` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED   NOT NULL,
    `company_id`  INT UNSIGNED   NULL,
    `type`        VARCHAR(80)    NOT NULL,
    `title`       VARCHAR(255)   NOT NULL,
    `body`        TEXT           NULL,
    `link`        VARCHAR(255)   NULL,
    `is_read`     TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifs_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 25. ACTIVITY LOGS (Audit Trail)
-- ──────────────────────────────────────────
CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED   NULL,
    `company_id`  INT UNSIGNED   NULL,
    `action`      VARCHAR(100)   NOT NULL,
    `model`       VARCHAR(80)    NULL,
    `model_id`    INT UNSIGNED   NULL,
    `description` TEXT           NULL,
    `properties`  JSON           NULL,
    `ip_address`  VARCHAR(45)    NULL,
    `user_agent`  VARCHAR(255)   NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_actlogs_company` (`company_id`),
    INDEX `idx_actlogs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 26. PASSWORD RESETS
-- ──────────────────────────────────────────
CREATE TABLE `password_resets` (
    `email`       VARCHAR(191)  NOT NULL,
    `token`       VARCHAR(255)  NOT NULL,
    `created_at`  DATETIME      NULL,
    INDEX `idx_pr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- 27. DATA IMPORT JOBS
-- ──────────────────────────────────────────
CREATE TABLE `import_jobs` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id`    INT UNSIGNED   NOT NULL,
    `branch_id`     INT UNSIGNED   NULL,
    `user_id`       INT UNSIGNED   NOT NULL,
    `source_type`   ENUM('google_sheets','csv','excel','json') NOT NULL DEFAULT 'csv',
    `source_url`    VARCHAR(1000)  NULL COMMENT 'Google Sheets share URL',
    `source_file`   VARCHAR(255)   NULL COMMENT 'Uploaded file path',
    `target_entity` ENUM('customers','vendors','products','expenses','invoices','accounts','currencies','taxes') NOT NULL,
    `column_mapping` JSON          NOT NULL COMMENT 'Maps sheet columns → system fields',
    `options`       JSON           NULL COMMENT 'skip_header, update_existing, default_branch, etc.',
    `status`        ENUM('pending','previewing','importing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    `total_rows`    INT UNSIGNED   NOT NULL DEFAULT 0,
    `processed_rows` INT UNSIGNED  NOT NULL DEFAULT 0,
    `success_rows`  INT UNSIGNED   NOT NULL DEFAULT 0,
    `failed_rows`   INT UNSIGNED   NOT NULL DEFAULT 0,
    `error_summary` TEXT           NULL,
    `preview_data`  LONGTEXT       NULL COMMENT 'JSON preview of first 10 rows',
    `started_at`    DATETIME       NULL,
    `completed_at`  DATETIME       NULL,
    `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME       NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`)  REFERENCES `branches`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)     ON DELETE RESTRICT,
    INDEX `idx_import_company` (`company_id`),
    INDEX `idx_import_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `import_row_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `import_job_id` INT UNSIGNED  NOT NULL,
    `row_number`    INT UNSIGNED  NOT NULL,
    `raw_data`      JSON          NOT NULL,
    `status`        ENUM('success','failed','skipped','duplicate') NOT NULL DEFAULT 'success',
    `error_message` VARCHAR(500)  NULL,
    `entity_id`     INT UNSIGNED  NULL COMMENT 'ID of created/updated record',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`import_job_id`) REFERENCES `import_jobs`(`id`) ON DELETE CASCADE,
    INDEX `idx_ilog_job`    (`import_job_id`),
    INDEX `idx_ilog_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

