<?php
/**
 * VacacionesModel — acceso a datos del modulo VACACIONES.
 *
 * Modelo de datos (post-migracion 11):
 *   - vacaciones_movimientos: 1 fila por movimiento.
 *   - vacaciones_saldos: saldo derivado por (personal_id, periodo_id).
 *   - vacaciones_periodos: catalogo de periodos (1ER/2DO 2025, etc).
 *   - Vistas: vw_vacaciones_movimientos, vw_vacaciones_sindicalizados_2026,
 *     vw_vacaciones_saldos_negativos, vw_vacaciones_pendientes_revision.
 */
class VacacionesModel
{
    /** Whitelist de ordenamiento del listado pivote. */
    private const ORDER_WHITELIST = [
        'nombre'         => 'nombre_completo ASC',
        'num'            => 'numero_empleado ASC NULLS LAST',
        'p1_restantes'   => 'p1_restantes ASC',
        'p2_restantes'   => 'p2_restantes ASC',
        'incons'         => 'tiene_inconsistencia_global DESC, nombre_completo ASC',
    ];

    // =================================================================
    // Listado pivote por empleado (para index)
    // =================================================================
    public static function listarPivote(array $filtros = [], int $pagina = 1, int $porPagina = 25): array
    {
        $pdo = Database::pdo();
        $where = ['1=1'];
        $params = [];

        if (!empty($filtros['q'])) {
            $where[] = "(v.nombre_completo ILIKE :q OR COALESCE(v.numero_empleado,'') ILIKE :q)";
            $params[':q'] = '%' . $filtros['q'] . '%';
        }
        if (!empty($filtros['num'])) {
            $where[] = "v.numero_empleado ILIKE :num";
            $params[':num'] = '%' . $filtros['num'] . '%';
        }
        if (!empty($filtros['periodo']) && in_array($filtros['periodo'], ['1','2'], true)) {
            // Se usa para filtrar quienes tienen dias en ese periodo (>0 asignados)
            if ($filtros['periodo'] === '1') {
                $where[] = 'v.p1_asignados > 0';
            } else {
                $where[] = 'v.p2_asignados > 0';
            }
        }
        if (!empty($filtros['inconsistencias']) && $filtros['inconsistencias'] === '1') {
            $where[] = 'v.tiene_inconsistencia_global = TRUE';
        }
        if (!empty($filtros['bajos']) && $filtros['bajos'] === '1') {
            // 2 o menos restantes en cualquier periodo con asignacion
            $where[] = "((v.p1_asignados > 0 AND v.p1_restantes <= 2)
                       OR (v.p2_asignados > 0 AND v.p2_restantes <= 2))";
        }

        $orderKey = $filtros['orden'] ?? 'nombre';
        $order = self::ORDER_WHITELIST[$orderKey] ?? self::ORDER_WHITELIST['nombre'];

        $whereSQL = implode(' AND ', $where);

        $stmtCount = $pdo->prepare(
            "SELECT COUNT(*) FROM vw_vacaciones_sindicalizados_2026 v WHERE $whereSQL"
        );
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = max(0, ($pagina - 1) * $porPagina);
        $stmt = $pdo->prepare(
            "SELECT * FROM vw_vacaciones_sindicalizados_2026 v
              WHERE $whereSQL
              ORDER BY $order
              LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,    PDO::PARAM_INT);
        $stmt->execute();
        return [
            'data'    => $stmt->fetchAll(),
            'total'   => $total,
            'paginas' => max(1, (int)ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    // =================================================================
    // Detalle por empleado: saldos de ambos periodos + historial
    // =================================================================
    public static function detalleEmpleado(int $personalId): array
    {
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT p.id, p.numero_empleado, p.nombre_completo,
                    tp.clave AS tipo_clave, tp.nombre AS tipo_nombre
               FROM personal p
               JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
              WHERE p.id = :id"
        );
        $stmt->execute([':id' => $personalId]);
        $persona = $stmt->fetch();
        if (!$persona) return [];

        $stmt = $pdo->prepare(
            "SELECT vs.*, vp.clave AS periodo_clave, vp.nombre AS periodo_nombre,
                    vp.anio
               FROM vacaciones_saldos vs
               JOIN vacaciones_periodos vp ON vp.id = vs.periodo_id
              WHERE vs.personal_id = :pid
              ORDER BY vp.anio, vp.orden"
        );
        $stmt->execute([':pid' => $personalId]);
        $saldos = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT * FROM vw_vacaciones_movimientos
              WHERE personal_id = :pid
              ORDER BY COALESCE(fecha_inicio, fecha_vacaciones, created_at::date) DESC, id DESC"
        );
        $stmt->execute([':pid' => $personalId]);
        $movimientos = $stmt->fetchAll();

        return [
            'persona'     => $persona,
            'saldos'      => $saldos,
            'movimientos' => $movimientos,
        ];
    }

    // =================================================================
    // Movimiento individual
    // =================================================================
    public static function obtenerMovimiento(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM vw_vacaciones_movimientos WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // =================================================================
    // Catalogos
    // =================================================================
    public static function estatusCatalogo(): array
    {
        return Database::pdo()->query(
            "SELECT id, clave, nombre, color FROM estatus_vacaciones
              WHERE activo = TRUE ORDER BY orden"
        )->fetchAll();
    }

    public static function periodosCatalogo(): array
    {
        return Database::pdo()->query(
            "SELECT id, clave, nombre, anio, orden FROM vacaciones_periodos
              WHERE activo = TRUE ORDER BY anio DESC, orden"
        )->fetchAll();
    }

    /** Autocomplete server-side para el selector de empleado. */
    public static function buscarPersonal(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $stmt = Database::pdo()->prepare(
            "SELECT p.id, p.numero_empleado, p.nombre_completo, tp.clave AS tipo_clave
               FROM personal p
               JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
              WHERE p.activo = TRUE
                AND (p.nombre_normalizado ILIKE :q OR COALESCE(p.numero_empleado,'') ILIKE :q)
              ORDER BY p.nombre_completo
              LIMIT :lim"
        );
        $stmt->bindValue(':q', '%' . PersonalModel::normalizarNombre($q) . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =================================================================
    // Resumen para dashboard del modulo
    // =================================================================
    public static function resumenDashboard(): array
    {
        $pdo = Database::pdo();
        $sql = "SELECT
                  (SELECT COUNT(*) FROM vw_vacaciones_sindicalizados_2026)                   AS empleados_con_saldo,
                  (SELECT COUNT(*) FROM vacaciones_movimientos)                              AS total_movimientos,
                  (SELECT COUNT(*) FROM vw_vacaciones_pendientes_revision)                   AS pendientes_revision,
                  (SELECT COUNT(*) FROM vacaciones_saldos WHERE tiene_inconsistencia = TRUE) AS inconsistencias,
                  (SELECT COUNT(*) FROM vw_vacaciones_saldos_negativos)                      AS saldos_negativos";
        return $pdo->query($sql)->fetch() ?: [];
    }
}
