<?php
namespace App\Controllers;
use App\Core\Controller; use App\Core\Auth; use App\Core\Database;

class SettingsController extends Controller
{
    public function index(): void
    {
        $c = $this->companyId(); $u = $this->currentUser();
        $company    = Database::fetch("SELECT * FROM companies WHERE id=?", [$c]);
        $taxes      = Database::fetchAll("SELECT * FROM taxes WHERE company_id=? AND deleted_at IS NULL", [$c]);
        $currencies = Database::fetchAll("SELECT * FROM currencies WHERE is_active=1 ORDER BY code", []);
        $this->view('settings.index', compact('company','taxes','currencies'));
    }
    public function update(): void
    {
        Auth::verifyCsrf();
        // User profile
        \App\Models\User::update(\App\Core\Auth::id(), ['name' => $this->sanitize($_POST['name']??''),
            'timezone' => $_POST['timezone']??'UTC', 'locale' => $_POST['locale']??'en']);
        $this->with('success', 'Profile updated.')->redirect('/settings');
    }
    public function updateCompany(): void
    {
        Auth::verifyCsrf(); $c = $this->companyId();
        Database::update('companies', ['name' => $this->sanitize($_POST['name']??''),
            'email' => $_POST['email']??'', 'phone' => $_POST['phone']??'',
            'currency' => $_POST['currency']??'USD', 'timezone' => $_POST['timezone']??'UTC',
            'address' => $this->sanitize($_POST['address']??''), 'invoice_prefix' => strtoupper($this->sanitize($_POST['invoice_prefix']??'INV-'))],
            ['id' => $c]);
        $this->with('success', 'Company settings updated.')->redirect('/settings');
    }
}
