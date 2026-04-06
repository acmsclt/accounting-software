<?php
// app/Core/Auth.php — JWT + Session Auth, RBAC

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static array $config;

    private static function config(): array
    {
        if (empty(self::$config)) {
            self::$config = require BASE_PATH . '/config/app.php';
        }
        return self::$config;
    }

    // ── Session-based Web Auth ──────────────────────────────────────────────

    public static function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['user_role']    = $user['role'];
        $_SESSION['company_id']   = $user['active_company_id'] ?? null;
        $_SESSION['logged_in']    = true;
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public static function check(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return [
            'id'         => $_SESSION['user_id'],
            'email'      => $_SESSION['user_email'],
            'role'       => $_SESSION['user_role'],
            'company_id' => $_SESSION['company_id'],
        ];
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function companyId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return !empty($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null;
    }

    public static function role(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION['user_role'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireAuth();
        if (!self::hasRole($role, 'super_admin')) {
            http_response_code(403);
            require BASE_PATH . '/views/errors/403.php';
            exit;
        }
    }

    public static function switchCompany(int $companyId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['company_id'] = $companyId;
    }

    // ── JWT API Auth ────────────────────────────────────────────────────────

    public static function generateJwt(array $user, int $companyId = 0): string
    {
        $cfg = self::config()['jwt'];
        $now = time();

        $payload = [
            'iss'        => self::config()['url'],
            'iat'        => $now,
            'exp'        => $now + $cfg['expiry'],
            'sub'        => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'company_id' => $companyId,
        ];

        return JWT::encode($payload, $cfg['secret'], 'HS256');
    }

    public static function generateRefreshToken(int $userId): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function decodeJwt(string $token): ?object
    {
        try {
            $cfg = self::config()['jwt'];
            return JWT::decode($token, new Key($cfg['secret'], 'HS256'));
        } catch (\Exception) {
            return null;
        }
    }

    public static function requireJwt(): void
    {
        $token = self::extractBearerToken();

        if (!$token) {
            self::jwtError('Missing authorization token.', 401);
        }

        $decoded = self::decodeJwt($token);

        if (!$decoded) {
            self::jwtError('Invalid or expired token.', 401);
        }

        // Inject into request
        $_REQUEST['_jwt_user'] = (array) $decoded;
    }

    public static function jwtUser(): ?array
    {
        return $_REQUEST['_jwt_user'] ?? null;
    }

    public static function extractBearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return $_GET['token'] ?? null;
    }

    // ── CSRF ─────────────────────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die(json_encode(['success' => false, 'message' => 'CSRF token mismatch.']));
        }
    }

    // ── API Key Auth ──────────────────────────────────────────────────────────

    public static function retrieveApiKey(): ?array
    {
        $key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        if (!$key) return null;

        $apiKey = Database::fetch(
            "SELECT ak.*, c.id AS company_id FROM api_keys ak
             JOIN companies c ON c.id = ak.company_id
             WHERE ak.key_value = ? AND ak.is_active = 1 AND ak.deleted_at IS NULL",
            [$key]
        );

        if ($apiKey) {
            // Rate limiting check
            self::checkRateLimit($apiKey['id']);
        }

        return $apiKey ?: null;
    }

    private static function checkRateLimit(int $apiKeyId): void
    {
        // Simple rate limit: 1000 req/hour per API key
        $count = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM api_request_logs
             WHERE api_key_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$apiKeyId]
        );

        if ($count >= 1000) {
            header('Content-Type: application/json');
            http_response_code(429);
            die(json_encode(['success' => false, 'message' => 'Rate limit exceeded. Max 1000 req/hour.']));
        }

        Database::insert('api_request_logs', [
            'api_key_id' => $apiKeyId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function jwtError(string $message, int $code): never
    {
        header('Content-Type: application/json');
        http_response_code($code);
        die(json_encode(['success' => false, 'message' => $message]));
    }
}
