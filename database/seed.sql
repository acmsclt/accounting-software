-- ================================================================
-- AccountingPro ERP — Seed / Demo Data
-- ================================================================

USE `accounting_saas`;

-- Plans
INSERT INTO `plans` (`name`, `slug`, `price_monthly`, `price_yearly`, `max_companies`, `max_users`, `max_invoices`, `features`) VALUES
('Starter',      'starter',      19,  190,  1,  3,   50,    '["invoices","customers","expenses"]'),
('Professional', 'professional', 49,  490,  5,  10,  500,   '["invoices","customers","expenses","vendors","api","webhooks"]'),
('Enterprise',   'enterprise',   149, 1490, 99, 999, 99999, '["invoices","customers","expenses","vendors","api","webhooks","multi-company","priority-support"]');

-- Default admin user (password: Admin@123)
INSERT INTO `users` (`plan_id`, `name`, `email`, `password`, `role`, `is_active`, `email_verified_at`, `subscription_status`) VALUES
(3, 'Super Admin', 'admin@accountingpro.com', '$2y$12$s2WqnD6j.F5FQxqU26EG1.Zk9jtM0fYpBVGDwHOo2L5u8RxoX9RM6', 'super_admin', 1, NOW(), 'active'),
(2, 'John Doe',    'john@demo.com',           '$2y$12$s2WqnD6j.F5FQxqU26EG1.Zk9jtM0fYpBVGDwHOo2L5u8RxoX9RM6', 'admin',       1, NOW(), 'active');

-- Demo company
INSERT INTO `companies` (`owner_id`, `name`, `legal_name`, `email`, `phone`, `country`, `currency`, `timezone`, `invoice_prefix`, `invoice_counter`) VALUES
(2, 'Acme Corp', 'Acme Corporation LLC', 'billing@acmecorp.com', '+1 555 123 4567', 'US', 'USD', 'America/New_York', 'INV-', 1001);

-- Link user to company
UPDATE `users` SET `active_company_id` = 1 WHERE `id` = 2;
INSERT INTO `company_users` (`company_id`, `user_id`, `role`) VALUES (1, 2, 'admin');

-- Branches for Acme Corp
INSERT INTO `branches` (`company_id`, `name`, `code`, `city`, `state`, `country`, `phone`, `is_default`, `is_active`) VALUES
(1, 'Headquarters',      'HQ',   'New York',    'NY', 'US', '+1 555 100 0001', 1, 1),
(1, 'West Coast Office', 'WC',   'Los Angeles', 'CA', 'US', '+1 555 200 0002', 0, 1),
(1, 'Chicago Branch',    'CHI',  'Chicago',     'IL', 'US', '+1 555 300 0003', 0, 1);

-- Assign admin user to all branches
INSERT INTO `branch_users` (`branch_id`, `user_id`, `role`) VALUES
(1, 2, 'manager'),
(2, 2, 'manager'),
(3, 2, 'manager');

-- Default currencies
INSERT INTO `currencies` (`code`, `name`, `symbol`, `position`) VALUES
('USD', 'US Dollar',          '$',  'before'),
('EUR', 'Euro',               '€',  'before'),
('GBP', 'British Pound',      '£',  'before'),
('INR', 'Indian Rupee',       '₹',  'before'),
('AED', 'UAE Dirham',         'AED','before'),
('SAR', 'Saudi Riyal',        'SAR','before'),
('CAD', 'Canadian Dollar',    'CA$','before'),
('AUD', 'Australian Dollar',  'A$', 'before');

-- Exchange rates (USD base)
INSERT INTO `exchange_rates` (`from_currency`, `to_currency`, `rate`, `source`, `date`) VALUES
('USD', 'EUR', 0.92000000, 'manual', CURDATE()),
('USD', 'GBP', 0.79000000, 'manual', CURDATE()),
('USD', 'INR', 83.50000000,'manual', CURDATE()),
('USD', 'AED', 3.67000000, 'manual', CURDATE());

-- Taxes for Acme Corp
INSERT INTO `taxes` (`company_id`, `name`, `type`, `rate`, `is_inclusive`) VALUES
(1, 'Sales Tax 10%',  'Sales Tax', 10.00, 0),
(1, 'GST 18%',        'GST',       18.00, 0),
(1, 'VAT 20%',        'VAT',       20.00, 0),
(1, 'Zero Rated',     'Custom',     0.00, 0);

