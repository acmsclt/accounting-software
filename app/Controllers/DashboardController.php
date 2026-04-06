<?php
// app/Controllers/DashboardController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Branch;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index(): void
    {
        $companyId = $this->companyId();
        $branchId  = $_GET['branch_id'] ?? null;
        $year      = (int)($_GET['year'] ?? date('Y'));
        $period    = $_GET['period'] ?? 'this_month';

        [$dateFrom, $dateTo] = $this->periodDates($period);

        // Invoice summary
        $invoiceSummary = Invoice::summary($companyId);

        // Revenue for period
        $revenue = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(total), 0) FROM invoices
             WHERE company_id = ? AND invoice_date BETWEEN ? AND ?
             AND deleted_at IS NULL AND status != 'cancelled'"
             . ($branchId ? " AND branch_id = {$branchId}" : ''),
            [$companyId, $dateFrom, $dateTo]
        );

        // Expenses for period
        $expenses = Expense::totalByPeriod($companyId, $dateFrom, $dateTo, $branchId ? (int)$branchId : null);

        // Monthly chart data
        $monthlySales    = Invoice::monthlySales($companyId, $year);
        $salesByMonth    = array_fill(1, 12, 0);
        foreach ($monthlySales as $s) {
            $salesByMonth[$s['month']] = (float) $s['total'];
        }

        // Expense by category (for donut chart)
        $expensesByCategory = Expense::byCategory($companyId, $dateFrom, $dateTo, $branchId ? (int)$branchId : null);

        // Recent invoices
        $recentInvoices = Invoice::all($companyId, [], 5, 0);

        // Low stock alerts
        $lowStock = Product::lowStock($companyId);

        // Branches summary
        $branches        = Branch::allForCompany($companyId);
        $branchSummaries = Branch::summary($companyId, $period);

        // Overdue invoices count
        $overdueCount = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM invoices
             WHERE company_id = ? AND status = 'sent' AND due_date < CURDATE() AND deleted_at IS NULL",
            [$companyId]
        );

        // Recent unread notifications
        $notifications = Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5",
            [Auth::id()]
        );

        $this->view('dashboard.index', compact(
            'invoiceSummary', 'revenue', 'expenses',
            'salesByMonth', 'expensesByCategory',
            'recentInvoices', 'lowStock',
            'branches', 'branchSummaries',
            'overdueCount', 'notifications',
            'period', 'year', 'branchId',
            'dateFrom', 'dateTo'
        ));
    }

    private function periodDates(string $period): array
    {
        return match ($period) {
            'today'       => [date('Y-m-d'), date('Y-m-d')],
            'this_week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
            'this_month'  => [date('Y-m-01'), date('Y-m-t')],
            'last_month'  => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
            'this_year'   => [date('Y-01-01'), date('Y-12-31')],
            default       => [date('Y-m-01'), date('Y-m-t')],
        };
    }
}
