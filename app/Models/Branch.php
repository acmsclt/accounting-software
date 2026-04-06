<?php
// app/Models/Branch.php

namespace App\Models;

use App\Core\Database;

class Branch
{
    public static function allForCompany(int $companyId): array
    {
        return Database::fetchAll(
            "SELECT b.*, u.name AS manager_name
             FROM branches b
             LEFT JOIN users u ON u.id = b.manager_id
             WHERE b.company_id = ? AND b.deleted_at IS NULL
             ORDER BY b.is_default DESC, b.name",
            [$companyId]
        );
    }

    public static function findById(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM branches WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$id, $companyId]
        );
    }

    public static function getDefault(int $companyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM branches WHERE company_id = ? AND is_default = 1 AND deleted_at IS NULL LIMIT 1",
            [$companyId]
        );
    }

    public static function create(int $companyId, array $data): int
    {
        // If first branch or set as default, clear others
        if (!empty($data['is_default'])) {
            Database::update('branches', ['is_default' => 0], ['company_id' => $companyId]);
        }

        $data['company_id'] = $companyId;
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('branches', $data);
    }

    public static function update(int $id, int $companyId, array $data): int
    {
        if (!empty($data['is_default'])) {
            Database::update('branches', ['is_default' => 0], ['company_id' => $companyId]);
        }
        return Database::update('branches', $data, ['id' => $id, 'company_id' => $companyId]);
    }

    public static function delete(int $id, int $companyId): int
    {
        // Prevent deleting the default branch
        $branch = self::findById($id, $companyId);
        if (!$branch || $branch['is_default']) return 0;

        return Database::update('branches', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public static function getUsers(int $branchId): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.name, u.email, bu.role
             FROM branch_users bu
             JOIN users u ON u.id = bu.user_id
             WHERE bu.branch_id = ?",
            [$branchId]
        );
    }

    public static function assignUser(int $branchId, int $userId, string $role = 'staff'): void
    {
        // Upsert
        Database::query(
            "INSERT INTO branch_users (branch_id, user_id, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)",
            [$branchId, $userId, $role]
        );
    }

    /** Summary stats per branch */
    public static function summary(int $companyId, string $period = 'this_month'): array
    {
        [$dateFrom, $dateTo] = self::periodDates($period);

        return Database::fetchAll(
            "SELECT b.id, b.name, b.code,
                COUNT(DISTINCT i.id)  AS invoice_count,
                COALESCE(SUM(i.total), 0) AS total_revenue,
                COALESCE(SUM(e.amount), 0) AS total_expenses
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id = b.id
                AND i.invoice_date BETWEEN ? AND ?
                AND i.deleted_at IS NULL AND i.status != 'cancelled'
             LEFT JOIN expenses e ON e.branch_id = b.id
                AND e.expense_date BETWEEN ? AND ?
                AND e.deleted_at IS NULL
             WHERE b.company_id = ? AND b.deleted_at IS NULL
             GROUP BY b.id
             ORDER BY total_revenue DESC",
            [$dateFrom, $dateTo, $dateFrom, $dateTo, $companyId]
        );
    }

    private static function periodDates(string $period): array
    {
        return match ($period) {
            'this_month'  => [date('Y-m-01'), date('Y-m-t')],
            'last_month'  => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
            'this_year'   => [date('Y-01-01'), date('Y-12-31')],
            default       => [date('Y-m-01'), date('Y-m-t')],
        };
    }
}
