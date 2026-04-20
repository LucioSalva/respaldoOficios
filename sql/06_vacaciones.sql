-- =====================================================================
-- 06_vacaciones.sql
-- Migración aditiva para el MÓDULO VACACIONES.
--
-- Objetivo:
--   - Catálogo estatus_vacaciones (PROGRAMADA / ACTIVA / FINALIZADA /
--     CANCELADA / PENDIENTE_REVISION).
--   - Tabla vacaciones con CHECKs de fechas y días.
--   - Staging import_vacaciones_raw.
--   - Vista vw_vacaciones_resumen y vw_importacion_errores (unificada
--     para personal, vacaciones y folios internos sub).
--
-- Reglas:
--   - fecha_fin >= fecha_inicio
--   - fecha_regreso >= fecha_fin (si se captura)
--   - dias_solicitados, dias_disfrutados, dias_pendientes >= 0
--   - dias_pendientes = dias_solicitados - dias_disfrutados (check suave;
--     no es constraint dura por flexibilidad en datos heredados).
--
-- Ejecutar después de 05_personal.sql.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Catálogo estatus_vacaciones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estatus_vacaciones (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(30)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    color       VARCHAR(30)  NOT NULL DEFAULT 'secondary',
    orden       SMALLINT     NOT NULL DEFAULT 99,
    descripcion TEXT,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO estatus_vacaciones (clave, nombre, color, orden, descripcion) VALUES
    ('PROGRAMADA',         'Programada',          'info',     10, 'Vacaciones planeadas, aún no inician.'),
    ('ACTIVA',             'En curso',            'warning',  20, 'El empleado está actualmente en vacaciones.'),
    ('FINALIZADA',         'Finalizada',          'success',  30, 'Vacaciones concluidas; el empleado ya regresó.'),
    ('CANCELADA',          'Cancelada',           'secondary',40, 'Las vacaciones no se tomaron.'),
    ('PENDIENTE_REVISION', 'Pendiente de revisar','danger',   50, 'Datos incompletos o dudosos, requieren revisión manual.')
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 2. Tabla vacaciones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vacaciones (
    id                  SERIAL PRIMARY KEY,
    personal_id         INTEGER NOT NULL REFERENCES personal(id) ON DELETE RESTRICT,

    -- Periodo (texto normalizado del Excel) + año
    -- Ejemplo: '1ER 2024', '2DO 2025'. Se acepta libre hasta 40 caracteres.
    periodo             VARCHAR(40),
    anio                SMALLINT,

    -- Texto libre original del Excel para referencia humana (p.ej. "DEL 25 AL 27 DE MARZO DE 2026")
    periodo_texto       TEXT,

    -- Fechas del descanso
    fecha_solicitud     DATE,        -- fecha de incidencia / solicitud (col "FECHA DE INCIDENCIA")
    fecha_inicio        DATE NOT NULL,
    fecha_fin           DATE NOT NULL,
    fecha_regreso       DATE,        -- "REGRESA A LABORAR"
    fecha_recibido_coord DATE,       -- "RECIBIDO POR LA COORDINACION"

    -- Días
    dias_solicitados    SMALLINT NOT NULL DEFAULT 0,
    dias_disfrutados    SMALLINT NOT NULL DEFAULT 0,
    dias_pendientes     SMALLINT NOT NULL DEFAULT 0,

    -- Folio de tesorería (texto; formato heredado del Excel)
    folio_tm            VARCHAR(60),

    -- Estatus
    estatus_id          INTEGER NOT NULL REFERENCES estatus_vacaciones(id),

    -- Captura
    capturado_por_user_id INTEGER REFERENCES usuarios(id),
    observaciones       TEXT,

    -- Control
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),

    -- CHECKs
    CONSTRAINT vac_fechas_chk      CHECK (fecha_fin >= fecha_inicio),
    CONSTRAINT vac_regreso_chk     CHECK (fecha_regreso IS NULL OR fecha_regreso >= fecha_fin),
    CONSTRAINT vac_dias_sol_chk    CHECK (dias_solicitados >= 0),
    CONSTRAINT vac_dias_dis_chk    CHECK (dias_disfrutados >= 0),
    CONSTRAINT vac_dias_pen_chk    CHECK (dias_pendientes  >= 0)
);

CREATE INDEX IF NOT EXISTS idx_vac_personal   ON vacaciones(personal_id);
CREATE INDEX IF NOT EXISTS idx_vac_estatus    ON vacaciones(estatus_id);
CREATE INDEX IF NOT EXISTS idx_vac_inicio     ON vacaciones(fecha_inicio);
CREATE INDEX IF NOT EXISTS idx_vac_anio       ON vacaciones(anio);

