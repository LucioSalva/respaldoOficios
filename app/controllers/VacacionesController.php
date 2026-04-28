<?php
/**
 * VacacionesController — modulo VACACIONES (post-migracion 11).
 *
 * Rutas:
 *   GET  /vacaciones                      index  (pivote por empleado)
 *   GET  /vacaciones/empleado/:pid        show   (detalle empleado + historial)
 *   GET  /vacaciones/crear                create
 *   POST /vacaciones/crear                store
 *   GET  /vacaciones/mov/:id/editar       edit
 *   POST /vacaciones/mov/:id/editar       update
 *   POST /vacaciones/mov/:id/cancelar     cancel
 *   GET  /vacaciones/inconsistencias      inconsistencias (Admin+)
 *   GET  /vacaciones/buscar-personal      autocomplete JSON
 *
 * Permisos:
 *   - Todos los usuarios autenticados pueden consultar y registrar.
 *   - Solo GOD/ADMIN pueden editar, cancelar y ver inconsistencias.
 */
class VacacionesController extends Controller
{
    // =================================================================
    // LISTADO (pivote por empleado, 1er/2do periodo 2025)
    // =================================================================
    public function index(): void
    {
        Auth::requireAuth();
        $filtros = [
            'q'                 => trim($_GET['q']                  ?? ''),
            'num'               => trim($_GET['num']                ?? ''),
            'periodo'           => trim($_GET['periodo']            ?? ''),
            'inconsistencias'   => trim($_GET['inconsistencias']    ?? ''),
            'bajos'             => trim($_GET['bajos']              ?? ''),
            'orden'             => trim($_GET['orden']              ?? 'nombre'),
        ];
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $res     = VacacionesModel::listarPivote($filtros, $pagina);
        $resumen = VacacionesModel::resumenDashboard();

        $this->view('vacaciones/index', [
            'rows'    => $res['data'],
            'total'   => $res['total'],
            'paginas' => $res['paginas'],
            'pagina'  => $res['pagina'],
            'filtros' => $filtros,
            'resumen' => $resumen,
            'puedeAdmin' => Auth::hasRole([ROL_GOD, ROL_ADMIN]),
        ]);
    }

    // =================================================================
    // DETALLE POR EMPLEADO
    // =================================================================
    public function show(array $params): void
    {
        Auth::requireAuth();
        $pid = (int)($params['pid'] ?? 0);
        if ($pid <= 0) { $this->notFound(); return; }

        $det = VacacionesModel::detalleEmpleado($pid);
        if (empty($det)) { $this->notFound(); return; }

        $this->view('vacaciones/show', [
            'persona'     => $det['persona'],
            'saldos'      => $det['saldos'],
            'movimientos' => $det['movimientos'],
            'puedeAdmin'  => Auth::hasRole([ROL_GOD, ROL_ADMIN]),
        ]);
    }

