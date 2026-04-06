<?php
// app/Api/CustomerApiController.php

namespace App\Api;

use App\Core\ApiController;
use App\Core\Webhook;
use App\Models\Customer;

class CustomerApiController extends ApiController
{
    /** GET /api/customers */
    public function index(): void
    {
        $this->requireAuth();
        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginationParams();

        $filters = array_filter([
            'search'  => $_GET['search'] ?? '',
            'country' => $_GET['country'] ?? '',
        ]);

        $customers = Customer::all($this->companyId, $filters, $perPage, $offset);
        $total     = Customer::count($this->companyId, $filters);

        $this->paginatedResponse($customers, $total, $page, $perPage);
    }

    /** POST /api/customers */
    public function store(): void
    {
        $this->requireAuth();
        $body   = $this->body();
        $errors = $this->validate($body, ['name' => 'required|min:2']);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);

        $allowedFields = ['name','email','phone','company_name','tax_id','address','city','state','country','postal_code','currency','credit_limit','notes'];
        $data = array_intersect_key($body, array_flip($allowedFields));

        $id = Customer::create($this->companyId, $data);
        $customer = Customer::findById($id, $this->companyId);
        Webhook::dispatch($this->companyId, 'customer.created', $customer);

        $this->created($customer, 'Customer created.');
    }

    /** GET /api/customers/{id} */
    public function show(string $id): void
    {
        $this->requireAuth();
        $customer = Customer::findById((int)$id, $this->companyId);
        if (!$customer) $this->notFound('Customer not found.');
        $this->success($customer);
    }

    /** PUT /api/customers/{id} */
    public function update(string $id): void
    {
        $this->requireAuth();
        $body     = $this->body();
        $customer = Customer::findById((int)$id, $this->companyId);
        if (!$customer) $this->notFound();

        $allowedFields = ['name','email','phone','company_name','tax_id','address','city','state','country','postal_code','currency','credit_limit','notes','is_active'];
        $data = array_intersect_key($body, array_flip($allowedFields));
        Customer::update((int)$id, $this->companyId, $data);

        $updated = Customer::findById((int)$id, $this->companyId);
        Webhook::dispatch($this->companyId, 'customer.updated', $updated);
        $this->success($updated, 'Customer updated.');
    }

    /** DELETE /api/customers/{id} */
    public function destroy(string $id): void
    {
        $this->requireAuth();
        $customer = Customer::findById((int)$id, $this->companyId);
        if (!$customer) $this->notFound();

        Customer::delete((int)$id, $this->companyId);
        $this->success(null, 'Customer deleted.');
    }

    /** GET /api/customers/{id}/ledger */
    public function ledger(string $id): void
    {
        $this->requireAuth();
        $customer = Customer::findById((int)$id, $this->companyId);
        if (!$customer) $this->notFound();

        $entries = Customer::ledger((int)$id, $this->companyId);
        $this->success(['customer' => $customer, 'ledger' => $entries]);
    }
}
