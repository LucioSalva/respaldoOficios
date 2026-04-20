<?php
/**
 * Configuración de sesiones seguras
 * Llamar ANTES de session_start()
 */

function configurar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Ya iniciada
    }

    // Nombre personalizado
    session_name(SESSION_NAME);

    // Parámetros de cookie seguros
    session_set_cookie_params([
        'lifetime' => 0,          // Cookie de sesión (expira al cerrar browser)
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,      // true si usas HTTPS
        'httponly' => true,       // No accesible por JS
        'samesite' => 'Strict',
    ]);

    // Tiempo máximo de vida en servidor
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_start();

    // Regenerar ID si la sesión es nueva
    if (!isset($_SESSION['_iniciada'])) {
        session_regenerate_id(true);
        $_SESSION['_iniciada']  = true;
        $_SESSION['_iniciada_en'] = time();
    }

    // Expiración por inactividad
    if (isset($_SESSION['_ultima_actividad'])) {
        if ((time() - $_SESSION['_ultima_actividad']) > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
        }
    }
    $_SESSION['_ultima_actividad'] = time();
}