-- Default Chart of Accounts for Acme Corp
INSERT INTO `accounts` (`company_id`, `code`, `name`, `type`, `sub_type`, `is_system`) VALUES
(1, '1000', 'Assets',                              'asset',     NULL,              1),
(1, '1010', 'Cash & Bank',                         'asset',     'current',         1),
(1, '1020', 'Accounts Receivable',                 'asset',     'current',         1),
(1, '1030', 'Inventory',                           'asset',     'current',         1),
(1, '1100', 'Fixed Assets',                        'asset',     'fixed',           1),
(1, '2000', 'Liabilities',                         'liability', NULL,              1),
(1, '2010', 'Accounts Payable',                    'liability', 'current',         1),
(1, '2020', 'Tax Payable',                         'liability', 'current',         1),
(1, '2100', 'Long-term Loans',                     'liability', 'long_term',       1),
(1, '3000', 'Equity',                              'equity',    NULL,              1),
(1, '3010', 'Owner''s Equity',                     'equity',    'paid_in',         1),
(1, '3020', 'Retained Earnings',                   'equity',    'retained',        1),
(1, '4000', 'Revenue',                             'revenue',   NULL,              1),
(1, '4010', 'Sales Revenue',                       'revenue',   'operating',       1),
(1, '4020', 'Service Revenue',                     'revenue',   'operating',       1),
(1, '4030', 'Other Income',                        'revenue',   'other',           1),
(1, '5000', 'Expenses',                            'expense',   NULL,              1),
(1, '5010', 'Cost of Goods Sold',                  'expense',   'cogs',            1),
(1, '5020', 'Salaries & Wages',                    'expense',   'operating',       1),
(1, '5030', 'Rent & Utilities',                    'expense',   'operating',       1),
(1, '5040', 'Marketing & Advertising',             'expense',   'operating',       1),
(1, '5050', 'Office Supplies',                     'expense',   'operating',       1),
(1, '5060', 'Travel & Entertainment',              'expense',   'operating',       1),
(1, '5070', 'Depreciation',                        'expense',   'non_cash',        1),
(1, '5080', 'Bank Charges & Fees',                 'expense',   'financial',       1);

-- Warehouses
INSERT INTO `warehouses` (`company_id`, `name`, `location`, `is_default`) VALUES
(1, 'Main Warehouse',  'New York, NY', 1),
(1, 'West Coast Depot','Los Angeles, CA', 0);

-- Categories
INSERT INTO `categories` (`company_id`, `name`) VALUES
(1, 'Electronics'),
(1, 'Software'),
(1, 'Services'),
(1, 'Office Supplies');

-- Expense categories
INSERT INTO `expense_categories` (`company_id`, `name`, `color`, `account_id`) VALUES
(1, 'Office',         '#4361ee', 21),
(1, 'Travel',         '#f72585', 22),
(1, 'Marketing',      '#7209b7', 20),
(1, 'Utilities',      '#3a0ca3', 20),
(1, 'Salaries',       '#4cc9f0', 19),
(1, 'Miscellaneous',  '#6c757d', 17);

-- Products
INSERT INTO `products` (`company_id`, `category_id`, `tax_id`, `account_id`, `name`, `sku`, `description`, `type`, `sale_price`, `purchase_price`, `track_inventory`, `stock_alert_qty`) VALUES
(1, 1, 1, 14, 'Laptop Pro 16"',      'LAP-PRO-16',  'High-performance laptop for professionals', 'product', 1299.00, 850.00,  1, 5),
(1, 1, 1, 14, 'Wireless Mouse',      'MOUSE-WL',    'Ergonomic wireless mouse',                  'product', 49.99,   18.50,   1, 20),
(1, 2, 1, 14, 'AccountingPro Suite', 'SOFT-APS',    'Annual software license',                   'service', 499.00,  0.00,    0, 0),
(1, 3, 4, 15, 'Consulting (hr)',     'CONSULT-HR',  'Professional consulting per hour',          'service', 150.00,  0.00,    0, 0),
(1, 4, 1, 14, 'A4 Paper Ream',       'PAPER-A4',    '500 sheets A4 copy paper',                 'product', 12.99,   6.50,    1, 50);

-- Inventory
INSERT INTO `inventory` (`product_id`, `warehouse_id`, `quantity`) VALUES
(1, 1, 25),
(2, 1, 100),
(5, 1, 200),
(1, 2, 10),
(2, 2, 50);

-- Customers
INSERT INTO `customers` (`company_id`, `name`, `email`, `phone`, `company_name`, `country`, `currency`) VALUES
(1, 'Alice Johnson',  'alice@techventures.com',  '+1 555 001 0001', 'Tech Ventures Inc',    'US', 'USD'),
(1, 'Bob Smith',      'bob@globalretail.com',    '+1 555 002 0002', 'Global Retail Ltd',    'US', 'USD'),
(1, 'Charlie Brown',  'charlie@startupxyz.com',  '+44 20 1234 5678', 'StartupXYZ',          'GB', 'GBP'),
(1, 'Diana Prince',   'diana@megacorp.co.in',    '+91 98765 43210', 'MegaCorp India',       'IN', 'INR');

