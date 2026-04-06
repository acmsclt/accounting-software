<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ExportService;
use App\Models\Branch;

class ReportController extends Controller
{
    private array $reportRegistry = [
        'profit-loss'       => ['title' => 'Profit & Loss',           'group' => 'Financial',  'icon' => '📈'],
        'balance-sheet'     => ['title' => 'Balance Sheet',           'group' => 'Financial',  'icon' => '⚖️'],
        'trial-balance'     => ['title' => 'Trial Balance',           'group' => 'Financial',  'icon' => '📒'],
        'cash-flow'         => ['title' => 'Cash Flow',               'group' => 'Financial',  'icon' => '💵'],
        'revenue-customer'  => ['title' => 'Revenue by Customer',     'group' => 'Sales',      'icon' => '👥'],
        'revenue-product'   => ['title' => 'Revenue by Product',      'group' => 'Sales',      'icon' => '📦'],
        'revenue-branch'    => ['title' => 'Revenue by Branch',       'group' => 'Sales',      'icon' => '🏢'],
        'invoice-aging'     => ['title' => 'Invoice Aging (AR)',      'group' => 'Sales',      'icon' => '⏱️'],
        'sales-summary'     => ['title' => 'Sales Summary',           'group' => 'Sales',      'icon' => '🧾'],
        'expense-summary'   => ['title' => 'Expense Summary',         'group' => 'Expenses',   'icon' => '💸'],
        'expense-category'  => ['title' => 'Expense by Category',     'group' => 'Expenses',   'icon' => '🏷️'],
        'expense-branch'    => ['title' => 'Expense by Branch',       'group' => 'Expenses',   'icon' => '🏢'],
        'stock-level'       => ['title' => 'Stock Level',             'group' => 'Inventory',  'icon' => '📊'],
        'low-stock'         => ['title' => 'Low Stock Alerts',        'group' => 'Inventory',  'icon' => '⚠️'],
        'stock-movement'    => ['title' => 'Stock Movement',          'group' => 'Inventory',  'icon' => '🔄'],
        'customer-ledger'   => ['title' => 'Customer Ledger',         'group' => 'Customers',  'icon' => '📋'],
        'payables-aging'    => ['title' => 'Payables Aging (AP)',     'group' => 'Vendors',    'icon' => '📆'],
        'vendor-ledger'     => ['title' => 'Vendor Ledger',           'group' => 'Vendors',    'icon' => '📋'],
        'tax-summary'       => ['title' => 'Tax Summary',             'group' => 'Tax',        'icon' => '🏛️'],
        'branch-comparison' => ['title' => 'Branch Comparison',       'group' => 'Branches',   'icon' => '⚡'],
        'general-ledger'    => ['title' => 'General Ledger',          'group' => 'Accounting', 'icon' => '📔'],
        'payment-received'  => ['title' => 'Payments Received',       'group' => 'Accounting', 'icon' => '✅'],
    ];

    /** GET /reports — 360° hub */
    public function index(): void
    {
        $companyId = $this->companyId();
        $branches  = Branch::allForCompany($companyId);
        $registry  = $this->reportRegistry;
        $groups    = [];
        foreach ($registry as $slug => $r) {
            $groups[$r['group']][] = array_merge($r, ['slug' => $slug]);
        }
        $this->view('reports.index', compact('groups', 'branches'));
    }

    /** GET /reports/{slug} — Generate & display a report */
    public function show(string $slug): void
    {
        if (!isset($this->reportRegistry[$slug])) $this->redirect('/reports');

        $companyId = $this->companyId();
        $from      = $_GET['date_from'] ?? date('Y-m-01');
        $to        = $_GET['date_to']   ?? date('Y-m-t');
        $branchId  = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $branches  = Branch::allForCompany($companyId);
        $meta      = $this->reportRegistry[$slug];

        [$columns, $data, $summary] = $this->generateReport($slug, $companyId, $from, $to, $branchId);

        // Export?
        $format = $_GET['export'] ?? null;
        if ($format) {
            $filename = "{$slug}_{$from}_{$to}";
            $this->exportReport($format, $columns, $data, $meta['title'], $from, $to, $filename, $summary);
        }

        $this->view('reports.viewer', compact(
            'slug', 'meta', 'columns', 'data', 'summary',
            'from', 'to', 'branchId', 'branches'
        ));
    }

