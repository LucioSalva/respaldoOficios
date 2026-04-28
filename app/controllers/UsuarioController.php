<?php
/**
 * Controlador de Usuarios (solo GOD y Admin)
 */

class UsuarioController extends Controller
{
    /** GET /usuarios */
    public function index(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $pdo = Database::pdo();

        $stmt = $pdo->query(
            "SELECT u.id, u.nombre, u.username, u.email, u.activo, u.created_at,
                    r.nombre AS rol_nombre, r.id AS rol_id
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             ORDER BY u.created_at DESC"
        );
        $usuarios = $stmt->fetchAll();

        $this->view('usuarios/index', compact('usuarios'));
    }

    /** GET /usuarios/crear */
    public function create(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $roles = Database::pdo()->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
        $this->view('usuarios/create', compact('roles'));
    }

    /** POST /usuarios/crear */
    public function store(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $v = new Validator($_POST);
        $v->required('nombre',   'Nombre completo')
          ->maxLen('nombre',     120, 'Nombre completo')
          ->required('username', 'Usuario')
          ->maxLen('username',   60,  'Usuario')
          ->required('password', 'Contraseña')
          ->passwordComplex('password', 'La contraseña')
          ->required('rol_id',   'Rol');

        $pdo = Database::pdo();

        if ($v->fails()) {
            $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
            $errors = $v->errors();
            $old    = $_POST;
            $this->view('usuarios/create', compact('roles', 'errors', 'old'));
            return;
        }

        // Verificar username único
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE username = :username");
        $chk->execute([':username' => mb_strtolower(trim($_POST['username']))]);
        if ($chk->fetch()) {
            $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
            $errors = ['username' => 'Este nombre de usuario ya está en uso.'];
            $old    = $_POST;
            $this->view('usuarios/create', compact('roles', 'errors', 'old'));
            return;
        }

        // Solo GOD puede crear GOD
        $rolSolicitado = (int)$_POST['rol_id'];
        if ($rolSolicitado === ROL_GOD && Auth::userRolId() !== ROL_GOD) {
            $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
            $errors = ['rol_id' => 'No tienes permisos para asignar ese rol.'];
            $old    = $_POST;
            $this->view('usuarios/create', compact('roles', 'errors', 'old'));
            return;
        }

        $hash = password_hash(trim($_POST['password']), PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre, username, password_hash, rol_id)
             VALUES (:nombre, :username, :hash, :rol_id)
             RETURNING id"
        );
        $stmt->execute([
            ':nombre'   => trim($_POST['nombre']),
            ':username' => mb_strtolower(trim($_POST['username'])),
            ':hash'     => $hash,
            ':rol_id'   => $rolSolicitado,
        ]);
        $uid = $stmt->fetchColumn();

