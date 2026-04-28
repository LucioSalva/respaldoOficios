<?php
/**
 * Helpers de subida de archivos.
 *
 *   upload_detect_mime($tmp)
 *       Devuelve MIME real leido con finfo (nunca confiar en $_FILES['type']).
 *
 *   upload_extension_for_mime($mime)
 *       Mapea MIME conocido a una extension segura. null si no permitido.
 *
 *   upload_sanitize_filename($name)
 *       Saca path traversal, deja [a-z0-9._-], minusculas, max 80 chars.
 *
 *   upload_generate_physical_name($extension)
 *       Genera nombre fisico aleatorio: <uniq>.<ext>. Sin colision con
 *       nombres de usuario; el original se guarda aparte (en BD).
 *
 *   upload_validate($file, $allowedMimes, $maxSize)
 *       Valida $_FILES[x]: error code, tamano, MIME real. Devuelve
 *       ['ok'=>bool,'error'=>string|null,'mime'=>string|null,'ext'=>string|null].
 */

if (!function_exists('upload_detect_mime')) {
    function upload_detect_mime(string $tmpPath): ?string
    {
        if (!is_file($tmpPath)) return null;
        if (!function_exists('finfo_open')) return null;
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if (!$f) return null;
        $mime = @finfo_file($f, $tmpPath) ?: null;
        @finfo_close($f);
        return $mime ?: null;
    }
}

if (!function_exists('upload_extension_for_mime')) {
    function upload_extension_for_mime(string $mime): ?string
    {
        $map = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/pjpeg'     => 'jpg',
            'image/png'       => 'png',
        ];
        $mime = strtolower(trim($mime));
        return $map[$mime] ?? null;
    }
}

if (!function_exists('upload_sanitize_filename')) {
    function upload_sanitize_filename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? '';
        $name = strtolower($name);
        $name = trim($name, '._-');
        if ($name === '') $name = 'archivo';
        if (strlen($name) > 80) {
            $name = substr($name, 0, 80);
        }
        return $name;
    }
}

if (!function_exists('upload_generate_physical_name')) {
    function upload_generate_physical_name(string $extension): string
    {
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension) ?? '';
        $extension = strtolower($extension);
        if ($extension === '') $extension = 'bin';
        $rand = bin2hex(random_bytes(12));
        return date('Ymd') . '_' . $rand . '.' . $extension;
    }
}

if (!function_exists('upload_validate')) {
    /**
     * @param array $file        Item de $_FILES['xxx']
     * @param array $allowedMimes Lista blanca de MIME permitidos
     * @param int   $maxSize     Tamano maximo en bytes
     * @return array{ok:bool,error:?string,mime:?string,ext:?string}
     */
    function upload_validate(array $file, array $allowedMimes, int $maxSize): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            $msg = match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamano maximo permitido.',
                UPLOAD_ERR_PARTIAL                        => 'La subida fue interrumpida.',
                UPLOAD_ERR_NO_FILE                        => 'No se recibio ningun archivo.',
                UPLOAD_ERR_NO_TMP_DIR                     => 'Directorio temporal no disponible.',
                UPLOAD_ERR_CANT_WRITE                     => 'No se pudo escribir el archivo.',
                UPLOAD_ERR_EXTENSION                      => 'Subida rechazada por extension PHP.',
                default                                   => 'Error desconocido en la subida.',
            };
            return ['ok' => false, 'error' => $msg, 'mime' => null, 'ext' => null];
        }

        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['ok' => false, 'error' => 'Archivo invalido.', 'mime' => null, 'ext' => null];
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxSize) {
            return ['ok' => false, 'error' => 'Tamano fuera de rango.', 'mime' => null, 'ext' => null];
        }

        $mime = upload_detect_mime($file['tmp_name']);
        if (!$mime || !in_array($mime, $allowedMimes, true)) {
            return ['ok' => false, 'error' => 'Tipo de archivo no permitido.', 'mime' => $mime, 'ext' => null];
        }
        $ext = upload_extension_for_mime($mime);
        if (!$ext) {
            return ['ok' => false, 'error' => 'Extension no soportada.', 'mime' => $mime, 'ext' => null];
        }
        return ['ok' => true, 'error' => null, 'mime' => $mime, 'ext' => $ext];
    }
}
