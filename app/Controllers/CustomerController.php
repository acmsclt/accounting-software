<?php
// app/Controllers/CustomerController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index(): void
    {
        $companyId = $this->companyId();
        $filters   = array_filter(['search' => $_GET['search'] ?? '', 'country' => $_GET['country'] ?? '']);
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 20;
        $offset    = ($page - 1) * $perPage;

        $customers  = Customer::all($companyId, $filters, $perPage, $offset);
        $total      = Customer::count($companyId, $filters);
        $pagination = ['total' => $total, 'per_page' => $perPage, 'current_page' => $page, 'last_page' => (int)ceil($total/$perPage)];

        $this->view('customers.index', compact('customers', 'pagination', 'filters'));
    }

    public function create(): void
    {
        $this->view('customers.form', ['customer' => null]);
    }

    public function store(): void
    {
        Auth::verifyCsrf();
        $companyId = $this->companyId();
        $data      = [
            'name'         => $this->sanitize($_POST['name'] ?? ''),
            'email'        => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'phone'        => $this->sanitize($_POST['phone'] ?? ''),
            'company_name' => $this->sanitize($_POST['company_name'] ?? ''),
            'address'      => $this->sanitize($_POST['address'] ?? ''),
            'city'         => $this->sanitize($_POST['city'] ?? ''),
            'state'        => $this->sanitize($_POST['state'] ?? ''),
            'country'      => $this->sanitize($_POST['country'] ?? ''),
            'currency'     => $this->sanitize($_POST['currency'] ?? 'USD'),
            'notes'        => $this->sanitize($_POST['notes'] ?? ''),
        ];
        $errors = $this->validate($data, ['name' => 'required|min:2']);
        if (!empty($errors)) $this->with('errors', $errors)->redirect('/customers/create');

        $id = Customer::create($companyId, $data);
        \App\Core\Webhook::dispatch($companyId, 'customer.created', Customer::findById($id, $companyId));

        $this->with('success', 'Customer created.')->redirect('/customers');
    }

    public function show(string $id): void
    {
        $companyId = $this->companyId();
        $customer  = Customer::findById((int)$id, $companyId);
        if (!$customer) $this->redirect('/customers');
        $ledger = Customer::ledger((int)$id, $companyId);
        $this->view('customers.show', compact('customer', 'ledger'));
    }

    public function edit(string $id): void
    {
        $customer = Customer::findById((int)$id, $this->companyId());
        if (!$customer) $this->redirect('/customers');
        $this->view('customers.form', compact('customer'));
    }

    public function update(string $id): void
    {
        Auth::verifyCsrf();
        $data = ['name' => $this->sanitize($_POST['name'] ?? ''), 'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? '',
                 'city' => $_POST['city'] ?? '', 'country' => $_POST['country'] ?? '', 'notes' => $_POST['notes'] ?? ''];
        Customer::update((int)$id, $this->companyId(), $data);
        $this->with('success', 'Customer updated.')->redirect('/customers');
    }

    public function delete(string $id): void
    {
        Auth::verifyCsrf();
        Customer::delete((int)$id, $this->companyId());
        $this->with('success', 'Customer deleted.')->redirect('/customers');
    }
}
