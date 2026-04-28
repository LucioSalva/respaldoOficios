<?php
/**
 * Configuracion central de la aplicacion.
 * Requiere que paths.php ya este cargado por public/index.php.
 */

if (!defined('APP_PATH')) {
    require_once __DIR__ . '/paths.php';
}

// Zona horaria
$tz = getenv('APP_TIMEZONE') ?: 'America/Mexico_City';
date_default_timezone_set($tz);

// Constantes de aplicacion
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// APP_DEBUG: en produccion se FUERZA a false, pase lo que pase en .env.
$__debugRaw = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
define('APP_DEBUG', APP_ENV === 'production' ? false : $__debugRaw);

define('APP_URL',    rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'));
define('APP_SECRET', getenv('APP_SECRET') ?: 'default_insecure_secret_change_me');

// Ruta base bajo la cual vive la app cuando no es raiz del host.
$__basePathRaw = getenv('APP_BASE_PATH');
if ($__basePathRaw === false || $__basePathRaw === null) {
    $__basePathRaw = '';
}
$__basePathRaw = trim($__basePathRaw);
if ($__basePathRaw !== '' && $__basePathRaw[0] !== '/') {
    $__basePathRaw = '/' . $__basePathRaw;
}
$__basePathRaw = rtrim($__basePathRaw, '/');
define('APP_BASE_PATH', $__basePathRaw);

// Base de datos (defaults para Laragon + Postgres local)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'respaldooficios');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Uploads
define('UPLOAD_DIR',      getenv('UPLOAD_DIR')      ?: UPLOADS_PATH);
define('UPLOAD_MAX_SIZE', (int)(getenv('UPLOAD_MAX_SIZE') ?: 10485760));

// Sesion
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 7200));
define('SESSION_NAME',     getenv('SESSION_NAME') ?: 'respaldo_oficios_sess');

// CSRF
define('CSRF_TOKEN_NAME', getenv('CSRF_TOKEN_NAME') ?: 'csrf_token');

// ROOT_PATH conservado por compatibilidad con codigo existente. Apunta a app/.
define('ROOT_PATH', APP_PATH);

// Folio de tesoreria - prefijo fijo institucional
define('FOLIO_PREFIJO', 'TM/ECA/STIyC');

// Roles
define('ROL_GOD',   1);
define('ROL_ADMIN', 2);
define('ROL_USER',  3);

// Error reporting segun entorno
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php_errors.log');
}
