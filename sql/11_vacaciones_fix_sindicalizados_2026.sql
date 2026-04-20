-- =====================================================================
-- 11_vacaciones_fix_sindicalizados_2026.sql
-- Fuente: BASE VACACIONES 2026 / Hoja: VACACIONES SINDICALIZADOS 2026 / Periodos detectados: 2025
--
-- Reconstruccion completa del modulo de VACACIONES.
--   * Modelo 1-fila-por-MOVIMIENTO en vacaciones_movimientos.
--   * Saldos DERIVADOS por (personal_id, periodo_id) en vacaciones_saldos.
--   * Catalogo vacaciones_periodos (2025) con capacidad de altas dinamicas.
--   * Staging crudo import_vacaciones_sindicalizados_2026_raw (jsonb + campos *_raw).
--   * Vistas: pivote por empleado, saldos negativos, pendientes revision,
--     errores de importacion.
--
-- Decisiones confirmadas:
--   D1 Movimientos -> saldos derivados (MAX corresponden, SUM otorgados,
--      restantes generados, tiene_inconsistencia si difiere DIAS_PENDIENTES).
--   D2 Periodos = 2025 (1ER/2DO), altas dinamicas permitidas.
--   D3 Nombre: 11_vacaciones_fix_sindicalizados_2026.sql.
--   D4 Legacy sin perdida: rename a *_legacy_20260420, COMMENT documentando.
--      NO DROP. pg_dump logico se ejecuta fuera de esta migracion
--      (ver tools/importar_vacaciones_sindicalizados_2026.py -> --backup
--      o comando psql documentado al final).
--   D5 Solo hoja VACACIONES SINDICALIZADOS 2026 (importador valida).
--   D6 Tipo por S/C; ambiguo -> PENDIENTE_REVISION y saldo no afectado.
--   R1 folio_tm_vacaciones VARCHAR(60) sin normalizar, indice no-unique.
--   R2 Todos los *_raw conservados en staging (fecha_vaciones_raw sic).
--   R3 Parseo tolerante de fechas: si falla, NULL + observacion.
--
-- Idempotente. Transaccional. NO elimina datos. Requiere migraciones 01-10.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 0. Pre-chequeos: existencia de tablas dependientes
-- ---------------------------------------------------------------------
DO $$
BEGIN
    IF to_regclass('public.personal') IS NULL THEN
        RAISE EXCEPTION 'personal no existe. Aplica migraciones 01-10 antes.';
    END IF;
    IF to_regclass('public.usuarios') IS NULL THEN
        RAISE EXCEPTION 'usuarios no existe. Aplica migraciones 01-10 antes.';
    END IF;
END$$;

-- ---------------------------------------------------------------------
-- 1. RENAME legacy (D4) — conservar historial, NO borrar
--    Se renombran solo si EXISTEN y si NO existe ya el legacy previo.
-- ---------------------------------------------------------------------
DO $$
DECLARE v_has_legacy BOOL;
BEGIN
    -- vacaciones -> vacaciones_legacy_20260420
    IF to_regclass('public.vacaciones') IS NOT NULL
       AND to_regclass('public.vacaciones_legacy_20260420') IS NULL THEN
        -- desactivar vista dependiente antes de rename
        DROP VIEW IF EXISTS vw_importacion_errores;
        DROP VIEW IF EXISTS vw_vacaciones_resumen;
        ALTER TABLE vacaciones RENAME TO vacaciones_legacy_20260420;
        COMMENT ON TABLE vacaciones_legacy_20260420 IS
            'Legacy pre-migracion 11. Intocable. Ver vacaciones_movimientos y vw_vacaciones_* para modelo actual.';
    END IF;

    -- import_vacaciones_raw -> import_vacaciones_raw_legacy_20260420
    IF to_regclass('public.import_vacaciones_raw') IS NOT NULL
       AND to_regclass('public.import_vacaciones_raw_legacy_20260420') IS NULL THEN
        ALTER TABLE import_vacaciones_raw RENAME TO import_vacaciones_raw_legacy_20260420;
        COMMENT ON TABLE import_vacaciones_raw_legacy_20260420 IS
            'Legacy pre-migracion 11. Intocable. Ver import_vacaciones_sindicalizados_2026_raw.';
    END IF;

    -- vacaciones_saldos no existia historicamente; chequear por si acaso
    IF to_regclass('public.vacaciones_saldos') IS NOT NULL
       AND to_regclass('public.vacaciones_saldos_legacy_20260420') IS NULL THEN
        ALTER TABLE vacaciones_saldos RENAME TO vacaciones_saldos_legacy_20260420;
        COMMENT ON TABLE vacaciones_saldos_legacy_20260420 IS
            'Legacy pre-migracion 11. Intocable.';
    END IF;
