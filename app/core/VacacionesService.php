<?php
/**
 * VacacionesService — orquestador del modulo VACACIONES.
 *
 * Responsabilidades:
 *   - Registrar / editar / cancelar movimientos de vacaciones.
 *   - Recalcular saldos derivados por (personal_id, periodo_id).
 *   - Detectar inconsistencias contra el dato DIAS_PENDIENTES del Excel.
 *   - Reglas atomicas con pg_advisory_xact_lock por par (personal, periodo).
 *
 * Reglas de negocio:
 *   - dias_asignados = MAX(dias_corresponden) entre movimientos NO
 *     CANCELADA / NO PENDIENTE_REVISION del mismo (personal, periodo).
 *   - dias_usados    = SUM(dias_tomados) en los mismos filtros.
 *   - dias_restantes = GENERATED en la tabla (asignados - usados).
 *   - tiene_inconsistencia = TRUE si el ultimo movimiento valido del grupo
 *     reporto dias_pendientes_excel distinto de (asignados - usados).
 */

final class VacacionesService
{
    /**
     * Inserta un movimiento y recalcula el saldo del par (personal, periodo).
     * Devuelve el ID del movimiento.
     *
     * @param array $data
     *   personal_id, periodo_id, fecha_vacaciones, fecha_inicio, fecha_fin,
     *   fecha_regreso, fecha_recibido_coord, dias_corresponden, dias_tomados,
     *   dias_pendientes_excel, folio_tm_vacaciones, estatus_id, observaciones
     * @param int $userId
     */
    public static function registrarMovimiento(array $data, int $userId): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::lockPar($pdo, (int)$data['personal_id'], (int)$data['periodo_id']);

