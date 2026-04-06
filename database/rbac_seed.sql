-- RBAC seed data — default roles and permissions for every company
-- Called by install.php after schema.sql and seed.sql

-- ── Default permissions matrix ────────────────────────────────
-- (inserted per company via PHP install script; this is the template seed)

-- For company_id=1 (Acme Corp demo)
SET @cid = 1;

-- Permissions
INSERT IGNORE INTO `permissions` (`company_id`,`module`,`action`,`name`) VALUES
(@cid,'dashboard','view','View Dashboard'),
(@cid,'invoices','view','View Invoices'),
(@cid,'invoices','create','Create Invoices'),
(@cid,'invoices','edit','Edit Invoices'),
(@cid,'invoices','delete','Delete Invoices'),
(@cid,'invoices','export','Export Invoices'),
(@cid,'customers','view','View Customers'),
(@cid,'customers','create','Create Customers'),
(@cid,'customers','edit','Edit Customers'),
(@cid,'customers','delete','Delete Customers'),
(@cid,'vendors','view','View Vendors'),
(@cid,'vendors','create','Create Vendors'),
(@cid,'vendors','edit','Edit Vendors'),
(@cid,'vendors','delete','Delete Vendors'),
(@cid,'products','view','View Products'),
(@cid,'products','create','Create Products'),
(@cid,'products','edit','Edit Products'),
(@cid,'products','delete','Delete Products'),
(@cid,'expenses','view','View Expenses'),
(@cid,'expenses','create','Create Expenses'),
(@cid,'expenses','edit','Edit Expenses'),
(@cid,'expenses','delete','Delete Expenses'),
(@cid,'expenses','approve','Approve Expenses'),
(@cid,'accounting','view','View Accounting'),
(@cid,'accounting','create','Create Journal Entries'),
(@cid,'reports','view','View Reports'),
(@cid,'reports','export','Export Reports'),
(@cid,'branches','view','View Branches'),
(@cid,'branches','create','Create Branches'),
(@cid,'branches','edit','Edit Branches'),
(@cid,'branches','delete','Delete Branches'),
(@cid,'users','view','View Users'),
(@cid,'users','create','Invite Users'),
(@cid,'users','edit','Edit Users'),
(@cid,'users','delete','Remove Users'),
(@cid,'roles','view','View Roles'),
(@cid,'roles','create','Create Roles'),
(@cid,'roles','edit','Edit Roles'),
(@cid,'roles','delete','Delete Roles'),
(@cid,'import','view','View Imports'),
(@cid,'import','create','Run Imports'),
(@cid,'webhooks','view','View Webhooks'),
(@cid,'webhooks','create','Create Webhooks'),
(@cid,'webhooks','edit','Edit Webhooks'),
(@cid,'webhooks','delete','Delete Webhooks'),
(@cid,'settings','view','View Settings'),
(@cid,'settings','edit','Edit Settings');

-- Roles
INSERT IGNORE INTO `roles` (`company_id`,`name`,`slug`,`description`,`color`,`is_system`) VALUES
(@cid,'Administrator','admin','Full access to all features','#4f46e5',1),
(@cid,'Accountant','accountant','Full financial access, no user management','#0891b2',1),
(@cid,'Sales Manager','sales','Manage invoices, customers, and products','#059669',1),
(@cid,'Staff','staff','View-only access to core modules','#d97706',1);

-- Admin role gets ALL permissions
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.company_id=@cid AND r.slug='admin' AND p.company_id=@cid;

-- Accountant gets financial permissions
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.company_id=@cid AND r.slug='accountant' AND p.company_id=@cid
AND p.module IN ('dashboard','invoices','customers','vendors','expenses','accounting','reports','products','branches','import')
AND p.action IN ('view','create','edit','export','approve');

-- Sales gets sales permissions
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.company_id=@cid AND r.slug='sales' AND p.company_id=@cid
AND p.module IN ('dashboard','invoices','customers','products','expenses','reports')
AND p.action IN ('view','create','edit','export');

-- Staff gets view-only
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.company_id=@cid AND r.slug='staff' AND p.company_id=@cid
AND p.action='view';

-- Assign admin role to company admin user (user_id=1)
INSERT IGNORE INTO `user_roles` (`user_id`,`role_id`,`company_id`,`assigned_by`)
SELECT 1, r.id, @cid, 1 FROM roles r WHERE r.company_id=@cid AND r.slug='admin';
