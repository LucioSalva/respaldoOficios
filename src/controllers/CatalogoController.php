<?php
/**
 * Controlador de Catálogos (Dependencias, Estados, Tipos de Evidencia)
 */

class CatalogoController extends Controller
{
    /** GET /catalogos */
    public function index(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $pdo = Database::pdo();

        $dependencias    = $pdo->query("SELECT * FROM dependencias    ORDER BY nombre")->fetchAll();
        $areas_internas  = $pdo->query("SELECT * FROM areas_internas  ORDER BY nombre")->fetchAll();
        $estados         = $pdo->query("SELECT * FROM estados_oficio  ORDER BY orden")->fetchAll();
        $tipos_ev        = $pdo->query("SELECT * FROM tipos_evidencia ORDER BY nombre")->fetchAll();
        $tipos_doc       = $pdo->query("SELECT * FROM tipos_documento ORDER BY nombre")->fetchAll();

        $this->view('catalogos/index', compact(
            'dependencias', 'areas_internas', 'estados', 'tipos_ev', 'tipos_doc'
        ));
    }

    // -------------------------------------------------------------------------
    // DEPENDENCIAS
    // -------------------------------------------------------------------------

    /** POST /catalogos/dependencias */
    public function storeDependencia(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $nombre = trim($_POST['nombre'] ?? '');
        $clave  = trim($_POST['clave']  ?? '');

        if ($nombre === '') {
            $this->flash('danger', 'El nombre de la dependencia es obligatorio.');
            $this->redirect('/catalogos#dependencias');
            return;
        }

        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare("INSERT INTO dependencias (nombre, clave) VALUES (:nombre, :clave)");
            $stmt->execute([':nombre' => $nombre, ':clave' => $clave ?: null]);
            Auth::registrarBitacora(Auth::userId(), 'CREATE', 'dependencias', (int)$pdo->lastInsertId(), null);
            $this->flash('success', 'Dependencia agregada correctamente.');
        } catch (PDOException $e) {
            $this->flash('danger', 'Esa dependencia ya existe o hubo un error al guardar.');
        }
        $this->redirect('/catalogos#dependencias');
    }

    /** POST /catalogos/dependencias/:id/toggle */
    public function toggleDependencia(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE dependencias SET activo = NOT activo WHERE id = :id RETURNING nombre, activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $estado = $row['activo'] ? 'activada' : 'desactivada';
            $this->flash('success', "Dependencia '{$row['nombre']}' $estado.");
        }
        $this->redirect('/catalogos#dependencias');
    }

    /** POST /catalogos/dependencias/:id/editar */
    public function updateDependencia(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id     = (int)($params['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $clave  = trim($_POST['clave']  ?? '');

        if ($nombre === '') {
            $this->flash('danger', 'El nombre es obligatorio.');
            $this->redirect('/catalogos#dependencias');
            return;
        }

        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("UPDATE dependencias SET nombre=:nombre, clave=:clave WHERE id=:id");
        $stmt->execute([':nombre' => $nombre, ':clave' => $clave ?: null, ':id' => $id]);
        Auth::registrarBitacora(Auth::userId(), 'UPDATE', 'dependencias', $id, null);
        $this->flash('success', 'Dependencia actualizada.');
        $this->redirect('/catalogos#dependencias');
    }

    // -------------------------------------------------------------------------
    // ÁREAS INTERNAS (Oficios internos)
    // -------------------------------------------------------------------------

    /** POST /catalogos/areas-internas */
    public function storeAreaInterna(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $nombre = trim($_POST['nombre'] ?? '');
        $clave  = trim($_POST['clave']  ?? '');

        if ($nombre === '') {
            $this->flash('danger', 'El nombre del área interna es obligatorio.');
            $this->redirect('/catalogos#areas-internas');
            return;
        }

        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare("INSERT INTO areas_internas (nombre, clave) VALUES (:nombre, :clave)");
            $stmt->execute([':nombre' => $nombre, ':clave' => $clave ?: null]);
            Auth::registrarBitacora(Auth::userId(), 'CREATE', 'areas_internas', (int)$pdo->lastInsertId(), null);
            $this->flash('success', 'Área interna agregada correctamente.');
        } catch (PDOException $e) {
            $this->flash('danger', 'Esa área interna ya existe o hubo un error al guardar.');
        }
        $this->redirect('/catalogos#areas-internas');
    }

    /** POST /catalogos/areas-internas/:id/toggle */
    public function toggleAreaInterna(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id  = (int)($params['id'] ?? 0);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE areas_internas SET activo = NOT activo WHERE id = :id RETURNING nombre, activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $estado = $row['activo'] ? 'activada' : 'desactivada';
            $this->flash('success', "Área '{$row['nombre']}' $estado.");
        }
        $this->redirect('/catalogos#areas-internas');
    }

    /** POST /catalogos/areas-internas/:id/editar */
    public function updateAreaInterna(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id     = (int)($params['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $clave  = trim($_POST['clave']  ?? '');

        if ($nombre === '') {
            $this->flash('danger', 'El nombre es obligatorio.');
            $this->redirect('/catalogos#areas-internas');
            return;
        }

        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare("UPDATE areas_internas SET nombre=:nombre, clave=:clave WHERE id=:id");
            $stmt->execute([':nombre' => $nombre, ':clave' => $clave ?: null, ':id' => $id]);
            Auth::registrarBitacora(Auth::userId(), 'UPDATE', 'areas_internas', $id, null);
            $this->flash('success', 'Área interna actualizada.');
        } catch (PDOException $e) {
            $this->flash('danger', 'No se pudo actualizar (nombre duplicado u otro error).');
        }
        $this->redirect('/catalogos#areas-internas');
    }

    // -------------------------------------------------------------------------
    // ESTADOS
    // -------------------------------------------------------------------------

    /** POST /catalogos/estados */
    public function storeEstado(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $nombre = trim($_POST['nombre'] ?? '');
        $color  = trim($_POST['color']  ?? 'secondary');
        $orden  = (int)($_POST['orden'] ?? 99);

        if ($nombre === '') {
            $this->flash('danger', 'El nombre del estado es obligatorio.');
            $this->redirect('/catalogos#estados');
            return;
        }

        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare("INSERT INTO estados_oficio (nombre, color, orden) VALUES (:n, :c, :o)");
            $stmt->execute([':n' => $nombre, ':c' => $color, ':o' => $orden]);
            $this->flash('success', 'Estado agregado correctamente.');
        } catch (PDOException $e) {
            $this->flash('danger', 'Ese estado ya existe.');
        }
        $this->redirect('/catalogos#estados');
    }

    /** POST /catalogos/estados/:id/toggle */
    public function toggleEstado(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id   = (int)($params['id'] ?? 0);
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("UPDATE estados_oficio SET activo = NOT activo WHERE id = :id RETURNING nombre, activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $estado = $row['activo'] ? 'activado' : 'desactivado';
            $this->flash('success', "Estado '{$row['nombre']}' $estado.");
        }
        $this->redirect('/catalogos#estados');
    }

    // -------------------------------------------------------------------------
    // TIPOS DE EVIDENCIA
    // -------------------------------------------------------------------------

    /** POST /catalogos/tipos-evidencia */
    public function storeTipoEvidencia(): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $nombre = trim($_POST['nombre']      ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');

        if ($nombre === '') {
            $this->flash('danger', 'El nombre es obligatorio.');
            $this->redirect('/catalogos#tipos-evidencia');
            return;
        }

        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare("INSERT INTO tipos_evidencia (nombre, descripcion) VALUES (:n, :d)");
            $stmt->execute([':n' => $nombre, ':d' => $desc ?: null]);
            $this->flash('success', 'Tipo de evidencia agregado.');
        } catch (PDOException $e) {
            $this->flash('danger', 'Ese tipo de evidencia ya existe.');
        }
        $this->redirect('/catalogos#tipos-evidencia');
    }

    /** POST /catalogos/tipos-evidencia/:id/toggle */
    public function toggleTipoEvidencia(array $params): void
    {
        Auth::requireRole([ROL_GOD, ROL_ADMIN]);
        $this->verifyCsrf();

        $id   = (int)($params['id'] ?? 0);
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("UPDATE tipos_evidencia SET activo = NOT activo WHERE id = :id RETURNING nombre, activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $estado = $row['activo'] ? 'activado' : 'desactivado';
            $this->flash('success', "Tipo '{$row['nombre']}' $estado.");
        }
        $this->redirect('/catalogos#tipos-evidencia');
    }
}
