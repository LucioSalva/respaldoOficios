<?php
/**
 * Controlador de Evidencias PDF
 */

class EvidenciaController extends Controller
{
    private const ALLOWED_MIME = ['application/pdf'];
    private const ALLOWED_EXT  = ['pdf'];

    /** POST /oficios/:id/evidencia */
    public function store(array $params): void
    {
        Auth::requireAuth();
        $this->verifyCsrf();

        $oficio_id = (int)($params['id'] ?? 0);
        $pdo       = Database::pdo();

        // Verificar que el oficio existe
        $stmtOf = $pdo->prepare("SELECT id FROM oficios WHERE id = :id");
        $stmtOf->execute([':id' => $oficio_id]);
        if (!$stmtOf->fetch()) {
            $this->flash('danger', 'Oficio no encontrado.');
            $this->redirect('/oficios');
            return;
        }

        // Validar que se subió un archivo
        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['pdf']['error'] ?? -1;
            $msg = $this->uploadErrorMsg($err);
            $this->flash('danger', "Error al subir el archivo: $msg");
            $this->redirect('/oficios/' . $oficio_id . '#evidencias');
            return;
        }

        $file = $_FILES['pdf'];

        // Validar tamaño
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $maxMB = round(UPLOAD_MAX_SIZE / 1048576, 1);
            $this->flash('danger', "El archivo supera el tamaño máximo permitido ({$maxMB} MB).");
            $this->redirect('/oficios/' . $oficio_id . '#evidencias');
            return;
        }

        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $this->flash('danger', 'Solo se permiten archivos PDF.');
            $this->redirect('/oficios/' . $oficio_id . '#evidencias');
            return;
        }

        // Validar MIME real (no confiar en el del cliente)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeReal, self::ALLOWED_MIME, true)) {
            $this->flash('danger', 'El archivo no es un PDF válido.');
            $this->redirect('/oficios/' . $oficio_id . '#evidencias');
            return;
        }

        // Nombre seguro: no usamos el nombre original en el disco
        $nombreSeguro   = uniqid('pdf_', true) . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $subdirectorio  = UPLOAD_DIR . '/' . $oficio_id;

        if (!is_dir($subdirectorio)) {
            mkdir($subdirectorio, 0750, true);
        }

        $rutaDestino = $subdirectorio . '/' . $nombreSeguro;

        if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
            $this->flash('danger', 'No se pudo guardar el archivo. Contacte al administrador.');
            $this->redirect('/oficios/' . $oficio_id . '#evidencias');
            return;
        }

        // Sanitizar nombre original para guardar en BD (solo para display)
        $nombreOriginalSafe = preg_replace('/[^a-zA-Z0-9._\-áéíóúÁÉÍÓÚüÜñÑ ]/', '_', $file['name']);

        $tipo_evidencia_id = !empty($_POST['tipo_evidencia_id']) ? (int)$_POST['tipo_evidencia_id'] : null;

        $stmt = $pdo->prepare(
            "INSERT INTO evidencias_pdf
                (oficio_id, tipo_evidencia_id, nombre_original, nombre_archivo, ruta,
                 tamano_bytes, mime_type, usuario_subio_id)
             VALUES
                (:oid, :tid, :nombre_orig, :nombre_arch, :ruta,
                 :tamano, :mime, :uid)"
        );
        $stmt->execute([
            ':oid'         => $oficio_id,
            ':tid'         => $tipo_evidencia_id,
            ':nombre_orig' => $nombreOriginalSafe,
            ':nombre_arch' => $nombreSeguro,
            ':ruta'        => $rutaDestino,
            ':tamano'      => $file['size'],
            ':mime'        => $mimeReal,
            ':uid'         => Auth::userId(),
        ]);

        $evId = $pdo->lastInsertId();
        Auth::registrarBitacora(Auth::userId(), 'UPLOAD', 'evidencias_pdf', (int)$evId, [
            'oficio_id'    => $oficio_id,
            'nombre_orig'  => $nombreOriginalSafe,
        ]);

        $this->flash('success', 'Archivo PDF subido correctamente.');
        $this->redirect('/oficios/' . $oficio_id . '#evidencias');
    }

    /** GET /evidencia/:id/descargar */
    public function download(array $params): void
    {
        Auth::requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT ep.*, o.id AS oficio_id
             FROM evidencias_pdf ep
             JOIN oficios o ON o.id = ep.oficio_id
             WHERE ep.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $ev = $stmt->fetch();

        if (!$ev) {
            http_response_code(404);
            echo 'Archivo no encontrado.';
            return;
        }

        // Protección path traversal: verificar que la ruta esté dentro del upload dir
        $realRuta     = realpath($ev['ruta']);
        $realUploadDir = realpath(UPLOAD_DIR);

        if ($realRuta === false || $realUploadDir === false
            || strpos($realRuta, $realUploadDir) !== 0
            || !file_exists($realRuta)
        ) {
            http_response_code(404);
            echo 'Archivo no disponible.';
            return;
        }

        Auth::registrarBitacora(Auth::userId(), 'DOWNLOAD', 'evidencias_pdf', $id, null);

        // Servir el archivo
        $nombreDescarga = $ev['nombre_original'] ?: 'documento.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . addslashes($nombreDescarga) . '"');
        header('Content-Length: ' . filesize($realRuta));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        readfile($realRuta);
        exit;
    }

    /** GET /evidencia/:id/ver */
    public function view_pdf(array $params): void
    {
        Auth::requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT * FROM evidencias_pdf WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $ev = $stmt->fetch();

        if (!$ev) {
            http_response_code(404);
            echo 'Archivo no encontrado.';
            return;
        }

        $realRuta      = realpath($ev['ruta']);
        $realUploadDir = realpath(UPLOAD_DIR);

        if ($realRuta === false || $realUploadDir === false
            || strpos($realRuta, $realUploadDir) !== 0
            || !file_exists($realRuta)
        ) {
            http_response_code(404);
            echo 'Archivo no disponible.';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . addslashes($ev['nombre_original']) . '"');
        header('Content-Length: ' . filesize($realRuta));
        header('Cache-Control: no-cache');
        readfile($realRuta);
        exit;
    }

    private function uploadErrorMsg(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño permitido.',
            UPLOAD_ERR_PARTIAL  => 'El archivo se subió parcialmente.',
            UPLOAD_ERR_NO_FILE  => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'No hay directorio temporal disponible.',
            UPLOAD_ERR_CANT_WRITE => 'No se puede escribir en disco.',
            default             => 'Error desconocido.',
        };
    }
}
