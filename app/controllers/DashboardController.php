<?php
/**
 * Controlador del Dashboard principal
 */

class DashboardController extends Controller
{
    /** GET /dashboard */
    public function index(): void
    {
        Auth::requireAuth();

        $pdo = Database::pdo();

        // Resumen estadístico
        $resumen = $pdo->query("SELECT * FROM v_dashboard_resumen")->fetch();

        // Últimos 10 oficios registrados
        $stmt = $pdo->prepare(
            "SELECT o.id, o.folio_tesoreria, o.asunto, o.fecha_recepcion,
                    d.nombre AS dependencia, e.nombre AS estado, e.color AS estado_color
             FROM oficios o
             LEFT JOIN dependencias   d ON d.id = o.dependencia_id
             LEFT JOIN estados_oficio e ON e.id = o.estado_id
             ORDER BY o.created_at DESC
             LIMIT 10"
        );
        $stmt->execute();
        $ultimos_oficios = $stmt->fetchAll();

        // Oficios por estado (para gráfica sencilla)
        $stmt2 = $pdo->prepare(
            "SELECT e.nombre, e.color, COUNT(o.id) AS total
             FROM estados_oficio e
             LEFT JOIN oficios o ON o.estado_id = e.id
             WHERE e.activo = TRUE
             GROUP BY e.id, e.nombre, e.color
             ORDER BY e.orden"
        );
        $stmt2->execute();
        $por_estado = $stmt2->fetchAll();

        // Mini-resumen de incidencias del mes actual (tabla nueva, puede no existir todavía en BD)
        $incidenciasMini = null;
        try {
            $incidenciasMini = IncidenciaModel::resumenDashboard();
        } catch (Throwable $e) {
            // Si la tabla aún no se migró, el dashboard sigue funcionando.
            $incidenciasMini = null;
        }

        // Resumen por tipo de oficio (externo / interno / conocimiento)
        // Tolerante: si la vista aún no existe, no rompe el dashboard.
        $porTipo        = [];
        $conocimiento   = null;
        try {
            $stmtTipo = $pdo->query(
                "SELECT tipo_clave, tipo_nombre, total, archivados,
                        de_conocimiento, contestados, pendientes_por_responder,
                        requiere_respuesta, requiere_pdf_contestado
                   FROM vw_oficios_por_tipo"
            );
            $porTipo = $stmtTipo->fetchAll();
            foreach ($porTipo as $row) {
                if (($row['tipo_clave'] ?? '') === 'CONOCIMIENTO') {
                    $conocimiento = $row;
                    break;
                }
            }
        } catch (Throwable $e) {
            $porTipo = [];
            $conocimiento = null;
        }

        $this->view('dashboard/index', compact(
            'resumen', 'ultimos_oficios', 'por_estado',
            'incidenciasMini', 'porTipo', 'conocimiento'
        ));
    }
}
