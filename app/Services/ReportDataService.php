<?php
// app/Services/ReportDataService.php
// Static shim so the REST API can call report generators without HTTP context.

namespace App\Services;

use App\Core\Database;

class ReportDataService
{
    /**
     * Proxy to the report data generators.
     * Returns [columns[], data[], summary[]]
     */
    public static function generate(
        string $slug,
        int    $companyId,
        string $from,
        string $to,
        ?int   $branchId = null
    ): array {
        $bw = $branchId ? "AND branch_id = {$branchId}" : '';

        return match ($slug) {
            'profit-loss'      => self::rProfitLoss($companyId, $from, $to, $bw),
            'balance-sheet'    => self::rBalanceSheet($companyId),
            'trial-balance'    => self::rTrialBalance($companyId),
            'cash-flow'        => self::rCashFlow($companyId, $from, $to, $bw),
            'revenue-customer' => self::rRevenueCustomer($companyId, $from, $to, $bw),
            'revenue-product'  => self::rRevenueProduct($companyId, $from, $to, $bw),
            'revenue-branch'   => self::rRevenueBranch($companyId, $from, $to),
            'invoice-aging'    => self::rInvoiceAging($companyId, $bw),
            'sales-summary'    => self::rSalesSummary($companyId, $from, $to, $bw),
            'expense-summary'  => self::rExpenseSummary($companyId, $from, $to, $bw),
            'expense-category' => self::rExpenseCategory($companyId, $from, $to, $bw),
            'expense-branch'   => self::rExpenseBranch($companyId, $from, $to),
            'stock-level'      => self::rStockLevel($companyId),
            'low-stock'        => self::rLowStock($companyId),
            'stock-movement'   => self::rStockMovement($companyId, $from, $to),
            'customer-ledger'  => self::rCustomerLedger($companyId, $from, $to),
            'payables-aging'   => self::rPayablesAging($companyId),
            'vendor-ledger'    => self::rVendorLedger($companyId, $from, $to),
            'tax-summary'      => self::rTaxSummary($companyId, $from, $to, $bw),
            'branch-comparison'=> self::rBranchComparison($companyId, $from, $to),
            'general-ledger'   => self::rGeneralLedger($companyId, $from, $to),
            'payment-received' => self::rPaymentsReceived($companyId, $from, $to, $bw),
            default            => [[], [], []],
        };
    }

