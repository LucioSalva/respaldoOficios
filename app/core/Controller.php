<?php
/**
 * Controlador base
 */

abstract class Controller
{
    /**
     * Renderiza una vista dentro del layout principal.
     *
     * IMPORTANTE: se usa el identificador reservado $__layoutContent para
     * pasar el contenido al layout, evitando colisiones con una variable
     * `$content` que viniera dentro de $data (EXTR_SKIP lo protege pero
     * nunca dentro del layout). El layout debe usar $__layoutContent.
     */
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        // Extraer variables para la vista (sin sobrescribir existentes)
        extract($data, EXTR_SKIP);

        $viewFile = VIEWS_PATH . '/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new RuntimeException("Vista no encontrada: $view");
        }

        // Buffer de contenido de la vista
        ob_start();
        require $viewFile;
        $__layoutContent = ob_get_clean();

        // Renderizar layout (recibe $__layoutContent; también publicamos
        // $content como alias para compatibilidad con layouts existentes,
        // pero el layout oficial ya consume $__layoutContent).
        $content = $__layoutContent;

        $layoutFile = VIEWS_PATH . '/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $__layoutContent;
        }
    }

    /**
     * Redirige a una URL interna respetando APP_BASE_PATH.
     * - Si recibe una ruta absoluta interna ("/dashboard"), antepone el
     *   base path automaticamente via redirect_to().
     * - Si recibe una URL absoluta con esquema ("http://", "https://") o
     *   "//host/...", se respeta tal cual.
     */
    protected function redirect(string $url): void
    {
        $isAbsolute = (
            strpos($url, 'http://')  === 0 ||
            strpos($url, 'https://') === 0 ||
            strpos($url, '//')       === 0
        );
        if ($isAbsolute) {
            header('Location: ' . $url);
            exit;
        }
        redirect_to($url);
    }

    /**
     * Devuelve JSON (para peticiones AJAX)
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Guarda un mensaje flash en sesión
     */
    protected function flash(string $type, string $msg): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
    }

    /**
     * Obtiene y limpia los mensajes flash
     */
    public static function getFlash(): array
    {
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $msgs;
    }

    /**
     * Verifica CSRF y aborta con 403 amigable si falla.
     */
    protected function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($token)) {
            http_response_code(403);
            header('X-Content-Type-Options: nosniff');
            $view = VIEWS_PATH . '/errors/403.php';
            if (is_file($view)) {
                require $view;
            } else {
                echo '<!doctype html><meta charset="utf-8"><title>403</title>'
                   . '<h1>Token de seguridad inválido</h1>'
                   . '<p>Recarga la página e intenta nuevamente.</p>';
            }
            exit;
        }
    }

    /**
     * Sanitizar string de entrada
     */
    protected function clean(?string $val): string
    {
        return trim(htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Sanitizar texto largo (textarea)
     */
    protected function cleanText(?string $val): string
    {
        return trim($val ?? '');
    }
}
