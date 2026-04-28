<?php
/**
 * Rutas absolutas del proyecto.
 *
 * Este archivo se carga PRIMERO en public/index.php para que todas las
 * constantes de path queden disponibles antes que cualquier require posterior.
 *
 * Layout fisico esperado:
 *   <BASE_PATH>/
 *     ├── public/                 (webroot, expuesto via Alias /oficios)
 *     ├── app/
 *     │   ├── config/             <-- este archivo vive aqui
 *     │   ├── controllers/
 *     │   ├── core/
 *     │   ├── helpers/
 *     │   ├── models/
 *     │   └── views/
 *     ├── storage/{logs,cache}/   (NO expuesto a la web)
 *     ├── uploads/                (NO expuesto a la web)
 *     └── sql/
 */

if (!defined('APP_PATH')) {
    define('APP_PATH', realpath(__DIR__ . '/..'));
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../..'));
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}
if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', APP_PATH . '/views');
}
if (!defined('CONTROLLERS_PATH')) {
    define('CONTROLLERS_PATH', APP_PATH . '/controllers');
}
if (!defined('MODELS_PATH')) {
    define('MODELS_PATH', APP_PATH . '/models');
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', APP_PATH . '/core');
}
if (!defined('HELPERS_PATH')) {
    define('HELPERS_PATH', APP_PATH . '/helpers');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', APP_PATH . '/config');
}
if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', BASE_PATH . '/uploads');
}
if (!defined('LOG_PATH')) {
    define('LOG_PATH', BASE_PATH . '/storage/logs');
}
if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', BASE_PATH . '/storage/cache');
}
if (!defined('SQL_PATH')) {
    define('SQL_PATH', BASE_PATH . '/sql');
}

// Carga del .env (parser minimalista, sin dependencias).
// Evita reescritura si una variable ya existe en getenv (server-level).
if (!function_exists('respaldo_oficios_load_env')) {
    function respaldo_oficios_load_env(string $envFile): void
    {
        if (!is_file($envFile)) return;
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // Quita comillas envolventes opcionales.
            if (strlen($v) >= 2) {
                $first = $v[0];
                $last  = $v[strlen($v) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $v = substr($v, 1, -1);
                }
            }
            if (getenv($k) === false) {
                putenv("$k=$v");
                $_ENV[$k]    = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
    respaldo_oficios_load_env(BASE_PATH . '/.env');
}

// Asegura directorios runtime con permisos restringidos.
foreach ([LOG_PATH, CACHE_PATH] as $__dir) {
    if (!is_dir($__dir)) {
        @mkdir($__dir, 0750, true);
    }
}
