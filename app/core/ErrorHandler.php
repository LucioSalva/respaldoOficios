<?php
/**
 * Handler global de errores y excepciones.
 *
 * En producción:
 *   - Se loguea detalle técnico en storage/logs/app.log (fuera de webroot).
 *   - Se devuelve HTTP 500 con vista amigable sin stack trace.
 *   - Si hay sesión activa, se registra en bitácora (best-effort).
 *
 * En debug:
 *   - Se deja el comportamiento normal de PHP (display_errors=1).
 */

class ErrorHandler
{
    /** Marca si ya se inicializó para evitar doble registro. */
    private static bool $booted = false;

    public static function register(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        // Respeta el operador @ y error_reporting actual.
        if (!(error_reporting() & $errno)) return false;
        // Convierte errores recuperables a excepción para un único flujo.
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleException(Throwable $e): void
    {
        $incident = self::logIncident($e);
        self::bitacoraBestEffort($e, $incident);
        self::renderResponse($incident, $e);
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) return;
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'] ?? 0, $fatal, true)) return;

        $e = new ErrorException(
            $err['message'] ?? 'Fatal',
            0,
            $err['type'] ?? E_ERROR,
            $err['file'] ?? '',
            $err['line'] ?? 0
        );
        $incident = self::logIncident($e);
        self::bitacoraBestEffort($e, $incident);
        // Si ya se enviaron headers no podemos hacer mucho; intentamos al menos vista.
        if (!headers_sent()) {
            self::renderResponse($incident, $e);
        }
    }

    private static function logIncident(Throwable $e): string
    {
        $incident = bin2hex(random_bytes(6));
        $line = sprintf(
            "[%s] [%s] %s: %s in %s:%d\n%s\n---\n",
            date('c'),
            $incident,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        $logFile = (defined('LOG_PATH') ? LOG_PATH : sys_get_temp_dir()) . '/app.log';
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        // Mantener también error_log estándar.
        error_log('[' . $incident . '] ' . $e->getMessage());
        return $incident;
    }

    private static function bitacoraBestEffort(Throwable $e, string $incident): void
    {
        try {
            if (!class_exists('Auth') || !class_exists('Database')) return;
            if (session_status() !== PHP_SESSION_ACTIVE) return;
            $uid = $_SESSION['user_id'] ?? null;
            Auth::registrarBitacora(
                $uid,
                'ERROR',
                null,
                null,
                [
                    'incident' => $incident,
                    'type'     => get_class($e),
                    'msg'      => mb_substr($e->getMessage(), 0, 300),
                    'uri'      => $_SERVER['REQUEST_URI'] ?? null,
                ]
            );
        } catch (\Throwable $ignored) {
            // Nunca dejar que la bitácora rompa el handler.
        }
    }

    private static function renderResponse(string $incident, Throwable $e): void
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            // En debug, re-lanzar o imprimir con detalle (mantiene DX).
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo "[DEBUG] " . get_class($e) . ': ' . $e->getMessage() . "\n";
            echo $e->getTraceAsString();
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        $view = VIEWS_PATH . '/errors/500.php';
        if (is_file($view)) {
            $incident_id = $incident;
            require $view;
        } else {
            echo '<h1>500 — Error interno</h1>';
            echo '<p>Ocurrió un error. Intente de nuevo o contacte al administrador.</p>';
            echo '<p>Ref: ' . htmlspecialchars($incident) . '</p>';
        }
    }
}
