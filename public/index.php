<?php
// public/index.php — Front Controller

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// Load Composer autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Session
session_set_cookie_params([
    'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Instantiate router
$router = new \App\Core\Router();

// ──────────────────────────────────────────────
// WEB ROUTES
// ──────────────────────────────────────────────

// Auth
$router->get('/login',    [\App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login',   [\App\Controllers\AuthController::class, 'login']);
$router->get('/register', [\App\Controllers\AuthController::class, 'showRegister']);
$router->post('/register',[\App\Controllers\AuthController::class, 'register']);
$router->get('/logout',   [\App\Controllers\AuthController::class, 'logout'],   ['auth']);
$router->post('/logout',  [\App\Controllers\AuthController::class, 'logout'],   ['auth']);

// Dashboard
$router->get('/',          [\App\Controllers\DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [\App\Controllers\DashboardController::class, 'index'], ['auth']);

// Branches
$router->get('/branches',               [\App\Controllers\BranchController::class, 'index'],  ['auth']);
$router->get('/branches/create',        [\App\Controllers\BranchController::class, 'create'], ['auth']);
$router->post('/branches',              [\App\Controllers\BranchController::class, 'store'],  ['auth']);
$router->get('/branches/{id}/edit',     [\App\Controllers\BranchController::class, 'edit'],   ['auth']);
$router->post('/branches/{id}',         [\App\Controllers\BranchController::class, 'update'], ['auth']);
$router->post('/branches/{id}/delete',  [\App\Controllers\BranchController::class, 'delete'], ['auth']);
$router->post('/branches/switch',       [\App\Controllers\BranchController::class, 'switchBranch'], ['auth']);

// Customers
$router->get('/customers',              [\App\Controllers\CustomerController::class, 'index'],  ['auth']);
$router->get('/customers/create',       [\App\Controllers\CustomerController::class, 'create'], ['auth']);
$router->post('/customers',             [\App\Controllers\CustomerController::class, 'store'],  ['auth']);
$router->get('/customers/{id}',         [\App\Controllers\CustomerController::class, 'show'],   ['auth']);
$router->get('/customers/{id}/edit',    [\App\Controllers\CustomerController::class, 'edit'],   ['auth']);
$router->post('/customers/{id}',        [\App\Controllers\CustomerController::class, 'update'], ['auth']);
$router->post('/customers/{id}/delete', [\App\Controllers\CustomerController::class, 'delete'], ['auth']);

// Vendors
$router->get('/vendors',              [\App\Controllers\VendorController::class, 'index'],  ['auth']);
$router->get('/vendors/create',       [\App\Controllers\VendorController::class, 'create'], ['auth']);
$router->post('/vendors',             [\App\Controllers\VendorController::class, 'store'],  ['auth']);
$router->get('/vendors/{id}/edit',    [\App\Controllers\VendorController::class, 'edit'],   ['auth']);
$router->post('/vendors/{id}',        [\App\Controllers\VendorController::class, 'update'], ['auth']);
$router->post('/vendors/{id}/delete', [\App\Controllers\VendorController::class, 'delete'], ['auth']);

// Products
$router->get('/products',              [\App\Controllers\ProductController::class, 'index'],  ['auth']);
$router->get('/products/create',       [\App\Controllers\ProductController::class, 'create'], ['auth']);
$router->post('/products',             [\App\Controllers\ProductController::class, 'store'],  ['auth']);
$router->get('/products/{id}/edit',    [\App\Controllers\ProductController::class, 'edit'],   ['auth']);
$router->post('/products/{id}',        [\App\Controllers\ProductController::class, 'update'], ['auth']);
$router->post('/products/{id}/delete', [\App\Controllers\ProductController::class, 'delete'], ['auth']);

// Invoices
$router->get('/invoices',                           [\App\Controllers\InvoiceController::class, 'index'],         ['auth']);
$router->get('/invoices/create',                    [\App\Controllers\InvoiceController::class, 'create'],        ['auth']);
$router->post('/invoices',                          [\App\Controllers\InvoiceController::class, 'store'],         ['auth']);
$router->get('/invoices/{id}',                      [\App\Controllers\InvoiceController::class, 'show'],          ['auth']);
$router->get('/invoices/{id}/pdf',                  [\App\Controllers\InvoiceController::class, 'pdf'],           ['auth']);
$router->post('/invoices/{id}/payment',             [\App\Controllers\InvoiceController::class, 'recordPayment'], ['auth']);

// Expenses
$router->get('/expenses',              [\App\Controllers\ExpenseController::class, 'index'],  ['auth']);
$router->get('/expenses/create',       [\App\Controllers\ExpenseController::class, 'create'], ['auth']);
$router->post('/expenses',             [\App\Controllers\ExpenseController::class, 'store'],  ['auth']);
$router->get('/expenses/{id}/edit',    [\App\Controllers\ExpenseController::class, 'edit'],   ['auth']);
$router->post('/expenses/{id}',        [\App\Controllers\ExpenseController::class, 'update'], ['auth']);
$router->post('/expenses/{id}/delete', [\App\Controllers\ExpenseController::class, 'delete'], ['auth']);

// Accounting (GL/Journal)
$router->get('/accounting',            [\App\Controllers\AccountingController::class, 'index'],       ['auth']);
$router->get('/accounting/journal',    [\App\Controllers\AccountingController::class, 'journal'],     ['auth']);
$router->get('/accounting/trial-balance', [\App\Controllers\AccountingController::class, 'trialBalance'],  ['auth']);

// 360° Reports
$router->get('/reports',              [\App\Controllers\ReportController::class, 'index'], ['auth']);
$router->get('/reports/{slug}',       [\App\Controllers\ReportController::class, 'show'],  ['auth']);

// Settings
$router->get('/settings',        [\App\Controllers\SettingsController::class, 'index'],  ['auth']);
$router->post('/settings',       [\App\Controllers\SettingsController::class, 'update'], ['auth']);
$router->post('/settings/company', [\App\Controllers\SettingsController::class, 'updateCompany'], ['auth']);

// Webhooks settings UI
$router->get('/webhooks',             [\App\Controllers\WebhookController::class, 'index'],  ['auth']);
$router->post('/webhooks',            [\App\Controllers\WebhookController::class, 'store'],  ['auth']);
$router->post('/webhooks/{id}/delete',[\App\Controllers\WebhookController::class, 'delete'], ['auth']);
$router->post('/webhooks/{id}/toggle',[\App\Controllers\WebhookController::class, 'toggle'], ['auth']);

// Data Import
$router->get('/import',                [\App\Controllers\ImportController::class, 'index'],   ['auth']);
$router->get('/import/new',            [\App\Controllers\ImportController::class, 'create'],  ['auth']);
$router->post('/import/preview',       [\App\Controllers\ImportController::class, 'preview'], ['auth']);
$router->post('/import/run',           [\App\Controllers\ImportController::class, 'run'],     ['auth']);
$router->get('/import/{id}',           [\App\Controllers\ImportController::class, 'show'],    ['auth']);
$router->get('/import/template/{entity}', [\App\Controllers\ImportController::class, 'template'], ['auth']);

// User Management
$router->get('/users',                    [\App\Controllers\UserController::class, 'index'],  ['auth']);
$router->get('/users/invite',             [\App\Controllers\UserController::class, 'create'], ['auth']);
$router->post('/users/invite',            [\App\Controllers\UserController::class, 'invite'], ['auth']);
$router->get('/users/{id}/edit',          [\App\Controllers\UserController::class, 'edit'],   ['auth']);
$router->post('/users/{id}/update',       [\App\Controllers\UserController::class, 'update'], ['auth']);
$router->post('/users/{id}/remove',       [\App\Controllers\UserController::class, 'remove'], ['auth']);

// Role Management
$router->get('/roles',                    [\App\Controllers\RoleController::class, 'index'],        ['auth']);
$router->get('/roles/new',                [\App\Controllers\RoleController::class, 'create'],       ['auth']);
$router->post('/roles',                   [\App\Controllers\RoleController::class, 'store'],        ['auth']);
$router->get('/roles/{id}/edit',          [\App\Controllers\RoleController::class, 'edit'],         ['auth']);
$router->post('/roles/{id}/update',       [\App\Controllers\RoleController::class, 'update'],       ['auth']);
$router->post('/roles/{id}/delete',       [\App\Controllers\RoleController::class, 'delete'],       ['auth']);
$router->post('/roles/{id}/duplicate',    [\App\Controllers\RoleController::class, 'duplicate'],    ['auth']);
$router->get('/roles/{id}/permissions',   [\App\Controllers\RoleController::class, 'permissionsJson'], ['auth']);

// Tour
$router->post('/tour/complete', [\App\Controllers\TourController::class, 'complete'], ['auth']);
$router->get('/tour/reset',     [\App\Controllers\TourController::class, 'reset'],    ['auth']);

// ──────────────────────────────────────────────
// REST API ROUTES
// ──────────────────────────────────────────────

// Auth API
$router->post('/api/login',    [\App\Api\AuthApiController::class, 'login']);
$router->post('/api/register', [\App\Api\AuthApiController::class, 'register']);
$router->post('/api/refresh',  [\App\Api\AuthApiController::class, 'refresh']);

// Customers API
$router->get('/api/customers',           [\App\Api\CustomerApiController::class, 'index'],   ['api.auth']);
$router->post('/api/customers',          [\App\Api\CustomerApiController::class, 'store'],   ['api.auth']);
$router->get('/api/customers/{id}',      [\App\Api\CustomerApiController::class, 'show'],    ['api.auth']);
$router->put('/api/customers/{id}',      [\App\Api\CustomerApiController::class, 'update'],  ['api.auth']);
$router->delete('/api/customers/{id}',   [\App\Api\CustomerApiController::class, 'destroy'], ['api.auth']);
$router->get('/api/customers/{id}/ledger', [\App\Api\CustomerApiController::class, 'ledger'],['api.auth']);

// Products API
$router->get('/api/products',       [\App\Api\ProductApiController::class, 'index'], ['api.auth']);
$router->post('/api/products',      [\App\Api\ProductApiController::class, 'store'], ['api.auth']);
$router->get('/api/products/{id}',  [\App\Api\ProductApiController::class, 'show'],  ['api.auth']);
$router->put('/api/products/{id}',  [\App\Api\ProductApiController::class, 'update'],['api.auth']);
$router->delete('/api/products/{id}',[\App\Api\ProductApiController::class, 'destroy'],['api.auth']);

// Invoices API
$router->get('/api/invoices',              [\App\Api\InvoiceApiController::class, 'index'],   ['api.auth']);
$router->post('/api/invoices',             [\App\Api\InvoiceApiController::class, 'store'],   ['api.auth']);
$router->get('/api/invoices/{id}',         [\App\Api\InvoiceApiController::class, 'show'],    ['api.auth']);
$router->post('/api/invoices/{id}/payment',[\App\Api\InvoiceApiController::class, 'payment'], ['api.auth']);

// Branches API
$router->get('/api/branches',          [\App\Api\BranchApiController::class, 'index'],  ['api.auth']);
$router->post('/api/branches',         [\App\Api\BranchApiController::class, 'store'],  ['api.auth']);
$router->get('/api/branches/{id}',     [\App\Api\BranchApiController::class, 'show'],   ['api.auth']);
$router->put('/api/branches/{id}',     [\App\Api\BranchApiController::class, 'update'], ['api.auth']);
$router->delete('/api/branches/{id}',  [\App\Api\BranchApiController::class, 'destroy'],['api.auth']);

// Reports API
$router->get('/api/reports',               [\App\Api\ReportsApiController::class, 'index'], ['api.auth']);
$router->get('/api/reports/{slug}',        [\App\Api\ReportsApiController::class, 'show'],  ['api.auth']);

// Legacy direct report endpoints
$router->get('/api/reports/profit-loss',       [\App\Api\ReportApiController::class, 'profitLoss'],       ['api.auth']);
$router->get('/api/reports/balance-sheet',     [\App\Api\ReportApiController::class, 'balanceSheet'],     ['api.auth']);
$router->get('/api/reports/branch-comparison', [\App\Api\ReportApiController::class, 'branchComparison'], ['api.auth']);


// Webhooks API
$router->get('/api/webhooks',             [\App\Api\WebhookApiController::class, 'index'],   ['api.auth']);
$router->post('/api/webhooks',            [\App\Api\WebhookApiController::class, 'store'],   ['api.auth']);
$router->delete('/api/webhooks/{id}',     [\App\Api\WebhookApiController::class, 'destroy'], ['api.auth']);

// Dispatch
$router->dispatch();
