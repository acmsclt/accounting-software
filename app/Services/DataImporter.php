<?php
// app/Services/DataImporter.php
// Executes the actual row-by-row import into the target entity table.

namespace App\Services;

use App\Core\Database;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\Expense;

class DataImporter
{
    private int   $companyId;
    private ?int  $branchId;
    private int   $jobId;
    private array $options;

    public function __construct(int $companyId, ?int $branchId, int $jobId, array $options = [])
    {
        $this->companyId = $companyId;
        $this->branchId  = $branchId;
        $this->jobId     = $jobId;
        $this->options   = $options;
    }

    /**
     * Import rows into the target entity.
     * @param array  $rows          Associative rows with original sheet columns
     * @param array  $columnMapping ['sheet_column' => 'system_field']
     * @param string $entity        Target entity name
     */
    public function import(array $rows, array $columnMapping, string $entity): array
    {
        $success = 0;
        $failed  = 0;
        $skipped = 0;

        Database::update('import_jobs', [
            'status'     => 'importing',
            'total_rows' => count($rows),
            'started_at' => date('Y-m-d H:i:s'),
        ], ['id' => $this->jobId]);

        foreach ($rows as $rowNum => $rawRow) {
            // Remap columns
            $mapped = [];
            foreach ($columnMapping as $sheetCol => $sysField) {
                if ($sysField && isset($rawRow[$sheetCol])) {
                    $mapped[$sysField] = trim($rawRow[$sheetCol]);
                }
            }

            if (empty(array_filter($mapped))) {
                $this->logRow($rowNum + 1, $rawRow, 'skipped', 'Empty row', null);
                $skipped++;
                continue;
            }

            try {
                $entityId = match ($entity) {
                    'customers' => $this->importCustomer($mapped),
                    'vendors'   => $this->importVendor($mapped),
                    'products'  => $this->importProduct($mapped),
                    'expenses'  => $this->importExpense($mapped),
                    'accounts'  => $this->importAccount($mapped),
                    default     => throw new \RuntimeException("Unsupported entity: {$entity}"),
                };

                $this->logRow($rowNum + 1, $rawRow, 'success', null, $entityId);
                $success++;

            } catch (\Throwable $e) {
                $this->logRow($rowNum + 1, $rawRow, 'failed', $e->getMessage(), null);
                $failed++;
            }

            // Update progress every 20 rows
            if (($rowNum + 1) % 20 === 0) {
                Database::update('import_jobs', [
                    'processed_rows' => $rowNum + 1,
                    'success_rows'   => $success,
                    'failed_rows'    => $failed,
                ], ['id' => $this->jobId]);
            }
        }

        Database::update('import_jobs', [
            'status'         => $failed === count($rows) ? 'failed' : 'completed',
            'processed_rows' => count($rows),
            'success_rows'   => $success,
            'failed_rows'    => $failed,
            'completed_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $this->jobId]);

        return compact('success', 'failed', 'skipped');
    }

    // ── Entity importers ────────────────────────────────────────────────────

    private function importCustomer(array $d): int
    {
        $updateExisting = $this->options['update_existing'] ?? false;

        if (!empty($d['email']) && $updateExisting) {
            $existing = Database::fetch(
                "SELECT id FROM customers WHERE company_id = ? AND email = ? AND deleted_at IS NULL",
                [$this->companyId, $d['email']]
            );
            if ($existing) {
                Customer::update($existing['id'], $this->companyId, $this->cleanCustomer($d));
                return $existing['id'];
            }
        }

        if (empty($d['name'])) throw new \RuntimeException("'name' is required.");
        return Customer::create($this->companyId, $this->cleanCustomer($d));
    }

    private function cleanCustomer(array $d): array
    {
        return array_intersect_key($d, array_flip([
            'name','email','phone','company_name','tax_id',
            'address','city','state','country','postal_code',
            'currency','credit_limit','notes',
        ]));
    }

    private function importVendor(array $d): int
    {
        $updateExisting = $this->options['update_existing'] ?? false;

        if (!empty($d['email']) && $updateExisting) {
            $existing = Database::fetch(
                "SELECT id FROM vendors WHERE company_id = ? AND email = ? AND deleted_at IS NULL",
                [$this->companyId, $d['email']]
            );
            if ($existing) {
                Database::update('vendors', $this->cleanVendor($d), ['id' => $existing['id']]);
                return $existing['id'];
            }
        }

        if (empty($d['name'])) throw new \RuntimeException("'name' is required.");
        $d['company_id'] = $this->companyId;
        $d['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('vendors', $this->cleanVendor($d) + ['company_id' => $this->companyId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    private function cleanVendor(array $d): array
    {
        return array_intersect_key($d, array_flip([
            'name','email','phone','company_name','tax_id','address','city','country','currency','notes',
        ]));
    }

    private function importProduct(array $d): int
    {
        if (empty($d['name'])) throw new \RuntimeException("'name' is required.");
        if (empty($d['sku'])) {
            // Auto-generate SKU
            $d['sku'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', $d['name'])) . '-' . substr(uniqid(), -4);
        }

        $existing = Database::fetch(
            "SELECT id FROM products WHERE company_id = ? AND sku = ? AND deleted_at IS NULL",
            [$this->companyId, $d['sku']]
        );

        if ($existing && ($this->options['update_existing'] ?? false)) {
            Product::update($existing['id'], $this->companyId, $this->cleanProduct($d));
            return $existing['id'];
        } elseif ($existing) {
            throw new \RuntimeException("SKU '{$d['sku']}' already exists. Enable 'Update existing' to overwrite.");
        }

        return Product::create($this->companyId, $this->cleanProduct($d));
    }

    private function cleanProduct(array $d): array
    {
        return array_intersect_key($d, array_flip([
            'name','sku','description','type','unit','sale_price','purchase_price','stock_alert_qty','track_inventory',
        ]));
    }

    private function importExpense(array $d): int
    {
        if (empty($d['title']))        throw new \RuntimeException("'title' is required.");
        if (empty($d['amount']))       throw new \RuntimeException("'amount' is required.");
        if (!is_numeric($d['amount'])) throw new \RuntimeException("'amount' must be numeric.");

        $d['company_id']   = $this->companyId;
        $d['branch_id']    = $this->branchId;
        $d['user_id']      = $this->options['user_id'] ?? 0;
        $d['expense_date'] = $d['expense_date'] ?? date('Y-m-d');
        $d['created_at']   = date('Y-m-d H:i:s');

        return Database::insert('expenses', array_intersect_key($d, array_flip([
            'company_id','branch_id','user_id','title','amount','expense_date','payment_method','reference','notes','created_at',
        ])));
    }

    private function importAccount(array $d): int
    {
        if (empty($d['code'])) throw new \RuntimeException("'code' is required.");
        if (empty($d['name'])) throw new \RuntimeException("'name' is required.");

        $valid_types = ['asset','liability','equity','revenue','expense'];
        if (!in_array(strtolower($d['type'] ?? ''), $valid_types)) {
            $d['type'] = 'expense'; // fallback
        }

        $existing = Database::fetch(
            "SELECT id FROM accounts WHERE company_id = ? AND code = ? AND deleted_at IS NULL",
            [$this->companyId, $d['code']]
        );

        if ($existing) {
            Database::update('accounts', ['name' => $d['name'], 'type' => $d['type']], ['id' => $existing['id']]);
            return $existing['id'];
        }

        return Database::insert('accounts', [
            'company_id' => $this->companyId,
            'code'       => $d['code'],
            'name'       => $d['name'],
            'type'       => strtolower($d['type'] ?? 'expense'),
            'sub_type'   => $d['sub_type'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Row logging ─────────────────────────────────────────────────────────

    private function logRow(int $rowNum, array $rawData, string $status, ?string $error, ?int $entityId): void
    {
        Database::insert('import_row_logs', [
            'import_job_id' => $this->jobId,
            'row_number'    => $rowNum,
            'raw_data'      => json_encode($rawData),
            'status'        => $status,
            'error_message' => $error,
            'entity_id'     => $entityId,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
