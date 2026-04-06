<?php
// config/app.php — Application-level configuration

return [
    'name'     => $_ENV['APP_NAME']     ?? 'AccountingPro',
    'env'      => $_ENV['APP_ENV']      ?? 'production',
    'url'      => $_ENV['APP_URL']      ?? 'http://localhost',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'locale'   => $_ENV['APP_LOCALE']   ?? 'en',
    'key'      => $_ENV['APP_KEY']      ?? '',

    'jwt' => [
        'secret'          => $_ENV['JWT_SECRET']         ?? '',
        'expiry'          => (int)($_ENV['JWT_EXPIRY']   ?? 3600),
        'refresh_expiry'  => (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800),
    ],

    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
    ],

    'webhook' => [
        'secret'      => $_ENV['WEBHOOK_SECRET']      ?? '',
        'timeout'     => (int)($_ENV['WEBHOOK_TIMEOUT']      ?? 30),
        'max_retries' => (int)($_ENV['WEBHOOK_MAX_RETRIES']  ?? 3),
    ],

    'roles' => [
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        'accountant'  => 'Accountant',
        'staff'       => 'Staff',
    ],

    'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'AED', 'SAR'],

    'plans' => [
        'starter'      => ['price' => 19,  'companies' => 1,  'users' => 3,   'invoices' => 50],
        'professional' => ['price' => 49,  'companies' => 5,  'users' => 10,  'invoices' => 500],
        'enterprise'   => ['price' => 149, 'companies' => 99, 'users' => 999, 'invoices' => 99999],
    ],
];
