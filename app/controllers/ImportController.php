<?php
/**
 * Controlador del módulo de Importación (admin).
 *
 * Expone vistas para ver el estado de staging de Personal / Vacaciones /
 * Folios Interno Sub / Incidencias / Oficios de Conocimiento, y para
 * listar errores.
 *
 * IMPORTANTE:
 *   - El procesamiento pesado (lectura de Excel) se hace con los scripts
 *     Python en tools/ para aprovechar openpyxl y mantener el webroot ligero.
 *   - Este controlador acepta subir el Excel y guardarlo en UPLOAD_DIR
 *     como referencia histórica (NO procesa el Excel en PHP).
 *   - Todos los endpoints aquí requieren ROL_GOD o ROL_ADMIN.
 */

class ImportController extends Controller
{
    private const EXCEL_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip', // a veces openxml aparece como zip
        'application/octet-stream',
    ];

    /**
     * GET /importar — dashboard de importación.
     */
    public function index(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $pdo = Database::pdo();

        $stats = [
            'personal'             => self::stats($pdo, 'import_personal_raw'),
            'vacaciones'           => self::stats($pdo, 'import_vacaciones_raw'),
            'folios_interno_sub'   => self::stats($pdo, 'import_folios_interno_sub_raw'),
            'incidencias'          => self::stats($pdo, 'import_incidencias_raw'),
            'oficios_conocimiento' => self::stats($pdo, 'import_oficios_conocimiento_raw'),
        ];

        // Últimos errores unificados
        $errores = $pdo->query(
            "SELECT * FROM vw_importacion_errores
              ORDER BY created_at DESC
              LIMIT 100"
        )->fetchAll();

        $this->view('importar/index', compact('stats', 'errores'));
    }

    /**
     * POST /importar/subir — guarda un Excel en UPLOAD_DIR/imports/.
     * Solo registra el archivo para que el admin luego ejecute el script Python.
     * NO procesa datos (eso se hace por CLI).
     */
    public function upload(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'No se recibió el archivo. Verifica el tamaño (máx 12 MB).');
            $this->redirect('/importar');
            return;
        }

        $file = $_FILES['excel'];
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $this->flash('danger', 'El archivo excede el tamaño máximo permitido.');
            $this->redirect('/importar');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            $this->flash('danger', 'Solo se aceptan archivos Excel (.xlsx, .xls).');
            $this->redirect('/importar');
            return;
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeReal, self::EXCEL_MIMES, true)) {
            $this->flash('danger', 'El archivo no parece un Excel válido.');
            $this->redirect('/importar');
            return;
        }

        $nombreSeguro = 'import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $subdir = UPLOAD_DIR . '/imports';
        if (!is_dir($subdir)) {
            mkdir($subdir, 0750, true);
        }
        $destino = $subdir . '/' . $nombreSeguro;
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            $this->flash('danger', 'No se pudo guardar el archivo en el servidor.');
            $this->redirect('/importar');
            return;
        }

        $nombreOrig = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file['name']);

        Auth::registrarBitacora(Auth::userId(), 'UPLOAD', 'imports', null, [
            'original'   => $nombreOrig,
            'guardado'   => $nombreSeguro,
            'size_bytes' => $file['size'],
            'mime'       => $mimeReal,
        ]);

        $this->flash('success', "Archivo '$nombreOrig' recibido. Ahora ejecute el importador Python correspondiente.");
        $this->redirect('/importar');
    }

    // -----------------------------------------------------------------
    /**
     * Devuelve stats completas de una tabla de staging.
     * Cada staging expone: total / procesados / pendientes / creados /
     * actualizados / duplicados / en_revision / con_error.
     *
     * La columna de control varía por tabla: algunas usan accion=CREADO|ACTUALIZADO|OMITIDO,
     * otras usan estado_revision=OK|PENDIENTE_REVISION|ERROR, otras error_mensaje IS NOT NULL.
     * Esta función aplana todas las métricas a una API común.
     */
    private static function stats(PDO $pdo, string $tabla): array
    {
        // Whitelist estricto de nombres de tabla (no viene de input).
        $permitidas = [
            'import_personal_raw',
            'import_vacaciones_raw',
            'import_folios_interno_sub_raw',
            'import_incidencias_raw',
            'import_oficios_conocimiento_raw',
        ];
        if (!in_array($tabla, $permitidas, true)) {
            throw new InvalidArgumentException('Tabla de staging no permitida');
        }

        // Si la tabla todavía no existe (migración pendiente), devolver ceros.
        $exists = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
              WHERE table_name = " . $pdo->quote($tabla)
        )->fetchColumn();
        if ($exists === 0) {
            return self::emptyStats();
        }

        // Columnas soportadas por tabla
        $cols = self::introspectColumns($pdo, $tabla);

        $procesadoCol = in_array('procesado', $cols, true) ? 'procesado' : null;

        $totalSql = "SELECT COUNT(*) FROM $tabla";
        $total = (int)$pdo->query($totalSql)->fetchColumn();

        $procesados = $procesadoCol
            ? (int)$pdo->query("SELECT COUNT(*) FROM $tabla WHERE $procesadoCol = TRUE")->fetchColumn()
            : 0;
        $pendientes = $procesadoCol
            ? (int)$pdo->query("SELECT COUNT(*) FROM $tabla WHERE $procesadoCol = FALSE")->fetchColumn()
            : max(0, $total - $procesados);

        // Acción: CREADO / ACTUALIZADO / OMITIDO / OMITIDO_DUP / OMITIDO_VACIA / ERROR
        $creados = $actualizados = $duplicados = 0;
        if (in_array('accion', $cols, true)) {
            $creados = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE accion = 'CREADO'"
            )->fetchColumn();
            $actualizados = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE accion = 'ACTUALIZADO'"
            )->fetchColumn();
            $duplicados = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE accion IN ('OMITIDO','OMITIDO_DUP','OMITIDO_VACIA')"
            )->fetchColumn();
        }

        // En revisión / con error
        $enRevision = $conError = 0;
        if (in_array('estado_revision', $cols, true)) {
            $enRevision = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE estado_revision = 'PENDIENTE_REVISION'"
            )->fetchColumn();
            $conError = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE estado_revision = 'ERROR'"
            )->fetchColumn();
        } elseif (in_array('error_mensaje', $cols, true)) {
            $conError = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE error_mensaje IS NOT NULL"
            )->fetchColumn();
        } elseif (in_array('error_importacion', $cols, true)) {
            $conError = (int)$pdo->query(
                "SELECT COUNT(*) FROM $tabla WHERE error_importacion IS NOT NULL"
            )->fetchColumn();
        }

        // Totales consolidados
        return [
            'total'         => $total,
            'procesados'    => $procesados,
            'pendientes'    => $pendientes,
            'creados'       => $creados,
            'actualizados'  => $actualizados,
            'duplicados'    => $duplicados,
            'en_revision'   => $enRevision,
            'con_error'     => $conError,
            // Alias legacy (vista vieja leía 'errores')
            'errores'       => $enRevision + $conError,
        ];
    }

    private static function emptyStats(): array
    {
        return [
            'total' => 0, 'procesados' => 0, 'pendientes' => 0,
            'creados' => 0, 'actualizados' => 0, 'duplicados' => 0,
            'en_revision' => 0, 'con_error' => 0, 'errores' => 0,
        ];
    }

    private static function introspectColumns(PDO $pdo, string $tabla): array
    {
        $stmt = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns
              WHERE table_name = :t"
        );
        $stmt->execute([':t' => $tabla]);
        return array_map(fn($r) => $r['column_name'], $stmt->fetchAll());
    }
}
