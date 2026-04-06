<?php
// app/Models/Customer.php

namespace App\Models;

use App\Core\Database;

class Customer
{
    public static function all(int $companyId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where  = "company_id = ? AND deleted_at IS NULL";
        $params = [$companyId];

        if (!empty($filters['search'])) {
            $s        = '%' . $filters['search'] . '%';
            $where   .= " AND (name LIKE ? OR email LIKE ? OR company_name LIKE ?)";
            $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if (!empty($filters['country'])) {
            $where   .= " AND country = ?";
            $params[] = $filters['country'];
        }

        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT * FROM customers WHERE {$where} ORDER BY name LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function count(int $companyId, array $filters = []): int
    {
        $where  = "company_id = ? AND deleted_at IS NULL";
        $params = [$companyId];
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where .= " AND (name LIKE ? OR email LIKE ?)";
            $params[] = $s; $params[] = $s;
        }
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM customers WHERE {$where}", $params);
    }

    public static function findById(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL",
            [$id, $companyId]
        );
    }

    public static function create(int $companyId, array $data): int
    {
        $data['company_id'] = $companyId;
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('customers', $data);
    }

    public static function update(int $id, int $companyId, array $data): int
    {
        return Database::update('customers', $data, ['id' => $id, 'company_id' => $companyId]);
    }

    public static function delete(int $id, int $companyId): int
    {
        return Database::update('customers', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id, 'company_id' => $companyId]);
    }

    public static function ledger(int $customerId, int $companyId): array
    {
        return Database::fetchAll(
            "SELECT 'invoice' AS type, invoice_number AS reference, invoice_date AS date,
                    total AS debit, 0 AS credit, status
             FROM invoices
             WHERE customer_id = ? AND company_id = ? AND deleted_at IS NULL
             UNION ALL
             SELECT 'payment', reference, payment_date, 0, amount, status
             FROM payments
             WHERE customer_id = ? AND company_id = ? AND deleted_at IS NULL
             ORDER BY date DESC",
            [$customerId, $companyId, $customerId, $companyId]
        );
    }
}
