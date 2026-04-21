<?php
/**
 * Controlador de Oficios - CRUD completo
 */

class OficioController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /oficios  — listado con filtros y paginación
    // -------------------------------------------------------------------------
    public function index(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();

        $por_pagina = 20;
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $offset     = ($pagina - 1) * $por_pagina;

        // Filtros
        $filtro_folio   = trim($_GET['folio']        ?? '');
        $filtro_asunto  = trim($_GET['asunto']       ?? '');
        $filtro_dep     = (int)($_GET['dependencia'] ?? 0);
        $filtro_area    = (int)($_GET['area_interna']?? 0);
        $filtro_estado  = (int)($_GET['estado']      ?? 0);
        $filtro_tipo    = trim($_GET['tipo']         ?? '');   // '', 'EXTERNO', 'INTERNO'
        $filtro_desde   = trim($_GET['desde']        ?? '');
        $filtro_hasta   = trim($_GET['hasta']        ?? '');

        $where  = ['1=1'];
        $params = [];

        if ($filtro_folio !== '') {
            $where[]  = "(o.folio_tesoreria ILIKE :folio
                          OR o.folio_interno_texto ILIKE :folio
                          OR o.folio_direccion ILIKE :folio)";
            $params[':folio'] = '%' . $filtro_folio . '%';
        }
        if ($filtro_asunto !== '') {
            $where[]  = "o.asunto ILIKE :asunto";
            $params[':asunto'] = '%' . $filtro_asunto . '%';
        }
        if ($filtro_dep > 0) {
            $where[]  = "o.dependencia_id = :dep";
            $params[':dep'] = $filtro_dep;
        }
        if ($filtro_area > 0) {
            $where[]  = "o.area_interna_id = :area";
            $params[':area'] = $filtro_area;
        }
        if ($filtro_estado > 0) {
            $where[]  = "o.estado_id = :estado";
            $params[':estado'] = $filtro_estado;
        }
        if (in_array($filtro_tipo, ['EXTERNO', 'INTERNO', 'CONOCIMIENTO'], true)) {
            $where[] = "t.clave = :tipo";
            $params[':tipo'] = $filtro_tipo;
        }
        if ($filtro_desde !== '') {
            $where[]  = "o.fecha_recepcion >= :desde";
            $params[':desde'] = $filtro_desde;
        }
        if ($filtro_hasta !== '') {
            $where[]  = "o.fecha_recepcion <= :hasta";
            $params[':hasta'] = $filtro_hasta;
        }

        $whereSQL = implode(' AND ', $where);

        // Total para paginación
        $stmtCount = $pdo->prepare(
            "SELECT COUNT(*)
               FROM oficios o
          LEFT JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
              WHERE $whereSQL"
        );
        $stmtCount->execute($params);
        $total    = (int)$stmtCount->fetchColumn();
        $paginas  = (int)ceil($total / $por_pagina);

        // Datos paginados.
        // Desde la migración 09, folio_tesoreria es la fuente de verdad
        // (sin truncamiento). folio_display solo antepone folio_interno_texto
        // para INTERNOs con captura legacy — el resto usa folio_tesoreria.
        $stmt = $pdo->prepare(
            "SELECT o.id,
                    o.folio_tesoreria,
                    o.numero_folio,
                    o.anio_folio,
                    o.folio_interno_texto,
                    o.folio_direccion,
                    o.asunto, o.fecha_recepcion,
                    o.fecha_compromiso, o.created_at,
                    t.clave  AS tipo_oficio_clave,
                    t.nombre AS tipo_oficio_nombre,
                    d.nombre  AS dependencia,
                    ai.nombre AS area_interna,
                    e.nombre AS estado, e.color AS estado_color,
                    CASE
                        WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_interno_texto,'') <> ''
                             THEN o.folio_interno_texto
                        WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_direccion,'') <> ''
                             THEN o.folio_direccion
                        ELSE o.folio_tesoreria
                    END AS folio_display
             FROM oficios o
             LEFT JOIN tipos_oficio   t  ON t.id  = o.tipo_oficio_id
             LEFT JOIN dependencias   d  ON d.id  = o.dependencia_id
             LEFT JOIN areas_internas ai ON ai.id = o.area_interna_id
             LEFT JOIN estados_oficio e  ON e.id  = o.estado_id
             WHERE $whereSQL
             ORDER BY o.created_at DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
        $stmt->execute();
        $oficios = $stmt->fetchAll();

        // Catálogos para filtros
        $dependencias   = $pdo->query("SELECT id, nombre FROM dependencias   WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $areas_internas = $pdo->query("SELECT id, nombre FROM areas_internas WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $estados        = $pdo->query("SELECT id, nombre, color FROM estados_oficio WHERE activo=TRUE ORDER BY orden")->fetchAll();
        $tipos_oficio   = $pdo->query(
            "SELECT id, clave, nombre, requiere_respuesta, requiere_pdf_contestado
               FROM tipos_oficio WHERE activo=TRUE ORDER BY clave"
        )->fetchAll();

        $this->view('oficios/index', compact(
            'oficios', 'dependencias', 'areas_internas', 'estados', 'tipos_oficio',
            'total', 'paginas', 'pagina',
            'filtro_folio', 'filtro_asunto', 'filtro_dep', 'filtro_area',
            'filtro_estado', 'filtro_tipo', 'filtro_desde', 'filtro_hasta'
        ));
    }

    // -------------------------------------------------------------------------
    // GET /oficios/crear
    // -------------------------------------------------------------------------
    public function create(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();

        $dependencias      = $pdo->query("SELECT id, nombre FROM dependencias   WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $areas_internas    = $pdo->query("SELECT id, nombre FROM areas_internas WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $estados           = $pdo->query("SELECT id, nombre FROM estados_oficio WHERE activo=TRUE ORDER BY orden")->fetchAll();
        $tipos_doc         = $pdo->query("SELECT id, nombre FROM tipos_documento WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $tipos_oficio      = $pdo->query(
            "SELECT id, clave, nombre, requiere_respuesta, requiere_pdf_contestado
               FROM tipos_oficio WHERE activo=TRUE ORDER BY clave"
        )->fetchAll();
        $estado_inicial_id = $pdo->query("SELECT id FROM estados_oficio WHERE nombre='Recibido' LIMIT 1")->fetchColumn();
        $anio_actual       = (int)date('Y');

        $this->view('oficios/create', compact(
            'dependencias', 'areas_internas', 'estados', 'tipos_doc',
            'tipos_oficio', 'anio_actual', 'estado_inicial_id'
        ));
    }

    // -------------------------------------------------------------------------
    // POST /oficios/crear
    // -------------------------------------------------------------------------
    public function store(): void
    {
        Auth::requireAuth();
        $this->verifyCsrf();

        $pdo = Database::pdo();

        // ---- Resolver tipo de oficio primero (whitelist estricta) ----
        $tipo_clave_raw = strtoupper(trim((string)($_POST['tipo_oficio'] ?? 'EXTERNO')));
        if (!in_array($tipo_clave_raw, ['EXTERNO', 'INTERNO', 'CONOCIMIENTO'], true)) {
            $tipo_clave_raw = 'EXTERNO';
        }
        $stmtTipo = $pdo->prepare(
            "SELECT id, clave, requiere_respuesta, requiere_pdf_contestado
               FROM tipos_oficio WHERE clave = :c"
        );
        $stmtTipo->execute([':c' => $tipo_clave_raw]);
        $tipo_row = $stmtTipo->fetch();
        if (!$tipo_row) {
            // Fallback duro
            $stmtTipo->execute([':c' => 'EXTERNO']);
            $tipo_row = $stmtTipo->fetch();
            $tipo_clave_raw = 'EXTERNO';
        }
        $tipo_oficio_id  = (int)$tipo_row['id'];
        $es_interno      = ($tipo_clave_raw === 'INTERNO');
        $es_conocimiento = ($tipo_clave_raw === 'CONOCIMIENTO');
        $flag_requiere_respuesta = (bool)$tipo_row['requiere_respuesta'];

        $v = new Validator($_POST);
        $v->required('asunto',        'Asunto')
          ->maxLen('asunto',          500, 'Asunto')
          ->required('fecha_recepcion','Fecha de recibido')
          ->date('fecha_recepcion',   'Fecha de recibido')
          ->date('fecha_oficio_tics', 'Fecha de oficio TICs')
          ->date('fecha_acuse',       'Fecha acuse oficialía');

        // Regla de FK: INTERNO exige área; EXTERNO exige dependencia;
        // CONOCIMIENTO acepta sin dependencia (opcional).
        if ($es_interno) {
            $v->required('area_interna_id', 'Área interna');
        } elseif (!$es_conocimiento) {
            $v->required('dependencia_id',  'Dependencia');
        }

        // Folio de Tesorería:
        //   - EXTERNO: el usuario captura el número manualmente (obligatorio NO).
        //     Si lo deja vacío, el oficio queda como "PENDIENTE DE FOLIO" y se puede
        //     completar después desde la edición.
        //   - INTERNO / CONOCIMIENTO: no usan folio numerado TM/ECA/STIyC; se reserva
        //     un número automáticamente del rango correspondiente para trazabilidad.
        $numero_folio_raw = trim((string)($_POST['numero_folio'] ?? ''));
        $anio_folio_raw   = trim((string)($_POST['anio_folio']   ?? ''));
        $folio_autogenerado = false;
        $folio_pendiente    = false;

        $anio_folio   = $anio_folio_raw !== '' ? (int)$anio_folio_raw : (int)date('Y');
        $numero_folio = null;

        if (!$es_interno && !$es_conocimiento) {
            if ($numero_folio_raw === '') {
                // EXTERNO sin número: se guarda pendiente.
                $folio_pendiente = true;
            } else {
                $numero_folio = (int)$numero_folio_raw;
                [$rango_min, $rango_max] = FolioService::rango($tipo_clave_raw);
                if ($numero_folio < $rango_min || $numero_folio > $rango_max) {
                    $v->addError('numero_folio', "El número de folio debe estar entre $rango_min y $rango_max.");
                }
                if ($anio_folio < 2020 || $anio_folio > 2099) {
                    $v->addError('anio_folio', 'El año del folio debe estar entre 2020 y 2099.');
                }
                if ($v->passes()) {
                    $chk = $pdo->prepare("SELECT id FROM oficios WHERE numero_folio=:n AND anio_folio=:a");
                    $chk->execute([':n' => $numero_folio, ':a' => $anio_folio]);
                    if ($chk->fetch()) {
                        $v->addError('numero_folio', "El folio $numero_folio/$anio_folio ya existe en el sistema.");
                    }
                }
            }
        } else {
            // INTERNO/CONOCIMIENTO: reserva automática del rango.
            $folio_autogenerado = true;
        }

        // Estatus por defecto:
        //   - CONOCIMIENTO => 'De Conocimiento'
        //   - otros        => 'En Proceso' (pendiente)
        $estado_id_raw = trim((string)($_POST['estado_id'] ?? ''));
        if ($estado_id_raw === '') {
            $nombreEstadoDefault = $es_conocimiento ? 'De Conocimiento' : 'En Proceso';
            $stmtEstadoDefault = $pdo->prepare("SELECT id FROM estados_oficio WHERE nombre = :n LIMIT 1");
            $stmtEstadoDefault->execute([':n' => $nombreEstadoDefault]);
            $estado_id = (int)$stmtEstadoDefault->fetchColumn();
            // Fallback: si por alguna razón no existe, cae a 'En Proceso'
            if (!$estado_id) {
                $estado_id = (int)$pdo->query("SELECT id FROM estados_oficio WHERE nombre='En Proceso' LIMIT 1")->fetchColumn();
            }
        } else {
            $estado_id = (int)$estado_id_raw;
        }

        if ($v->fails()) {
            $dependencias     = $pdo->query("SELECT id, nombre FROM dependencias   WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $areas_internas   = $pdo->query("SELECT id, nombre FROM areas_internas WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $estados          = $pdo->query("SELECT id, nombre FROM estados_oficio WHERE activo=TRUE ORDER BY orden")->fetchAll();
            $tipos_doc        = $pdo->query("SELECT id, nombre FROM tipos_documento WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $tipos_oficio     = $pdo->query(
                "SELECT id, clave, nombre, requiere_respuesta, requiere_pdf_contestado
                   FROM tipos_oficio WHERE activo=TRUE ORDER BY clave"
            )->fetchAll();
            $estado_inicial_id = $pdo->query("SELECT id FROM estados_oficio WHERE nombre='Recibido' LIMIT 1")->fetchColumn();
            $anio_actual      = (int)date('Y');
            $errors           = $v->errors();
            $old              = $_POST;
            $this->view('oficios/create', compact(
                'dependencias', 'areas_internas', 'estados', 'tipos_doc',
                'tipos_oficio', 'anio_actual', 'estado_inicial_id', 'errors', 'old'
            ));
            return;
        }

        // Para CONOCIMIENTO: dependencia_id es opcional (si vino, se guarda; si no, null).
        $dep_in = null;
        if ($es_interno) {
            $dep_in = null;
        } elseif ($es_conocimiento) {
            $dep_raw = trim((string)($_POST['dependencia_id'] ?? ''));
            $dep_in  = $dep_raw !== '' && (int)$dep_raw > 0 ? (int)$dep_raw : null;
        } else {
            $dep_in = (int)$_POST['dependencia_id'];
        }

        // -------------------------------------------------------------
        // Transacción: advisory lock + reserva + INSERT + movimiento
        // -------------------------------------------------------------
        try {
            $pdo->beginTransaction();

            if ($folio_autogenerado) {
                $numero_folio = FolioService::generarNuevoFolio($pdo, $tipo_clave_raw, $anio_folio);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO oficios
                    (numero_folio, anio_folio, folio_minutario, folio_direccion,
                     folio_interno_texto,
                     tipo_oficio_id, dependencia_id, area_interna_id,
                     estado_id, tipo_documento_id,
                     asunto, observaciones,
                     fecha_recepcion, fecha_oficio_tics, fecha_acuse,
                     realizo, usuario_capturo_id)
                 VALUES
                    (:numero_folio, :anio_folio, :folio_minutario, :folio_direccion,
                     :folio_interno_texto,
                     :tipo_oficio_id, :dependencia_id, :area_interna_id,
                     :estado_id, :tipo_documento_id,
                     :asunto, :observaciones,
                     :fecha_recepcion, :fecha_oficio_tics, :fecha_acuse,
                     :realizo, :usuario_capturo_id)
                 RETURNING id, folio_tesoreria"
            );
            $stmt->execute([
                ':numero_folio'       => $numero_folio,
                ':anio_folio'         => $anio_folio,
                ':folio_minutario'    => trim($_POST['folio_minutario'] ?? '') ?: null,
                ':folio_direccion'    => trim($_POST['folio_direccion'] ?? '') ?: null,
                ':folio_interno_texto'=> $es_interno ? (trim($_POST['folio_interno_texto'] ?? '') ?: null) : null,
                ':tipo_oficio_id'     => $tipo_oficio_id,
                ':dependencia_id'     => $dep_in,
                ':area_interna_id'    => $es_interno ? (int)$_POST['area_interna_id'] : null,
                ':estado_id'          => $estado_id,
                ':tipo_documento_id'  => !empty($_POST['tipo_documento_id']) ? (int)$_POST['tipo_documento_id'] : null,
                ':asunto'             => trim($_POST['asunto']),
                ':observaciones'      => trim($_POST['observaciones'] ?? '') ?: null,
                ':fecha_recepcion'    => $_POST['fecha_recepcion'],
                ':fecha_oficio_tics'  => trim($_POST['fecha_oficio_tics'] ?? '') ?: null,
                ':fecha_acuse'        => trim($_POST['fecha_acuse'] ?? '') ?: null,
                ':realizo'            => trim($_POST['realizo'] ?? '') ?: null,
                ':usuario_capturo_id' => Auth::userId(),
            ]);
            $row = $stmt->fetch();

            // Movimiento inicial (dentro de la misma transacción)
            if ($folio_pendiente) {
                $obsMov = 'Oficio registrado PENDIENTE DE FOLIO DE TESORERÍA. El folio se capturará cuando la Tesorería lo asigne.';
            } elseif ($folio_autogenerado) {
                $obsMov = 'Oficio registrado en el sistema (folio auto-asignado).';
            } else {
                $obsMov = 'Oficio registrado en el sistema.';
            }
            $movStmt = $pdo->prepare(
                "INSERT INTO movimientos_oficio (oficio_id, estado_anterior_id, estado_nuevo_id, observacion, usuario_id)
                 VALUES (:oid, NULL, :eid, :obs, :uid)"
            );
            $movStmt->execute([
                ':oid' => $row['id'],
                ':eid' => $estado_id,
                ':obs' => $obsMov,
                ':uid' => Auth::userId(),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('OficioController::store transaction error: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo registrar el oficio. Intente de nuevo.');
            $this->redirect('/oficios/crear');
            return;
        }

        // Procesar PDFs opcionales
        $tiposEv = $pdo->query("SELECT id, nombre FROM tipos_evidencia WHERE activo=TRUE")->fetchAll();
        $mapTipos = [];
        foreach ($tiposEv as $te) {
            $mapTipos[strtoupper($te['nombre'])] = $te['id'];
        }

        $archivos = [
            'pdf_recibido'   => $mapTipos['OFICIO RECIBIDO']   ?? ($mapTipos['RECIBIDO'] ?? null),
        ];
        // pdf_contestado solo aplica si el tipo requiere respuesta
        if ($flag_requiere_respuesta) {
            $archivos['pdf_contestado'] = $mapTipos['CONTESTADO'] ?? ($mapTipos['OFICIO DE RESPUESTA'] ?? null);
        }

        foreach ($archivos as $campo => $tipoId) {
            if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) continue;
            $file = $_FILES[$campo];
            if ($file['size'] > UPLOAD_MAX_SIZE) continue;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') continue;
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mimeReal !== 'application/pdf') continue;

            $nombreSeguro = uniqid('pdf_', true) . '_' . bin2hex(random_bytes(6)) . '.pdf';
            $subdir       = UPLOAD_DIR . '/' . $row['id'];
            if (!is_dir($subdir)) mkdir($subdir, 0750, true);
            $destino = $subdir . '/' . $nombreSeguro;

            if (!move_uploaded_file($file['tmp_name'], $destino)) continue;

            $nombreOrig = preg_replace('/[^a-zA-Z0-9._\-áéíóúÁÉÍÓÚüÜñÑ ]/', '_', $file['name']);

            $evStmt = $pdo->prepare(
                "INSERT INTO evidencias_pdf (oficio_id, tipo_evidencia_id, nombre_original, nombre_archivo, ruta, tamano_bytes, mime_type, usuario_subio_id)
                 VALUES (:oid, :tid, :orig, :arch, :ruta, :tam, :mime, :uid)"
            );
            $evStmt->execute([
                ':oid'  => $row['id'],
                ':tid'  => $tipoId,
                ':orig' => $nombreOrig,
                ':arch' => $nombreSeguro,
                ':ruta' => $destino,
                ':tam'  => $file['size'],
                ':mime' => $mimeReal,
                ':uid'  => Auth::userId(),
            ]);
        }

        Auth::registrarBitacora(Auth::userId(), 'CREATE', 'oficios', $row['id'], [
            'folio' => $row['folio_tesoreria'],
            'tipo'  => $tipo_clave_raw,
        ]);
        $etiquetaTipo = match ($tipo_clave_raw) {
            'INTERNO'      => 'Oficio INTERNO ' . ($_POST['folio_interno_texto'] ?? $row['folio_tesoreria']),
            'CONOCIMIENTO' => 'Oficio de CONOCIMIENTO ' . $row['folio_tesoreria'],
            default        => 'Oficio ' . $row['folio_tesoreria'],
        };
        if ($folio_pendiente) {
            $this->flash(
                'warning',
                "$etiquetaTipo registrado. <strong>Quedó PENDIENTE de folio de Tesorería</strong>; "
                . 'cuando la Tesorería asigne el número, captúralo desde la edición del oficio.'
            );
        } else {
            $this->flash('success', "$etiquetaTipo registrado exitosamente.");
        }
        $this->redirect('/oficios/' . $row['id']);
    }

    // -------------------------------------------------------------------------
    // GET /oficios/:id
    // -------------------------------------------------------------------------
    public function show(array $params): void
    {
        Auth::requireAuth();
        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT * FROM v_oficios_completo WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oficio = $stmt->fetch();

        if (!$oficio) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        // Flags del tipo (para que la vista decida qué mostrar / ocultar sin hardcode)
        $stmtFlags = $pdo->prepare(
            "SELECT clave, requiere_respuesta, requiere_pdf_contestado
               FROM tipos_oficio WHERE id = :tid"
        );
        $stmtFlags->execute([':tid' => $oficio['tipo_oficio_id']]);
        $tipo_flags = $stmtFlags->fetch() ?: [
            'clave' => $oficio['tipo_oficio_clave'] ?? 'EXTERNO',
            'requiere_respuesta' => true,
            'requiere_pdf_contestado' => true,
        ];

        // Movimientos
        $stmtMov = $pdo->prepare(
            "SELECT m.*, ea.nombre AS estado_anterior, en.nombre AS estado_nuevo,
                    en.color AS estado_color, u.nombre AS usuario_nombre
             FROM movimientos_oficio m
             LEFT JOIN estados_oficio ea ON ea.id = m.estado_anterior_id
             LEFT JOIN estados_oficio en ON en.id = m.estado_nuevo_id
             LEFT JOIN usuarios       u  ON u.id  = m.usuario_id
             WHERE m.oficio_id = :oid
             ORDER BY m.fecha ASC"
        );
        $stmtMov->execute([':oid' => $id]);
        $movimientos = $stmtMov->fetchAll();

        // Evidencias
        $stmtEv = $pdo->prepare(
            "SELECT ep.*, te.nombre AS tipo_nombre, u.nombre AS usuario_nombre
             FROM evidencias_pdf ep
             LEFT JOIN tipos_evidencia te ON te.id = ep.tipo_evidencia_id
             LEFT JOIN usuarios        u  ON u.id  = ep.usuario_subio_id
             WHERE ep.oficio_id = :oid
             ORDER BY ep.created_at DESC"
        );
        $stmtEv->execute([':oid' => $id]);
        $evidencias = $stmtEv->fetchAll();

        // Catálogos para formularios inline
        $estados         = $pdo->query("SELECT id, nombre, color FROM estados_oficio WHERE activo=TRUE ORDER BY orden")->fetchAll();
        $tipos_evidencia = $pdo->query("SELECT id, nombre FROM tipos_evidencia WHERE activo=TRUE ORDER BY nombre")->fetchAll();

        $this->view('oficios/show', compact(
            'oficio', 'movimientos', 'evidencias', 'estados', 'tipos_evidencia', 'tipo_flags'
        ));
    }

    // -------------------------------------------------------------------------
    // GET /oficios/:id/editar
    // -------------------------------------------------------------------------
    public function edit(array $params): void
    {
        Auth::requireAuth();
        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT o.*, t.clave AS tipo_oficio_clave,
                    t.requiere_respuesta, t.requiere_pdf_contestado
               FROM oficios o
          LEFT JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
              WHERE o.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $oficio = $stmt->fetch();

        if (!$oficio) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        $tipo_flags = [
            'clave'                    => $oficio['tipo_oficio_clave'] ?? 'EXTERNO',
            'requiere_respuesta'       => (bool)($oficio['requiere_respuesta'] ?? true),
            'requiere_pdf_contestado'  => (bool)($oficio['requiere_pdf_contestado'] ?? true),
        ];

        $dependencias   = $pdo->query("SELECT id, nombre FROM dependencias   WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $areas_internas = $pdo->query("SELECT id, nombre FROM areas_internas WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $estados        = $pdo->query("SELECT id, nombre FROM estados_oficio WHERE activo=TRUE ORDER BY orden")->fetchAll();
        $tipos_doc      = $pdo->query("SELECT id, nombre FROM tipos_documento WHERE activo=TRUE ORDER BY nombre")->fetchAll();
        $usuarios       = $pdo->query("SELECT id, nombre FROM usuarios WHERE activo=TRUE ORDER BY nombre")->fetchAll();

        $stmtEv = $pdo->prepare(
            "SELECT ep.*, te.nombre AS tipo_nombre
             FROM evidencias_pdf ep
             LEFT JOIN tipos_evidencia te ON te.id = ep.tipo_evidencia_id
             WHERE ep.oficio_id = :oid ORDER BY ep.created_at DESC"
        );
        $stmtEv->execute([':oid' => $id]);
        $evidencias = $stmtEv->fetchAll();

        $this->view('oficios/edit', compact(
            'oficio', 'dependencias', 'areas_internas', 'estados',
            'tipos_doc', 'usuarios', 'evidencias', 'tipo_flags'
        ));
    }

    // -------------------------------------------------------------------------
    // POST /oficios/:id/editar
    // -------------------------------------------------------------------------
    public function update(array $params): void
    {
        Auth::requireAuth();
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT o.id, o.tipo_oficio_id,
                    t.clave AS tipo_oficio_clave,
                    t.requiere_respuesta, t.requiere_pdf_contestado
               FROM oficios o
          LEFT JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
              WHERE o.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $oficio_actual = $stmt->fetch();
        if (!$oficio_actual) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }
        $tipo_clave_actual = $oficio_actual['tipo_oficio_clave'] ?? 'EXTERNO';
        $es_interno        = ($tipo_clave_actual === 'INTERNO');
        $es_conocimiento   = ($tipo_clave_actual === 'CONOCIMIENTO');

        $v = new Validator($_POST);
        $v->required('asunto',         'Asunto')
          ->maxLen('asunto',           500, 'Asunto')
          ->required('fecha_recepcion','Fecha de recepción')
          ->date('fecha_recepcion',    'Fecha de recepción')
          ->date('fecha_oficio_tics',  'Fecha de oficio TICs')
          ->date('fecha_acuse',        'Fecha de acuse')
          ->required('estado_id',      'Estatus');

        if ($es_interno) {
            $v->required('area_interna_id', 'Área interna');
        } elseif (!$es_conocimiento) {
            $v->required('dependencia_id',  'Dependencia');
        }
        // CONOCIMIENTO: dependencia_id es opcional; no se valida required.

        if ($v->fails()) {
            $errors       = $v->errors();
            $old          = $_POST;
            $stmtOf = $pdo->prepare(
                "SELECT o.*, t.clave AS tipo_oficio_clave,
                        t.requiere_respuesta, t.requiere_pdf_contestado
                   FROM oficios o
              LEFT JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
                  WHERE o.id = :id"
            );
            $stmtOf->execute([':id' => $id]);
            $oficio_row     = $stmtOf->fetch();
            $tipo_flags     = [
                'clave'                   => $oficio_row['tipo_oficio_clave'] ?? 'EXTERNO',
                'requiere_respuesta'      => (bool)($oficio_row['requiere_respuesta'] ?? true),
                'requiere_pdf_contestado' => (bool)($oficio_row['requiere_pdf_contestado'] ?? true),
            ];
            $oficio         = array_merge($oficio_row, $old);
            $dependencias   = $pdo->query("SELECT id, nombre FROM dependencias   WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $areas_internas = $pdo->query("SELECT id, nombre FROM areas_internas WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $estados        = $pdo->query("SELECT id, nombre FROM estados_oficio WHERE activo=TRUE ORDER BY orden")->fetchAll();
            $tipos_doc      = $pdo->query("SELECT id, nombre FROM tipos_documento WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $usuarios       = $pdo->query("SELECT id, nombre FROM usuarios WHERE activo=TRUE ORDER BY nombre")->fetchAll();
            $stmtEv = $pdo->prepare("SELECT ep.*, te.nombre AS tipo_nombre FROM evidencias_pdf ep LEFT JOIN tipos_evidencia te ON te.id = ep.tipo_evidencia_id WHERE ep.oficio_id = :oid ORDER BY ep.created_at DESC");
            $stmtEv->execute([':oid' => $id]);
            $evidencias = $stmtEv->fetchAll();
            $this->view('oficios/edit', compact(
                'oficio','dependencias','areas_internas','estados','tipos_doc',
                'usuarios','evidencias','errors','old','tipo_flags'
            ));
            return;
        }

        // Detectar cambio de estado para registrar movimiento
        $stmtEstadoActual = $pdo->prepare("SELECT estado_id, numero_folio, anio_folio FROM oficios WHERE id = :id");
        $stmtEstadoActual->execute([':id' => $id]);
        $estadoActualRow  = $stmtEstadoActual->fetch();
        $estadoAnteriorId = (int)($estadoActualRow['estado_id'] ?? 0);
        $estadoNuevoId    = (int)$_POST['estado_id'];

        // ----- Asignación de folio pendiente -----
        // Solo aplica si el oficio EXTERNO/CONOCIMIENTO no tenía numero_folio y el usuario lo captura ahora.
        $folio_fue_asignado = false;
        $numero_folio_upd   = null;
        $anio_folio_upd     = null;
        $folioNumRaw = trim((string)($_POST['numero_folio'] ?? ''));
        if (!$es_interno && empty($estadoActualRow['numero_folio']) && $folioNumRaw !== '') {
            $numero_folio_upd = (int)$folioNumRaw;
            $anio_folio_upd   = (int)(trim((string)($_POST['anio_folio'] ?? '')) ?: date('Y'));

            [$rango_min, $rango_max] = FolioService::rango($tipo_clave_actual);
            if ($numero_folio_upd < $rango_min || $numero_folio_upd > $rango_max) {
                $this->flash('danger', "El número de folio debe estar entre $rango_min y $rango_max.");
                $this->redirect('/oficios/' . $id . '/editar');
                return;
            }
            if ($anio_folio_upd < 2020 || $anio_folio_upd > 2099) {
                $this->flash('danger', 'El año del folio debe estar entre 2020 y 2099.');
                $this->redirect('/oficios/' . $id . '/editar');
                return;
            }
            $chk = $pdo->prepare("SELECT id FROM oficios WHERE numero_folio=:n AND anio_folio=:a AND id <> :id");
            $chk->execute([':n' => $numero_folio_upd, ':a' => $anio_folio_upd, ':id' => $id]);
            if ($chk->fetch()) {
                $this->flash('danger', "El folio $numero_folio_upd/$anio_folio_upd ya existe en el sistema.");
                $this->redirect('/oficios/' . $id . '/editar');
                return;
            }
            $folio_fue_asignado = true;
        }

        $updStmt = $pdo->prepare(
            "UPDATE oficios SET
                dependencia_id       = :dependencia_id,
                area_interna_id      = :area_interna_id,
                folio_interno_texto  = :folio_interno_texto,
                tipo_documento_id    = :tipo_documento_id,
                estado_id            = :estado_id,
                asunto               = :asunto,
                observaciones        = :observaciones,
                fecha_recepcion      = :fecha_recepcion,
                fecha_oficio_tics    = :fecha_oficio_tics,
                fecha_acuse          = :fecha_acuse,
                folio_minutario      = :folio_minutario,
                folio_direccion      = :folio_direccion,
                realizo              = :realizo
             WHERE id = :id"
        );

        // Dependencia: EXTERNO la exige; INTERNO no la usa; CONOCIMIENTO es opcional.
        $dep_upd = null;
        if ($es_interno) {
            $dep_upd = null;
        } elseif ($es_conocimiento) {
            $dep_raw = trim((string)($_POST['dependencia_id'] ?? ''));
            $dep_upd = $dep_raw !== '' && (int)$dep_raw > 0 ? (int)$dep_raw : null;
        } else {
            $dep_upd = (int)$_POST['dependencia_id'];
        }

        $updStmt->execute([
            ':dependencia_id'      => $dep_upd,
            ':area_interna_id'     => $es_interno ? (int)$_POST['area_interna_id'] : null,
            ':folio_interno_texto' => $es_interno ? (trim($_POST['folio_interno_texto'] ?? '') ?: null) : null,
            ':tipo_documento_id'   => (!empty($_POST['tipo_documento_id']) ? (int)$_POST['tipo_documento_id'] : null),
            ':estado_id'           => $estadoNuevoId,
            ':asunto'              => trim($_POST['asunto']),
            ':observaciones'       => trim($_POST['observaciones'] ?? '') ?: null,
            ':fecha_recepcion'     => $_POST['fecha_recepcion'],
            ':fecha_oficio_tics'   => trim($_POST['fecha_oficio_tics'] ?? '') ?: null,
            ':fecha_acuse'         => trim($_POST['fecha_acuse'] ?? '') ?: null,
            ':folio_minutario'     => trim($_POST['folio_minutario'] ?? '') ?: null,
            ':folio_direccion'     => trim($_POST['folio_direccion'] ?? '') ?: null,
            ':realizo'             => trim($_POST['realizo'] ?? '') ?: null,
            ':id'                  => $id,
        ]);

        // Si el usuario capturó el folio pendiente ahora, asignarlo y registrar movimiento.
        if ($folio_fue_asignado) {
            $pdo->prepare(
                "UPDATE oficios SET numero_folio = :n, anio_folio = :a WHERE id = :id"
            )->execute([
                ':n'  => $numero_folio_upd,
                ':a'  => $anio_folio_upd,
                ':id' => $id,
            ]);
            $stmtFolioFinal = $pdo->prepare("SELECT folio_tesoreria FROM oficios WHERE id = :id");
            $stmtFolioFinal->execute([':id' => $id]);
            $folioFinal = $stmtFolioFinal->fetchColumn();

            $pdo->prepare(
                "INSERT INTO movimientos_oficio (oficio_id, estado_anterior_id, estado_nuevo_id, observacion, usuario_id)
                 VALUES (:oid, :ant, :nuevo, :obs, :uid)"
            )->execute([
                ':oid'   => $id,
                ':ant'   => $estadoNuevoId,
                ':nuevo' => $estadoNuevoId,
                ':obs'   => 'Folio de Tesorería asignado: ' . $folioFinal . '.',
                ':uid'   => Auth::userId(),
            ]);
            Auth::registrarBitacora(Auth::userId(), 'ASIGNAR_FOLIO', 'oficios', $id, [
                'numero_folio' => $numero_folio_upd,
                'anio_folio'   => $anio_folio_upd,
                'folio'        => $folioFinal,
            ]);
        }

        // Registrar movimiento si cambió el estado
        if ($estadoAnteriorId !== $estadoNuevoId) {
            $movStmt = $pdo->prepare(
                "INSERT INTO movimientos_oficio (oficio_id, estado_anterior_id, estado_nuevo_id, observacion, usuario_id)
                 VALUES (:oid, :ant, :nuevo, :obs, :uid)"
            );
            $movStmt->execute([
                ':oid'   => $id,
                ':ant'   => $estadoAnteriorId ?: null,
                ':nuevo' => $estadoNuevoId,
                ':obs'   => 'Estado actualizado desde edición.',
                ':uid'   => Auth::userId(),
            ]);
        }

        // Procesar PDFs opcionales
        $tiposEv = $pdo->query("SELECT id, nombre FROM tipos_evidencia WHERE activo=TRUE")->fetchAll();
        $mapTipos = [];
        foreach ($tiposEv as $te) {
            $mapTipos[strtoupper($te['nombre'])] = $te['id'];
        }
        $archivos = [
            'pdf_recibido' => $mapTipos['OFICIO RECIBIDO'] ?? ($mapTipos['RECIBIDO'] ?? null),
        ];
        // pdf_contestado solo si el tipo del oficio requiere respuesta
        if (!empty($oficio_actual['requiere_respuesta'])) {
            $archivos['pdf_contestado'] = $mapTipos['CONTESTADO'] ?? ($mapTipos['OFICIO DE RESPUESTA'] ?? null);
        }
        foreach ($archivos as $campo => $tipoId) {
            if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) continue;
            if (!$tipoId) continue;
            $file = $_FILES[$campo];
            if ($file['size'] > UPLOAD_MAX_SIZE) continue;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') continue;
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mimeReal !== 'application/pdf') continue;

            $nombreSeguro = uniqid('pdf_', true) . '_' . bin2hex(random_bytes(6)) . '.pdf';
            $subdir       = UPLOAD_DIR . '/' . $id;
            if (!is_dir($subdir)) mkdir($subdir, 0750, true);
            if (!move_uploaded_file($file['tmp_name'], $subdir . '/' . $nombreSeguro)) continue;

            $nombreOrig = preg_replace('/[^a-zA-Z0-9._\-áéíóúÁÉÍÓÚüÜñÑ ]/', '_', $file['name']);

            // Reemplazar evidencia previa del mismo tipo
            $pdo->prepare("DELETE FROM evidencias_pdf WHERE oficio_id = :oid AND tipo_evidencia_id = :tid")
                ->execute([':oid' => $id, ':tid' => $tipoId]);

            $evStmt = $pdo->prepare(
                "INSERT INTO evidencias_pdf (oficio_id, tipo_evidencia_id, nombre_original, nombre_archivo, ruta, tamano_bytes, mime_type, usuario_subio_id)
                 VALUES (:oid, :tid, :orig, :arch, :ruta, :tam, :mime, :uid)"
            );
            $evStmt->execute([
                ':oid'  => $id, ':tid'  => $tipoId,
                ':orig' => $nombreOrig, ':arch' => $nombreSeguro,
                ':ruta' => $subdir . '/' . $nombreSeguro,
                ':tam'  => $file['size'], ':mime' => $mimeReal,
                ':uid'  => Auth::userId(),
            ]);
        }

        Auth::registrarBitacora(Auth::userId(), 'UPDATE', 'oficios', $id, null);
        $this->flash('success', 'Oficio actualizado correctamente.');
        $this->redirect('/oficios/' . $id);
    }

    // -------------------------------------------------------------------------
    // POST /oficios/:id/eliminar  — borrado permanente (solo GOD/ADMIN)
    // -------------------------------------------------------------------------
    public function destroy(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT o.id, o.folio_tesoreria, o.folio_interno_texto, t.clave AS tipo_clave
               FROM oficios o
          LEFT JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
              WHERE o.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $oficio = $stmt->fetch();

        if (!$oficio) {
            $this->flash('danger', 'El oficio no existe o ya fue eliminado.');
            $this->redirect('/oficios');
            return;
        }

        // Lista de archivos físicos a borrar (tras commit DB).
        $stmtPdfs = $pdo->prepare("SELECT ruta FROM evidencias_pdf WHERE oficio_id = :oid");
        $stmtPdfs->execute([':oid' => $id]);
        $rutas = array_column($stmtPdfs->fetchAll(), 'ruta');

        try {
            $pdo->beginTransaction();
            // importacion_raw tiene FK sin ON DELETE — se desvincula para no bloquear,
            // pero solo si la tabla existe en esta instalación (es opcional del módulo importar).
            $existeRaw = (bool)$pdo->query("SELECT to_regclass('public.importacion_raw') IS NOT NULL")->fetchColumn();
            if ($existeRaw) {
                $pdo->prepare("UPDATE importacion_raw SET oficio_id = NULL WHERE oficio_id = :id")
                    ->execute([':id' => $id]);
            }
            // movimientos_oficio y evidencias_pdf caen en cascada (ON DELETE CASCADE).
            $pdo->prepare("DELETE FROM oficios WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('OficioController::destroy error: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo eliminar el oficio: ' . $e->getMessage());
            $this->redirect('/oficios/' . $id);
            return;
        }

        // Borrar archivos PDF físicos y la subcarpeta si quedó vacía.
        foreach ($rutas as $ruta) {
            if ($ruta && is_file($ruta)) {
                @unlink($ruta);
            }
        }
        $subdir = UPLOAD_DIR . '/' . $id;
        if (is_dir($subdir)) {
            $restos = @scandir($subdir);
            if ($restos && count(array_diff($restos, ['.', '..'])) === 0) {
                @rmdir($subdir);
            }
        }

        Auth::registrarBitacora(Auth::userId(), 'DELETE', 'oficios', $id, [
            'folio'         => $oficio['folio_tesoreria'],
            'folio_interno' => $oficio['folio_interno_texto'],
            'tipo'          => $oficio['tipo_clave'],
        ]);

        $etiqueta = $oficio['folio_interno_texto'] ?: $oficio['folio_tesoreria'];
        $this->flash('success', "Oficio {$etiqueta} eliminado permanentemente.");
        $this->redirect('/oficios');
    }
}
