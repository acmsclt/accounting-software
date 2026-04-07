<?php
// TEMPORARY diagnostic file — DELETE after debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>✅ PHP is working via Apache+FPM</h2>";
echo "<b>PHP Version:</b> " . PHP_VERSION . "<br>";
echo "<b>Server:</b> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "<br>";
echo "<b>Document Root:</b> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "<br>";
echo "<b>Script:</b> " . __FILE__ . "<br>";
echo "<b>Request URI:</b> " . ($_SERVER['REQUEST_URI'] ?? '') . "<br>";
echo "<b>Script Name:</b> " . ($_SERVER['SCRIPT_NAME'] ?? '') . "<br>";
echo "<hr>";

// Test autoloader
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
    echo "✅ Composer autoload OK<br>";
} else {
    echo "❌ Composer autoload MISSING at: $autoload<br>";
}

// Test .env
$envFile = dirname(__DIR__) . '/.env';
echo file_exists($envFile) ? "✅ .env exists<br>" : "❌ .env MISSING<br>";

// Test DB
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_DATABASE']}",
        $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']
    );
    echo "✅ Database connected: " . $_ENV['DB_DATABASE'] . "<br>";
} catch (Exception $e) {
    echo "❌ DB Error: " . $e->getMessage() . "<br>";
}

echo "<hr><b>DELETE public/test.php after debugging!</b>";