            $sql = "INSERT INTO vacaciones_movimientos
                      (personal_id, periodo_id,
                       fecha_vacaciones, fecha_inicio, fecha_fin, fecha_regreso,
                       fecha_recibido_coord,
                       dias_corresponden, dias_tomados, dias_pendientes_excel,
                       folio_tm_vacaciones, estatus_id,
                       capturado_por_user_id, observaciones, origen)
                    VALUES
                      (:pid, :per,
                       :fv, :fi, :ff, :fr,
                       :frc,
                       :dc, :dt, :dpe,
                       :folio, :est,
                       :uid, :obs, 'MANUAL')
                    RETURNING id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pid'   => (int)$data['personal_id'],
                ':per'   => (int)$data['periodo_id'],
                ':fv'    => self::dateOrNull($data['fecha_vacaciones'] ?? null),
                ':fi'    => self::dateOrNull($data['fecha_inicio']     ?? null),
                ':ff'    => self::dateOrNull($data['fecha_fin']        ?? null),
                ':fr'    => self::dateOrNull($data['fecha_regreso']    ?? null),
                ':frc'   => self::dateOrNull($data['fecha_recibido_coord'] ?? null),
                ':dc'    => (int)($data['dias_corresponden'] ?? 0),
                ':dt'    => (int)($data['dias_tomados']      ?? 0),
                ':dpe'   => isset($data['dias_pendientes_excel']) && $data['dias_pendientes_excel'] !== ''
                                ? (int)$data['dias_pendientes_excel'] : null,
                ':folio' => self::strOrNull($data['folio_tm_vacaciones'] ?? null),
                ':est'   => (int)$data['estatus_id'],
                ':uid'   => $userId,
                ':obs'   => self::strOrNull($data['observaciones'] ?? null),
            ]);
            $id = (int)$stmt->fetchColumn();

            self::recalcularSaldoInterno(
                $pdo, (int)$data['personal_id'], (int)$data['periodo_id']
            );
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Edita un movimiento existente; recalcula el saldo del par.
     */
    public static function editarMovimiento(int $id, array $data, int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Leer estado actual para detectar cambios de par (personal, periodo)
            $row = self::obtenerMovimientoParaLock($pdo, $id);
            if (!$row) {
                throw new RuntimeException("Movimiento $id no existe.");
            }
            $newPid = (int)($data['personal_id'] ?? $row['personal_id']);
            $newPer = (int)($data['periodo_id']  ?? $row['periodo_id']);

            self::lockPar($pdo, $newPid, $newPer);
            if ($newPid !== (int)$row['personal_id'] || $newPer !== (int)$row['periodo_id']) {
                self::lockPar($pdo, (int)$row['personal_id'], (int)$row['periodo_id']);
            }

            $sql = "UPDATE vacaciones_movimientos SET
                        personal_id           = :pid,
                        periodo_id            = :per,
                        fecha_vacaciones      = :fv,
                        fecha_inicio          = :fi,
                        fecha_fin             = :ff,
                        fecha_regreso         = :fr,
                        fecha_recibido_coord  = :frc,
                        dias_corresponden     = :dc,
                        dias_tomados          = :dt,
                        dias_pendientes_excel = :dpe,
                        folio_tm_vacaciones   = :folio,
                        estatus_id            = :est,
                        observaciones         = :obs,
                        updated_at            = NOW()
                      WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pid'   => $newPid,
                ':per'   => $newPer,
                ':fv'    => self::dateOrNull($data['fecha_vacaciones'] ?? null),
                ':fi'    => self::dateOrNull($data['fecha_inicio']     ?? null),
                ':ff'    => self::dateOrNull($data['fecha_fin']        ?? null),
                ':fr'    => self::dateOrNull($data['fecha_regreso']    ?? null),
                ':frc'   => self::dateOrNull($data['fecha_recibido_coord'] ?? null),
                ':dc'    => (int)($data['dias_corresponden'] ?? 0),
                ':dt'    => (int)($data['dias_tomados']      ?? 0),
                ':dpe'   => isset($data['dias_pendientes_excel']) && $data['dias_pendientes_excel'] !== ''
                                ? (int)$data['dias_pendientes_excel'] : null,
                ':folio' => self::strOrNull($data['folio_tm_vacaciones'] ?? null),
                ':est'   => (int)$data['estatus_id'],
                ':obs'   => self::strOrNull($data['observaciones'] ?? null),
                ':id'    => $id,
            ]);

            self::recalcularSaldoInterno($pdo, $newPid, $newPer);
            if ($newPid !== (int)$row['personal_id'] || $newPer !== (int)$row['periodo_id']) {
                self::recalcularSaldoInterno(
                    $pdo, (int)$row['personal_id'], (int)$row['periodo_id']
                );
            }
            $pdo->commit();

            Auth::registrarBitacora(
                $userId, 'UPDATE', 'vacaciones_movimientos', $id,
                ['nuevo_par' => [$newPid, $newPer]]
            );
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estatus a CANCELADA y recalcula saldo.
     */
    public static function cancelarMovimiento(int $id, int $userId, ?string $motivo = null): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = self::obtenerMovimientoParaLock($pdo, $id);
            if (!$row) {
                throw new RuntimeException("Movimiento $id no existe.");
            }
            self::lockPar($pdo, (int)$row['personal_id'], (int)$row['periodo_id']);

            $stmt = $pdo->prepare(
                "UPDATE vacaciones_movimientos SET
                    estatus_id    = (SELECT id FROM estatus_vacaciones WHERE clave = 'CANCELADA'),
                    observaciones = CASE
                        WHEN :m::text IS NULL OR :m::text = '' THEN observaciones
                        ELSE COALESCE(observaciones, '') ||
                             CASE WHEN observaciones IS NULL OR observaciones = '' THEN '' ELSE ' | ' END ||
                             'Cancelado: ' || :m::text
                    END,
                    updated_at    = NOW()
                  WHERE id = :id"
            );
            $stmt->execute([':id' => $id, ':m' => $motivo]);

            self::recalcularSaldoInterno(
                $pdo, (int)$row['personal_id'], (int)$row['periodo_id']
            );
            $pdo->commit();

            Auth::registrarBitacora(
                $userId, 'CANCEL', 'vacaciones_movimientos', $id,
                ['motivo' => $motivo]
            );
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Fuerza un recalculo explicito del saldo (util tras correcciones manuales).
     */
    public static function recalcularSaldo(int $personalId, int $periodoId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::lockPar($pdo, $personalId, $periodoId);
            self::recalcularSaldoInterno($pdo, $personalId, $periodoId);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Empleados con saldo marcado como inconsistente.
     */
    public static function detectarInconsistencias(): array
    {
        $sql = "SELECT vs.id, vs.personal_id, p.numero_empleado, p.nombre_completo,
                       vp.clave AS periodo_clave, vp.nombre AS periodo_nombre,
                       vs.dias_asignados, vs.dias_usados, vs.dias_restantes,
                       vs.observaciones
                  FROM vacaciones_saldos vs
                  JOIN personal p            ON p.id  = vs.personal_id
                  JOIN vacaciones_periodos vp ON vp.id = vs.periodo_id
                 WHERE vs.tiene_inconsistencia = TRUE
                 ORDER BY p.nombre_completo";
        return Database::pdo()->query($sql)->fetchAll();
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Aplica pg_advisory_xact_lock sobre (personalId, periodoId). */
    private static function lockPar(PDO $pdo, int $pid, int $perId): void
    {
        $stmt = $pdo->prepare("SELECT pg_advisory_xact_lock(:a, :b)");
        $stmt->execute([':a' => $pid, ':b' => $perId]);
    }

    private static function obtenerMovimientoParaLock(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, personal_id, periodo_id
               FROM vacaciones_movimientos
              WHERE id = :id
              FOR UPDATE"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Calcula saldo y lo upsertea en vacaciones_saldos. Debe llamarse dentro
     * de una transaccion con advisory lock ya adquirido.
     */
    private static function recalcularSaldoInterno(PDO $pdo, int $pid, int $perId): void
    {
        $sql = "SELECT
                    COALESCE(MAX(CASE WHEN ev.clave IN ('CANCELADA','PENDIENTE_REVISION')
                                      THEN NULL ELSE m.dias_corresponden END), 0)       AS dias_asignados,
                    COALESCE(SUM(CASE WHEN ev.clave IN ('CANCELADA','PENDIENTE_REVISION')
                                      THEN 0 ELSE m.dias_tomados END), 0)               AS dias_usados,
                    (SELECT m2.dias_pendientes_excel
                       FROM vacaciones_movimientos m2
                       JOIN estatus_vacaciones ev2 ON ev2.id = m2.estatus_id
                      WHERE m2.personal_id = :pid AND m2.periodo_id = :per
                        AND ev2.clave NOT IN ('CANCELADA','PENDIENTE_REVISION')
                      ORDER BY m2.id DESC LIMIT 1)                                       AS dpe_ultimo
                  FROM vacaciones_movimientos m
                  JOIN estatus_vacaciones ev ON ev.id = m.estatus_id
                 WHERE m.personal_id = :pid AND m.periodo_id = :per";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pid' => $pid, ':per' => $perId]);
        $r = $stmt->fetch();

        $asignados = (int)($r['dias_asignados'] ?? 0);
        $usados    = (int)($r['dias_usados']    ?? 0);
        $dpeUlt    = $r['dpe_ultimo'];
        $restantes = $asignados - $usados;
        $inc = false;
        $obs = null;
        if ($dpeUlt !== null && (int)$dpeUlt !== $restantes) {
            $inc = true;
            $obs = "Inconsistencia: DIAS_PENDIENTES reportado={$dpeUlt}; derivado={$restantes}.";
        }

        // Si no hay movimientos activos, marcar saldo como cero (conservar fila).
        $up = $pdo->prepare(
            "INSERT INTO vacaciones_saldos
                (personal_id, periodo_id, dias_asignados, dias_usados,
                 tiene_inconsistencia, fuente, observaciones)
             VALUES (:pid, :per, :da, :du, :inc, 'DERIVADO_MANUAL', :obs)
             ON CONFLICT (personal_id, periodo_id)
             DO UPDATE SET
                 dias_asignados       = EXCLUDED.dias_asignados,
                 dias_usados          = EXCLUDED.dias_usados,
                 tiene_inconsistencia = EXCLUDED.tiene_inconsistencia,
                 fuente               = CASE WHEN vacaciones_saldos.fuente LIKE 'DERIVADO_IMPORT%'
                                             THEN vacaciones_saldos.fuente
                                             ELSE EXCLUDED.fuente END,
                 observaciones        = EXCLUDED.observaciones,
                 updated_at           = NOW()"
        );
        $up->execute([
            ':pid' => $pid, ':per' => $perId,
            ':da'  => $asignados, ':du' => $usados,
            ':inc' => $inc ? 'true' : 'false',
            ':obs' => $obs,
        ]);
    }

    private static function dateOrNull(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;
        // Acepta Y-m-d (valor de form input type=date).
        $d = DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    private static function strOrNull(?string $v): ?string
    {
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
}
