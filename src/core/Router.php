<?php
/**
 * Router simple basado en PATH_INFO / REQUEST_URI
 */

class Router
{
    private array $routes = [];

    /**
     * Registra una ruta
     * @param string $method GET|POST|ANY
     */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->add('ANY', $pattern, $handler);
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = '/' . trim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            // Convertir patrón con :param a regex
            $pattern = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $route['pattern']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                // Extraer solo parámetros nombrados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        require VIEWS_PATH . '/errors/404.php';
    }
}