END$$;

-- ---------------------------------------------------------------------
-- 2. Catalogo vacaciones_periodos (D2)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vacaciones_periodos (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(40)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    anio        SMALLINT     NOT NULL,
    orden       SMALLINT     NOT NULL DEFAULT 99,
    activo      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_vac_periodos_updated_at ON vacaciones_periodos;
CREATE TRIGGER trg_vac_periodos_updated_at
    BEFORE UPDATE ON vacaciones_periodos
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

INSERT INTO vacaciones_periodos (clave, nombre, anio, orden) VALUES
    ('1ER_PERIODO_2025', '1er periodo 2025', 2025, 1),
    ('2DO_PERIODO_2025', '2do periodo 2025', 2025, 2)
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 3. Catalogo estatus_vacaciones (puede haber sido dropeado con las vistas)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estatus_vacaciones (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(30)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    color       VARCHAR(30)  NOT NULL DEFAULT 'secondary',
    orden       SMALLINT     NOT NULL DEFAULT 99,
    descripcion TEXT,
    activo      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

INSERT INTO estatus_vacaciones (clave, nombre, color, orden, descripcion) VALUES
    ('PROGRAMADA',         'Programada',           'info',      10, 'Vacaciones planeadas, aun no inician.'),
    ('ACTIVA',             'En curso',             'warning',   20, 'El empleado esta actualmente en vacaciones.'),
    ('FINALIZADA',         'Finalizada',           'success',   30, 'Vacaciones concluidas; el empleado ya regreso.'),
    ('CANCELADA',          'Cancelada',            'secondary', 40, 'Las vacaciones no se tomaron.'),
    ('PENDIENTE_REVISION', 'Pendiente de revisar', 'danger',    50, 'Datos incompletos o dudosos, requieren revision manual.')
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 4. vacaciones_movimientos (D1)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vacaciones_movimientos (
    id                      SERIAL PRIMARY KEY,
    personal_id             INTEGER     NOT NULL REFERENCES personal(id) ON DELETE RESTRICT,
    periodo_id              INTEGER     NOT NULL REFERENCES vacaciones_periodos(id) ON DELETE RESTRICT,
    -- Fechas
    fecha_vacaciones        DATE        NULL,     -- "FECHA DE VACIONES" del Excel (cabecera del movimiento)
    fecha_inicio            DATE        NULL,
    fecha_fin               DATE        NULL,
    fecha_regreso           DATE        NULL,
    fecha_recibido_coord    DATE        NULL,
    -- Dias del movimiento
    dias_corresponden       SMALLINT    NOT NULL DEFAULT 0,   -- "DIAS QUE CORRESPONDEN" (sirve para MAX de saldo)
    dias_tomados            SMALLINT    NOT NULL DEFAULT 0,   -- "DIAS OTORGADOS" (suma a dias_usados)
    dias_pendientes_excel   SMALLINT    NULL,                 -- "DIAS PENDIENTES" reportado por fila (diagnostico)
    -- Folio TM (R1: sin normalizar, sin validar)
    folio_tm_vacaciones     VARCHAR(60) NULL,
    -- Estatus
    estatus_id              INTEGER     NOT NULL REFERENCES estatus_vacaciones(id),
    -- Captura
    capturado_por_user_id   INTEGER     NULL REFERENCES usuarios(id),
    observaciones           TEXT        NULL,
    -- Origen
    origen                  VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
                                -- MANUAL | IMPORT_2026
    import_raw_id           INTEGER     NULL,   -- FK soft hacia staging (sin constraint dura)
    -- Control
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    -- CHECKs
    CONSTRAINT vm_dias_corr_chk    CHECK (dias_corresponden >= 0),
    CONSTRAINT vm_dias_tom_chk     CHECK (dias_tomados      >= 0),
    CONSTRAINT vm_dias_pen_chk     CHECK (dias_pendientes_excel IS NULL OR dias_pendientes_excel >= 0),
    CONSTRAINT vm_fechas_chk       CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio),
    CONSTRAINT vm_regreso_chk      CHECK (fecha_regreso IS NULL OR fecha_fin IS NULL OR fecha_regreso >= fecha_fin)
);

CREATE INDEX IF NOT EXISTS idx_vm_personal        ON vacaciones_movimientos(personal_id);
CREATE INDEX IF NOT EXISTS idx_vm_periodo         ON vacaciones_movimientos(periodo_id);
CREATE INDEX IF NOT EXISTS idx_vm_pers_periodo    ON vacaciones_movimientos(personal_id, periodo_id);
CREATE INDEX IF NOT EXISTS idx_vm_estatus         ON vacaciones_movimientos(estatus_id);
CREATE INDEX IF NOT EXISTS idx_vm_inicio          ON vacaciones_movimientos(fecha_inicio);
CREATE INDEX IF NOT EXISTS idx_vm_folio_tm        ON vacaciones_movimientos(folio_tm_vacaciones);  -- R1: NO unique
CREATE INDEX IF NOT EXISTS idx_vm_origen          ON vacaciones_movimientos(origen);

DROP TRIGGER IF EXISTS trg_vm_updated_at ON vacaciones_movimientos;
CREATE TRIGGER trg_vm_updated_at
    BEFORE UPDATE ON vacaciones_movimientos
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- ---------------------------------------------------------------------
-- 5. vacaciones_saldos (derivado)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vacaciones_saldos (
    id                   SERIAL PRIMARY KEY,
    personal_id          INTEGER  NOT NULL REFERENCES personal(id) ON DELETE RESTRICT,
    periodo_id           INTEGER  NOT NULL REFERENCES vacaciones_periodos(id) ON DELETE RESTRICT,
    dias_asignados       SMALLINT NOT NULL DEFAULT 0,
    dias_usados          SMALLINT NOT NULL DEFAULT 0,
    dias_restantes       SMALLINT GENERATED ALWAYS AS (dias_asignados - dias_usados) STORED,
    tiene_inconsistencia BOOLEAN  NOT NULL DEFAULT FALSE,
    fuente               VARCHAR(40) NOT NULL DEFAULT 'DERIVADO',
    observaciones        TEXT     NULL,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT vs_unq_pers_per UNIQUE (personal_id, periodo_id),
    CONSTRAINT vs_asig_chk  CHECK (dias_asignados >= 0),
    CONSTRAINT vs_usad_chk  CHECK (dias_usados    >= 0)
);

CREATE INDEX IF NOT EXISTS idx_vs_personal    ON vacaciones_saldos(personal_id);
CREATE INDEX IF NOT EXISTS idx_vs_periodo     ON vacaciones_saldos(periodo_id);
CREATE INDEX IF NOT EXISTS idx_vs_incons      ON vacaciones_saldos(tiene_inconsistencia) WHERE tiene_inconsistencia = TRUE;

DROP TRIGGER IF EXISTS trg_vs_updated_at ON vacaciones_saldos;
CREATE TRIGGER trg_vs_updated_at
    BEFORE UPDATE ON vacaciones_saldos
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- ---------------------------------------------------------------------
-- 6. Staging import_vacaciones_sindicalizados_2026_raw (R2)
--    Nota: fecha_vaciones_raw (sic) conserva ortografia del Excel original.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_vacaciones_sindicalizados_2026_raw (
    id                          SERIAL PRIMARY KEY,
    source_file                 VARCHAR(160) NOT NULL,
    source_sheet                VARCHAR(120) NOT NULL,
    fila_excel                  INTEGER      NOT NULL,
    raw_data                    JSONB        NOT NULL,
    -- Crudos columna a columna (R2)
    cons_raw                    TEXT,
    fecha_vaciones_raw          TEXT,   -- sic: "FECHA DE VACIONES" (typo en Excel)
    numero_empleado_raw         TEXT,
    nombre_raw                  TEXT,
    s_raw                       TEXT,
    c_raw                       TEXT,
    dias_por_justificar_raw     TEXT,
    periodo_por_justificar_raw  TEXT,
    dias_corresponden_raw       TEXT,
    dias_otorgados_raw          TEXT,
    dias_pendientes_raw         TEXT,
    regresa_a_laborar_raw       TEXT,
    recibido_por_coordinacion_raw TEXT,
    folio_tm_raw                TEXT,
    -- Resolucion
    personal_id                 INTEGER NULL REFERENCES personal(id),
    periodo_id                  INTEGER NULL REFERENCES vacaciones_periodos(id),
    movimiento_id               INTEGER NULL REFERENCES vacaciones_movimientos(id),
    tipo_personal_resuelto      VARCHAR(20) NULL,
    estado_revision             VARCHAR(30) NOT NULL DEFAULT 'OK',
                                   -- OK | PENDIENTE_REVISION | ERROR
    error_importacion           TEXT         NULL,
    importado                   BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_isv_estado   ON import_vacaciones_sindicalizados_2026_raw(estado_revision);
CREATE INDEX IF NOT EXISTS idx_isv_personal ON import_vacaciones_sindicalizados_2026_raw(personal_id);
CREATE INDEX IF NOT EXISTS idx_isv_periodo  ON import_vacaciones_sindicalizados_2026_raw(periodo_id);
CREATE INDEX IF NOT EXISTS idx_isv_importado ON import_vacaciones_sindicalizados_2026_raw(importado);

-- ---------------------------------------------------------------------
-- 7. Vistas de reporte
-- ---------------------------------------------------------------------

-- 7.1 Resumen detalle por movimiento
CREATE OR REPLACE VIEW vw_vacaciones_movimientos AS
SELECT
    m.id,
    m.personal_id,
    p.numero_empleado,
    p.nombre_completo,
    tp.clave                AS tipo_personal_clave,
    tp.nombre               AS tipo_personal_nombre,
    m.periodo_id,
    vp.clave                AS periodo_clave,
    vp.nombre               AS periodo_nombre,
    vp.anio                 AS periodo_anio,
    m.fecha_vacaciones,
    m.fecha_inicio,
    m.fecha_fin,
    m.fecha_regreso,
    m.fecha_recibido_coord,
    m.dias_corresponden,
    m.dias_tomados,
    m.dias_pendientes_excel,
    m.folio_tm_vacaciones,
    ev.clave                AS estatus_clave,
    ev.nombre               AS estatus_nombre,
    ev.color                AS estatus_color,
    m.observaciones,
    u.nombre                AS capturo_nombre,
    m.origen,
    m.created_at,
    m.updated_at,
    CASE
        WHEN ev.clave IN ('CANCELADA','PENDIENTE_REVISION') THEN ev.clave
        WHEN m.fecha_inicio IS NULL OR m.fecha_fin IS NULL THEN 'PROGRAMADA'
        WHEN m.fecha_inicio > CURRENT_DATE                 THEN 'PROGRAMADA'
        WHEN m.fecha_inicio <= CURRENT_DATE AND m.fecha_fin >= CURRENT_DATE THEN 'ACTIVA'
        ELSE 'FINALIZADA'
    END                     AS estatus_derivado
FROM vacaciones_movimientos m
JOIN personal         p  ON p.id  = m.personal_id
JOIN tipos_personal   tp ON tp.id = p.tipo_personal_id
JOIN vacaciones_periodos vp ON vp.id = m.periodo_id
JOIN estatus_vacaciones  ev ON ev.id = m.estatus_id
LEFT JOIN usuarios    u  ON u.id  = m.capturado_por_user_id;

-- 7.2 Pivote por empleado (1er y 2do periodo 2025 en columnas)
CREATE OR REPLACE VIEW vw_vacaciones_sindicalizados_2026 AS
WITH base AS (
    SELECT p.id AS personal_id, p.numero_empleado, p.nombre_completo,
           tp.clave AS tipo_personal_clave, tp.nombre AS tipo_personal_nombre
      FROM personal p
      JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
     WHERE p.activo = TRUE
),
p1 AS (
    SELECT vs.personal_id,
           vs.dias_asignados, vs.dias_usados, vs.dias_restantes,
           vs.tiene_inconsistencia, vs.observaciones
      FROM vacaciones_saldos vs
      JOIN vacaciones_periodos vp ON vp.id = vs.periodo_id
     WHERE vp.clave = '1ER_PERIODO_2025'
),
p2 AS (
    SELECT vs.personal_id,
           vs.dias_asignados, vs.dias_usados, vs.dias_restantes,
           vs.tiene_inconsistencia, vs.observaciones
      FROM vacaciones_saldos vs
      JOIN vacaciones_periodos vp ON vp.id = vs.periodo_id
     WHERE vp.clave = '2DO_PERIODO_2025'
)
SELECT
    b.personal_id,
    b.numero_empleado,
    b.nombre_completo,
    b.tipo_personal_clave,
    b.tipo_personal_nombre,
    COALESCE(p1.dias_asignados, 0)   AS p1_asignados,
    COALESCE(p1.dias_usados,    0)   AS p1_usados,
    COALESCE(p1.dias_restantes, 0)   AS p1_restantes,
    COALESCE(p1.tiene_inconsistencia, FALSE) AS p1_inconsistencia,
    p1.observaciones                 AS p1_observaciones,
    COALESCE(p2.dias_asignados, 0)   AS p2_asignados,
    COALESCE(p2.dias_usados,    0)   AS p2_usados,
    COALESCE(p2.dias_restantes, 0)   AS p2_restantes,
    COALESCE(p2.tiene_inconsistencia, FALSE) AS p2_inconsistencia,
    p2.observaciones                 AS p2_observaciones,
    (COALESCE(p1.tiene_inconsistencia, FALSE)
     OR COALESCE(p2.tiene_inconsistencia, FALSE)) AS tiene_inconsistencia_global
FROM base b
LEFT JOIN p1 ON p1.personal_id = b.personal_id
LEFT JOIN p2 ON p2.personal_id = b.personal_id
WHERE p1.personal_id IS NOT NULL OR p2.personal_id IS NOT NULL;

-- 7.3 Saldos negativos
CREATE OR REPLACE VIEW vw_vacaciones_saldos_negativos AS
SELECT vs.id, vs.personal_id, p.numero_empleado, p.nombre_completo,
       vp.clave AS periodo_clave, vp.nombre AS periodo_nombre, vp.anio,
       vs.dias_asignados, vs.dias_usados, vs.dias_restantes,
       vs.tiene_inconsistencia, vs.observaciones
  FROM vacaciones_saldos vs
  JOIN personal p           ON p.id  = vs.personal_id
  JOIN vacaciones_periodos vp ON vp.id = vs.periodo_id
 WHERE vs.dias_restantes < 0;

-- 7.4 Movimientos pendientes de revision
CREATE OR REPLACE VIEW vw_vacaciones_pendientes_revision AS
SELECT vm.*
  FROM vw_vacaciones_movimientos vm
 WHERE vm.estatus_clave = 'PENDIENTE_REVISION';

-- 7.5 Errores de staging de esta migracion
CREATE OR REPLACE VIEW vw_import_vacaciones_errores AS
SELECT id, source_file, source_sheet, fila_excel,
       numero_empleado_raw, nombre_raw,
       estado_revision, error_importacion, importado, created_at
  FROM import_vacaciones_sindicalizados_2026_raw
 WHERE estado_revision <> 'OK' OR error_importacion IS NOT NULL;

-- 7.6 Recrear vista unificada de errores de importacion (antes referenciaba
--     import_vacaciones_raw que ahora esta en legacy).
CREATE OR REPLACE VIEW vw_importacion_errores AS
SELECT 'personal'               AS origen,
        ip.id                    AS staging_id,
        ip.fuente,
        ip.fila_excel,
        ip.estado_revision,
        ip.error_importacion     AS mensaje,
        ip.created_at
  FROM  import_personal_raw ip
  WHERE ip.estado_revision <> 'OK' OR ip.error_importacion IS NOT NULL
UNION ALL
SELECT 'vacaciones_sindicalizados_2026',
        iv.id,
        iv.source_file || '!' || iv.source_sheet,
        iv.fila_excel,
        iv.estado_revision,
        iv.error_importacion,
        iv.created_at
  FROM  import_vacaciones_sindicalizados_2026_raw iv
  WHERE iv.estado_revision <> 'OK' OR iv.error_importacion IS NOT NULL
UNION ALL
SELECT 'folios_interno_sub',
        fs.id,
        'BASE GENERAL.xlsx!FOLIOS INTERNO SUB',
        fs.fila_excel,
        CASE WHEN fs.error_mensaje IS NULL THEN 'OK' ELSE 'ERROR' END,
        fs.error_mensaje,
        fs.created_at
  FROM  import_folios_interno_sub_raw fs
  WHERE fs.error_mensaje IS NOT NULL;

COMMIT;

-- =====================================================================
-- Verificacion post-migracion
-- =====================================================================
SELECT 'vacaciones_periodos' AS tabla, COUNT(*)::text AS filas FROM vacaciones_periodos
UNION ALL SELECT 'estatus_vacaciones',       COUNT(*)::text FROM estatus_vacaciones
UNION ALL SELECT 'vacaciones_movimientos',   COUNT(*)::text FROM vacaciones_movimientos
UNION ALL SELECT 'vacaciones_saldos',        COUNT(*)::text FROM vacaciones_saldos
UNION ALL SELECT 'import_vacaciones_sindicalizados_2026_raw',
       COUNT(*)::text FROM import_vacaciones_sindicalizados_2026_raw
UNION ALL SELECT 'vacaciones_legacy_20260420',
       CASE WHEN to_regclass('public.vacaciones_legacy_20260420') IS NULL
            THEN 'AUSENTE' ELSE (SELECT COUNT(*)::text FROM vacaciones_legacy_20260420) END
UNION ALL SELECT 'import_vacaciones_raw_legacy_20260420',
       CASE WHEN to_regclass('public.import_vacaciones_raw_legacy_20260420') IS NULL
            THEN 'AUSENTE' ELSE (SELECT COUNT(*)::text FROM import_vacaciones_raw_legacy_20260420) END;

-- =====================================================================
-- BACKUP LOGICO (ejecutar MANUALMENTE antes de aplicar esta migracion):
--   pg_dump -h host.docker.internal -U postgres -d respaldooficios \
--     -t vacaciones -t vacaciones_saldos -t import_vacaciones_raw \
--     -t estatus_vacaciones \
--     --no-owner --no-privileges \
--     -f backups/vacaciones_legacy_20260420.sql
-- =====================================================================
