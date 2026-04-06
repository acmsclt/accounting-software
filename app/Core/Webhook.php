<?php
// app/Core/Webhook.php — Webhook Dispatch Engine

namespace App\Core;

class Webhook
{
    private static array $config;

    private static function config(): array
    {
        if (empty(self::$config)) {
            self::$config = require BASE_PATH . '/config/app.php';
        }
        return self::$config;
    }

    /**
     * Dispatch a webhook event to all registered endpoints for a company.
     */
    public static function dispatch(int $companyId, string $event, array $data): void
    {
        $endpoints = Database::fetchAll(
            "SELECT * FROM webhook_endpoints
             WHERE company_id = ? AND event = ? AND is_active = 1 AND deleted_at IS NULL",
            [$companyId, $event]
        );

        foreach ($endpoints as $endpoint) {
            self::send($endpoint, $event, $data);
        }
    }

    /**
     * Send webhook payload to a specific endpoint with retry support.
     */
    private static function send(array $endpoint, string $event, array $data): void
    {
        $cfg     = self::config()['webhook'];
        $payload = json_encode([
            'event'      => $event,
            'timestamp'  => date('c'),
            'company_id' => $endpoint['company_id'],
            'data'       => $data,
        ]);

        $signature = self::sign($payload, $endpoint['secret'] ?? $cfg['secret']);

        $attempt  = 0;
        $success  = false;
        $response = '';
        $httpCode = 0;

        while ($attempt < $cfg['max_retries'] && !$success) {
            $attempt++;
            [$httpCode, $response, $error] = self::httpPost(
                $endpoint['url'],
                $payload,
                $signature,
                $cfg['timeout']
            );
            $success = ($httpCode >= 200 && $httpCode < 300);

            if (!$success && $attempt < $cfg['max_retries']) {
                sleep($attempt * 2); // Exponential back-off
            }
        }

        // Log the attempt
        Database::insert('webhook_logs', [
            'webhook_endpoint_id' => $endpoint['id'],
            'event'               => $event,
            'payload'             => $payload,
            'response_code'       => $httpCode,
            'response_body'       => substr($response, 0, 2000),
            'status'              => $success ? 'success' : 'failed',
            'attempts'            => $attempt,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * HTTP POST via cURL.
     */
    private static function httpPost(string $url, string $payload, string $signature, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'X-Webhook-Event: ' . '',
                'User-Agent: AccountingPro-Webhook/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        return [$httpCode, $response ?: '', $error];
    }

    /**
     * Generate HMAC-SHA256 signature for payload verification.
     */
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify an incoming webhook signature (for receiving webhooks from providers).
     */
    public static function verify(string $payload, string $signature, string $secret): bool
    {
        return hash_equals(self::sign($payload, $secret), $signature);
    }

    /**
     * Register a new webhook endpoint for a company.
     */
    public static function register(int $companyId, string $url, string $event, string $secret = ''): int
    {
        return Database::insert('webhook_endpoints', [
            'company_id' => $companyId,
            'url'        => $url,
            'event'      => $event,
            'secret'     => $secret ?: bin2hex(random_bytes(16)),
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * List all events supported by the webhook system.
     */
    public static function events(): array
    {
        return [
            'invoice.created',
            'invoice.updated',
            'invoice.paid',
            'payment.received',
            'customer.created',
            'customer.updated',
            'product.updated',
            'stock.changed',
            'expense.created',
        ];
    }
}
