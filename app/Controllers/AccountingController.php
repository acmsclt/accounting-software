<?php
namespace App\Controllers;
use App\Core\Controller; use App\Core\Database;

class AccountingController extends Controller
{
    public function index(): void    { $this->view('accounting.index', []); }
    public function journal(): void
    {
        $c = $this->companyId();
        $entries = Database::fetchAll("SELECT je.*, u.name AS user_name FROM journal_entries je JOIN users u ON u.id=je.user_id
            WHERE je.company_id=? ORDER BY je.entry_date DESC LIMIT 100", [$c]);
        $this->view('accounting.journal', compact('entries'));
    }
    public function trialBalance(): void
    {
        $c = $this->companyId();
        $accounts = Database::fetchAll("SELECT * FROM accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY code", [$c]);
        $this->view('accounting.trial_balance', compact('accounts'));
    }
    public function profitLoss(): void
    {
        $c = $this->companyId();
        $from = $_GET['from'] ?? date('Y-01-01'); $to = $_GET['to'] ?? date('Y-12-31');
        $revenue  = (float) Database::fetchColumn("SELECT COALESCE(SUM(total),0) FROM invoices WHERE company_id=? AND invoice_date BETWEEN ? AND ? AND deleted_at IS NULL AND status!='cancelled'", [$c,$from,$to]);
        $expenses = (float) Database::fetchColumn("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id=? AND expense_date BETWEEN ? AND ? AND deleted_at IS NULL", [$c,$from,$to]);
        $this->view('accounting.profit_loss', compact('revenue','expenses','from','to'));
    }
    public function balanceSheet(): void
    {
        $c = $this->companyId();
        $accounts = Database::fetchAll("SELECT * FROM accounts WHERE company_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY type,code", [$c]);
        $this->view('accounting.balance_sheet', compact('accounts'));
    }
}
