<?php
namespace App\Controllers;
use App\Core\Controller; use App\Core\Auth; use App\Models\Expense; use App\Models\Branch; use App\Core\Database;

class ExpenseController extends Controller
{
    public function index(): void
    {
        $c = $this->companyId();
        $filters    = array_filter(['branch_id' => $_GET['branch_id']??'', 'category_id' => $_GET['category_id']??'',
                        'date_from' => $_GET['date_from']??'', 'date_to' => $_GET['date_to']??'']);
        $expenses   = Expense::all($c, $filters, 30, 0);
        $branches   = Branch::allForCompany($c);
        $categories = Database::fetchAll("SELECT * FROM expense_categories WHERE company_id=? AND deleted_at IS NULL", [$c]);
        $this->view('expenses.index', compact('expenses', 'branches', 'categories', 'filters'));
    }
    public function create(): void
    {
        $c = $this->companyId();
        $categories = Database::fetchAll("SELECT * FROM expense_categories WHERE company_id=?", [$c]);
        $branches   = Branch::allForCompany($c);
        $this->view('expenses.form', ['expense' => null, 'categories' => $categories, 'branches' => $branches]);
    }
    public function store(): void
    {
        Auth::verifyCsrf(); $c = $this->companyId(); $user = $this->currentUser();
        $data = ['title' => $this->sanitize($_POST['title']??''), 'amount' => (float)($_POST['amount']??0),
                 'expense_date' => $_POST['expense_date']??date('Y-m-d'), 'category_id' => $_POST['category_id']??null,
                 'branch_id' => $_POST['branch_id']??null, 'payment_method' => $_POST['payment_method']??'',
                 'reference' => $_POST['reference']??'', 'notes' => $this->sanitize($_POST['notes']??''),
                 'user_id' => $user['id']];
        Expense::create($c, $data);
        $this->with('success', 'Expense recorded.')->redirect('/expenses');
    }
    public function edit(string $id): void
    {
        $expense = Expense::findById((int)$id, $this->companyId());
        $c = $this->companyId();
        $categories = Database::fetchAll("SELECT * FROM expense_categories WHERE company_id=?", [$c]);
        $branches   = Branch::allForCompany($c);
        $this->view('expenses.form', compact('expense', 'categories', 'branches'));
    }
    public function update(string $id): void
    {
        Auth::verifyCsrf();
        Expense::update((int)$id, $this->companyId(), ['title' => $this->sanitize($_POST['title']??''),
            'amount' => (float)($_POST['amount']??0), 'expense_date' => $_POST['expense_date']??'',
            'notes'  => $this->sanitize($_POST['notes']??'')]);
        $this->with('success', 'Expense updated.')->redirect('/expenses');
    }
    public function delete(string $id): void
    {
        Auth::verifyCsrf(); Expense::delete((int)$id, $this->companyId());
        $this->with('success', 'Expense deleted.')->redirect('/expenses');
    }
}
