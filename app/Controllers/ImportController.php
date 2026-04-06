<?php
// app/Controllers/ImportController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\GoogleSheetsImporter;
use App\Services\DataImporter;
use App\Models\Branch;

class ImportController extends Controller
{
    /** GET /import — List all past import jobs */
    public function index(): void
    {
        $companyId = $this->companyId();
        $jobs = Database::fetchAll(
            "SELECT ij.*, b.name AS branch_name
             FROM import_jobs ij
             LEFT JOIN branches b ON b.id = ij.branch_id
             WHERE ij.company_id = ? AND ij.deleted_at IS NULL
             ORDER BY ij.created_at DESC
             LIMIT 50",
            [$companyId]
        );

        $branches = Branch::allForCompany($companyId);
        $this->view('import.index', compact('jobs', 'branches'));
    }

    /** GET /import/new — Show the import wizard */
    public function create(): void
    {
        $companyId = $this->companyId();
        $branches  = Branch::allForCompany($companyId);
        $entities  = [
            'customers' => 'Customers',
            'vendors'   => 'Vendors',
            'products'  => 'Products',
            'expenses'  => 'Expenses',
            'accounts'  => 'Chart of Accounts',
        ];
        $this->view('import.wizard', compact('branches', 'entities'));
    }

    /**
     * POST /import/preview
     * AJAX: Fetch sheet/file, return [headers, preview_rows, auto_mapping]
     */
    public function preview(): void
    {
        Auth::verifyCsrf();

        $sourceType = $_POST['source_type'] ?? 'google_sheets';
        $entity     = $this->sanitize($_POST['entity'] ?? 'customers');

        try {
            if ($sourceType === 'google_sheets') {
                $url = trim($_POST['sheet_url'] ?? '');
                if (empty($url)) {
                    $this->json(['success' => false, 'message' => 'Google Sheets URL is required.']);
                }
                [$headers, $rows, $total] = GoogleSheetsImporter::fetch($url, 0, 10);

            } elseif ($sourceType === 'csv') {
                $file = $_FILES['csv_file'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    $this->json(['success' => false, 'message' => 'Please upload a valid CSV file.']);
                }

                // Move to temp
                $tmpPath = sys_get_temp_dir() . '/' . uniqid('import_') . '.csv';
                move_uploaded_file($file['tmp_name'], $tmpPath);

                [$headers, $rows, $total] = GoogleSheetsImporter::fetchFromFile($tmpPath, 10);
                unlink($tmpPath);

            } else {
                $this->json(['success' => false, 'message' => 'Unsupported source type.']);
            }

            $autoMap = GoogleSheetsImporter::autoMap($headers, $entity);
            $fields  = GoogleSheetsImporter::entityFields($entity);

            $this->json([
                'success'    => true,
                'headers'    => $headers,
                'rows'       => $rows,
                'total'      => $total,
                'auto_map'   => $autoMap,
                'fields'     => array_keys($fields),
                'field_labels' => $fields,
                'preview_count' => count($rows),
            ]);

        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /import/run
     * AJAX: Execute the full import with the confirmed mapping.
     */
    public function run(): void
    {
        Auth::verifyCsrf();
        $companyId = $this->companyId();
        $user      = $this->currentUser();

        $sourceType    = $_POST['source_type']     ?? 'google_sheets';
        $entity        = $this->sanitize($_POST['entity']        ?? 'customers');
        $columnMapping = json_decode($_POST['column_mapping']    ?? '{}', true);
        $branchId      = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $options       = [
            'update_existing'   => isset($_POST['update_existing']),
            'skip_errors'       => isset($_POST['skip_errors']),
            'user_id'           => $user['id'],
        ];

        if (empty($columnMapping)) {
            $this->json(['success' => false, 'message' => 'No column mapping provided.']);
        }

        try {
            // Create job record first
            $jobId = Database::insert('import_jobs', [
                'company_id'     => $companyId,
                'branch_id'      => $branchId,
                'user_id'        => $user['id'],
                'source_type'    => $sourceType,
                'source_url'     => $sourceType === 'google_sheets' ? trim($_POST['sheet_url'] ?? '') : null,
                'target_entity'  => $entity,
                'column_mapping' => json_encode($columnMapping),
                'options'        => json_encode($options),
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // Re-fetch full data
            if ($sourceType === 'google_sheets') {
                $url = trim($_POST['sheet_url'] ?? '');
                [, $rows,] = GoogleSheetsImporter::fetch($url);

            } elseif ($sourceType === 'csv') {
                $savedPath = $this->handleFileUpload($companyId, $jobId);
                [, $rows,] = GoogleSheetsImporter::fetchFromFile($savedPath);
                Database::update('import_jobs', ['source_file' => $savedPath], ['id' => $jobId]);
            } else {
                $rows = [];
            }

            $importer = new DataImporter($companyId, $branchId, $jobId, $options);
            $result   = $importer->import($rows, $columnMapping, $entity);

            $this->json([
                'success'  => true,
                'job_id'   => $jobId,
                'result'   => $result,
                'message'  => "Import complete: {$result['success']} imported, {$result['failed']} failed.",
            ]);

        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /** GET /import/{id} — Job detail with row-level log */
    public function show(string $id): void
    {
        $companyId = $this->companyId();
        $job = Database::fetch(
            "SELECT * FROM import_jobs WHERE id = ? AND company_id = ?",
            [(int)$id, $companyId]
        );
        if (!$job) $this->redirect('/import');

        $logs = Database::fetchAll(
            "SELECT * FROM import_row_logs WHERE import_job_id = ? ORDER BY row_number LIMIT 500",
            [(int)$id]
        );

        $this->view('import.show', compact('job', 'logs'));
    }

    /** GET /import/{id}/template — Download a blank CSV template */
    public function template(string $entity): void
    {
        $fields = GoogleSheetsImporter::entityFields($entity);
        if (empty($fields)) {
            $this->redirect('/import/new');
        }

        $headers = array_values(array_map(fn($labels) => $labels[0], $fields));

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$entity}_template.csv\"");

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);

        // Add one example row
        $examples = $this->exampleRows($entity);
        foreach ($examples as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function handleFileUpload(int $companyId, int $jobId): string
    {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('No file uploaded.');
        }
        $uploadDir = BASE_PATH . '/storage/imports/' . $companyId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = $jobId . '_' . time() . '.csv';
        $path     = $uploadDir . $filename;
        move_uploaded_file($file['tmp_name'], $path);
        return $path;
    }

    private function exampleRows(string $entity): array
    {
        return match ($entity) {
            'customers' => [['John Doe', 'john@example.com', '+1 555 000 0001', 'Acme Inc', '', '123 Main St', 'New York', 'NY', 'US', '10001', 'USD', '', '']],
            'vendors'   => [['Dell Inc', 'orders@dell.com', '+1 800 000 0001', 'Dell Technologies', '', '1 Dell Way', 'Round Rock', 'US', 'USD', '']],
            'products'  => [['Laptop Pro', 'LAP-001', '15" Pro laptop', 'product', 'unit', '1299.00', '850.00', '10', '5']],
            'expenses'  => [['Office Rent', '3500.00', '2026-01-01', 'Office', 'bank_transfer', 'JAN-RENT', '']],
            'accounts'  => [['5090', 'Miscellaneous Expense', 'expense', 'operating']],
            default     => [],
        };
    }
}
