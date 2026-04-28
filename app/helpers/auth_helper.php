<?php
/**
 * Helpers livianos sobre la clase Auth para usar en vistas y controladores.
 * No introducen logica nueva: son wrappers para mejor legibilidad.
 */

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return class_exists('Auth') && Auth::check();
    }
}

if (!function_exists('current_user')) {
    /**
     * Devuelve un arreglo con datos basicos del usuario autenticado o null
     * si no hay sesion. Solo lee de $_SESSION; no consulta la base.
     */
    function current_user(): ?array
    {
        if (!is_logged_in()) return null;
        return [
            'id'       => $_SESSION['user_id']       ?? null,
            'nombre'   => $_SESSION['user_nombre']   ?? null,
            'username' => $_SESSION['user_username'] ?? null,
            'email'    => $_SESSION['user_email']    ?? null,
            'rol_id'   => $_SESSION['user_rol_id']   ?? null,
            'rol'      => $_SESSION['user_rol']      ?? null,
        ];
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return is_logged_in() ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('current_user_rol_id')) {
    function current_user_rol_id(): int
    {
        return is_logged_in() ? (int)$_SESSION['user_rol_id'] : 0;
    }
}

if (!function_exists('has_role')) {
    function has_role($roles): bool
    {
        return class_exists('Auth') ? Auth::hasRole($roles) : false;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (class_exists('Auth')) Auth::requireAuth();
    }
}

if (!function_exists('require_role')) {
    function require_role($roles): void
    {
        if (class_exists('Auth')) Auth::requireRole($roles);
    }
}
