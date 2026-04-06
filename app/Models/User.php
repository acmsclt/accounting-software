<?php
// app/Models/User.php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );
    }

    public static function create(array $data): int
    {
        $data['password']   = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('users', $data);
    }

    public static function update(int $id, array $data): int
    {
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        return Database::update('users', $data, ['id' => $id]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function companies(int $userId): array
    {
        return Database::fetchAll(
            "SELECT c.*, cu.role as company_role
             FROM companies c
             JOIN company_users cu ON cu.company_id = c.id
             WHERE cu.user_id = ? AND c.deleted_at IS NULL
             ORDER BY c.name",
            [$userId]
        );
    }

    public static function updateLastLogin(int $id): void
    {
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }
}
