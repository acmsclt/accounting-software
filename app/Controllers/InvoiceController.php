<?php
// app/Controllers/InvoiceController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Webhook;
use App\Core\Database;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Branch;

class InvoiceController extends Controller
{
    public function index(): void
    {
        $companyId = $this->companyId();
        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginationParams();
        $filters   = array_filter([
            'status'     => $_GET['status'] ?? '',
            'search'     => $_GET['search'] ?? '',
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
            'branch_id'  => $_GET['branch_id'] ?? '',
        ]);

        $invoices = Invoice::all($companyId, $filters, $perPage, $offset);
        $total    = Invoice::count($companyId, $filters);
        $branches = Branch::allForCompany($companyId);

        $pagination = [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];

        $this->view('invoices.index', compact('invoices', 'pagination', 'filters', 'branches'));
    }

    public function create(): void
    {
        $companyId = $this->companyId();
        $customers = Customer::all($companyId, [], 1000, 0);
        $products  = Product::all($companyId, [], 1000, 0);
        $branches  = Branch::allForCompany($companyId);
        $taxes     = Database::fetchAll("SELECT * FROM taxes WHERE company_id = ? AND is_active = 1", [$companyId]);
        $company   = Database::fetch("SELECT * FROM companies WHERE id = ?", [$companyId]);

        $this->view('invoices.builder', compact('customers', 'products', 'taxes', 'company', 'branches'));
    }

    public function store(): void
    {
        Auth::verifyCsrf();
        $companyId = $this->companyId();
        $user      = $this->currentUser();

        $data = [
            'company_id'     => $companyId,
            'branch_id'      => $_POST['branch_id'] ?? null,
            'customer_id'    => (int)($_POST['customer_id'] ?? 0),
            'user_id'        => $user['id'],
            'currency'       => $this->sanitize($_POST['currency'] ?? 'USD'),
            'exchange_rate'  => (float)($_POST['exchange_rate'] ?? 1),
            'invoice_date'   => $_POST['invoice_date'] ?? date('Y-m-d'),
            'due_date'       => $_POST['due_date'] ?? null,
            'status'         => 'draft',
            'discount_type'  => $_POST['discount_type'] ?? null,
            'discount_value' => (float)($_POST['discount_value'] ?? 0),
            'notes'          => $this->sanitize($_POST['notes'] ?? ''),
            'terms'          => $this->sanitize($_POST['terms'] ?? ''),
        ];

        $items = json_decode($_POST['items'] ?? '[]', true);

        if (empty($items)) {
            $this->with('error', 'Invoice must have at least one item.')->redirect('/invoices/create');
        }

        $invoiceId = Invoice::create($data, $items);

        // Fire webhook
        $invoice = Invoice::findById($invoiceId, $companyId);
        Webhook::dispatch($companyId, 'invoice.created', $invoice);

        if (isset($_POST['send'])) {
            $this->sendInvoice($invoiceId, $companyId);
        }

        $this->with('success', 'Invoice created successfully.')->redirect("/invoices/{$invoiceId}");
    }

    public function show(string $id): void
    {
        $companyId = $this->companyId();
        $invoice   = Invoice::findById((int)$id, $companyId);
        if (!$invoice) $this->redirect('/invoices');

        $items    = Invoice::items((int)$id);
        $payments = Database::fetchAll(
            "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC",
            [(int)$id]
        );

        $this->view('invoices.show', compact('invoice', 'items', 'payments'));
    }

    public function pdf(string $id): void
    {
        $companyId = $this->companyId();
        $invoice   = Invoice::findById((int)$id, $companyId);
        if (!$invoice) $this->redirect('/invoices');

        $items = Invoice::items((int)$id);

        // Use TCPDF or simple HTML template
        $this->generatePdf($invoice, $items);
    }

    public function recordPayment(string $id): void
    {
        Auth::verifyCsrf();
        $companyId = $this->companyId();
        $invoice   = Invoice::findById((int)$id, $companyId);

        if (!$invoice) $this->json(['success' => false, 'message' => 'Invoice not found.'], 404);

        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) $this->json(['success' => false, 'message' => 'Invalid amount.']);

        Database::beginTransaction();
        try {
            Database::insert('payments', [
                'company_id'    => $companyId,
                'branch_id'     => $invoice['branch_id'],
                'invoice_id'    => $invoice['id'],
                'customer_id'   => $invoice['customer_id'],
                'user_id'       => $this->currentUser()['id'],
                'type'          => 'received',
                'amount'        => $amount,
                'currency'      => $invoice['currency'],
                'payment_date'  => $_POST['payment_date'] ?? date('Y-m-d'),
                'method'        => $this->sanitize($_POST['method'] ?? 'bank_transfer'),
                'reference'     => $this->sanitize($_POST['reference'] ?? ''),
                'status'        => 'completed',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            Invoice::applyPayment($invoice['id'], $amount);
            Database::commit();

            Webhook::dispatch($companyId, 'payment.received', [
                'invoice_id' => $invoice['id'],
                'amount'     => $amount,
            ]);

            $this->json(['success' => true, 'message' => 'Payment recorded.']);
        } catch (\Throwable $e) {
            Database::rollback();
            $this->json(['success' => false, 'message' => 'Failed to record payment.'], 500);
        }
    }

    private function paginationParams(): array
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;
        return compact('page', 'perPage', 'offset');
    }

    private function sendInvoice(int $invoiceId, int $companyId): void
    {
        Database::update('invoices', [
            'status'  => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ], ['id' => $invoiceId]);
        // TODO: Email dispatch via PHPMailer
    }

    private function generatePdf(array $invoice, array $items): never
    {
        ob_start();
        require BASE_PATH . '/views/invoices/pdf_template.php';
        $html = ob_get_clean();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.pdf"');

        // TCPDF implementation
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('AccountingPro');
        $pdf->SetTitle($invoice['invoice_number']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($invoice['invoice_number'] . '.pdf', 'D');
        exit;
    }
}
