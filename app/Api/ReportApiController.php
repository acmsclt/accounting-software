<?php
// app/Api/ReportApiController.php

namespace App\Api;

use App\Core\ApiController;
use App\Core\Database;

class ReportApiController extends ApiController
{
    /** GET /api/reports/profit-loss */
    public function profitLoss(): void
    {
        $this->requireAuth();
        $from     = $_GET['date_from'] ?? date('Y-01-01');
        $to       = $_GET['date_to']   ?? date('Y-12-31');
        $branchId = $_GET['branch_id'] ?? null;

        $branchWhere = $branchId ? "AND branch_id = {$branchId}" : '';

        $revenue = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(total), 0) FROM invoices
             WHERE company_id = ? AND invoice_date BETWEEN ? AND ?
             AND deleted_at IS NULL AND status != 'cancelled' {$branchWhere}",
            [$this->companyId, $from, $to]
        );

        $expenses = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses
             WHERE company_id = ? AND expense_date BETWEEN ? AND ?
             AND deleted_at IS NULL {$branchWhere}",
            [$this->companyId, $from, $to]
        );

        $cogs = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             WHERE e.company_id = ? AND e.expense_date BETWEEN ? AND ?
             AND ec.name LIKE '%Cost of Goods%' AND e.deleted_at IS NULL {$branchWhere}",
            [$this->companyId, $from, $to]
        );

        $grossProfit   = $revenue - $cogs;
        $netProfit     = $revenue - $expenses;
        $profitMargin  = $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0;

        // Revenue by month
        $byMonth = Database::fetchAll(
            "SELECT YEAR(invoice_date) AS year, MONTH(invoice_date) AS month,
                    SUM(total) AS revenue
             FROM invoices
             WHERE company_id = ? AND invoice_date BETWEEN ? AND ?
             AND deleted_at IS NULL AND status != 'cancelled'
             GROUP BY year, month ORDER BY year, month",
            [$this->companyId, $from, $to]
        );

        // Expenses by category
        $byCategory = Database::fetchAll(
            "SELECT ec.name AS category, SUM(e.amount) AS total
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             WHERE e.company_id = ? AND e.expense_date BETWEEN ? AND ?
             AND e.deleted_at IS NULL
             GROUP BY e.category_id ORDER BY total DESC",
            [$this->companyId, $from, $to]
        );

        $this->success([
            'period'        => ['from' => $from, 'to' => $to],
            'revenue'       => $revenue,
            'cogs'          => $cogs,
            'gross_profit'  => $grossProfit,
            'operating_expenses' => $expenses - $cogs,
            'net_profit'    => $netProfit,
            'profit_margin' => $profitMargin,
            'by_month'      => $byMonth,
            'expenses_by_category' => $byCategory,
        ]);
    }

    /** GET /api/reports/balance-sheet */
    public function balanceSheet(): void
    {
        $this->requireAuth();
        $asOfDate = $_GET['as_of'] ?? date('Y-m-d');

        $assets      = $this->getAccountsByType('asset', $asOfDate);
        $liabilities = $this->getAccountsByType('liability', $asOfDate);
        $equity      = $this->getAccountsByType('equity', $asOfDate);

        $totalAssets      = array_sum(array_column($assets, 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        $totalEquity      = array_sum(array_column($equity, 'balance'));

        $this->success([
            'as_of'           => $asOfDate,
            'assets'          => $assets,
            'liabilities'     => $liabilities,
            'equity'          => $equity,
            'total_assets'    => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity'    => $totalEquity,
            'balanced'        => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        ]);
    }

    /** GET /api/reports/branch-comparison */
    public function branchComparison(): void
    {
        $this->requireAuth();
        $from = $_GET['date_from'] ?? date('Y-m-01');
        $to   = $_GET['date_to']   ?? date('Y-m-t');

        $data = Database::fetchAll(
            "SELECT b.id, b.name, b.code,
                    COALESCE(SUM(i.total), 0) AS revenue,
                    COALESCE(SUM(i.amount_due), 0) AS outstanding,
                    COUNT(DISTINCT i.id) AS invoice_count
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id = b.id
                 AND i.invoice_date BETWEEN ? AND ?
                 AND i.deleted_at IS NULL AND i.status != 'cancelled'
             WHERE b.company_id = ? AND b.deleted_at IS NULL
             GROUP BY b.id
             ORDER BY revenue DESC",
            [$from, $to, $this->companyId]
        );

        $this->success(['period' => compact('from', 'to'), 'branches' => $data]);
    }

    private function getAccountsByType(string $type, string $asOfDate): array
    {
        return Database::fetchAll(
            "SELECT code, name, sub_type, balance
             FROM accounts
             WHERE company_id = ? AND type = ? AND is_active = 1 AND deleted_at IS NULL
             ORDER BY code",
            [$this->companyId, $type]
        );
    }
}
