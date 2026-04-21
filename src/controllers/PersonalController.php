<?php
/**
 * Controlador del módulo Personal (catálogo operativo).
 * Distinto de UsuarioController: acá NO hay autenticación ni contraseñas.
 */

class PersonalController extends Controller
{
    /**
     * GET /personal  — listado con filtros.
     */
    public function index(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);

        $filtros = [
            'num'    => trim($_GET['num']    ?? ''),
            'nombre' => trim($_GET['nombre'] ?? ''),
            'tipo'   => trim($_GET['tipo']   ?? ''),
            'activo' => $_GET['activo']      ?? '',
        ];

        $personal = PersonalModel::listar($filtros);
        $tipos    = PersonalModel::tiposPersonal();

        $this->view('personal/index', compact('personal', 'tipos', 'filtros'));
    }

    /**
     * GET /personal/crear
     */
    public function create(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $tipos = PersonalModel::tiposPersonal();
        $this->view('personal/create', compact('tipos'));
    }

    /**
     * POST /personal/crear
     */
    public function store(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $v = new Validator($_POST);
        $v->required('nombre_completo',   'Nombre completo')
          ->maxLen('nombre_completo',  180, 'Nombre completo')
          ->maxLen('numero_empleado',  20,  'Número de empleado')
          ->required('tipo_personal_id',   'Tipo de personal')
          ->integer('tipo_personal_id',    'Tipo de personal');

        // Numero_empleado: opcional pero si viene debe ser alfanumérico razonable
        $num = trim($_POST['numero_empleado'] ?? '');
        if ($num !== '' && !preg_match('/^[A-Z0-9\-]{1,20}$/i', $num)) {
            $v->addError('numero_empleado', 'Número de empleado inválido (máx 20 caracteres, solo letras, números y guion).');
        }

        // Duplicados
        if ($v->passes()) {
            $pdo = Database::pdo();
            if ($num !== '') {
                $chk = $pdo->prepare("SELECT id FROM personal WHERE numero_empleado = :n");
                $chk->execute([':n' => $num]);
                if ($chk->fetch()) {
                    $v->addError('numero_empleado', "Ya existe un empleado con ese número.");
                }
            }
            $norm = PersonalModel::normalizarNombre($_POST['nombre_completo']);
            $chk2 = $pdo->prepare("SELECT id FROM personal WHERE nombre_normalizado = :n");
            $chk2->execute([':n' => $norm]);
            if ($chk2->fetch()) {
                $v->addError('nombre_completo', "Ya existe personal con ese nombre.");
            }
        }

        if ($v->fails()) {
            $tipos  = PersonalModel::tiposPersonal();
            $errors = $v->errors();
            $old    = $_POST;
            $this->view('personal/create', compact('tipos', 'errors', 'old'));
            return;
        }

        $id = PersonalModel::crearManual([
            'numero_empleado'   => $num,
            'nombre_completo'   => trim($_POST['nombre_completo']),
            'tipo_personal_id'  => (int)$_POST['tipo_personal_id'],
            'categoria'         => trim($_POST['categoria'] ?? ''),
            'horario'           => trim($_POST['horario']   ?? ''),
            'observaciones'     => trim($_POST['observaciones'] ?? ''),
        ]);

        Auth::registrarBitacora(Auth::userId(), 'CREATE', 'personal', $id, [
            'nombre' => trim($_POST['nombre_completo']),
            'num'    => $num,
        ]);
        $this->flash('success', 'Personal agregado correctamente.');
        $this->redirect('/personal');
    }

    /**
     * GET /personal/:id  — detalle con historial de vacaciones.
     */
    public function show(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $id = (int)($params['id'] ?? 0);
        $p  = PersonalModel::obtener($id);
        if (!$p) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }
        $historial            = VacacionesModel::historialPorPersonal($id);
        $incidencias          = IncidenciaModel::historialPorPersonal($id, 20);
        $incidenciasResumen   = IncidenciaModel::resumenPorPersonal($id);
        $this->view('personal/show', [
            'persona'             => $p,
            'historial'           => $historial,
            'incidencias'         => $incidencias,
            'incidenciasResumen'  => $incidenciasResumen,
        ]);
    }

    /**
     * GET /personal/:id/editar
     */
    public function edit(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $id = (int)($params['id'] ?? 0);
        $p  = PersonalModel::obtener($id);
        if (!$p) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }
        $tipos = PersonalModel::tiposPersonal();
        $this->view('personal/edit', ['persona' => $p, 'tipos' => $tipos]);
    }

    /**
     * POST /personal/:id/editar
     */
    public function update(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();
        $id = (int)($params['id'] ?? 0);

        $persona = PersonalModel::obtener($id);
        if (!$persona) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        $v = new Validator($_POST);
        $v->required('nombre_completo', 'Nombre completo')
          ->maxLen('nombre_completo', 180, 'Nombre completo')
          ->required('tipo_personal_id', 'Tipo de personal')
          ->integer('tipo_personal_id',  'Tipo de personal');

        $num = trim($_POST['numero_empleado'] ?? '');
        if ($num !== '' && !preg_match('/^[A-Z0-9\-]{1,20}$/i', $num)) {
            $v->addError('numero_empleado', 'Número de empleado inválido.');
        }

        // Duplicados excluyendo a sí mismo
        if ($v->passes()) {
            $pdo = Database::pdo();
            if ($num !== '') {
                $chk = $pdo->prepare("SELECT id FROM personal WHERE numero_empleado = :n AND id <> :id");
                $chk->execute([':n' => $num, ':id' => $id]);
                if ($chk->fetch()) {
                    $v->addError('numero_empleado', "Ya existe otro empleado con ese número.");
                }
            }
            $norm = PersonalModel::normalizarNombre($_POST['nombre_completo']);
            $chk2 = $pdo->prepare("SELECT id FROM personal WHERE nombre_normalizado = :n AND id <> :id");
            $chk2->execute([':n' => $norm, ':id' => $id]);
            if ($chk2->fetch()) {
                $v->addError('nombre_completo', "Ya existe otro personal con ese nombre.");
            }
        }

        if ($v->fails()) {
            $tipos  = PersonalModel::tiposPersonal();
            $errors = $v->errors();
            $old    = array_merge($persona, $_POST);
            $this->view('personal/edit', ['persona' => $old, 'tipos' => $tipos, 'errors' => $errors]);
            return;
        }

        PersonalModel::actualizarManual($id, [
            'numero_empleado'   => $num,
            'nombre_completo'   => trim($_POST['nombre_completo']),
            'tipo_personal_id'  => (int)$_POST['tipo_personal_id'],
            'categoria'         => trim($_POST['categoria'] ?? ''),
            'horario'           => trim($_POST['horario']   ?? ''),
            'observaciones'     => trim($_POST['observaciones'] ?? ''),
            'activo'            => !empty($_POST['activo']),
        ]);

        Auth::registrarBitacora(Auth::userId(), 'UPDATE', 'personal', $id, null);
        $this->flash('success', 'Datos del personal actualizados.');
        $this->redirect('/personal/' . $id);
    }
}