    private static function rProfitLoss(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Month'=>'month_label','Revenue'=>'revenue','Expenses'=>'expenses','Net Profit'=>'net'];
        $rows = Database::fetchAll(
            "SELECT DATE_FORMAT(invoice_date,'%Y-%m') AS month_label, COALESCE(SUM(total),0) AS revenue, 0 AS expenses, 0 AS net
             FROM invoices WHERE company_id=? AND invoice_date BETWEEN ? AND ? AND deleted_at IS NULL AND status!='cancelled' {$bw}
             GROUP BY month_label ORDER BY month_label",
            [$c, $from, $to]
        );
        $expByMonth = Database::fetchAll(
            "SELECT DATE_FORMAT(expense_date,'%Y-%m') AS m, COALESCE(SUM(amount),0) AS total
             FROM expenses WHERE company_id=? AND expense_date BETWEEN ? AND ? AND deleted_at IS NULL {$bw} GROUP BY m",
            [$c, $from, $to]
        );
        $em = array_column($expByMonth, 'total', 'm');
        foreach ($rows as &$r) { $r['expenses'] = $em[$r['month_label']] ?? 0; $r['net'] = $r['revenue'] - $r['expenses']; }
        $totalRev = array_sum(array_column($rows,'revenue')); $totalExp = array_sum(array_column($rows,'expenses'));
        return [$cols, $rows, ['Total Revenue'=>number_format($totalRev,2),'Total Expenses'=>number_format($totalExp,2),'Net Profit'=>number_format($totalRev-$totalExp,2)]];
    }
    private static function rBalanceSheet(int $c): array
    {
        $cols = ['Code'=>'code','Account Name'=>'name','Type'=>'type','Balance'=>'balance'];
        $data = Database::fetchAll("SELECT code,name,type,balance FROM accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY type,code",[$c]);
        return [$cols,$data,[]];
    }
    private static function rTrialBalance(int $c): array
    {
        $cols = ['Code'=>'code','Account'=>'name','Debit'=>'debit','Credit'=>'credit','Balance'=>'balance'];
        $data = Database::fetchAll("SELECT code,name,CASE WHEN balance>0 THEN balance ELSE 0 END AS debit,CASE WHEN balance<0 THEN ABS(balance) ELSE 0 END AS credit,balance FROM accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY code",[$c]);
        return [$cols,$data,[]];
    }
    private static function rCashFlow(int $c, string $from, string $to, string $bw): array
    {
        $cols = ['Date'=>'payment_date','Type'=>'type','Method'=>'method','Amount'=>'amount'];
        $data = Database::fetchAll("SELECT payment_date,type,method,amount FROM payments WHERE company_id=? AND payment_date BETWEEN ? AND ? {$bw} ORDER BY payment_date DESC",[$c,$from,$to]);
        $in=array_sum(array_column(array_filter($data,fn($r)=>$r['type']==='received'),'amount'));
        $out=array_sum(array_column(array_filter($data,fn($r)=>$r['type']==='paid'),'amount'));
        return [$cols,$data,['Cash In'=>number_format($in,2),'Cash Out'=>number_format($out,2),'Net Flow'=>number_format($in-$out,2)]];
    }
    private static function rRevenueCustomer(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Customer'=>'customer_name','Invoices'=>'invoice_count','Revenue'=>'revenue','Outstanding'=>'outstanding'];
        $data=Database::fetchAll("SELECT COALESCE(cu.name,'Unknown') AS customer_name,COUNT(i.id) AS invoice_count,SUM(i.total) AS revenue,SUM(i.amount_due) AS outstanding FROM invoices i LEFT JOIN customers cu ON cu.id=i.customer_id WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw} GROUP BY i.customer_id ORDER BY revenue DESC",[$c,$from,$to]);
        return [$cols,$data,['Total Revenue'=>number_format(array_sum(array_column($data,'revenue')),2)]];
    }
    private static function rRevenueProduct(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Product'=>'product_name','SKU'=>'sku','Qty'=>'qty','Revenue'=>'revenue'];
        $data=Database::fetchAll("SELECT COALESCE(p.name,ii.description) AS product_name,p.sku,SUM(ii.quantity) AS qty,SUM(ii.total) AS revenue FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id LEFT JOIN products p ON p.id=ii.product_id WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw} GROUP BY ii.product_id ORDER BY revenue DESC",[$c,$from,$to]);
        return [$cols,$data,['Total Revenue'=>number_format(array_sum(array_column($data,'revenue')),2)]];
    }
    private static function rRevenueBranch(int $c,string $from,string $to): array
    {
        $cols=['Branch'=>'branch_name','Revenue'=>'revenue','Outstanding'=>'outstanding'];
        $data=Database::fetchAll("SELECT b.name AS branch_name,COALESCE(SUM(i.total),0) AS revenue,COALESCE(SUM(i.amount_due),0) AS outstanding FROM branches b LEFT JOIN invoices i ON i.branch_id=b.id AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL WHERE b.company_id=? AND b.deleted_at IS NULL GROUP BY b.id ORDER BY revenue DESC",[$from,$to,$c]);
        return [$cols,$data,[]];
    }
    private static function rInvoiceAging(int $c,string $bw): array
    {
        $cols=['Invoice #'=>'invoice_number','Customer'=>'customer_name','Due Date'=>'due_date','Days Overdue'=>'days_overdue','Amount Due'=>'amount_due','Bucket'=>'bucket'];
        $data=Database::fetchAll("SELECT i.invoice_number,COALESCE(cu.name,'Unknown') AS customer_name,i.due_date,DATEDIFF(CURDATE(),i.due_date) AS days_overdue,i.amount_due,CASE WHEN DATEDIFF(CURDATE(),i.due_date)<=0 THEN 'Current' WHEN DATEDIFF(CURDATE(),i.due_date)<=30 THEN '1-30 days' WHEN DATEDIFF(CURDATE(),i.due_date)<=60 THEN '31-60 days' ELSE '60+ days' END AS bucket FROM invoices i LEFT JOIN customers cu ON cu.id=i.customer_id WHERE i.company_id=? AND i.amount_due>0 AND i.deleted_at IS NULL {$bw} ORDER BY days_overdue DESC",[$c]);
        return [$cols,$data,['Total Outstanding'=>number_format(array_sum(array_column($data,'amount_due')),2)]];
    }
    private static function rSalesSummary(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Invoice #'=>'invoice_number','Date'=>'invoice_date','Customer'=>'customer_name','Status'=>'status','Total'=>'total','Paid'=>'amount_paid','Due'=>'amount_due'];
        $data=Database::fetchAll("SELECT i.invoice_number,i.invoice_date,COALESCE(cu.name,'') AS customer_name,i.status,i.total,i.amount_paid,i.amount_due FROM invoices i LEFT JOIN customers cu ON cu.id=i.customer_id WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw} ORDER BY i.invoice_date DESC",[$c,$from,$to]);
        return [$cols,$data,['Total Invoiced'=>number_format(array_sum(array_column($data,'total')),2),'Outstanding'=>number_format(array_sum(array_column($data,'amount_due')),2)]];
    }
    private static function rExpenseSummary(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Date'=>'expense_date','Title'=>'title','Category'=>'category_name','Amount'=>'amount'];
        $data=Database::fetchAll("SELECT e.expense_date,e.title,COALESCE(ec.name,'Uncategorised') AS category_name,e.amount FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id WHERE e.company_id=? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL {$bw} ORDER BY e.expense_date DESC",[$c,$from,$to]);
        return [$cols,$data,['Total'=>number_format(array_sum(array_column($data,'amount')),2)]];
    }
    private static function rExpenseCategory(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Category'=>'category','Count'=>'count','Total'=>'total','%'=>'pct'];
        $data=Database::fetchAll("SELECT COALESCE(ec.name,'Uncategorised') AS category,COUNT(*) AS count,SUM(e.amount) AS total FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id WHERE e.company_id=? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL {$bw} GROUP BY e.category_id ORDER BY total DESC",[$c,$from,$to]);
        $grand=array_sum(array_column($data,'total'))?:1; foreach($data as &$r) $r['pct']=round($r['total']/$grand*100,1).'%';
        return [$cols,$data,['Grand Total'=>number_format($grand,2)]];
    }
    private static function rExpenseBranch(int $c,string $from,string $to): array
    {
        $cols=['Branch'=>'branch_name','Count'=>'count','Total'=>'total'];
        $data=Database::fetchAll("SELECT COALESCE(b.name,'No Branch') AS branch_name,COUNT(*) AS count,SUM(e.amount) AS total FROM expenses e LEFT JOIN branches b ON b.id=e.branch_id WHERE e.company_id=? AND e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL GROUP BY e.branch_id ORDER BY total DESC",[$c,$from,$to]);
        return [$cols,$data,[]];
    }
    private static function rStockLevel(int $c): array
    {
        $cols=['SKU'=>'sku','Product'=>'name','On Hand'=>'stock_quantity','Alert'=>'stock_alert_qty','Sale Price'=>'sale_price'];
        $data=Database::fetchAll("SELECT sku,name,stock_quantity,stock_alert_qty,sale_price FROM products WHERE company_id=? AND deleted_at IS NULL AND track_inventory=1 ORDER BY name",[$c]);
        return [$cols,$data,[]];
    }
    private static function rLowStock(int $c): array
    {
        $cols=['SKU'=>'sku','Product'=>'name','On Hand'=>'stock_quantity','Alert'=>'stock_alert_qty'];
        $data=Database::fetchAll("SELECT sku,name,stock_quantity,stock_alert_qty FROM products WHERE company_id=? AND deleted_at IS NULL AND track_inventory=1 AND stock_quantity<=stock_alert_qty ORDER BY stock_quantity",[$c]);
        return [$cols,$data,['Products Below Alert'=>count($data)]];
    }
    private static function rStockMovement(int $c,string $from,string $to): array
    {
        $cols=['Date'=>'created_at','Product'=>'product_name','Type'=>'movement_type','Qty'=>'quantity'];
        $data=Database::fetchAll("SELECT sm.created_at,COALESCE(p.name,'') AS product_name,sm.movement_type,sm.quantity FROM stock_movements sm LEFT JOIN products p ON p.id=sm.product_id WHERE sm.company_id=? AND DATE(sm.created_at) BETWEEN ? AND ? ORDER BY sm.created_at DESC",[$c,$from,$to]);
        return [$cols,$data,[]];
    }
    private static function rCustomerLedger(int $c,string $from,string $to): array
    {
        $cols=['Customer'=>'customer_name','Invoice #'=>'invoice_number','Date'=>'invoice_date','Total'=>'total','Paid'=>'amount_paid','Due'=>'amount_due','Status'=>'status'];
        $data=Database::fetchAll("SELECT cu.name AS customer_name,i.invoice_number,i.invoice_date,i.total,i.amount_paid,i.amount_due,i.status FROM invoices i JOIN customers cu ON cu.id=i.customer_id WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL ORDER BY cu.name,i.invoice_date",[$c,$from,$to]);
        return [$cols,$data,['Total Receivable'=>number_format(array_sum(array_column($data,'amount_due')),2)]];
    }
    private static function rPayablesAging(int $c): array
    {
        $cols=['Vendor'=>'vendor_name','PO #'=>'po_number','Total'=>'total_amount','Balance'=>'balance','Bucket'=>'bucket'];
        $data=Database::fetchAll("SELECT COALESCE(v.name,'Unknown') AS vendor_name,po.po_number,po.total_amount,(po.total_amount-po.amount_paid) AS balance,CASE WHEN DATEDIFF(CURDATE(),po.expected_delivery)<=0 THEN 'Current' WHEN DATEDIFF(CURDATE(),po.expected_delivery)<=30 THEN '1-30 days' ELSE '30+ days' END AS bucket FROM purchase_orders po LEFT JOIN vendors v ON v.id=po.vendor_id WHERE po.company_id=? AND po.amount_paid<po.total_amount AND po.deleted_at IS NULL ORDER BY bucket",[$c]);
        return [$cols,$data,['Total Payable'=>number_format(array_sum(array_column($data,'balance')),2)]];
    }
    private static function rVendorLedger(int $c,string $from,string $to): array
    {
        $cols=['Vendor'=>'vendor_name','PO #'=>'po_number','Date'=>'order_date','Total'=>'total_amount','Balance'=>'balance'];
        $data=Database::fetchAll("SELECT COALESCE(v.name,'Unknown') AS vendor_name,po.po_number,po.order_date,po.total_amount,(po.total_amount-po.amount_paid) AS balance FROM purchase_orders po LEFT JOIN vendors v ON v.id=po.vendor_id WHERE po.company_id=? AND po.order_date BETWEEN ? AND ? AND po.deleted_at IS NULL ORDER BY v.name",[$c,$from,$to]);
        return [$cols,$data,['Total Payable'=>number_format(array_sum(array_column($data,'balance')),2)]];
    }
    private static function rTaxSummary(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Tax'=>'tax_name','Rate'=>'rate','Taxable Amount'=>'taxable','Tax Collected'=>'tax_collected'];
        $data=Database::fetchAll("SELECT COALESCE(t.name,'Standard') AS tax_name,COALESCE(t.rate,0) AS rate,SUM(ii.total) AS taxable,SUM(ii.tax_amount) AS tax_collected FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id LEFT JOIN taxes t ON t.id=ii.tax_id WHERE i.company_id=? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL {$bw} GROUP BY ii.tax_id ORDER BY tax_collected DESC",[$c,$from,$to]);
        return [$cols,$data,['Total Tax'=>number_format(array_sum(array_column($data,'tax_collected')),2)]];
    }
    private static function rBranchComparison(int $c,string $from,string $to): array
    {
        $cols=['Branch'=>'branch_name','Revenue'=>'revenue','Expenses'=>'expenses','Net'=>'net'];
        $rows=Database::fetchAll("SELECT b.name AS branch_name,COALESCE(SUM(i.total),0) AS revenue FROM branches b LEFT JOIN invoices i ON i.branch_id=b.id AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL WHERE b.company_id=? AND b.deleted_at IS NULL GROUP BY b.id ORDER BY revenue DESC",[$from,$to,$c]);
        $em=array_column(Database::fetchAll("SELECT b.name AS bn,COALESCE(SUM(e.amount),0) AS expenses FROM branches b LEFT JOIN expenses e ON e.branch_id=b.id AND e.expense_date BETWEEN ? AND ? WHERE b.company_id=? GROUP BY b.id",[$from,$to,$c]),'expenses','bn');
        foreach($rows as &$r){$r['expenses']=$em[$r['branch_name']]??0;$r['net']=$r['revenue']-$r['expenses'];}
        return [$cols,$rows,[]];
    }
    private static function rGeneralLedger(int $c,string $from,string $to): array
    {
        $cols=['Date'=>'entry_date','Ref'=>'reference','Account'=>'account_name','Debit'=>'debit','Credit'=>'credit'];
        $data=Database::fetchAll("SELECT je.entry_date,je.reference,COALESCE(a.name,'') AS account_name,jl.debit,jl.credit FROM journal_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id LEFT JOIN accounts a ON a.id=jl.account_id WHERE je.company_id=? AND je.entry_date BETWEEN ? AND ? ORDER BY je.entry_date,je.id",[$c,$from,$to]);
        return [$cols,$data,[]];
    }
    private static function rPaymentsReceived(int $c,string $from,string $to,string $bw): array
    {
        $cols=['Date'=>'payment_date','Invoice #'=>'invoice_number','Customer'=>'customer_name','Method'=>'method','Amount'=>'amount'];
        $data=Database::fetchAll("SELECT p.payment_date,COALESCE(i.invoice_number,'') AS invoice_number,COALESCE(cu.name,'') AS customer_name,p.method,p.amount FROM payments p LEFT JOIN invoices i ON i.id=p.invoice_id LEFT JOIN customers cu ON cu.id=p.customer_id WHERE p.company_id=? AND p.payment_date BETWEEN ? AND ? AND p.type='received' {$bw} ORDER BY p.payment_date DESC",[$c,$from,$to]);
        return [$cols,$data,['Total Received'=>number_format(array_sum(array_column($data,'amount')),2)]];
    }
}
