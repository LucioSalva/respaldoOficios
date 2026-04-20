<?php
/**
 * Controlador de Movimientos de Oficio
 */

class MovimientoController extends Controller
{
    /** POST /oficios/:id/movimiento */
    public function store(array $params): void
    {
        Auth::requireAuth();
        $this->verifyCsrf();

        $oficio_id = (int)($params['id'] ?? 0);
        $pdo       = Database::pdo();

        // Verificar que el oficio existe
        $stmtOf = $pdo->prepare("SELECT id, estado_id FROM oficios WHERE id = :id");
        $stmtOf->execute([':id' => $oficio_id]);
        $oficio = $stmtOf->fetch();

        if (!$oficio) {
            $this->flash('danger', 'Oficio no encontrado.');
            $this->redirect('/oficios');
            return;
        }

        $v = new Validator($_POST);
        $v->required('estado_nuevo_id', 'Nuevo estado')
          ->required('observacion',     'Observación')
          ->maxLen('observacion',       1000, 'Observación');

        if ($v->fails()) {
            $_SESSION['mov_errors'] = $v->errors();
            $_SESSION['mov_old']    = $_POST;
            $this->redirect('/oficios/' . $oficio_id . '#movimientos');
            return;
        }

        $estado_anterior_id = (int)$oficio['estado_id'];
        $estado_nuevo_id    = (int)$_POST['estado_nuevo_id'];

        // Insertar movimiento
        $stmtMov = $pdo->prepare(
            "INSERT INTO movimientos_oficio
                (oficio_id, estado_anterior_id, estado_nuevo_id, observacion, usuario_id, fecha)
             VALUES (:oid, :ea, :en, :obs, :uid, :fecha)"
        );
        $stmtMov->execute([
            ':oid'  => $oficio_id,
            ':ea'   => $estado_anterior_id,
            ':en'   => $estado_nuevo_id,
            ':obs'  => trim($_POST['observacion']),
            ':uid'  => Auth::userId(),
            ':fecha'=> trim($_POST['fecha'] ?? '') ?: date('Y-m-d H:i:s'),
        ]);

        // Actualizar estado del oficio
        $updEstado = $pdo->prepare("UPDATE oficios SET estado_id = :eid WHERE id = :id");
        $updEstado->execute([':eid' => $estado_nuevo_id, ':id' => $oficio_id]);

        Auth::registrarBitacora(Auth::userId(), 'MOVIMIENTO', 'oficios', $oficio_id, [
            'estado_anterior' => $estado_anterior_id,
            'estado_nuevo'    => $estado_nuevo_id,
        ]);

        $this->flash('success', 'Movimiento registrado y estado actualizado.');
        $this->redirect('/oficios/' . $oficio_id . '#movimientos');
    }
}