        Auth::registrarBitacora(Auth::userId(), 'CREATE', 'usuarios', (int)$uid, null);
        $this->flash('success', 'Usuario creado correctamente.');
        $this->redirect('/usuarios');
    }

    /** GET /usuarios/:id/editar */
    public function edit(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT id, nombre, username, rol_id, activo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        $roles = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
        $this->view('usuarios/edit', compact('usuario', 'roles'));
    }

    /** POST /usuarios/:id/editar */
    public function update(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT id, rol_id, username FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            http_response_code(404);
            require VIEWS_PATH . '/errors/404.php';
            return;
        }

        $v = new Validator($_POST);
        $v->required('nombre',   'Nombre completo')
          ->maxLen('nombre',     120, 'Nombre completo')
          ->required('username', 'Usuario')
          ->maxLen('username',   60,  'Usuario')
          ->required('rol_id',   'Rol');

        if ($v->fails()) {
            $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
            $errors = $v->errors();
            $old    = $_POST;
            $this->view('usuarios/edit', compact('usuario', 'roles', 'errors', 'old'));
            return;
        }

        $rolSolicitado = (int)$_POST['rol_id'];
        if ($rolSolicitado === ROL_GOD && Auth::userRolId() !== ROL_GOD) {
            $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
            $errors = ['rol_id' => 'No tienes permisos para asignar ese rol.'];
            $old    = $_POST;
            $this->view('usuarios/edit', compact('usuario', 'roles', 'errors', 'old'));
            return;
        }

        // Verificar username único (excluyendo el usuario actual)
        $newUsername = mb_strtolower(trim($_POST['username']));
        if ($newUsername !== $usuario['username']) {
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE username = :username AND id != :id");
            $chk->execute([':username' => $newUsername, ':id' => $id]);
            if ($chk->fetch()) {
                $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
                $errors = ['username' => 'Este nombre de usuario ya está en uso.'];
                $old    = $_POST;
                $this->view('usuarios/edit', compact('usuario', 'roles', 'errors', 'old'));
                return;
            }
        }

        $activo        = isset($_POST['activo']);

        // Salvaguarda del último GOD activo en UPDATE:
        // si el usuario actual es GOD y activo, se rechaza degradarlo o
        // desactivarlo cuando es el único GOD activo del sistema.
        $targetRow = $pdo->prepare("SELECT rol_id, activo FROM usuarios WHERE id = :id");
        $targetRow->execute([':id' => $id]);
        $target = $targetRow->fetch();
        if ($target
            && (int)$target['rol_id'] === ROL_GOD
            && (bool)$target['activo'] === true
            && ($rolSolicitado !== ROL_GOD || !$activo)
        ) {
            $cnt = (int)$pdo->query(
                "SELECT COUNT(*) FROM usuarios
                  WHERE rol_id = " . ROL_GOD . "
                    AND activo = TRUE
                    AND id <> " . (int)$id
            )->fetchColumn();
            if ($cnt === 0) {
                $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
                $errors = ['rol_id' => 'No se puede degradar ni desactivar al último usuario GOD activo.'];
                $old    = $_POST;
                $this->view('usuarios/edit', compact('usuario', 'roles', 'errors', 'old'));
                return;
            }
        }

        $params_update = [
            ':nombre'   => trim($_POST['nombre']),
            ':username' => $newUsername,
            ':rol_id'   => $rolSolicitado,
            ':activo'   => $activo ? 'true' : 'false',
            ':id'       => $id,
        ];

        if (!empty(trim($_POST['password'] ?? ''))) {
            $vp = new Validator($_POST);
            $vp->passwordComplex('password', 'La contraseña', true);
            if ($vp->fails()) {
                $roles  = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
                $errors = $vp->errors();
                $old    = $_POST;
                $this->view('usuarios/edit', compact('usuario', 'roles', 'errors', 'old'));
                return;
            }
            $params_update[':hash'] = password_hash(trim($_POST['password']), PASSWORD_BCRYPT, ['cost' => 12]);
            $updStmt = $pdo->prepare(
                "UPDATE usuarios SET nombre=:nombre, username=:username, rol_id=:rol_id,
                 activo=:activo::boolean, password_hash=:hash WHERE id=:id"
            );
        } else {
            $updStmt = $pdo->prepare(
                "UPDATE usuarios SET nombre=:nombre, username=:username, rol_id=:rol_id,
                 activo=:activo::boolean WHERE id=:id"
            );
        }

        $updStmt->execute($params_update);

        Auth::registrarBitacora(Auth::userId(), 'UPDATE', 'usuarios', $id, null);
        $this->flash('success', 'Usuario actualizado correctamente.');
        $this->redirect('/usuarios');
    }

    /** POST /usuarios/:id/eliminar */
    public function destroy(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);

        if ($id === Auth::userId()) {
            $this->flash('danger', 'No puedes eliminar tu propia cuenta.');
            $this->redirect('/usuarios');
            return;
        }

        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT id, nombre, rol_id FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $this->flash('danger', 'El usuario no existe.');
            $this->redirect('/usuarios');
            return;
        }

        // Solo GOD puede eliminar a otro GOD
        if ((int)$usuario['rol_id'] === ROL_GOD && Auth::userRolId() !== ROL_GOD) {
            $this->flash('danger', 'No tienes permisos para eliminar a un usuario GOD.');
            $this->redirect('/usuarios');
            return;
        }

        // No permitir quedarse sin ningún GOD activo
        if ((int)$usuario['rol_id'] === ROL_GOD) {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = " . ROL_GOD)->fetchColumn();
            if ($cnt <= 1) {
                $this->flash('danger', 'No puedes eliminar al único usuario GOD del sistema.');
                $this->redirect('/usuarios');
                return;
            }
        }

        // Verificar dependencias (FKs NO ACTION)
        $deps = [
            'oficios capturados'    => "SELECT COUNT(*) FROM oficios WHERE usuario_capturo_id = :id OR usuario_responsable_id = :id",
            'movimientos'           => "SELECT COUNT(*) FROM movimientos_oficio WHERE usuario_id = :id",
            'evidencias subidas'    => "SELECT COUNT(*) FROM evidencias_pdf WHERE usuario_subio_id = :id",
        ];
        $bloqueos = [];
        foreach ($deps as $label => $sql) {
            $s = $pdo->prepare($sql);
            $s->execute([':id' => $id]);
            $n = (int)$s->fetchColumn();
            if ($n > 0) {
                $bloqueos[] = "$n $label";
            }
        }

        if ($bloqueos) {
            $this->flash(
                'danger',
                "No se puede eliminar a {$usuario['nombre']} porque tiene registros asociados: "
                . implode(', ', $bloqueos) . '. Desactívalo en su lugar.'
            );
            $this->redirect('/usuarios');
            return;
        }

        try {
            $pdo->beginTransaction();
            // Bitácora referencia al usuario: se reemplaza por NULL en ON DELETE — si es NO ACTION,
            // primero re-apuntamos/limpiamos las entradas de bitácora del propio usuario eliminado.
            $pdo->prepare("DELETE FROM bitacora WHERE usuario_id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM usuarios WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Error al eliminar el usuario: ' . $e->getMessage());
            $this->redirect('/usuarios');
            return;
        }

        Auth::registrarBitacora(Auth::userId(), 'DELETE', 'usuarios', $id, ['nombre' => $usuario['nombre']]);
        $this->flash('success', "Usuario {$usuario['nombre']} eliminado permanentemente.");
        $this->redirect('/usuarios');
    }

    /** POST /usuarios/:id/toggle */
    public function toggle(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);

        if ($id === Auth::userId()) {
            $this->flash('danger', 'No puedes desactivar tu propia cuenta.');
            $this->redirect('/usuarios');
            return;
        }

        $pdo = Database::pdo();

        // Cargar usuario objetivo para conocer rol y estado actual.
        $stmt = $pdo->prepare(
            "SELECT id, nombre, rol_id, activo FROM usuarios WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch();

        if (!$target) {
            $this->flash('danger', 'El usuario no existe.');
            $this->redirect('/usuarios');
            return;
        }

        // Solo GOD puede togglear a otro GOD.
        if ((int)$target['rol_id'] === ROL_GOD && Auth::userRolId() !== ROL_GOD) {
            $this->flash('danger', 'No tienes permisos para modificar a un usuario GOD.');
            $this->redirect('/usuarios');
            return;
        }

        // Salvaguarda del último GOD activo:
        // si el usuario es GOD y está activo, solo se puede desactivar si
        // queda al menos otro GOD activo distinto.
        if ((int)$target['rol_id'] === ROL_GOD && (bool)$target['activo'] === true) {
            $cnt = (int)$pdo->query(
                "SELECT COUNT(*) FROM usuarios
                  WHERE rol_id = " . ROL_GOD . "
                    AND activo = TRUE
                    AND id <> " . (int)$id
            )->fetchColumn();
            if ($cnt === 0) {
                $this->flash(
                    'danger',
                    'No se puede desactivar el último usuario GOD activo del sistema.'
                );
                $this->redirect('/usuarios');
                return;
            }
        }

        $upd = $pdo->prepare(
            "UPDATE usuarios SET activo = NOT activo WHERE id = :id RETURNING activo, nombre"
        );
        $upd->execute([':id' => $id]);
        $row = $upd->fetch();

        if ($row) {
            $estado = $row['activo'] ? 'activado' : 'desactivado';
            Auth::registrarBitacora(Auth::userId(), 'TOGGLE_ACTIVO', 'usuarios', $id, ['activo' => $row['activo']]);
            $this->flash('success', "Usuario {$row['nombre']} $estado correctamente.");
        }

        $this->redirect('/usuarios');
    }
}
