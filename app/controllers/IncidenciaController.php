<?php
/**
 * Controlador del modulo INCIDENCIAS.
 *
 * Reglas de acceso:
 *   - Ver (index/show): cualquier usuario autenticado.
 *   - Crear: GOD / ADMIN / USER (los 3 pueden registrar).
 *   - Editar / Justificar / Cancelar: solo GOD / ADMIN.
 *   - Eliminar (DELETE fisico): NO expuesto.
 */

class IncidenciaController extends Controller
{
    // =====================================================================
    // GET /incidencias
    // =====================================================================
    public function index(): void
    {
        Auth::requireAuth();

        $filtros = [
            'q'             => trim($_GET['q']             ?? ''),
            'tipo_personal' => trim($_GET['tipo_personal'] ?? ''),
            'tipo_id'       => trim($_GET['tipo_id']       ?? ''),
            'estatus_id'    => trim($_GET['estatus_id']    ?? ''),
            'fecha'         => trim($_GET['fecha']         ?? ''),
            'desde'         => trim($_GET['desde']         ?? ''),
            'hasta'         => trim($_GET['hasta']         ?? ''),
            'mes'           => trim($_GET['mes']           ?? ''),
            'anio'          => trim($_GET['anio']          ?? ''),
            'quincena'      => trim($_GET['quincena']      ?? ''),
            'pendientes'    => !empty($_GET['pendientes']) ? 1 : 0,
        ];
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $orden  = trim($_GET['orden'] ?? 'fecha');

        // Sanear fechas obvias (solo aceptar YYYY-MM-DD)
        foreach (['fecha', 'desde', 'hasta'] as $f) {
            if ($filtros[$f] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$f])) {
                $filtros[$f] = '';
            }
        }

        $res     = IncidenciaModel::listar($filtros, $pagina, 20, $orden);
        $tipos   = IncidenciaModel::tiposCatalogo();
        $estatus = IncidenciaModel::estatusCatalogo();
        $resumen = IncidenciaModel::resumenDashboard();
        $porTipo = IncidenciaModel::resumenPorTipo();
        $anios   = IncidenciaModel::aniosDisponibles();

        $this->view('incidencias/index', [
            'incidencias' => $res['data'],
            'total'       => $res['total'],
            'paginas'     => $res['paginas'],
            'pagina'      => $res['pagina'],
            'tipos'       => $tipos,
            'estatus'     => $estatus,
            'resumen'     => $resumen,
            'porTipo'     => $porTipo,
            'anios'       => $anios,
            'filtros'     => $filtros,
            'orden'       => $orden,
        ]);
    }

    // =====================================================================
    // GET /incidencias/crear
    // =====================================================================
    public function create(): void
    {
        Auth::requireAuth();
        $this->renderForm('incidencias/create');
    }

    // =====================================================================
    // POST /incidencias/crear
    // =====================================================================
    public function store(): void
    {
        Auth::requireAuth();
        $this->verifyCsrf();

        $v = $this->validarForm($_POST);
        if ($v->fails()) {
            $errors = $v->errors();
            $old    = $_POST;
            $this->renderForm('incidencias/create', compact('errors', 'old'));
            return;
        }

        // Derivar anio/mes/quincena desde fecha_incidencia si no se proveen
        $fincid = $_POST['fecha_incidencia'] ?? '';
        $anio   = $_POST['anio']     ?? '';
        $mes    = $_POST['mes']      ?? '';
        $quin   = $_POST['quincena'] ?? '';
        if ($fincid && ($anio === '' || $mes === '' || $quin === '')) {
            [$a, $m, $q] = self::partesFecha($fincid);
            if ($anio === '') $anio = $a;
            if ($mes  === '') $mes  = $m;
            if ($quin === '') $quin = $q;
        }

        $data = self::normalizarDatos($_POST, $anio, $mes, $quin, 'MANUAL');

        try {
            $id = IncidenciaModel::crear($data, Auth::userId());
        } catch (PDOException $e) {
            error_log('Incidencia store: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo guardar. Es posible que ya exista una incidencia igual para ese empleado y fechas.');
            $this->redirect('/incidencias/crear');
            return;
        }

        Auth::registrarBitacora(Auth::userId(), 'CREATE', 'incidencias', $id, [
            'personal_id'        => (int)$_POST['personal_id'],
            'tipo_incidencia_id' => (int)$_POST['tipo_incidencia_id'],
            'fecha'              => $data['fecha_incidencia'],
        ]);
        $this->flash('success', 'Incidencia registrada correctamente.');
        $this->redirect('/incidencias/' . $id);
    }

    // =====================================================================
    // GET /incidencias/:id
    // =====================================================================
    public function show(array $params): void
    {
        Auth::requireAuth();
        $id  = (int)($params['id'] ?? 0);
        $inc = IncidenciaModel::obtener($id);
        if (!$inc) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }
        $this->view('incidencias/show', ['inc' => $inc]);
    }

    // =====================================================================
    // GET /incidencias/:id/editar
    // =====================================================================
    public function edit(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $id  = (int)($params['id'] ?? 0);
        $inc = IncidenciaModel::obtener($id);
        if (!$inc) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }
        $this->renderForm('incidencias/edit', ['inc' => $inc]);
    }

    // =====================================================================
    // POST /incidencias/:id/editar
    // =====================================================================
    public function update(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();
        $id  = (int)($params['id'] ?? 0);
        $inc = IncidenciaModel::obtener($id);
        if (!$inc) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        // Forzar personal_id del registro existente (no permitir cambiarlo por edicion)
        $_POST['personal_id'] = (int)$inc['personal_id'];

        $v = $this->validarForm($_POST);
        if ($v->fails()) {
            $errors = $v->errors();
            $old    = array_merge($inc, $_POST);
            $this->renderForm('incidencias/edit', ['inc' => $old, 'errors' => $errors]);
            return;
        }

        $fincid = $_POST['fecha_incidencia'] ?? '';
        $anio   = $_POST['anio']     ?? '';
        $mes    = $_POST['mes']      ?? '';
        $quin   = $_POST['quincena'] ?? '';
        if ($fincid && ($anio === '' || $mes === '' || $quin === '')) {
            [$a, $m, $q] = self::partesFecha($fincid);
            if ($anio === '') $anio = $a;
            if ($mes  === '') $mes  = $m;
            if ($quin === '') $quin = $q;
        }

        $data = self::normalizarDatos($_POST, $anio, $mes, $quin, $inc['fuente'] ?? 'MANUAL');

        try {
            IncidenciaModel::actualizar($id, $data);
        } catch (PDOException $e) {
            error_log('Incidencia update: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo actualizar. Conflicto con una incidencia existente.');
            $this->redirect('/incidencias/' . $id . '/editar');
            return;
        }

        Auth::registrarBitacora(Auth::userId(), 'UPDATE', 'incidencias', $id, null);
        $this->flash('success', 'Incidencia actualizada.');
        $this->redirect('/incidencias/' . $id);
    }

    // =====================================================================
    // POST /incidencias/:id/justificar
    // =====================================================================
    public function justificar(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();
        $id = (int)($params['id'] ?? 0);

        $inc = IncidenciaModel::obtener($id);
        if (!$inc) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        $texto = trim($_POST['justificacion'] ?? '');
        if (mb_strlen($texto) < 5) {
            $this->flash('warning', 'Captura un texto de justificacion (mínimo 5 caracteres).');
            $this->redirect('/incidencias/' . $id);
            return;
        }
        if (mb_strlen($texto) > 2000) {
            $texto = mb_substr($texto, 0, 2000);
        }

        IncidenciaModel::cambiarEstatus($id, 'JUSTIFICADA', $texto);
        Auth::registrarBitacora(Auth::userId(), 'JUSTIFICAR', 'incidencias', $id, [
            'justificacion_len' => mb_strlen($texto),
        ]);
        $this->flash('success', 'Incidencia marcada como justificada.');
        $this->redirect('/incidencias/' . $id);
    }

    // =====================================================================
    // POST /incidencias/:id/cancelar
    // =====================================================================
    public function cancelar(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();
        $id = (int)($params['id'] ?? 0);

        $inc = IncidenciaModel::obtener($id);
        if (!$inc) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        IncidenciaModel::cambiarEstatus($id, 'CANCELADA', null);
        Auth::registrarBitacora(Auth::userId(), 'CANCELAR', 'incidencias', $id, null);
        $this->flash('success', 'Incidencia cancelada.');
        $this->redirect('/incidencias/' . $id);
    }

    // =====================================================================
    // Helpers privados
    // =====================================================================

    /** Renderiza el formulario de create/edit con catalogos. */
    private function renderForm(string $view, array $extra = []): void
    {
        $pdo = Database::pdo();
        $personal = $pdo->query(
            "SELECT p.id, p.numero_empleado, p.nombre_completo, tp.clave AS tipo_clave, tp.nombre AS tipo_nombre
               FROM personal p
               JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
              WHERE p.activo = TRUE
              ORDER BY p.nombre_completo"
        )->fetchAll();

        $tipos   = IncidenciaModel::tiposCatalogo();
        $estatus = IncidenciaModel::estatusCatalogo();

        $this->view($view, array_merge([
            'personal' => $personal,
            'tipos'    => $tipos,
            'estatus'  => $estatus,
        ], $extra));
    }

    /** Validaciones comunes create/update. */
    private function validarForm(array $post): Validator
    {
        $v = new Validator($post);
        $v->required('personal_id',         'Empleado')
          ->integer('personal_id',          'Empleado')
          ->required('tipo_incidencia_id',  'Tipo de incidencia')
          ->integer('tipo_incidencia_id',   'Tipo de incidencia')
          ->required('estatus_id',          'Estatus')
          ->integer('estatus_id',           'Estatus')
          ->date('fecha_incidencia',        'Fecha de la incidencia')
          ->date('fecha_inicio',            'Fecha de inicio')
          ->date('fecha_fin',               'Fecha de fin')
          ->date('fecha_recibido_coord',    'Fecha de recibido por coordinación')
          ->maxLen('periodo',          40,  'Periodo')
          ->maxLen('motivo',          500,  'Motivo')
          ->maxLen('observaciones',  1000,  'Observaciones')
          ->maxLen('justificacion',  2000,  'Justificación')
          ->maxLen('folio_justificacion', 60, 'Folio de justificación');

        // Al menos una fecha debe venir
        if (empty($post['fecha_incidencia']) && empty($post['fecha_inicio'])) {
            $v->addError('fecha_incidencia', 'Debe indicar al menos fecha de incidencia o fecha de inicio.');
        }

        $fi = $post['fecha_inicio'] ?? '';
        $ff = $post['fecha_fin']    ?? '';
        if ($fi !== '' && $ff !== '' && $ff < $fi) {
            $v->addError('fecha_fin', 'La fecha de fin no puede ser anterior a la de inicio.');
        }

        foreach (['dias' => [0, 366], 'horas' => [0, 23], 'minutos' => [0, 59]] as $f => [$min, $max]) {
            if (!isset($post[$f]) || $post[$f] === '') continue;
            if (!ctype_digit((string)$post[$f]) || (int)$post[$f] < $min || (int)$post[$f] > $max) {
                $v->addError($f, "Valor inválido (entre $min y $max).");
            }
        }
        foreach (['anio' => [2000, 2100], 'mes' => [1, 12], 'quincena' => [1, 2]] as $f => [$min, $max]) {
            if (!isset($post[$f]) || $post[$f] === '') continue;
            if (!ctype_digit((string)$post[$f]) || (int)$post[$f] < $min || (int)$post[$f] > $max) {
                $v->addError($f, "Valor inválido.");
            }
        }

        return $v;
    }

    /** Saca año, mes, quincena desde YYYY-MM-DD. */
    private static function partesFecha(string $iso): array
    {
        try {
            $d = new DateTimeImmutable($iso);
            $dia = (int)$d->format('d');
            return [(int)$d->format('Y'), (int)$d->format('m'), $dia <= 15 ? 1 : 2];
        } catch (Throwable $e) {
            return ['', '', ''];
        }
    }

    /** Armar arreglo final a enviar al modelo. */
    private static function normalizarDatos(array $post, $anio, $mes, $quin, string $fuente): array
    {
        return [
            'personal_id'          => (int)$post['personal_id'],
            'tipo_incidencia_id'   => (int)$post['tipo_incidencia_id'],
            'estatus_id'           => (int)$post['estatus_id'],
            'fecha_incidencia'     => $post['fecha_incidencia']     ?? '',
            'fecha_inicio'         => $post['fecha_inicio']         ?? '',
            'fecha_fin'            => $post['fecha_fin']            ?? '',
            'fecha_recibido_coord' => $post['fecha_recibido_coord'] ?? '',
            'periodo'              => trim($post['periodo']         ?? ''),
            'anio'                 => $anio,
            'mes'                  => $mes,
            'quincena'             => $quin,
            'dias'                 => $post['dias']    ?? 0,
            'horas'                => $post['horas']   ?? 0,
            'minutos'              => $post['minutos'] ?? 0,
            'motivo'               => trim($post['motivo']        ?? ''),
            'observaciones'        => trim($post['observaciones'] ?? ''),
            'justificacion'        => trim($post['justificacion'] ?? ''),
            'folio_justificacion'  => trim($post['folio_justificacion'] ?? ''),
            'fuente'               => $fuente,
        ];
    }
}
