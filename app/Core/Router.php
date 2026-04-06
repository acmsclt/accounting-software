<?php
// app/Core/Router.php — URL Router

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function get(string $uri, array|string $action, array $middleware = []): void
    {
        $this->routes[] = ['method' => 'GET', 'uri' => $uri, 'action' => $action, 'middleware' => $middleware];
    }

    public function post(string $uri, array|string $action, array $middleware = []): void
    {
        $this->routes[] = ['method' => 'POST', 'uri' => $uri, 'action' => $action, 'middleware' => $middleware];
    }

    public function put(string $uri, array|string $action, array $middleware = []): void
    {
        $this->routes[] = ['method' => 'PUT', 'uri' => $uri, 'action' => $action, 'middleware' => $middleware];
    }

    public function delete(string $uri, array|string $action, array $middleware = []): void
    {
        $this->routes[] = ['method' => 'DELETE', 'uri' => $uri, 'action' => $action, 'middleware' => $middleware];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Strip base path
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
        $uri      = '/' . ltrim(substr($requestUri, strlen($basePath)), '/');
        $uri      = ($uri === '') ? '/' : $uri;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod && !($requestMethod === 'POST' && $this->methodOverride($route['method']))) {
                continue;
            }

            $pattern = $this->buildPattern($route['uri']);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $params = array_values($matches);

                // Run middleware
                foreach ($route['middleware'] as $mw) {
                    $this->runMiddleware($mw);
                }

                // Dispatch action
                $this->callAction($route['action'], $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Endpoint not found.']);
        } else {
            require BASE_PATH . '/views/errors/404.php';
        }
    }

    private function buildPattern(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    private function methodOverride(string $requiredMethod): bool
    {
        return ($_POST['_method'] ?? '') === $requiredMethod;
    }

    private function runMiddleware(string $middleware): void
    {
        match ($middleware) {
            'auth'       => Auth::requireAuth(),
            'api.auth'   => Auth::requireJwt(),
            'admin'      => Auth::requireRole('admin'),
            'superadmin' => Auth::requireRole('super_admin'),
            default      => null,
        };
    }

    private function callAction(array|string $action, array $params): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, $params);
            return;
        }
        [$class, $method] = $action;
        $controller = new $class();
        call_user_func_array([$controller, $method], $params);
    }
}
