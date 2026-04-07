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
$pdo->exec("USE `{$c['database']}`");
// Disable strict primary key requirement (DigitalOcean / cloud MySQL quirk)
try { $pdo->exec("SET SESSION sql_require_primary_key = OFF"); } catch(PDOException $e) { /* non-fatal */ }
try { $pdo->exec("SET SESSION sql_mode = ''"); } catch(PDOException $e) { /* non-fatal */ }

$schemaFiles = ['schema.sql', 'rbac.sql'];
foreach ($schemaFiles as $sf) {
    $file = __DIR__ . '/database/' . $sf;
    if (!file_exists($file)) continue;
    $sql = file_get_contents($file);
    // Remove USE/CREATE DATABASE statements (already selected)
    $sql = preg_replace('/^\s*(USE|CREATE DATABASE|SET FOREIGN_KEY_CHECKS\s*=\s*0).*/mi', '', $sql);
    try {
        $pdo->exec($sql);
        echo "✅ {$sf} applied\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '1050') || str_contains($e->getMessage(), 'already exists')) {
            echo "ℹ️  {$sf}: tables already exist — skipped\n";
        } else {
            echo "⚠️  {$sf} warning: " . $e->getMessage() . "\n";
        }
    }
}

// Seed question
echo "\n🌱 Seed demo data? [y/N]: ";
$ans = strtolower(trim(fgets(STDIN)));
if ($ans === 'y') {
    $seedFiles = ['seed.sql', 'rbac_seed.sql'];
    foreach ($seedFiles as $sf) {
        $file = __DIR__ . '/database/' . $sf;
        if (!file_exists($file)) continue;
        $sql = file_get_contents($file);
        try {
            $pdo->exec($sql);
            echo "✅ {$sf} seeded\n";
        } catch (PDOException $e) {
            echo "⚠️  {$sf} warning: " . $e->getMessage() . "\n";
        }
    }
    echo "   Admin: admin@accountingpro.com / Admin@123\n";
    echo "   Demo:  john@demo.com / Admin@123\n";
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

// Secure .env file
chmod(__DIR__ . '/.env', 0600);

// Update APP_URL with server IP
$serverIp = trim(shell_exec("hostname -I 2>/dev/null | awk '{print $1}'") ?? 'localhost');
$env = file_get_contents(__DIR__ . '/.env');
$env = preg_replace('/APP_URL=.*/', "APP_URL=http://{$serverIp}", $env);
file_put_contents(__DIR__ . '/.env', $env);
echo "✅ APP_URL set to http://{$serverIp}\n";

// Fix permissions
foreach (['storage', 'logs', 'public'] as $d) {
    if (is_dir(__DIR__.'/'.$d)) {
        shell_exec("chmod -R 755 " . __DIR__ . "/{$d}");
    }
}
echo "✅ Permissions set\n";

echo "\n";
echo "╔══════════════════════════════════════╗\n";
echo "║   🎉 Installation Complete!          ║\n";
echo "╚══════════════════════════════════════╝\n\n";
echo "🌐 Your app URL: http://{$serverIp}/\n";
echo "🌐 Quick test:   php -S 0.0.0.0:8000 -t public/\n";
echo "🔑 Admin login:  admin@accountingpro.com / Admin@123\n";
echo "\n📋 NEXT STEPS:\n";
echo "  1. Configure Nginx/Apache (see deploy/nginx.conf or deploy/apache.conf)\n";
echo "  2. Set up SSL: certbot --nginx -d yourdomain.com\n";
echo "  3. Update APP_URL in .env to your public domain\n";
echo "  4. Set APP_DEBUG=false in .env for production\n\n";
