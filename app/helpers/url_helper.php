<?php
/**
 * Helper de URLs para la app servida bajo subcarpeta (Apache Alias /oficios).
 *
 *   base_path()        -> "" o "/oficios"
 *   url_path('/foo')   -> base_path() + '/foo'           (relativo al host)
 *   app_url('/foo')    -> APP_URL + base_path() + '/foo' (URL absoluta)
 *   asset_url('x.css') -> url_path('/assets/x.css') + cache-buster filemtime
 *   redirect_to('/x')  -> Location: url_path('/x'); exit;
 *
 * El cache-buster lee filemtime() en PUBLIC_PATH/assets/<x>.
 */

if (!function_exists('base_path')) {
    function base_path(): string
    {
        return defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
    }
}

if (!function_exists('url_path')) {
    function url_path(string $path = '/'): string
    {
        if ($path === '') {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        $base = base_path();
        return $base === '' ? $path : $base . $path;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = '/'): string
    {
        $host = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        return $host . url_path($path);
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $relative): string
    {
        $relative  = ltrim($relative, '/');
        $assetPath = '/assets/' . $relative;

        $abs = defined('PUBLIC_PATH') ? PUBLIC_PATH . $assetPath : null;
        if ($abs && is_file($abs)) {
            $assetPath .= '?v=' . filemtime($abs);
        }
        return url_path($assetPath);
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        header('Location: ' . url_path($path));
        exit;
    }
}
