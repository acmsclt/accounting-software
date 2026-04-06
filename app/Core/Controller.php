<?php
// app/Core/Controller.php — Base Web Controller

namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = BASE_PATH . '/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            die("View not found: {$view}");
        }

        require $viewPath;
    }

    protected function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }

    protected function json(mixed $data, int $code = 200): never
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function back(): never
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function with(string $key, mixed $value): static
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['flash'][$key] = $value;
        return $this;
    }

    protected function flash(string $key): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
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
                    'email'    => (!filter_var($value, FILTER_VALIDATE_EMAIL)) && ($errors[$field][] = "{$field} must be a valid email."),
                    'min'      => (strlen($value) < (int)$param) && ($errors[$field][] = "{$field} must be at least {$param} characters."),
                    'max'      => (strlen($value) > (int)$param) && ($errors[$field][] = "{$field} must not exceed {$param} characters."),
                    'numeric'  => (!is_numeric($value)) && ($errors[$field][] = "{$field} must be a number."),
                    default    => null,
                };
            }
        }
        return $errors;
    }

    protected function sanitize(mixed $input): mixed
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
    }

    protected function currentUser(): ?array
    {
        return Auth::user();
    }

    protected function companyId(): ?int
    {
        return Auth::companyId();
    }

    protected function paginate(array $items, int $perPage = 20): array
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $total   = count($items);
        $pages   = (int) ceil($total / $perPage);
        $offset  = ($page - 1) * $perPage;
        $sliced  = array_slice($items, $offset, $perPage);

        return [
            'data'         => $sliced,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => $pages,
        ];
    }
}
