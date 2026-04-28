<?php
/**
 * Helpers de CSRF.
 *
 *   csrf_token()    -> string token (lo crea si no existe).
 *   csrf_field()    -> string '<input type="hidden" ...>' listo para imprimir.
 *   csrf_validate() -> aborta con 403 si el token POST no coincide.
 *   csrf_check()    -> bool, no imprime nada (uso programatico).
 *
 * Reusa la implementacion canonica de la clase Auth para no duplicar fuente
 * de verdad. El nombre del campo se rige por la constante CSRF_TOKEN_NAME.
 */

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (class_exists('Auth')) {
            return Auth::csrfToken();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $name  = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        $token = csrf_token();
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
             . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(): bool
    {
        $name  = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        $token = $_POST[$name] ?? '';
        if (class_exists('Auth')) {
            return Auth::verifyCsrf($token);
        }
        if (empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(): void
    {
        if (csrf_check()) return;

        http_response_code(403);
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: text/html; charset=utf-8');
        }
        $view = defined('VIEWS_PATH') ? VIEWS_PATH . '/errors/403.php' : null;
        if ($view && is_file($view)) {
            require $view;
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>403</title>'
               . '<h1>Token de seguridad invalido</h1>'
               . '<p>Recarga la pagina e intenta nuevamente.</p>';
        }
        exit;
    }
}
