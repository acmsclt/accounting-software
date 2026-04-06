<?php
namespace App\Controllers;
use App\Core\Controller; use App\Core\Auth; use App\Core\Database;

class VendorController extends Controller
{
    public function index(): void
    {
        $c = $this->companyId();
        $vendors = Database::fetchAll("SELECT * FROM vendors WHERE company_id=? AND deleted_at IS NULL ORDER BY name", [$c]);
        $this->view('vendors.index', compact('vendors'));
    }
    public function create(): void { $this->view('vendors.form', ['vendor' => null]); }
    public function store(): void
    {
        Auth::verifyCsrf(); $c = $this->companyId();
        $data = ['company_id' => $c, 'name' => $this->sanitize($_POST['name']??''), 'email' => $_POST['email']??'',
                 'phone' => $_POST['phone']??'', 'company_name' => $_POST['company_name']??'',
                 'country' => $_POST['country']??'', 'currency' => $_POST['currency']??'USD',
                 'created_at' => date('Y-m-d H:i:s')];
        $errors = $this->validate($data, ['name' => 'required']);
        if (!empty($errors)) $this->with('errors', $errors)->redirect('/vendors/create');
        Database::insert('vendors', $data);
        $this->with('success', 'Vendor created.')->redirect('/vendors');
    }
    public function edit(string $id): void
    {
        $vendor = Database::fetch("SELECT * FROM vendors WHERE id=? AND company_id=?", [(int)$id, $this->companyId()]);
        $this->view('vendors.form', compact('vendor'));
    }
    public function update(string $id): void
    {
        Auth::verifyCsrf();
        Database::update('vendors', ['name' => $this->sanitize($_POST['name']??''), 'email' => $_POST['email']??'',
            'phone' => $_POST['phone']??'', 'country' => $_POST['country']??''], ['id' => (int)$id, 'company_id' => $this->companyId()]);
        $this->with('success', 'Vendor updated.')->redirect('/vendors');
    }
    public function delete(string $id): void
    {
        Auth::verifyCsrf();
        Database::update('vendors', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => (int)$id, 'company_id' => $this->companyId()]);
        $this->with('success', 'Vendor deleted.')->redirect('/vendors');
    }
}
