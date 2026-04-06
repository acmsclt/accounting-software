<?php
// app/Models/Invoice.php

namespace App\Models;

use App\Core\Database;
use App\Core\Auth;

class Invoice
{
    public static function all(int $companyId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where  = "i.company_id = ? AND i.deleted_at IS NULL";
        $params = [$companyId];

        if (!empty($filters['status'])) {
            $where   .= " AND i.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $where   .= " AND i.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['search'])) {
            $where   .= " AND (i.invoice_number LIKE ? OR c.name LIKE ?)";
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($filters['date_from'])) {
            $where   .= " AND i.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where   .= " AND i.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }

        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT i.*, c.name AS customer_name, c.email AS customer_email
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             WHERE {$where}
             ORDER BY i.invoice_date DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function count(int $companyId, array $filters = []): int
    {
        $where  = "i.company_id = ? AND i.deleted_at IS NULL";
        $params = [$companyId];

        if (!empty($filters['status'])) {
            $where   .= " AND i.status = ?";
            $params[] = $filters['status'];
        }

        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             WHERE {$where}",
            $params
        );
    }

    public static function findById(int $id, int $companyId): ?array
    {
        return Database::fetch(
            "SELECT i.*, c.name AS customer_name, c.email AS customer_email,
                    c.address AS customer_address, c.country AS customer_country,
                    co.name AS company_name, co.address AS company_address,
                    co.email AS company_email, co.phone AS company_phone,
                    co.logo AS company_logo, co.tax_id AS company_tax_id
             FROM invoices i
             JOIN customers c  ON c.id  = i.customer_id
             JOIN companies co ON co.id = i.company_id
             WHERE i.id = ? AND i.company_id = ? AND i.deleted_at IS NULL",
            [$id, $companyId]
        );
    }

    public static function items(int $invoiceId): array
    {
        return Database::fetchAll(
            "SELECT ii.*, p.name AS product_name, p.sku
             FROM invoice_items ii
             LEFT JOIN products p ON p.id = ii.product_id
             WHERE ii.invoice_id = ?
             ORDER BY ii.sort_order",
            [$invoiceId]
        );
    }

    public static function create(array $data, array $items): int
    {
        Database::beginTransaction();

        try {
            // Auto-generate invoice number
            $company = Database::fetch(
                "SELECT invoice_prefix, invoice_counter FROM companies WHERE id = ? FOR UPDATE",
                [$data['company_id']]
            );
            $invoiceNumber = $company['invoice_prefix'] . $company['invoice_counter'];
            Database::update('companies', ['invoice_counter' => $company['invoice_counter'] + 1], ['id' => $data['company_id']]);

            $data['invoice_number'] = $invoiceNumber;
            $data['created_at']     = date('Y-m-d H:i:s');

            // Calculate totals
            $subTotal   = 0;
            $taxTotal   = 0;

            foreach ($items as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $taxAmt    = round($lineTotal * ($item['tax_rate'] / 100), 4);
                $subTotal  += $lineTotal;
                $taxTotal  += $taxAmt;
            }

            $discountAmt = 0;
            if (!empty($data['discount_value'])) {
                $discountAmt = $data['discount_type'] === 'percent'
                    ? round($subTotal * ($data['discount_value'] / 100), 4)
                    : (float) $data['discount_value'];
            }

            $data['sub_total']       = $subTotal;
            $data['discount_amount'] = $discountAmt;
            $data['tax_amount']      = $taxTotal;
            $data['total']           = $subTotal - $discountAmt + $taxTotal;
            $data['amount_due']      = $data['total'];
            $data['amount_paid']     = 0;

            $invoiceId = Database::insert('invoices', $data);

            foreach ($items as $sortOrder => $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $taxAmt    = round($lineTotal * ($item['tax_rate'] / 100), 4);

                Database::insert('invoice_items', [
                    'invoice_id'  => $invoiceId,
                    'product_id'  => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'discount'    => $item['discount'] ?? 0,
                    'tax_rate'    => $item['tax_rate'] ?? 0,
                    'tax_amount'  => $taxAmt,
                    'total'       => $lineTotal + $taxAmt,
                    'sort_order'  => $sortOrder,
                ]);
            }

            Database::commit();
            return $invoiceId;

        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function applyPayment(int $invoiceId, float $amount): void
    {
        $invoice = Database::fetch("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
        if (!$invoice) return;

        $newPaid = $invoice['amount_paid'] + $amount;
        $newDue  = $invoice['total'] - $newPaid;
        $status  = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : $invoice['status']);
        $paidAt  = $status === 'paid' ? date('Y-m-d H:i:s') : $invoice['paid_at'];

        Database::update('invoices', [
            'amount_paid' => $newPaid,
            'amount_due'  => max(0, $newDue),
            'status'      => $status,
            'paid_at'     => $paidAt,
        ], ['id' => $invoiceId]);
    }

    public static function summary(int $companyId): array
    {
        return Database::fetch(
            "SELECT
                SUM(total)                          AS total_invoiced,
                SUM(amount_paid)                    AS total_paid,
                SUM(amount_due)                     AS total_outstanding,
                SUM(CASE WHEN status='overdue' THEN amount_due ELSE 0 END) AS total_overdue,
                COUNT(*)                            AS total_count
             FROM invoices
             WHERE company_id = ? AND deleted_at IS NULL AND status != 'cancelled'",
            [$companyId]
        ) ?? [];
    }

    public static function monthlySales(int $companyId, int $year): array
    {
        return Database::fetchAll(
            "SELECT MONTH(invoice_date) AS month, SUM(total) AS total
             FROM invoices
             WHERE company_id = ? AND YEAR(invoice_date) = ? AND status != 'cancelled' AND deleted_at IS NULL
             GROUP BY MONTH(invoice_date)
             ORDER BY month",
            [$companyId, $year]
        );
    }
}