    // =================================================================
    // CREAR (form + store)
    // =================================================================
    public function create(): void
    {
        Auth::requireAuth();
        $this->view('vacaciones/create', [
            'estatus'      => VacacionesModel::estatusCatalogo(),
            'periodos'     => VacacionesModel::periodosCatalogo(),
            'personal_pre' => null,
        ]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $this->verifyCsrf();

        $errors = $this->validarMovimiento($_POST);
        if ($errors) {
            $this->view('vacaciones/create', [
                'estatus'  => VacacionesModel::estatusCatalogo(),
                'periodos' => VacacionesModel::periodosCatalogo(),
                'errors'   => $errors,
                'old'      => $_POST,
            ]);
            return;
        }

        try {
            $id = VacacionesService::registrarMovimiento([
                'personal_id'           => (int)$_POST['personal_id'],
                'periodo_id'            => (int)$_POST['periodo_id'],
                'fecha_vacaciones'      => $_POST['fecha_vacaciones']      ?? null,
                'fecha_inicio'          => $_POST['fecha_inicio']          ?? null,
                'fecha_fin'             => $_POST['fecha_fin']             ?? null,
                'fecha_regreso'         => $_POST['fecha_regreso']         ?? null,
                'fecha_recibido_coord'  => $_POST['fecha_recibido_coord']  ?? null,
                'dias_corresponden'     => $_POST['dias_corresponden']     ?? 0,
                'dias_tomados'          => $_POST['dias_tomados']          ?? 0,
                'dias_pendientes_excel' => $_POST['dias_pendientes_excel'] ?? null,
                'folio_tm_vacaciones'   => $_POST['folio_tm_vacaciones']   ?? null,
                'estatus_id'            => (int)$_POST['estatus_id'],
                'observaciones'         => $_POST['observaciones']         ?? null,
            ], (int)Auth::userId());
        } catch (Throwable $e) {
            error_log('Vacaciones store: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo guardar. Revisa los datos e intenta de nuevo.');
            $this->redirect('/vacaciones/crear');
            return;
        }

        Auth::registrarBitacora(
            Auth::userId(), 'CREATE', 'vacaciones_movimientos', $id,
            ['personal_id' => (int)$_POST['personal_id'],
             'periodo_id'  => (int)$_POST['periodo_id']]
        );
        $this->flash('success', 'Vacaciones registradas correctamente.');
        $this->redirect('/vacaciones/empleado/' . (int)$_POST['personal_id']);
    }

    // =================================================================
    // EDITAR (solo GOD/ADMIN)
    // =================================================================
    public function edit(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $id = (int)($params['id'] ?? 0);
        $mov = VacacionesModel::obtenerMovimiento($id);
        if (!$mov) { $this->notFound(); return; }

        $this->view('vacaciones/edit', [
            'mov'      => $mov,
            'estatus'  => VacacionesModel::estatusCatalogo(),
            'periodos' => VacacionesModel::periodosCatalogo(),
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();
        $id = (int)($params['id'] ?? 0);
        $mov = VacacionesModel::obtenerMovimiento($id);
        if (!$mov) { $this->notFound(); return; }

        $errors = $this->validarMovimiento($_POST);
        if ($errors) {
            $this->view('vacaciones/edit', [
                'mov'      => array_merge($mov, $_POST),
                'estatus'  => VacacionesModel::estatusCatalogo(),
                'periodos' => VacacionesModel::periodosCatalogo(),
                'errors'   => $errors,
            ]);
            return;
        }

        try {
            VacacionesService::editarMovimiento($id, [
                'personal_id'           => (int)$_POST['personal_id'],
                'periodo_id'            => (int)$_POST['periodo_id'],
                'fecha_vacaciones'      => $_POST['fecha_vacaciones']      ?? null,
                'fecha_inicio'          => $_POST['fecha_inicio']          ?? null,
                'fecha_fin'             => $_POST['fecha_fin']             ?? null,
                'fecha_regreso'         => $_POST['fecha_regreso']         ?? null,
                'fecha_recibido_coord'  => $_POST['fecha_recibido_coord']  ?? null,
                'dias_corresponden'     => $_POST['dias_corresponden']     ?? 0,
                'dias_tomados'          => $_POST['dias_tomados']          ?? 0,
                'dias_pendientes_excel' => $_POST['dias_pendientes_excel'] ?? null,
                'folio_tm_vacaciones'   => $_POST['folio_tm_vacaciones']   ?? null,
                'estatus_id'            => (int)$_POST['estatus_id'],
                'observaciones'         => $_POST['observaciones']         ?? null,
            ], (int)Auth::userId());
        } catch (Throwable $e) {
            error_log('Vacaciones update: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo actualizar el movimiento.');
            $this->redirect('/vacaciones/mov/' . $id . '/editar');
            return;
        }

        $this->flash('success', 'Movimiento actualizado correctamente.');
        $this->redirect('/vacaciones/empleado/' . (int)$_POST['personal_id']);
    }

    // =================================================================
    // CANCELAR (solo GOD/ADMIN)
    // =================================================================
    public function cancel(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();
        $id = (int)($params['id'] ?? 0);
        $mov = VacacionesModel::obtenerMovimiento($id);
        if (!$mov) { $this->notFound(); return; }

        try {
            VacacionesService::cancelarMovimiento(
                $id, (int)Auth::userId(),
                trim($_POST['motivo'] ?? '') ?: null
            );
        } catch (Throwable $e) {
            error_log('Vacaciones cancel: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo cancelar el movimiento.');
            $this->redirect('/vacaciones/empleado/' . (int)$mov['personal_id']);
            return;
        }

        $this->flash('success', 'Movimiento cancelado. Saldo recalculado.');
        $this->redirect('/vacaciones/empleado/' . (int)$mov['personal_id']);
    }

    // =================================================================
    // INCONSISTENCIAS (Admin+)
    // =================================================================
    public function inconsistencias(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->view('vacaciones/inconsistencias', [
            'lista' => VacacionesService::detectarInconsistencias(),
        ]);
    }

    // =================================================================
    // Autocomplete JSON
    // =================================================================
    public function buscarPersonal(): void
    {
        Auth::requireAuth();
        $q = trim($_GET['q'] ?? '');
        $res = ($q === '') ? [] : VacacionesModel::buscarPersonal($q, 20);
        $this->json(['data' => $res]);
    }

    // =================================================================
    // Helpers privados
    // =================================================================
    private function validarMovimiento(array $in): array
    {
        $v = new Validator($in);
        $v->required('personal_id', 'Empleado')
          ->integer('personal_id',  'Empleado')
          ->required('periodo_id',  'Periodo')
          ->integer('periodo_id',   'Periodo')
          ->required('estatus_id',  'Estatus')
          ->integer('estatus_id',   'Estatus')
          ->date('fecha_vacaciones',      'Fecha del movimiento')
          ->date('fecha_inicio',          'Fecha de inicio')
          ->date('fecha_fin',             'Fecha de fin')
          ->date('fecha_regreso',         'Fecha de regreso')
          ->date('fecha_recibido_coord',  'Fecha recibido por coordinacion')
          ->maxLen('folio_tm_vacaciones', 60, 'Folio TM de vacaciones')
          ->maxLen('observaciones', 1000, 'Observaciones');

        foreach (['dias_corresponden', 'dias_tomados', 'dias_pendientes_excel'] as $f) {
            if (!isset($in[$f]) || $in[$f] === '') continue;
            if (!ctype_digit((string)$in[$f]) || (int)$in[$f] < 0 || (int)$in[$f] > 366) {
                $v->addError($f, 'Dias invalidos (0 a 366).');
            }
        }
        $fi = $in['fecha_inicio'] ?? '';
        $ff = $in['fecha_fin']    ?? '';
        $fr = $in['fecha_regreso'] ?? '';
        if ($fi && $ff && $ff < $fi) {
            $v->addError('fecha_fin', 'La fecha de fin no puede ser antes que la de inicio.');
        }
        if ($ff && $fr && $fr < $ff) {
            $v->addError('fecha_regreso', 'La fecha de regreso no puede ser antes que el fin.');
        }

        return $v->fails() ? $v->errors() : [];
    }

    private function notFound(): void
    {
        http_response_code(404);
        $view = VIEWS_PATH . '/errors/404.php';
        if (is_file($view)) {
            require $view;
        } else {
            echo '<h1>No encontrado</h1>';
        }
    }
}