DROP TRIGGER IF EXISTS trg_vacaciones_updated_at ON vacaciones;
CREATE TRIGGER trg_vacaciones_updated_at
    BEFORE UPDATE ON vacaciones
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- Único: evitar cargar exactamente el mismo tramo para el mismo empleado.
CREATE UNIQUE INDEX IF NOT EXISTS idx_vac_unq_empleado_tramo
    ON vacaciones(personal_id, fecha_inicio, fecha_fin, COALESCE(periodo, ''));

-- ---------------------------------------------------------------------
-- 3. Staging import_vacaciones_raw
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_vacaciones_raw (
    id                  SERIAL PRIMARY KEY,
    fuente              VARCHAR(80) NOT NULL, -- 'BASE VACACIONES 2026.xlsx!VACACIONES 2025' etc
    fila_excel          INTEGER,

    raw_fecha_incidencia TEXT,
    raw_nombre           TEXT,
    raw_num_empleado     TEXT,
    raw_nomina           TEXT,
    raw_dias_justificar  TEXT,
    raw_periodo          TEXT,
    raw_dias_correspond  TEXT,
    raw_dias_otorgados   TEXT,
    raw_dias_pendientes  TEXT,
    raw_regresa          TEXT,
    raw_recibido_coord   TEXT,
    raw_folio_tm         TEXT,
    raw_data             JSONB,

    -- Derivados
    fecha_inicio_parsed  DATE,
    fecha_fin_parsed     DATE,

    procesado            BOOLEAN NOT NULL DEFAULT FALSE,
    vacaciones_id        INTEGER REFERENCES vacaciones(id),
    personal_id          INTEGER REFERENCES personal(id),
    accion               VARCHAR(20),  -- CREADO / ACTUALIZADO / OMITIDO
    estado_revision      VARCHAR(30) NOT NULL DEFAULT 'OK',
                              -- OK | PENDIENTE_REVISION | ERROR
    error_importacion    TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_imp_vac_procesado ON import_vacaciones_raw(procesado);
CREATE INDEX IF NOT EXISTS idx_imp_vac_estado    ON import_vacaciones_raw(estado_revision);

-- ---------------------------------------------------------------------
-- 4. Vistas de reporte
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_vacaciones_resumen AS
SELECT
    v.id,
    v.personal_id,
    p.numero_empleado,
    p.nombre_completo,
    tp.clave           AS tipo_personal_clave,
    tp.nombre          AS tipo_personal_nombre,
    v.periodo,
    v.periodo_texto,
    v.anio,
    v.fecha_solicitud,
    v.fecha_inicio,
    v.fecha_fin,
    v.fecha_regreso,
    v.fecha_recibido_coord,
    v.dias_solicitados,
    v.dias_disfrutados,
    v.dias_pendientes,
    v.folio_tm,
    ev.clave           AS estatus_clave,
    ev.nombre          AS estatus_nombre,
    ev.color           AS estatus_color,
    v.observaciones,
    u.nombre           AS capturo_nombre,
    v.created_at,
    v.updated_at,
    -- Estatus derivado por fecha actual (útil cuando estatus manual es stale).
    CASE
        WHEN v.fecha_inicio > CURRENT_DATE THEN 'PROGRAMADA'
        WHEN v.fecha_inicio <= CURRENT_DATE AND v.fecha_fin >= CURRENT_DATE THEN 'ACTIVA'
        ELSE 'FINALIZADA'
    END                AS estatus_derivado
FROM vacaciones v
JOIN personal        p  ON p.id  = v.personal_id
JOIN tipos_personal  tp ON tp.id = p.tipo_personal_id
JOIN estatus_vacaciones ev ON ev.id = v.estatus_id
LEFT JOIN usuarios   u  ON u.id  = v.capturado_por_user_id;

-- Vista unificada de errores de importación (personal, vacaciones y folios
-- internos sub). Útil para la pantalla admin "revisar pendientes".
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
SELECT 'vacaciones',
        iv.id,
        iv.fuente,
        iv.fila_excel,
        iv.estado_revision,
        iv.error_importacion,
        iv.created_at
  FROM  import_vacaciones_raw iv
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
-- Verificación rápida
-- =====================================================================
SELECT 'estatus_vacaciones'    AS tabla, COUNT(*) AS filas FROM estatus_vacaciones
UNION ALL
SELECT 'vacaciones',             COUNT(*) FROM vacaciones
UNION ALL
SELECT 'import_vacaciones_raw',  COUNT(*) FROM import_vacaciones_raw;