    // ── Report generators ───────────────────────────────────────────────────

    private function generateReport(string $slug, int $c, string $from, string $to, ?int $b): array
    {
        $bw = $b ? "AND branch_id = {$b}" : '';

        return match ($slug) {
            'profit-loss'      => $this->rProfitLoss($c, $from, $to, $bw),
            'balance-sheet'    => $this->rBalanceSheet($c),
            'trial-balance'    => $this->rTrialBalance($c),
            'cash-flow'        => $this->rCashFlow($c, $from, $to, $bw),
            'revenue-customer' => $this->rRevenueCustomer($c, $from, $to, $bw),
            'revenue-product'  => $this->rRevenueProduct($c, $from, $to, $bw),
            'revenue-branch'   => $this->rRevenueBranch($c, $from, $to),
            'invoice-aging'    => $this->rInvoiceAging($c, $bw),
            'sales-summary'    => $this->rSalesSummary($c, $from, $to, $bw),
            'expense-summary'  => $this->rExpenseSummary($c, $from, $to, $bw),
            'expense-category' => $this->rExpenseCategory($c, $from, $to, $bw),
            'expense-branch'   => $this->rExpenseBranch($c, $from, $to),
            'stock-level'      => $this->rStockLevel($c),
            'low-stock'        => $this->rLowStock($c),
            'stock-movement'   => $this->rStockMovement($c, $from, $to),
            'customer-ledger'  => $this->rCustomerLedger($c, $from, $to),
            'payables-aging'   => $this->rPayablesAging($c),
            'vendor-ledger'    => $this->rVendorLedger($c, $from, $to),
            'tax-summary'      => $this->rTaxSummary($c, $from, $to, $bw),
            'branch-comparison'=> $this->rBranchComparison($c, $from, $to),
            'general-ledger'   => $this->rGeneralLedger($c, $from, $to),
            'payment-received' => $this->rPaymentsReceived($c, $from, $to, $bw),
            default            => [[], [], []],
        };
    }

    // Profit & Loss
    private function rProfitLoss(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Month' => 'month_label', 'Revenue' => 'revenue', 'COGS' => 'cogs',
                 'Gross Profit' => 'gross', 'Expenses' => 'expenses', 'Net Profit' => 'net'];

        $rows = Database::fetchAll(
            "SELECT DATE_FORMAT(invoice_date,'%Y-%m') AS month_label,
                    COALESCE(SUM(total),0) AS revenue, 0 AS cogs, 0 AS gross, 0 AS expenses, 0 AS net
             FROM invoices WHERE company_id=? AND invoice_date BETWEEN ? AND ?
             AND deleted_at IS NULL AND status!='cancelled' {$bw}
             GROUP BY month_label ORDER BY month_label",
            [$c, $from, $to]
        );

        // Attach expenses per month
        $expByMonth = Database::fetchAll(
            "SELECT DATE_FORMAT(expense_date,'%Y-%m') AS m, COALESCE(SUM(amount),0) AS total
             FROM expenses WHERE company_id=? AND expense_date BETWEEN ? AND ? AND deleted_at IS NULL {$bw}
             GROUP BY m", [$c, $from, $to]
        );
        $expMap = array_column($expByMonth, 'total', 'm');

        foreach ($rows as &$row) {
            $row['expenses'] = $expMap[$row['month_label']] ?? 0;
            $row['gross']    = $row['revenue'] - $row['cogs'];
            $row['net']      = $row['revenue'] - $row['expenses'];
        }

