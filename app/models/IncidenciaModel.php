<?php
/**
 * Repositorio de Incidencias.
 * Todas las consultas usan PDO con prepared statements.
 */

class IncidenciaModel
{
    /** Whitelist de columnas ordenables (anti-SQLi en ORDER BY). */
    private const ORDER_WHITELIST = [
        'fecha'    => 'i.fecha_incidencia',
        'empleado' => 'p.nombre_completo',
        'tipo'     => 'ti.orden',
        'estatus'  => 'ei.orden',
        'creado'   => 'i.created_at',
    ];

    /**
     * Listado paginado con filtros.
     * Filtros soportados:
     *   q (empleado num/nombre), tipo_personal (SIND/CONF),
     *   tipo_id, estatus_id, fecha (exacta), desde, hasta,
     *   mes (1-12), anio, quincena (1-2), personal_id, pendientes (bool).
     */
    public static function listar(array $filtros = [], int $pagina = 1, int $porPagina = 20, string $orden = 'fecha'): array
    {
        $pdo = Database::pdo();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['q'])) {
            $where[] = "(p.nombre_normalizado ILIKE :q OR p.numero_empleado ILIKE :qnum)";
            $params[':q']    = '%' . PersonalModel::normalizarNombre($filtros['q']) . '%';
            $params[':qnum'] = '%' . trim($filtros['q']) . '%';
        }
        if (!empty($filtros['tipo_personal']) && in_array($filtros['tipo_personal'], ['SINDICALIZADO', 'CONFIANZA'], true)) {
            $where[] = "tp.clave = :tpclave";
            $params[':tpclave'] = $filtros['tipo_personal'];
        }
        if (!empty($filtros['tipo_id']) && ctype_digit((string)$filtros['tipo_id'])) {
            $where[] = "i.tipo_incidencia_id = :tid";
            $params[':tid'] = (int)$filtros['tipo_id'];
        }
        if (!empty($filtros['estatus_id']) && ctype_digit((string)$filtros['estatus_id'])) {
            $where[] = "i.estatus_id = :eid";
            $params[':eid'] = (int)$filtros['estatus_id'];
        }
        if (!empty($filtros['fecha'])) {
            $where[] = "i.fecha_incidencia = :fecha";
            $params[':fecha'] = $filtros['fecha'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = "i.fecha_incidencia >= :desde";
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = "i.fecha_incidencia <= :hasta";
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['mes'])) {
            $where[] = "i.mes = :mes";
            $params[':mes'] = (int)$filtros['mes'];
        }
        if (!empty($filtros['anio'])) {
            $where[] = "i.anio = :anio";
            $params[':anio'] = (int)$filtros['anio'];
        }
        if (!empty($filtros['quincena']) && in_array((int)$filtros['quincena'], [1, 2], true)) {
            $where[] = "i.quincena = :quin";
            $params[':quin'] = (int)$filtros['quincena'];
        }
        if (!empty($filtros['personal_id']) && ctype_digit((string)$filtros['personal_id'])) {
            $where[] = "i.personal_id = :pid";
            $params[':pid'] = (int)$filtros['personal_id'];
        }
        if (!empty($filtros['pendientes'])) {
            $where[] = "ei.clave = 'PENDIENTE_REVISION'";
        }

        $whereSQL = implode(' AND ', $where);

        // ORDEN con whitelist
        $orderCol = self::ORDER_WHITELIST[$orden] ?? self::ORDER_WHITELIST['fecha'];

        $stmtCount = $pdo->prepare(
            "SELECT COUNT(*) FROM incidencias i
               JOIN personal           p  ON p.id  = i.personal_id
               JOIN tipos_personal     tp ON tp.id = p.tipo_personal_id
               JOIN tipos_incidencia   ti ON ti.id = i.tipo_incidencia_id
               JOIN estatus_incidencia ei ON ei.id = i.estatus_id
              WHERE $whereSQL"
        );
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = max(0, ($pagina - 1) * $porPagina);
        $sql = "SELECT i.id,
                       i.personal_id,
                       p.numero_empleado,
                       p.nombre_completo,
                       tp.clave            AS tipo_personal_clave,
                       tp.nombre           AS tipo_personal_nombre,
                       ti.id               AS tipo_incidencia_id,
                       ti.clave            AS tipo_clave,
                       ti.nombre           AS tipo_nombre,
                       ti.color            AS tipo_color,
                       ti.icono            AS tipo_icono,
                       ei.id               AS estatus_id,
                       ei.clave            AS estatus_clave,
                       ei.nombre           AS estatus_nombre,
                       ei.color_bs         AS estatus_color,
                       i.fecha_incidencia,
                       i.fecha_inicio,
                       i.fecha_fin,
                       i.fecha_recibido_coord,
                       i.periodo, i.anio, i.mes, i.quincena,
                       i.dias, i.horas, i.minutos,
                       i.motivo, i.observaciones, i.justificacion,
                       i.fuente,
                       i.created_at, i.updated_at
                  FROM incidencias i
                  JOIN personal           p  ON p.id  = i.personal_id
                  JOIN tipos_personal     tp ON tp.id = p.tipo_personal_id
                  JOIN tipos_incidencia   ti ON ti.id = i.tipo_incidencia_id
                  JOIN estatus_incidencia ei ON ei.id = i.estatus_id
                 WHERE $whereSQL
                 ORDER BY $orderCol DESC NULLS LAST, i.id DESC
                 LIMIT :limite OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'    => $stmt->fetchAll(),
            'total'   => $total,
            'paginas' => max(1, (int)ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    public static function obtener(int $id): ?array
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("SELECT * FROM vw_incidencias_resumen WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function tiposCatalogo(): array
    {
        return Database::pdo()->query(
            "SELECT id, clave, nombre, color, icono, orden
               FROM tipos_incidencia
              WHERE activo = TRUE
              ORDER BY orden, nombre"
        )->fetchAll();
    }

    public static function estatusCatalogo(): array
    {
        return Database::pdo()->query(
            "SELECT id, clave, nombre, color_bs, orden
               FROM estatus_incidencia
              WHERE activo = TRUE
              ORDER BY orden"
        )->fetchAll();
    }

    /** Resumen global para dashboard de /incidencias. */
    public static function resumenDashboard(): array
    {
        $pdo = Database::pdo();
        $sql = "SELECT
                  COUNT(*)                                                         AS total,
                  COUNT(*) FILTER (WHERE date_trunc('month', fecha_incidencia)
                                        = date_trunc('month', CURRENT_DATE))       AS mes_actual,
                  COUNT(*) FILTER (WHERE estatus_clave = 'REGISTRADA')              AS registradas,
                  COUNT(*) FILTER (WHERE estatus_clave = 'JUSTIFICADA')             AS justificadas,
                  COUNT(*) FILTER (WHERE estatus_clave = 'NO_JUSTIFICADA')          AS no_justificadas,
                  COUNT(*) FILTER (WHERE estatus_clave = 'CANCELADA')               AS canceladas,
                  COUNT(*) FILTER (WHERE estatus_clave = 'PENDIENTE_REVISION')      AS pendientes
                FROM vw_incidencias_resumen";
        return $pdo->query($sql)->fetch() ?: [];
    }

    /** Top por tipo (usa vista vw_incidencias_por_tipo). */
    public static function resumenPorTipo(): array
    {
        return Database::pdo()->query("SELECT * FROM vw_incidencias_por_tipo")->fetchAll();
    }

    /** Historial por personal (usado en personal/show). */
    public static function historialPorPersonal(int $personalId, int $limite = 50): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM vw_incidencias_resumen
              WHERE personal_id = :pid
              ORDER BY fecha_incidencia DESC NULLS LAST, id DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':pid', $personalId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limite,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Resumen por personal (para personal/show). */
    public static function resumenPorPersonal(int $personalId): array
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("SELECT * FROM vw_incidencias_por_personal WHERE personal_id = :pid");
        $stmt->execute([':pid' => $personalId]);
        return $stmt->fetch() ?: [];
    }

    /** Inserta una incidencia desde el formulario. */
    public static function crear(array $data, ?int $userId): int
    {
        $pdo = Database::pdo();
        $sql = "INSERT INTO incidencias
                (personal_id, tipo_incidencia_id, estatus_id,
                 fecha_incidencia, fecha_inicio, fecha_fin, fecha_recibido_coord,
                 periodo, anio, mes, quincena,
                 dias, horas, minutos,
                 motivo, observaciones, justificacion, folio_justificacion,
                 capturado_por_user_id, fuente)
                VALUES
                (:pid, :tid, :eid,
                 :fincid, :fini, :ffin, :frec,
                 :periodo, :anio, :mes, :quin,
                 :dias, :horas, :minutos,
                 :motivo, :obs, :just, :folio,
                 :uid, :fuente)
                RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pid'     => (int)$data['personal_id'],
            ':tid'     => (int)$data['tipo_incidencia_id'],
            ':eid'     => (int)$data['estatus_id'],
            ':fincid'  => $data['fecha_incidencia'] ?: null,
            ':fini'    => $data['fecha_inicio']     ?: null,
            ':ffin'    => $data['fecha_fin']        ?: null,
            ':frec'    => $data['fecha_recibido_coord'] ?: null,
            ':periodo' => $data['periodo']          ?: null,
            ':anio'    => $data['anio']   !== '' ? (int)$data['anio'] : null,
            ':mes'     => $data['mes']    !== '' ? (int)$data['mes']  : null,
            ':quin'    => $data['quincena'] !== '' ? (int)$data['quincena'] : null,
            ':dias'    => (int)($data['dias']    ?? 0),
            ':horas'   => (int)($data['horas']   ?? 0),
            ':minutos' => (int)($data['minutos'] ?? 0),
            ':motivo'  => $data['motivo']        ?: null,
            ':obs'     => $data['observaciones'] ?: null,
            ':just'    => $data['justificacion'] ?: null,
            ':folio'   => $data['folio_justificacion'] ?: null,
            ':uid'     => $userId,
            ':fuente'  => $data['fuente'] ?? 'MANUAL',
        ]);
        return (int)$stmt->fetchColumn();
    }

    /** Actualiza una incidencia. */
    public static function actualizar(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $sql = "UPDATE incidencias SET
                    tipo_incidencia_id   = :tid,
                    estatus_id           = :eid,
                    fecha_incidencia     = :fincid,
                    fecha_inicio         = :fini,
                    fecha_fin            = :ffin,
                    fecha_recibido_coord = :frec,
                    periodo              = :periodo,
                    anio                 = :anio,
                    mes                  = :mes,
                    quincena             = :quin,
                    dias                 = :dias,
                    horas                = :horas,
                    minutos              = :minutos,
                    motivo               = :motivo,
                    observaciones        = :obs,
                    justificacion        = :just,
                    folio_justificacion  = :folio
                  WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tid'     => (int)$data['tipo_incidencia_id'],
            ':eid'     => (int)$data['estatus_id'],
            ':fincid'  => $data['fecha_incidencia'] ?: null,
            ':fini'    => $data['fecha_inicio']     ?: null,
            ':ffin'    => $data['fecha_fin']        ?: null,
            ':frec'    => $data['fecha_recibido_coord'] ?: null,
            ':periodo' => $data['periodo']          ?: null,
            ':anio'    => $data['anio']   !== '' ? (int)$data['anio'] : null,
            ':mes'     => $data['mes']    !== '' ? (int)$data['mes']  : null,
            ':quin'    => $data['quincena'] !== '' ? (int)$data['quincena'] : null,
            ':dias'    => (int)($data['dias']    ?? 0),
            ':horas'   => (int)($data['horas']   ?? 0),
            ':minutos' => (int)($data['minutos'] ?? 0),
            ':motivo'  => $data['motivo']        ?: null,
            ':obs'     => $data['observaciones'] ?: null,
            ':just'    => $data['justificacion'] ?: null,
            ':folio'   => $data['folio_justificacion'] ?: null,
            ':id'      => $id,
        ]);
    }

    /** Cambia el estatus (usado por cancelar / justificar). */
    public static function cambiarEstatus(int $id, string $nuevoClave, ?string $justificacion = null): void
    {
        // Whitelist de claves permitidas para cambiar programáticamente el estatus.
        // Previene que un valor inesperado llegue a la subconsulta.
        $permitidas = ['REGISTRADA', 'JUSTIFICADA', 'NO_JUSTIFICADA', 'CANCELADA', 'PENDIENTE_REVISION'];
        if (!in_array($nuevoClave, $permitidas, true)) {
            throw new InvalidArgumentException("Clave de estatus no permitida: $nuevoClave");
        }
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare(
            "UPDATE incidencias
                SET estatus_id = (SELECT id FROM estatus_incidencia WHERE clave = :clave),
                    justificacion = COALESCE(:just, justificacion)
              WHERE id = :id"
        );
        $stmt->execute([
            ':clave' => $nuevoClave,
            ':just'  => $justificacion,
            ':id'    => $id,
        ]);
    }

    /** Años distintos con incidencias (para filtro). */
    public static function aniosDisponibles(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo->query(
            "SELECT DISTINCT anio FROM incidencias
              WHERE anio IS NOT NULL
              ORDER BY anio DESC"
        )->fetchAll();
        return array_column($rows, 'anio');
    }
}
