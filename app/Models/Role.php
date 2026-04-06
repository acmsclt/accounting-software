<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Gate;

class Role
{
    /** All roles for a company */
    public static function allForCompany(int $companyId): array
    {
        return Database::fetchAll(
            "SELECT r.*, COUNT(DISTINCT ur.user_id) AS user_count,
                    COUNT(DISTINCT rp.permission_id) AS permission_count
             FROM roles r
             LEFT JOIN user_roles ur ON ur.role_id = r.id AND ur.company_id = r.company_id
             LEFT JOIN role_permissions rp ON rp.role_id = r.id
             WHERE r.company_id = ? AND r.deleted_at IS NULL
             GROUP BY r.id ORDER BY r.is_system DESC, r.name",
            [$companyId]
        );
    }

    public static function findById(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM roles WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$id, $companyId]
        ) ?: null;
    }

    public static function create(int $companyId, array $data): int
    {
        return Database::insert('roles', array_merge($data, [
            'company_id' => $companyId,
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public static function update(int $id, int $companyId, array $data): void
    {
        Database::update('roles', $data, ['id' => $id, 'company_id' => $companyId]);
    }

    public static function delete(int $id, int $companyId): bool
    {
        $role = self::findById($id, $companyId);
        if (!$role || $role['is_system']) return false;

        Database::update('roles', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id, 'company_id' => $companyId]);
        return true;
    }

    /** Get all permissions for a role, grouped by module */
    public static function getPermissions(int $roleId): array
    {
        $rows = Database::fetchAll(
            "SELECT p.* FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?",
            [$roleId]
        );
        $grouped = [];
        foreach ($rows as $p) {
            $grouped[$p['module']][] = $p['action'];
        }
        return $grouped;
    }

    /** Replace all permissions for a role */
    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::delete('role_permissions', ['role_id' => $roleId]);
        foreach ($permissionIds as $pid) {
            Database::insert('role_permissions', ['role_id' => $roleId, 'permission_id' => (int)$pid]);
        }
        // Flush all user caches for this role
        $users = Database::fetchAll("SELECT user_id, company_id FROM user_roles WHERE role_id = ?", [$roleId]);
        foreach ($users as $ur) Gate::flush($ur['user_id'], $ur['company_id']);
    }

    /** Users with this role */
    public static function getUsers(int $roleId): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.name, u.email, u.is_active, ur.assigned_at
             FROM user_roles ur JOIN users u ON u.id = ur.user_id
             WHERE ur.role_id = ? ORDER BY u.name",
            [$roleId]
        );
    }

    /** Slugify name */
    public static function makeSlug(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
    }
}
