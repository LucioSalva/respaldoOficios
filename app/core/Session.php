<?php
/**
 * Helpers OO sobre la sesion PHP.
 * NO inicia la sesion: eso lo hace configurar_sesion() en app/config/session.php.
 *
 * Sirve como API limpia para login/logout y proteccion contra fixation.
 */

class Session
{
    /** Inicia (si no esta activa) la sesion respetando la configuracion segura. */
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Delega al helper procedural existente para no duplicar config.
            if (function_exists('configurar_sesion')) {
                configurar_sesion();
            } else {
                session_start();
            }
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION ?? []);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Destruye la sesion y la cookie. Equivalente seguro de logout. */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path']     ?? '/',
                'domain'   => $p['domain']   ?? '',
                'secure'   => $p['secure']   ?? false,
                'httponly' => $p['httponly'] ?? true,
                'samesite' => $p['samesite'] ?? 'Strict',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
}
