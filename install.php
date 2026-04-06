#!/usr/bin/env php
<?php
/**
 * AccountingPro — Installation Script
 * Run: php install.php
 */

echo "\n";
echo "╔══════════════════════════════════════╗\n";
echo "║   AccountingPro ERP — Installer     ║\n";
echo "╚══════════════════════════════════════╝\n\n";

// Check PHP version
if (PHP_MAJOR_VERSION < 8) {
    echo "❌ PHP 8.0+ is required. You have " . PHP_VERSION . "\n";
    exit(1);
}
echo "✅ PHP " . PHP_VERSION . "\n";

// Check extensions
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'openssl'];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        echo "❌ Missing extension: {$ext}\n";
        exit(1);
    }
    echo "✅ ext-{$ext}\n";
}

// Check Composer
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "\n⚙️  Running composer install...\n";
    passthru('composer install --no-dev --optimize-autoloader');
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "❌ Composer install failed.\n";
        exit(1);
    }
}
echo "✅ Composer dependencies ready\n";

// Copy .env
if (!file_exists(__DIR__ . '/.env')) {
    copy(__DIR__ . '/.env.example', __DIR__ . '/.env');
    echo "✅ .env created from .env.example\n";
    echo "\n⚠️  Please edit .env and set your DB credentials before continuing.\n";
    echo "   Then re-run: php install.php\n\n";
    exit(0);
}

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Test DB connection
echo "\n🔌 Testing database connection...\n";
$dbCfg = require __DIR__ . '/config/database.php';
$c = $dbCfg['connections'][$dbCfg['default']];
try {
    $pdo = new PDO(
        "{$c['driver']}:host={$c['host']};port={$c['port']};charset={$c['charset']}",
        $c['username'], $c['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected to MySQL\n";
} catch (PDOException $e) {
    echo "❌ DB connection failed: " . $e->getMessage() . "\n";
    echo "   Check DB_HOST, DB_USERNAME, DB_PASSWORD in .env\n";
    exit(1);
}

// Create database
echo "\n📦 Creating database '{$c['database']}'...\n";
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "✅ Database ready\n";

// Run schema
echo "\n📊 Running schema...\n";
$schema = file_get_contents(__DIR__ . '/database/schema.sql');
$pdo->exec("USE `{$c['database']}`");
try {
    $pdo->exec($schema);
    echo "✅ Schema created\n";
} catch (PDOException $e) {
    echo "⚠️  Schema warning (may be already installed): " . $e->getMessage() . "\n";
}

// Seed question
echo "\n🌱 Seed demo data? [y/N]: ";
$ans = strtolower(trim(fgets(STDIN)));
if ($ans === 'y') {
    $seed = file_get_contents(__DIR__ . '/database/seed.sql');
    try {
        $pdo->exec($seed);
        echo "✅ Demo data seeded\n";
        echo "   Admin: admin@accountingpro.com / Admin@123\n";
        echo "   Demo:  john@demo.com / Admin@123\n";
    } catch (PDOException $e) {
        echo "⚠️  Seed warning: " . $e->getMessage() . "\n";
    }
}

// Create storage directories
$dirs = ['storage/imports', 'storage/receipts', 'storage/pdfs', 'logs'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) mkdir($path, 0755, true);
}
echo "✅ Storage directories created\n";

// Generate app key
$key = 'base64:' . base64_encode(random_bytes(32));
$env = file_get_contents(__DIR__ . '/.env');
$env = preg_replace('/APP_KEY=.*/', "APP_KEY={$key}", $env);
file_put_contents(__DIR__ . '/.env', $env);
echo "✅ App key generated\n";

// Generate JWT secret
$jwt = bin2hex(random_bytes(32));
$env = file_get_contents(__DIR__ . '/.env');
$env = preg_replace('/JWT_SECRET=.*/', "JWT_SECRET={$jwt}", $env);
file_put_contents(__DIR__ . '/.env', $env);
echo "✅ JWT secret generated\n";

echo "\n";
echo "╔══════════════════════════════════════╗\n";
echo "║   🎉 Installation Complete!          ║\n";
echo "╚══════════════════════════════════════╝\n\n";
echo "🌐 Start server: php -S localhost:8000 -t public/\n";
echo "🌐 Or configure Apache/Nginx to point DocumentRoot to public/\n\n";
