<?php
namespace App\Core;

/**
 * RBAC Gate — checks permissions from roles + direct overrides.
 * Caches permissions in session to avoid repeated DB queries.
 */
class Gate
{
    /** All modules and their supported actions */
    public static array $modules = [
        'dashboard'  => ['view'],
        'invoices'   => ['view','create','edit','delete','export'],
        'customers'  => ['view','create','edit','delete'],
        'vendors'    => ['view','create','edit','delete'],
        'products'   => ['view','create','edit','delete'],
        'expenses'   => ['view','create','edit','delete','approve'],
        'accounting' => ['view','create'],
        'reports'    => ['view','export'],
        'branches'   => ['view','create','edit','delete'],
        'users'      => ['view','create','edit','delete'],
        'roles'      => ['view','create','edit','delete'],
        'import'     => ['view','create'],
        'webhooks'   => ['view','create','edit','delete'],
        'settings'   => ['view','edit'],
    ];

    /** Check if current user can perform action on module */
    public static function can(string $module, string $action): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        // Super admin bypasses everything
        if (($user['role'] ?? '') === 'super_admin') return true;

        $companyId = Auth::companyId();
        if (!$companyId) return false;

        $perms = self::loadPermissions($user['id'], $companyId);

        $key = "{$module}.{$action}";

        // Direct user override (revoke wins)
        if (array_key_exists("deny:{$key}", $perms)) return false;
        if (array_key_exists("grant:{$key}", $perms)) return true;

        // Role-based
        return in_array($key, $perms['role'] ?? [], true);
    }

    /** Abort with 403 if user cannot do action */
    public static function authorize(string $module, string $action): void
    {
        if (!self::can($module, $action)) {
            http_response_code(403);
            $view = BASE_PATH . '/views/errors/403.php';
            if (file_exists($view)) {
                require $view;
            } else {
                echo '<h1>403 — Forbidden</h1><p>You do not have permission to perform this action.</p>';
            }
            exit;
        }
    }

    /** Return all permission keys the user holds */
    public static function all(int $userId, int $companyId): array
    {
        return self::loadPermissions($userId, $companyId);
    }

    /** Load and cache permission set for user+company */
    private static function loadPermissions(int $userId, int $companyId): array
    {
        $cacheKey = "rbac_{$userId}_{$companyId}";
        if (isset($_SESSION[$cacheKey])) return $_SESSION[$cacheKey];

        $db  = Database::getInstance();
        $out = ['role' => [], 'grant' => [], 'deny' => []];

        // Role-based permissions
        $rows = Database::fetchAll(
            "SELECT p.module, p.action
             FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ? AND ur.company_id = ?",
            [$userId, $companyId]
        );
        foreach ($rows as $r) {
            $out['role'][] = "{$r['module']}.{$r['action']}";
        }
        $out['role'] = array_unique($out['role']);

        // Direct overrides
        $overrides = Database::fetchAll(
            "SELECT p.module, p.action, up.granted
             FROM user_permissions up
             JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id = ? AND up.company_id = ?",
            [$userId, $companyId]
        );
        foreach ($overrides as $o) {
            $key = "{$o['module']}.{$o['action']}";
            if ($o['granted']) {
                $out["grant:{$key}"] = true;
            } else {
                $out["deny:{$key}"]  = true;
            }
        }

        $_SESSION[$cacheKey] = $out;
        return $out;
    }

    /** Flush permission cache (call after role/permission changes) */
    public static function flush(int $userId, int $companyId): void
    {
        unset($_SESSION["rbac_{$userId}_{$companyId}"]);
    }

    /** Check if user is assigned a specific role slug */
    public static function hasRole(string $slug): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if (($user['role'] ?? '') === 'super_admin') return true;

        $companyId = Auth::companyId();
        $result    = Database::fetchColumn(
            "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND ur.company_id = ? AND r.slug = ? AND r.deleted_at IS NULL",
            [$user['id'], $companyId, $slug]
        );
        return (int)$result > 0;
    }
}
