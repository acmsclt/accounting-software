<?php
namespace App\Api;

use App\Core\ApiController;
use App\Controllers\ReportController;

/**
 * REST wrapper around ReportController data generators.
 * GET /api/reports/{slug}?date_from=&date_to=&branch_id=&export=csv|excel|json|pdf
 */
class ReportsApiController extends ApiController
{
    public function show(string $slug): void
    {
        $this->requireAuth();

        $from     = $_GET['date_from'] ?? date('Y-m-01');
        $to       = $_GET['date_to']   ?? date('Y-m-t');
        $branchId = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;

        // Use the web ReportController — set context via session-compatible values
        // then call data via a direct static shim
        [$columns, $data, $summary] = \App\Services\ReportDataService::generate(
            $slug, $this->companyId, $from, $to, $branchId
        );

        $format = $_GET['export'] ?? null;
        if ($format && in_array($format, ['csv','excel','json','pdf'])) {
            $filename  = "{$slug}_{$from}_{$to}";
            $headers   = array_keys($columns);
            $fieldKeys = array_values($columns);
            $flat      = array_map(fn($row) => array_map(fn($k) => $row[$k] ?? '', $fieldKeys), $data);

            match ($format) {
                'csv'   => \App\Services\ExportService::csv($flat, $headers, $filename),
                'excel' => \App\Services\ExportService::excel($flat, $headers, $filename, $slug),
                'json'  => \App\Services\ExportService::json($data, ['report' => $slug, 'period' => "{$from} to {$to}"], $filename),
                'pdf'   => \App\Services\ExportService::pdf($slug,
                    \App\Services\ExportService::buildReportHtml($slug, $columns, $data, $summary, "{$from} to {$to}"),
                    $filename, count($columns) > 6 ? 'L' : 'P'),
                default => null,
            };
        }

        $this->success([
            'report'  => $slug,
            'period'  => ['from' => $from, 'to' => $to],
            'columns' => array_keys($columns),
            'total'   => count($data),
            'summary' => $summary,
            'data'    => $data,
        ]);
    }


    /** GET /api/reports — list all available reports */
    public function index(): void
    {
        $this->requireAuth();
        $this->success([
            'reports' => [
                ['slug' => 'profit-loss',       'title' => 'Profit & Loss',       'group' => 'Financial'],
                ['slug' => 'balance-sheet',     'title' => 'Balance Sheet',       'group' => 'Financial'],
                ['slug' => 'trial-balance',     'title' => 'Trial Balance',       'group' => 'Financial'],
                ['slug' => 'cash-flow',         'title' => 'Cash Flow',           'group' => 'Financial'],
                ['slug' => 'revenue-customer',  'title' => 'Revenue by Customer', 'group' => 'Sales'],
                ['slug' => 'revenue-product',   'title' => 'Revenue by Product',  'group' => 'Sales'],
                ['slug' => 'revenue-branch',    'title' => 'Revenue by Branch',   'group' => 'Sales'],
                ['slug' => 'invoice-aging',     'title' => 'Invoice Aging (AR)',  'group' => 'Sales'],
                ['slug' => 'sales-summary',     'title' => 'Sales Summary',       'group' => 'Sales'],
                ['slug' => 'expense-summary',   'title' => 'Expense Summary',     'group' => 'Expenses'],
                ['slug' => 'expense-category',  'title' => 'Expense by Category', 'group' => 'Expenses'],
                ['slug' => 'expense-branch',    'title' => 'Expense by Branch',   'group' => 'Expenses'],
                ['slug' => 'stock-level',       'title' => 'Stock Level',         'group' => 'Inventory'],
                ['slug' => 'low-stock',         'title' => 'Low Stock Alerts',    'group' => 'Inventory'],
                ['slug' => 'stock-movement',    'title' => 'Stock Movement',      'group' => 'Inventory'],
                ['slug' => 'customer-ledger',   'title' => 'Customer Ledger',     'group' => 'Customers'],
                ['slug' => 'payables-aging',    'title' => 'Payables Aging (AP)', 'group' => 'Vendors'],
                ['slug' => 'vendor-ledger',     'title' => 'Vendor Ledger',       'group' => 'Vendors'],
                ['slug' => 'tax-summary',       'title' => 'Tax Summary',         'group' => 'Tax'],
                ['slug' => 'branch-comparison', 'title' => 'Branch Comparison',   'group' => 'Branches'],
                ['slug' => 'general-ledger',    'title' => 'General Ledger',      'group' => 'Accounting'],
                ['slug' => 'payment-received',  'title' => 'Payments Received',   'group' => 'Accounting'],
            ],
            'export_formats' => ['csv', 'excel', 'json', 'pdf'],
        ]);
    }
}
