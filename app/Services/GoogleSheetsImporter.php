<?php
// app/Services/GoogleSheetsImporter.php
// Fetches a public Google Sheet as CSV without requiring an API key.
// The sheet must be shared as "Anyone with the link can view".

namespace App\Services;

class GoogleSheetsImporter
{
    /**
     * Convert a Google Sheets share URL to its CSV export URL.
     *
     * Supports:
     *  - https://docs.google.com/spreadsheets/d/{ID}/edit#gid={SHEET}
     *  - https://docs.google.com/spreadsheets/d/{ID}/pub?gid={SHEET}
     */
    public static function toCsvUrl(string $shareUrl, int $gid = 0): string
    {
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $shareUrl, $m)) {
            $id = $m[1];
        } else {
            throw new \InvalidArgumentException('Invalid Google Sheets URL. Could not extract spreadsheet ID.');
        }

        // Extract gid from URL if present
        if (preg_match('/[#&?]gid=(\d+)/', $shareUrl, $g)) {
            $gid = (int)$g[1];
        }

        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
    }

    /**
     * Fetch the sheet and return rows as array-of-arrays.
     * Returns [headers, data_rows, total_rows]
     *
     * @throws \RuntimeException on network failure or bad response
     */
    public static function fetch(string $shareUrl, int $gid = 0, int $maxRows = 0): array
    {
        $csvUrl = self::toCsvUrl($shareUrl, $gid);

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 20,
                'follow_location' => true,
                'max_redirects'   => 5,
                'header'          => "User-Agent: AccountingPro-Importer/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($csvUrl, false, $ctx);

        if ($raw === false) {
            throw new \RuntimeException(
                'Could not fetch the Google Sheet. Make sure the sheet is shared as "Anyone with the link can view" and the URL is correct.'
            );
        }

        // Detect and handle BOM
        $raw = ltrim($raw, "\xEF\xBB\xBF");

        return self::parseCsv($raw, $maxRows);
    }

    /**
     * Parse a CSV string from an uploaded file path.
     */
    public static function fetchFromFile(string $filePath, int $maxRows = 0): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException("File not found or not readable: {$filePath}");
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $raw = file_get_contents($filePath);
            $raw = ltrim($raw, "\xEF\xBB\xBF");
            return self::parseCsv($raw, $maxRows);
        }

        throw new \RuntimeException("Unsupported file type: {$ext}. Please upload a .csv file.");
    }

    /**
     * Parse CSV string into [headers[], rows[][], total_count].
     */
    public static function parseCsv(string $raw, int $maxRows = 0): array
    {
        $lines = array_filter(
            explode("\n", str_replace("\r\n", "\n", str_replace("\r", "\n", $raw)))
        );

        if (empty($lines)) {
            throw new \RuntimeException('The sheet appears to be empty.');
        }

        $lines   = array_values($lines);
        $headers = self::parseCsvLine($lines[0]);
        $headers = array_map('trim', $headers);

        $rows      = [];
        $total     = count($lines) - 1; // Exclude header

        $limit = ($maxRows > 0) ? min($maxRows, $total) : $total;

        for ($i = 1; $i <= $limit && $i < count($lines); $i++) {
            $row = self::parseCsvLine($lines[$i]);
            // Pad row to match header count
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $rows[] = array_combine($headers, array_slice($row, 0, count($headers)));
        }

        return [$headers, $rows, $total];
    }

    /**
     * Parse a single CSV line respecting quoted fields.
     */
    private static function parseCsvLine(string $line): array
    {
        $result = [];
        $len    = strlen($line);
        $i      = 0;
        $field  = '';
        $inQ    = false;

        while ($i < $len) {
            $ch = $line[$i];
            if ($inQ) {
                if ($ch === '"') {
                    if ($i + 1 < $len && $line[$i + 1] === '"') {
                        $field .= '"';
                        $i++;
                    } else {
                        $inQ = false;
                    }
                } else {
                    $field .= $ch;
                }
            } else {
                if ($ch === '"') {
                    $inQ = true;
                } elseif ($ch === ',') {
                    $result[] = $field;
                    $field    = '';
                } else {
                    $field .= $ch;
                }
            }
            $i++;
        }
        $result[] = $field;

        return $result;
    }

    /**
     * Auto-detect column mappings by fuzzy-matching sheet headers to system field names.
     */
    public static function autoMap(array $headers, string $entity): array
    {
        $fieldAliases = self::entityFieldAliases($entity);
        $mapping      = [];

        foreach ($headers as $header) {
            $normalised = strtolower(trim(preg_replace('/[\s_\-\.]+/', '_', $header)));
            foreach ($fieldAliases as $systemField => $aliases) {
                if ($normalised === $systemField || in_array($normalised, $aliases, true)) {
                    $mapping[$header] = $systemField;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Known system fields and their common aliases per entity.
     */
    public static function entityFields(string $entity): array
    {
        return match ($entity) {
            'customers' => [
                'name'         => ['Name', 'Customer Name', 'Full Name'],
                'email'        => ['Email', 'Email Address'],
                'phone'        => ['Phone', 'Phone Number', 'Mobile'],
                'company_name' => ['Company', 'Company Name', 'Organisation'],
                'address'      => ['Address', 'Street Address'],
                'city'         => ['City'],
                'state'        => ['State', 'Province'],
                'country'      => ['Country'],
                'postal_code'  => ['ZIP', 'Postal Code', 'Post Code'],
                'currency'     => ['Currency', 'Currency Code'],
                'tax_id'       => ['Tax ID', 'GST', 'VAT Number'],
                'credit_limit' => ['Credit Limit'],
                'notes'        => ['Notes', 'Comments', 'Remarks'],
            ],
            'vendors' => [
                'name'        => ['Name', 'Vendor Name', 'Supplier Name'],
                'email'       => ['Email'],
                'phone'       => ['Phone'],
                'company_name'=> ['Company'],
                'address'     => ['Address'],
                'city'        => ['City'],
                'country'     => ['Country'],
                'currency'    => ['Currency'],
                'tax_id'      => ['Tax ID'],
                'notes'       => ['Notes'],
            ],
            'products' => [
                'name'           => ['Name', 'Product Name', 'Item'],
                'sku'            => ['SKU', 'Code', 'Item Code'],
                'description'    => ['Description', 'Details'],
                'type'           => ['Type'],
                'unit'           => ['Unit', 'UOM'],
                'sale_price'     => ['Price', 'Sale Price', 'Selling Price'],
                'purchase_price' => ['Cost', 'Purchase Price', 'Cost Price'],
                'tax_rate'       => ['Tax %', 'Tax Rate', 'GST %'],
                'stock_alert_qty'=> ['Low Stock', 'Reorder Level'],
                'category'       => ['Category'],
            ],
            'expenses' => [
                'title'        => ['Title', 'Description', 'Expense'],
                'amount'       => ['Amount', 'Cost', 'Total'],
                'expense_date' => ['Date', 'Expense Date'],
                'category'     => ['Category'],
                'payment_method' => ['Method', 'Payment Method'],
                'reference'    => ['Reference', 'Invoice #'],
                'notes'        => ['Notes'],
            ],
            'accounts' => [
                'code'    => ['Code', 'Account Code', 'GL Code'],
                'name'    => ['Name', 'Account Name'],
                'type'    => ['Type', 'Account Type'],
                'sub_type'=> ['Sub Type', 'Subtype'],
            ],
            default => [],
        };
    }

    private static function entityFieldAliases(string $entity): array
    {
        $fields = self::entityFields($entity);
        $result = [];
        foreach ($fields as $sysField => $labels) {
            $aliases = [];
            foreach ($labels as $label) {
                $aliases[] = strtolower(preg_replace('/[\s_\-\.]+/', '_', $label));
            }
            $result[$sysField] = $aliases;
        }
        return $result;
    }
}
