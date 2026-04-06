<?php
// app/Models/Product.php

namespace App\Models;

use App\Core\Database;

class Product
{
    public static function all(int $companyId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where  = "p.company_id = ? AND p.deleted_at IS NULL";
        $params = [$companyId];

        if (!empty($filters['search'])) {
            $s        = '%' . $filters['search'] . '%';
            $where   .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
            $params[] = $s; $params[] = $s;
        }
        if (!empty($filters['category_id'])) {
            $where   .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['type'])) {
            $where   .= " AND p.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['low_stock'])) {
            $where .= " AND p.track_inventory = 1 AND COALESCE((SELECT SUM(quantity) FROM inventory WHERE product_id = p.id), 0) <= p.stock_alert_qty";
        }

        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT p.*, c.name AS category_name,
                    COALESCE((SELECT SUM(i.quantity) FROM inventory i WHERE i.product_id = p.id), 0) AS total_stock
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE {$where}
             ORDER BY p.name
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function count(int $companyId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM products WHERE company_id = ? AND deleted_at IS NULL",
            [$companyId]
        );
    }

    public static function findById(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT p.*, c.name AS category_name,
                    COALESCE((SELECT SUM(i.quantity) FROM inventory i WHERE i.product_id = p.id), 0) AS total_stock
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.company_id = ? AND p.deleted_at IS NULL",
            [$id, $companyId]
        );
    }

    public static function findBySku(string $sku, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM products WHERE sku = ? AND company_id = ? AND deleted_at IS NULL",
            [$sku, $companyId]
        );
    }

    public static function create(int $companyId, array $data): int
    {
        $data['company_id'] = $companyId;
        $data['created_at'] = date('Y-m-d H:i:s');
        $productId = Database::insert('products', $data);

        // Initialize inventory for default warehouse
        $warehouse = Database::fetch(
            "SELECT id FROM warehouses WHERE company_id = ? AND is_default = 1 LIMIT 1",
            [$companyId]
        );
        if ($warehouse && ($data['track_inventory'] ?? 0)) {
            Database::insert('inventory', [
                'product_id'   => $productId,
                'warehouse_id' => $warehouse['id'],
                'quantity'     => 0,
            ]);
        }
        return $productId;
    }

    public static function update(int $id, int $companyId, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('products', $data, ['id' => $id, 'company_id' => $companyId]);
    }

    public static function delete(int $id, int $companyId): int
    {
        return Database::update('products', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id, 'company_id' => $companyId]);
    }

    public static function adjustStock(int $productId, int $warehouseId, float $qty, string $type = 'add'): void
    {
        $delta = $type === 'add' ? $qty : -$qty;
        Database::query(
            "INSERT INTO inventory (product_id, warehouse_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
            [$productId, $warehouseId, $delta]
        );
    }

    public static function lowStock(int $companyId): array
    {
        return Database::fetchAll(
            "SELECT p.*, COALESCE(SUM(i.quantity), 0) AS total_stock
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.company_id = ? AND p.track_inventory = 1 AND p.deleted_at IS NULL
             GROUP BY p.id
             HAVING total_stock <= p.stock_alert_qty
             ORDER BY total_stock ASC",
            [$companyId]
        );
    }

    public static function stockByWarehouse(int $productId): array
    {
        return Database::fetchAll(
            "SELECT w.name AS warehouse_name, i.quantity, i.reserved_qty
             FROM inventory i
             JOIN warehouses w ON w.id = i.warehouse_id
             WHERE i.product_id = ?",
            [$productId]
        );
    }
}