-- Vendors
INSERT INTO `vendors` (`company_id`, `name`, `email`, `phone`, `company_name`, `country`, `currency`) VALUES
(1, 'Dell Technologies', 'orders@dell.com',        '+1 800 999 3355', 'Dell Technologies Inc',  'US', 'USD'),
(1, 'Logitech',          'supply@logitech.com',    '+1 510 795 8500', 'Logitech International', 'US', 'USD'),
(1, 'Office Depot',      'billing@officedepot.com','+1 800 463 3768', 'Office Depot Inc',       'US', 'USD');

-- Sample Invoices
INSERT INTO `invoices` (`company_id`, `customer_id`, `user_id`, `invoice_number`, `currency`, `invoice_date`, `due_date`, `status`, `sub_total`, `tax_amount`, `total`, `amount_paid`, `amount_due`) VALUES
(1, 1, 2, 'INV-1001', 'USD', '2026-01-15', '2026-02-15', 'paid',    1299.00, 129.90, 1428.90, 1428.90, 0.00),
(1, 2, 2, 'INV-1002', 'USD', '2026-02-01', '2026-03-01', 'partial', 549.99,  55.00,  604.99,  300.00,  304.99),
(1, 3, 2, 'INV-1003', 'USD', '2026-03-10', '2026-04-10', 'sent',    1800.00, 180.00, 1980.00, 0.00,    1980.00),
(1, 4, 2, 'INV-1004', 'USD', '2026-03-20', '2026-04-20', 'draft',   499.00,  49.90,  548.90,  0.00,    548.90);

-- Invoice items
INSERT INTO `invoice_items` (`invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `tax_rate`, `tax_amount`, `total`) VALUES
(1, 1, 'Laptop Pro 16"',      1, 1299.00, 10, 129.90, 1428.90),
(2, 3, 'AccountingPro Suite', 1, 499.00,  10, 49.90,  548.90),
(2, 2, 'Wireless Mouse',      1, 49.99,   10, 5.00,   54.99),
(3, 4, 'Consulting (hr)',     12, 150.00, 10, 180.00, 1980.00),
(4, 3, 'AccountingPro Suite', 1, 499.00,  10, 49.90,  548.90);

-- Sample Expenses
INSERT INTO `expenses` (`company_id`, `category_id`, `user_id`, `title`, `amount`, `expense_date`, `payment_method`, `status`) VALUES
(1, 1, 2, 'Office Rent - Jan 2026',     3500.00, '2026-01-01', 'bank_transfer', 'approved'),
(1, 2, 2, 'Client Meeting - NYC',       245.50,  '2026-01-18', 'card',          'approved'),
(1, 3, 2, 'Google Ads Campaign',        800.00,  '2026-02-05', 'card',          'approved'),
(1, 4, 2, 'Electricity Bill - Feb',     189.30,  '2026-02-15', 'bank_transfer', 'approved'),
(1, 5, 2, 'Employee Salaries - Feb',  12000.00,  '2026-02-28', 'bank_transfer', 'approved'),
(1, 1, 2, 'Office Rent - Mar 2026',     3500.00, '2026-03-01', 'bank_transfer', 'approved'),
(1, 3, 2, 'LinkedIn Ads - Mar',         450.00,  '2026-03-15', 'card',          'approved');

-- Payments
INSERT INTO `payments` (`company_id`, `invoice_id`, `customer_id`, `user_id`, `account_id`, `type`, `amount`, `currency`, `payment_date`, `method`, `status`) VALUES
(1, 1, 1, 2, 2, 'received', 1428.90, 'USD', '2026-01-20', 'bank_transfer', 'completed'),
(1, 2, 2, 2, 2, 'received', 300.00,  'USD', '2026-02-10', 'card',          'completed');

-- Webhook endpoints (demo)
INSERT INTO `webhook_endpoints` (`company_id`, `url`, `event`, `secret`, `description`, `is_active`) VALUES
(1, 'https://webhook.site/demo-accountingpro', 'invoice.created', 'demo_webhook_secret_abc123', 'Invoice creation alerts', 1),
(1, 'https://webhook.site/demo-accountingpro', 'payment.received','demo_webhook_secret_abc123', 'Payment notifications',   1);

-- API Key (demo)
INSERT INTO `api_keys` (`company_id`, `user_id`, `name`, `key_value`, `is_active`) VALUES
(1, 2, 'Default API Key', 'ak_live_demo1234567890abcdefghij', 1);
