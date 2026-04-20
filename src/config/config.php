<?php
/**
 * Configuración central de la aplicación
 * Carga variables de entorno y define constantes globales
 */

// Zona horaria
$tz = getenv('APP_TIMEZONE') ?: 'America/Mexico_City';
date_default_timezone_set($tz);

// Constantes de aplicación
define('APP_ENV',     getenv('APP_ENV')     ?: 'production');

// APP_DEBUG: en producción se FUERZA a false, pase lo que pase en .env.
// Esto evita que un despliegue accidental deje stack traces expuestos.
$__debugRaw = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
define('APP_DEBUG', APP_ENV === 'production' ? false : $__debugRaw);

define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'));
define('APP_SECRET',  getenv('APP_SECRET')  ?: 'default_insecure_secret_change_me');

// Base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'respaldo_oficios');
define('DB_USER', getenv('DB_USER') ?: 'oficios_user');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Uploads
define('UPLOAD_DIR',      getenv('UPLOAD_DIR')      ?: '/var/www/uploads');
define('UPLOAD_MAX_SIZE', (int)(getenv('UPLOAD_MAX_SIZE') ?: 10485760)); // 10MB

// Sesión
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 7200));
define('SESSION_NAME',     getenv('SESSION_NAME')     ?: 'respaldo_oficios_sess');

// Rutas del proyecto
define('ROOT_PATH',  dirname(__DIR__));
define('VIEWS_PATH', ROOT_PATH . '/views');

// Directorio de logs privado (fuera de webroot). Se crea si no existe.
define('LOG_PATH',   ROOT_PATH . '/storage/logs');
if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0750, true);
}

// Folio de tesorería - prefijo fijo institucional
define('FOLIO_PREFIJO', 'TM/ECA/STIyC');

// Roles
define('ROL_GOD',   1);
define('ROL_ADMIN', 2);
define('ROL_USER',  3);

// Error reporting según entorno
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php_errors.log');
}