        $totalRev  = array_sum(array_column($rows, 'revenue'));
        $totalExp  = array_sum(array_column($rows, 'expenses'));
        $netProfit = $totalRev - $totalExp;
        $summary   = ['Total Revenue' => number_format($totalRev,2), 'Total Expenses' => number_format($totalExp,2),
                      'Net Profit' => number_format($netProfit,2), 'Profit Margin' => ($totalRev>0 ? round($netProfit/$totalRev*100,1).'%' : '0%')];

        return [$cols, $rows, $summary];
    }

    private function rBalanceSheet(int $c): array
    {
        $cols = ['Code' => 'code', 'Account Name' => 'name', 'Type' => 'type', 'Balance' => 'balance'];
        $data = Database::fetchAll(
            "SELECT code, name, type, sub_type, balance FROM accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY type, code",
            [$c]
        );
        $summary = [
            'Total Assets'      => number_format(array_sum(array_column(array_filter($data, fn($r)=>$r['type']==='asset'), 'balance')), 2),
            'Total Liabilities' => number_format(array_sum(array_column(array_filter($data, fn($r)=>$r['type']==='liability'), 'balance')), 2),
            'Total Equity'      => number_format(array_sum(array_column(array_filter($data, fn($r)=>$r['type']==='equity'), 'balance')), 2),
        ];
        return [$cols, $data, $summary];
    }

    private function rTrialBalance(int $c): array
    {
        $cols = ['Code' => 'code', 'Account' => 'name', 'Type' => 'type', 'Debit' => 'debit', 'Credit' => 'credit', 'Balance' => 'balance'];
        $data = Database::fetchAll(
            "SELECT code, name, type,
                    CASE WHEN balance>0 THEN balance ELSE 0 END AS debit,
                    CASE WHEN balance<0 THEN ABS(balance) ELSE 0 END AS credit,
                    balance
             FROM accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY code",
            [$c]
        );
        return [$cols, $data, []];
    }

    private function rCashFlow(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Date' => 'payment_date', 'Type' => 'type', 'Ref' => 'reference', 'Method' => 'method', 'Amount' => 'amount'];
        $data = Database::fetchAll(
            "SELECT payment_date, type, reference, method, amount
             FROM payments WHERE company_id=? AND payment_date BETWEEN ? AND ? AND deleted_at IS NULL {$bw}
             ORDER BY payment_date DESC",
            [$c, $from, $to]
        );
        $in  = array_sum(array_column(array_filter($data, fn($r)=>$r['type']==='received'), 'amount'));
        $out = array_sum(array_column(array_filter($data, fn($r)=>$r['type']==='paid'), 'amount'));
        return [$cols, $data, ['Cash In' => number_format($in,2), 'Cash Out' => number_format($out,2), 'Net Flow' => number_format($in-$out,2)]];
    }

    private function rRevenueCustomer(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Customer' => 'customer_name', 'Invoices' => 'invoice_count', 'Revenue' => 'revenue', 'Paid' => 'paid', 'Outstanding' => 'outstanding'];
        $data = Database::fetchAll(
            "SELECT COALESCE(cu.name,'Unknown') AS customer_name,
                    COUNT(i.id) AS invoice_count, SUM(i.total) AS revenue,
                    SUM(i.amount_paid) AS paid, SUM(i.amount_due) AS outstanding
             FROM invoices i LEFT JOIN customers cu ON cu.id=i.customer_id
             WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL AND i.status!='cancelled' {$bw}
             GROUP BY i.customer_id ORDER BY revenue DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Revenue' => number_format(array_sum(array_column($data,'revenue')),2)]];
    }

    private function rRevenueProduct(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Product' => 'product_name', 'SKU' => 'sku', 'Qty Sold' => 'qty', 'Unit Price' => 'unit_price', 'Revenue' => 'revenue'];
        $data = Database::fetchAll(
            "SELECT COALESCE(p.name,ii.description) AS product_name, p.sku,
                    SUM(ii.quantity) AS qty, AVG(ii.unit_price) AS unit_price, SUM(ii.total) AS revenue
             FROM invoice_items ii
             JOIN invoices i ON i.id=ii.invoice_id
             LEFT JOIN products p ON p.id=ii.product_id
             WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw}
             GROUP BY ii.product_id ORDER BY revenue DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Revenue' => number_format(array_sum(array_column($data,'revenue')),2)]];
    }

    private function rRevenueBranch(int $c, string $from, string $to): array
    {
        $cols = ['Branch' => 'branch_name', 'Code' => 'code', 'Invoices' => 'invoice_count', 'Revenue' => 'revenue', 'Outstanding' => 'outstanding'];
        $data = Database::fetchAll(
            "SELECT b.name AS branch_name, b.code, COUNT(i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS revenue, COALESCE(SUM(i.amount_due),0) AS outstanding
             FROM branches b LEFT JOIN invoices i ON i.branch_id=b.id
                AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL AND i.status!='cancelled'
             WHERE b.company_id=? AND b.deleted_at IS NULL GROUP BY b.id ORDER BY revenue DESC",
            [$from, $to, $c]
        );
        return [$cols, $data, []];
    }

    private function rInvoiceAging(int $c, string $bw): array
    {
        $cols = ['Invoice #' => 'invoice_number', 'Customer' => 'customer_name', 'Due Date' => 'due_date',
                 'Days Overdue' => 'days_overdue', 'Total' => 'total', 'Amount Due' => 'amount_due', 'Bucket' => 'bucket'];
        $data = Database::fetchAll(
            "SELECT i.invoice_number, COALESCE(cu.name,'Unknown') AS customer_name,
                    i.due_date, DATEDIFF(CURDATE(),i.due_date) AS days_overdue,
                    i.total, i.amount_due,
                    CASE WHEN DATEDIFF(CURDATE(),i.due_date)<=0 THEN 'Current'
                         WHEN DATEDIFF(CURDATE(),i.due_date)<=30 THEN '1–30 days'
                         WHEN DATEDIFF(CURDATE(),i.due_date)<=60 THEN '31–60 days'
                         WHEN DATEDIFF(CURDATE(),i.due_date)<=90 THEN '61–90 days'
                         ELSE '90+ days' END AS bucket
             FROM invoices i LEFT JOIN customers cu ON cu.id=i.customer_id
             WHERE i.company_id=? AND i.amount_due>0 AND i.deleted_at IS NULL {$bw}
             ORDER BY days_overdue DESC",
            [$c]
        );
        return [$cols, $data, ['Total Outstanding' => number_format(array_sum(array_column($data,'amount_due')),2)]];
    }

    private function rSalesSummary(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Invoice #' => 'invoice_number', 'Date' => 'invoice_date', 'Customer' => 'customer_name',
                 'Status' => 'status', 'Subtotal' => 'subtotal', 'Tax' => 'tax_amount', 'Total' => 'total', 'Paid' => 'amount_paid', 'Due' => 'amount_due'];
        $data = Database::fetchAll(
            "SELECT i.invoice_number, i.invoice_date, COALESCE(cu.name,'') AS customer_name,
                    i.status, i.subtotal, i.tax_amount, i.total, i.amount_paid, i.amount_due
             FROM invoices i LEFT JOIN customers cu ON cu.id=i.customer_id
             WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw}
             ORDER BY i.invoice_date DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, [
            'Total Invoiced' => number_format(array_sum(array_column($data,'total')),2),
            'Total Collected' => number_format(array_sum(array_column($data,'amount_paid')),2),
            'Total Outstanding' => number_format(array_sum(array_column($data,'amount_due')),2),
        ]];
    }

    private function rExpenseSummary(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Date' => 'expense_date', 'Title' => 'title', 'Category' => 'category_name',
                 'Branch' => 'branch_name', 'Method' => 'payment_method', 'Amount' => 'amount'];
        $data = Database::fetchAll(
            "SELECT e.expense_date, e.title, COALESCE(ec.name,'Uncategorised') AS category_name,
                    COALESCE(b.name,'—') AS branch_name, e.payment_method, e.amount
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id=e.category_id
             LEFT JOIN branches b ON b.id=e.branch_id
             WHERE e.company_id=? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL {$bw}
             ORDER BY e.expense_date DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Expenses' => number_format(array_sum(array_column($data,'amount')),2)]];
    }

    private function rExpenseCategory(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Category' => 'category', 'Count' => 'count', 'Total' => 'total', '% Share' => 'pct'];
        $data = Database::fetchAll(
            "SELECT COALESCE(ec.name,'Uncategorised') AS category,
                    COUNT(*) AS count, SUM(e.amount) AS total
             FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id
             WHERE e.company_id=? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL {$bw}
             GROUP BY e.category_id ORDER BY total DESC",
            [$c, $from, $to]
        );
        $grand = array_sum(array_column($data, 'total')) ?: 1;
        foreach ($data as &$r) $r['pct'] = round($r['total']/$grand*100,1).'%';
        return [$cols, $data, ['Grand Total' => number_format($grand,2)]];
    }

    private function rExpenseBranch(int $c, string $from, string $to): array
    {
        $cols = ['Branch' => 'branch_name', 'Count' => 'count', 'Total' => 'total'];
        $data = Database::fetchAll(
            "SELECT COALESCE(b.name,'No Branch') AS branch_name, COUNT(*) AS count, SUM(e.amount) AS total
             FROM expenses e LEFT JOIN branches b ON b.id=e.branch_id
             WHERE e.company_id=? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL
             GROUP BY e.branch_id ORDER BY total DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, []];
    }

    private function rStockLevel(int $c): array
    {
        $cols = ['SKU' => 'sku', 'Product' => 'name', 'Type' => 'type', 'On Hand' => 'stock_quantity',
                 'Alert Level' => 'stock_alert_qty', 'Sale Price' => 'sale_price', 'Stock Value' => 'stock_value'];
        $data = Database::fetchAll(
            "SELECT sku, name, type, stock_quantity, stock_alert_qty, sale_price,
                    ROUND(stock_quantity * purchase_price, 2) AS stock_value
             FROM products WHERE company_id=? AND deleted_at IS NULL AND track_inventory=1 ORDER BY name",
            [$c]
        );
        return [$cols, $data, ['Total Stock Value' => number_format(array_sum(array_column($data,'stock_value')),2)]];
    }

    private function rLowStock(int $c): array
    {
        $cols = ['SKU' => 'sku', 'Product' => 'name', 'On Hand' => 'stock_quantity', 'Alert Level' => 'stock_alert_qty', 'Shortage' => 'shortage'];
        $data = Database::fetchAll(
            "SELECT sku, name, stock_quantity, stock_alert_qty,
                    (stock_alert_qty - stock_quantity) AS shortage
             FROM products WHERE company_id=? AND deleted_at IS NULL
             AND track_inventory=1 AND stock_quantity <= stock_alert_qty
             ORDER BY shortage DESC",
            [$c]
        );
        return [$cols, $data, ['Products Below Alert' => count($data)]];
    }

    private function rStockMovement(int $c, string $from, string $to): array
    {
        $cols = ['Date' => 'created_at', 'Product' => 'product_name', 'Type' => 'movement_type',
                 'Qty' => 'quantity', 'Ref' => 'reference', 'Notes' => 'notes'];
        $data = Database::fetchAll(
            "SELECT sm.created_at, COALESCE(p.name,'') AS product_name,
                    sm.movement_type, sm.quantity, sm.reference, sm.notes
             FROM stock_movements sm LEFT JOIN products p ON p.id=sm.product_id
             WHERE sm.company_id=? AND DATE(sm.created_at) BETWEEN ? AND ?
             ORDER BY sm.created_at DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, []];
    }

    private function rCustomerLedger(int $c, string $from, string $to): array
    {
        $cols = ['Customer'=>'customer_name','Invoice #'=>'invoice_number','Date'=>'invoice_date',
                 'Due'=>'due_date','Total'=>'total','Paid'=>'amount_paid','Balance'=>'amount_due','Status'=>'status'];
        $data = Database::fetchAll(
            "SELECT cu.name AS customer_name, i.invoice_number, i.invoice_date, i.due_date,
                    i.total, i.amount_paid, i.amount_due, i.status
             FROM invoices i JOIN customers cu ON cu.id=i.customer_id
             WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL
             ORDER BY cu.name, i.invoice_date",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Receivable' => number_format(array_sum(array_column($data,'amount_due')),2)]];
    }

    private function rPayablesAging(int $c): array
    {
        $cols = ['Vendor'=>'vendor_name','PO #'=>'po_number','Date'=>'order_date','Due'=>'expected_delivery',
                 'Total'=>'total_amount','Paid'=>'amount_paid','Balance'=>'balance','Bucket'=>'bucket'];
        $data = Database::fetchAll(
            "SELECT COALESCE(v.name,'Unknown') AS vendor_name, po.po_number, po.order_date, po.expected_delivery,
                    po.total_amount, po.amount_paid,
                    (po.total_amount - po.amount_paid) AS balance,
                    CASE WHEN DATEDIFF(CURDATE(),po.expected_delivery)<=0 THEN 'Current'
                         WHEN DATEDIFF(CURDATE(),po.expected_delivery)<=30 THEN '1–30 days'
                         WHEN DATEDIFF(CURDATE(),po.expected_delivery)<=60 THEN '31–60 days'
                         ELSE '60+ days' END AS bucket
             FROM purchase_orders po LEFT JOIN vendors v ON v.id=po.vendor_id
             WHERE po.company_id=? AND po.amount_paid < po.total_amount AND po.deleted_at IS NULL
             ORDER BY bucket, vendor_name",
            [$c]
        );
        return [$cols, $data, ['Total Payable' => number_format(array_sum(array_column($data,'balance')),2)]];
    }

    private function rVendorLedger(int $c, string $from, string $to): array
    {
        $cols = ['Vendor'=>'vendor_name','PO #'=>'po_number','Date'=>'order_date','Total'=>'total_amount','Paid'=>'amount_paid','Balance'=>'balance'];
        $data = Database::fetchAll(
            "SELECT COALESCE(v.name,'Unknown') AS vendor_name, po.po_number, po.order_date,
                    po.total_amount, po.amount_paid, (po.total_amount - po.amount_paid) AS balance
             FROM purchase_orders po LEFT JOIN vendors v ON v.id=po.vendor_id
             WHERE po.company_id=? AND po.order_date BETWEEN ? AND ? AND po.deleted_at IS NULL
             ORDER BY v.name, po.order_date",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Payable' => number_format(array_sum(array_column($data,'balance')),2)]];
    }

    private function rTaxSummary(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Tax Name'=>'tax_name','Rate'=>'rate','Taxable Amount'=>'taxable','Tax Collected'=>'tax_collected','Invoices'=>'invoice_count'];
        $data = Database::fetchAll(
            "SELECT COALESCE(t.name,'Standard') AS tax_name, COALESCE(t.rate,0) AS rate,
                    SUM(ii.total) AS taxable, SUM(ii.tax_amount) AS tax_collected, COUNT(DISTINCT i.id) AS invoice_count
             FROM invoice_items ii
             JOIN invoices i ON i.id=ii.invoice_id
             LEFT JOIN taxes t ON t.id=ii.tax_id
             WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw}
             GROUP BY ii.tax_id ORDER BY tax_collected DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Tax Collected' => number_format(array_sum(array_column($data,'tax_collected')),2)]];
    }

    private function rBranchComparison(int $c, string $from, string $to): array
    {
        $cols = ['Branch'=>'branch_name','Revenue'=>'revenue','Expenses'=>'expenses','Net'=>'net','Invoices'=>'invoice_count','Outstanding'=>'outstanding'];
        $rows = Database::fetchAll(
            "SELECT b.name AS branch_name,
                    COALESCE(SUM(i.total),0) AS revenue, COALESCE(SUM(i.amount_due),0) AS outstanding,
                    COUNT(DISTINCT i.id) AS invoice_count
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id=b.id AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL
             WHERE b.company_id=? AND b.deleted_at IS NULL GROUP BY b.id ORDER BY revenue DESC",
            [$from, $to, $c]
        );
        $expMap = Database::fetchAll(
            "SELECT b.name AS branch_name, COALESCE(SUM(e.amount),0) AS expenses
             FROM branches b LEFT JOIN expenses e ON e.branch_id=b.id AND e.expense_date BETWEEN ? AND ?
             WHERE b.company_id=? GROUP BY b.id",
            [$from, $to, $c]
        );
        $em = array_column($expMap, 'expenses', 'branch_name');
        foreach ($rows as &$r) {
            $r['expenses'] = $em[$r['branch_name']] ?? 0;
            $r['net']      = $r['revenue'] - $r['expenses'];
        }
        return [$cols, $rows, []];
    }

    private function rGeneralLedger(int $c, string $from, string $to): array
    {
        $cols = ['Date'=>'entry_date','Ref'=>'reference','Account'=>'account_name','Debit'=>'debit','Credit'=>'credit','Balance'=>'running_balance'];
        $data = Database::fetchAll(
            "SELECT je.entry_date, je.reference,
                    COALESCE(a.name,'') AS account_name, jl.debit, jl.credit, 0 AS running_balance
             FROM journal_lines jl
             JOIN journal_entries je ON je.id=jl.journal_entry_id
             LEFT JOIN accounts a ON a.id=jl.account_id
             WHERE je.company_id=? AND je.entry_date BETWEEN ? AND ?
             ORDER BY je.entry_date, je.id",
            [$c, $from, $to]
        );
        return [$cols, $data, []];
    }

    private function rPaymentsReceived(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Date'=>'payment_date','Invoice #'=>'invoice_number','Customer'=>'customer_name',
                 'Method'=>'method','Reference'=>'reference','Amount'=>'amount'];
        $data = Database::fetchAll(
            "SELECT p.payment_date, COALESCE(i.invoice_number,'') AS invoice_number,
                    COALESCE(cu.name,'') AS customer_name, p.method, p.reference, p.amount
             FROM payments p
             LEFT JOIN invoices i ON i.id=p.invoice_id
             LEFT JOIN customers cu ON cu.id=p.customer_id
             WHERE p.company_id=? AND p.payment_date BETWEEN ? AND ? AND p.type='received' {$bw}
             ORDER BY p.payment_date DESC",
            [$c, $from, $to]
        );
        return [$cols, $data, ['Total Received' => number_format(array_sum(array_column($data,'amount')),2)]];
    }

    // ── Export dispatcher ───────────────────────────────────────────────────
    private function exportReport(string $format, array $columns, array $data, string $title, string $from, string $to, string $filename, array $summary): void
    {
        $headers   = array_keys($columns);
        $fieldKeys = array_values($columns);

        $flat = array_map(function ($row) use ($fieldKeys) {
            $out = [];
            foreach ($fieldKeys as $key) {
                $out[] = $row[$key] ?? '';
            }
            return $out;
        }, $data);

        switch ($format) {
            case 'csv':
                ExportService::csv($flat, $headers, $filename);
                break;
            case 'excel':
                ExportService::excel($flat, $headers, $filename, $title);
                break;
            case 'json':
                ExportService::json($data, ['report' => $title, 'period' => "{$from} to {$to}"], $filename);
                break;
            case 'pdf':
                ExportService::pdf(
                    $title,
                    ExportService::buildReportHtml($title, $columns, $data, $summary, "{$from} to {$to}"),
                    $filename,
                    count($columns) > 6 ? 'L' : 'P'
                );
                break;
            default:
                break;
        }
    }

}
