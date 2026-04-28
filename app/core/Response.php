<?php
/**
 * Utilidades de respuesta HTTP.
 *
 *   Response::json($data, $status=200)
 *   Response::redirect($path)         <- usa redirect_to() (respeta APP_BASE_PATH)
 *   Response::file_download($path, $publicName, $mime='application/octet-stream')
 *   Response::no_cache()              <- aplica cabeceras de no-cache
 *
 * Estos helpers son seguros frente a path traversal en file_download:
 * el caller debe pasar la ruta absoluta que ya resolvio desde la BD; aqui
 * solo se valida que sea archivo regular y no se exponen rutas internas
 * en encabezados.
 */

class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function redirect(string $path): void
    {
        if (function_exists('redirect_to')) {
            redirect_to($path);
        }
        header('Location: ' . $path);
        exit;
    }

    public static function no_cache(): void
    {
        if (headers_sent()) return;
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function file_download(
        string $path,
        string $publicName,
        string $mime = 'application/octet-stream'
    ): void {
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $clean = function_exists('upload_sanitize_filename')
            ? upload_sanitize_filename($publicName)
            : preg_replace('/[^A-Za-z0-9._-]+/', '_', $publicName);

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string)filesize($path));
            header('Content-Disposition: attachment; filename="' . $clean . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
        }
        readfile($path);
        exit;
    }

    public static function file_inline(
        string $path,
        string $publicName,
        string $mime = 'application/pdf'
    ): void {
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $clean = function_exists('upload_sanitize_filename')
            ? upload_sanitize_filename($publicName)
            : preg_replace('/[^A-Za-z0-9._-]+/', '_', $publicName);

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string)filesize($path));
            header('Content-Disposition: inline; filename="' . $clean . '"');
            header('X-Content-Type-Options: nosniff');
        }
        readfile($path);
        exit;
    }
}
