<?php
// app/Core/ApiController.php — Base REST API Controller

namespace App\Core;

abstract class ApiController
{
    protected ?array $jwtUser = null;
    protected int $companyId  = 0;
    protected int $perPage    = 20;

    public function __construct()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    protected function requireAuth(): void
    {
        Auth::requireJwt();
        $this->jwtUser  = Auth::jwtUser();
        $this->companyId = (int) ($this->jwtUser['company_id'] ?? 0);
    }

    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): never
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function created(mixed $data = null, string $message = 'Created'): never
    {
        $this->success($data, $message, 201);
    }

    protected function error(string $message, int $code = 400, array $errors = []): never
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function notFound(string $message = 'Resource not found.'): never
    {
        $this->error($message, 404);
    }

    protected function forbidden(string $message = 'Forbidden.'): never
    {
        $this->error($message, 403);
    }

    protected function body(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }

        return $_POST;
    }

    protected function paginationParams(): array
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int)($_GET['per_page'] ?? $this->perPage)));
        $offset  = ($page - 1) * $perPage;

        return compact('page', 'perPage', 'offset');
    }

    protected function paginatedResponse(array $items, int $total, int $page, int $perPage): never
    {
        $this->success([
            'items'       => $items,
            'pagination'  => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $ruleList = explode('|', $rule);
            foreach ($ruleList as $r) {
                [$ruleName, $param] = array_pad(explode(':', $r, 2), 2, null);
                $value = $data[$field] ?? null;

                match ($ruleName) {
                    'required' => empty($value) && ($errors[$field][] = "{$field} is required."),
                    'email'    => (!filter_var($value, FILTER_VALIDATE_EMAIL)) && ($errors[$field][] = "Invalid email."),
                    'min'      => (strlen((string)$value) < (int)$param) && ($errors[$field][] = "{$field} min {$param} chars."),
                    'numeric'  => (!is_numeric($value)) && ($errors[$field][] = "{$field} must be numeric."),
                    default    => null,
                };
            }
        }
        return $errors;
    }
}
