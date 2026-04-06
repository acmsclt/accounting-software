<?php
// app/Models/Expense.php

namespace App\Models;

use App\Core\Database;

class Expense
{
    public static function all(int $companyId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where  = "e.company_id = ? AND e.deleted_at IS NULL";
        $params = [$companyId];

        if (!empty($filters['branch_id'])) {
            $where   .= " AND e.branch_id = ?";
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['category_id'])) {
            $where   .= " AND e.category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['date_from'])) {
            $where   .= " AND e.expense_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where   .= " AND e.expense_date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where   .= " AND e.title LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT e.*, ec.name AS category_name, ec.color AS category_color,
                    b.name AS branch_name
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE {$where}
             ORDER BY e.expense_date DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function count(int $companyId, array $filters = []): int
    {
        $where  = "company_id = ? AND deleted_at IS NULL";
        $params = [$companyId];
        if (!empty($filters['branch_id'])) {
            $where .= " AND branch_id = ?"; $params[] = $filters['branch_id'];
        }
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM expenses WHERE {$where}", $params);
    }

    public static function findById(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT e.*, ec.name AS category_name, b.name AS branch_name
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE e.id = ? AND e.company_id = ? AND e.deleted_at IS NULL",
            [$id, $companyId]
        );
    }

    public static function create(int $companyId, array $data): int
    {
        $data['company_id'] = $companyId;
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('expenses', $data);
    }

    public static function update(int $id, int $companyId, array $data): int
    {
        return Database::update('expenses', $data, ['id' => $id, 'company_id' => $companyId]);
    }

    public static function delete(int $id, int $companyId): int
    {
        return Database::update('expenses', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id, 'company_id' => $companyId]);
    }

    public static function totalByPeriod(int $companyId, string $from, string $to, ?int $branchId = null): float
    {
        $where  = "company_id = ? AND expense_date BETWEEN ? AND ? AND deleted_at IS NULL";
        $params = [$companyId, $from, $to];
        if ($branchId) { $where .= " AND branch_id = ?"; $params[] = $branchId; }
        return (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE {$where}", $params);
    }

    public static function byCategory(int $companyId, string $from, string $to, ?int $branchId = null): array
    {
        $where  = "e.company_id = ? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL";
        $params = [$companyId, $from, $to];
        if ($branchId) { $where .= " AND e.branch_id = ?"; $params[] = $branchId; }

        return Database::fetchAll(
            "SELECT ec.name, ec.color, SUM(e.amount) AS total
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             WHERE {$where}
             GROUP BY e.category_id
             ORDER BY total DESC",
            $params
        );
    }
}
