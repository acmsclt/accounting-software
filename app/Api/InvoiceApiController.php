<?php
// app/Api/InvoiceApiController.php

namespace App\Api;

use App\Core\ApiController;
use App\Core\Webhook;
use App\Core\Database;
use App\Models\Invoice;

class InvoiceApiController extends ApiController
{
    /** GET /api/invoices */
    public function index(): void
    {
        $this->requireAuth();
        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginationParams();

        $filters = array_filter([
            'status'      => $_GET['status']      ?? '',
            'customer_id' => $_GET['customer_id'] ?? '',
            'branch_id'   => $_GET['branch_id']   ?? '',
            'date_from'   => $_GET['date_from']   ?? '',
            'date_to'     => $_GET['date_to']     ?? '',
            'search'      => $_GET['search']      ?? '',
        ]);

        $invoices = Invoice::all($this->companyId, $filters, $perPage, $offset);
        $total    = Invoice::count($this->companyId, $filters);
        $this->paginatedResponse($invoices, $total, $page, $perPage);
    }

    /** POST /api/invoices */
    public function store(): void
    {
        $this->requireAuth();
        $body   = $this->body();
        $errors = $this->validate($body, [
            'customer_id'  => 'required|numeric',
            'invoice_date' => 'required',
        ]);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);

        $items = $body['items'] ?? [];
        if (empty($items)) $this->error('At least one item is required.', 422);

        $data = [
            'company_id'    => $this->companyId,
            'branch_id'     => $body['branch_id'] ?? null,
            'customer_id'   => (int)$body['customer_id'],
            'user_id'       => (int)($this->jwtUser['sub'] ?? 0),
            'currency'      => $body['currency'] ?? 'USD',
            'exchange_rate' => (float)($body['exchange_rate'] ?? 1),
            'invoice_date'  => $body['invoice_date'],
            'due_date'      => $body['due_date'] ?? null,
            'status'        => 'draft',
            'discount_type' => $body['discount_type'] ?? null,
            'discount_value'=> (float)($body['discount_value'] ?? 0),
            'notes'         => $body['notes'] ?? '',
            'terms'         => $body['terms'] ?? '',
        ];

        $id      = Invoice::create($data, $items);
        $invoice = Invoice::findById($id, $this->companyId);
        Webhook::dispatch($this->companyId, 'invoice.created', $invoice);

        $this->created(['invoice' => $invoice, 'items' => Invoice::items($id)], 'Invoice created.');
    }

    /** GET /api/invoices/{id} */
    public function show(string $id): void
    {
        $this->requireAuth();
        $invoice = Invoice::findById((int)$id, $this->companyId);
        if (!$invoice) $this->notFound();

        $this->success([
            'invoice' => $invoice,
            'items'   => Invoice::items((int)$id),
            'payments' => Database::fetchAll(
                "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC",
                [$id]
            ),
        ]);
    }

    /** POST /api/invoices/{id}/payment */
    public function payment(string $id): void
    {
        $this->requireAuth();
        $invoice = Invoice::findById((int)$id, $this->companyId);
        if (!$invoice) $this->notFound();

        $body   = $this->body();
        $errors = $this->validate($body, ['amount' => 'required|numeric']);
        if (!empty($errors)) $this->error('Validation failed.', 422, $errors);

        $amount = (float)$body['amount'];
        if ($amount <= 0) $this->error('Amount must be positive.');
        if ($amount > $invoice['amount_due']) $this->error('Amount exceeds balance due.');

        Database::insert('payments', [
            'company_id'   => $this->companyId,
            'branch_id'    => $invoice['branch_id'],
            'invoice_id'   => $invoice['id'],
            'customer_id'  => $invoice['customer_id'],
            'user_id'      => (int)($this->jwtUser['sub'] ?? 0),
            'type'         => 'received',
            'amount'       => $amount,
            'currency'     => $body['currency'] ?? $invoice['currency'],
            'payment_date' => $body['payment_date'] ?? date('Y-m-d'),
            'method'       => $body['method'] ?? 'bank_transfer',
            'reference'    => $body['reference'] ?? '',
            'status'       => 'completed',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Invoice::applyPayment((int)$id, $amount);
        $updated = Invoice::findById((int)$id, $this->companyId);
        Webhook::dispatch($this->companyId, 'payment.received', ['invoice_id' => (int)$id, 'amount' => $amount]);

        $this->success($updated, 'Payment recorded.');
    }
}
