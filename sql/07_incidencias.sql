-- =====================================================================
-- 07_incidencias.sql
-- Migracion aditiva para el MODULO INCIDENCIAS.
--
-- Fuente real: hoja 'INCIDENCIAS' de 'BASE GENERAL.xlsx'
-- Columnas detectadas: CONS. | FECHA DE INCIDENCIA | NO. DE EMPLEADO |
--   NOMBRE | S/C | DIA A JUSTIFICAR | E | S | DC | INC | MOTIVO |
--   RECIBIDO POR LA COORDINACION
--
-- Objetivo:
--   - Catalogo tipos_incidencia (OMISION_ENTRADA, OMISION_SALIDA, FALTA,
--     INCIDENCIA_GENERAL, COMISION, OTRO) con seeds derivados del
--     analisis real del Excel.
--   - Catalogo estatus_incidencia (REGISTRADA, JUSTIFICADA, NO_JUSTIFICADA,
--     CANCELADA, PENDIENTE_REVISION).
--   - Tabla incidencias relacionada con personal por FK.
--   - Staging import_incidencias_raw con columnas de control estandar.
--   - Vistas vw_incidencias_resumen, vw_incidencias_por_personal,
--     vw_incidencias_por_tipo.
--   - Extension (CREATE OR REPLACE) de vw_importacion_errores para
--     incluir import_incidencias_raw.
--
-- Reglas:
--   - fecha_fin >= fecha_inicio (si ambos)
--   - dias >= 0
--   - horas BETWEEN 0 AND 23
--   - minutos BETWEEN 0 AND 59
--
-- Ejecutar despues de 06_vacaciones.sql.
-- Migracion 100% idempotente (IF NOT EXISTS, ON CONFLICT DO NOTHING,
-- CREATE OR REPLACE).
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Catalogo tipos_incidencia
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_incidencia (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(40)  NOT NULL UNIQUE,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT,
    color       VARCHAR(30)  NOT NULL DEFAULT 'secondary',
    icono       VARCHAR(60),
    orden       SMALLINT     NOT NULL DEFAULT 99,
    activo      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

INSERT INTO tipos_incidencia (clave, nombre, descripcion, color, icono, orden) VALUES
    ('OMISION_ENTRADA',    'Omision de entrada',        'El empleado no registro su entrada en el reloj checador.',     'warning',   'fa-door-open',       10),
    ('OMISION_SALIDA',     'Omision de salida',         'El empleado no registro su salida en el reloj checador.',      'warning',   'fa-door-closed',     20),
    ('FALTA',              'Falta (dia completo)',      'Inasistencia de dia completo sin justificar previamente.',     'danger',    'fa-user-xmark',      30),
    ('INCIDENCIA_GENERAL', 'Incidencia general',        'Incidencia que no cae en entrada/salida/falta (marca INC).',   'info',      'fa-circle-exclamation', 40),
    ('COMISION',           'Comision / Apoyo externo',  'El empleado fue enviado a una comision o apoyo fuera de su sede habitual.', 'primary', 'fa-person-walking', 50),
    ('RETARDO',            'Retardo',                   'Llegada tarde al inicio de la jornada.',                       'warning',   'fa-clock',           60),
    ('PERMISO',            'Permiso',                   'Permiso laboral autorizado.',                                  'info',      'fa-hand',            70),
    ('INCAPACIDAD',        'Incapacidad medica',        'Incapacidad expedida por institucion medica.',                 'secondary', 'fa-hospital',        80),
    ('JUSTIFICACION',      'Justificacion',             'Documento justificativo de una incidencia previa.',            'success',   'fa-file-circle-check', 90),
    ('DIA_ECONOMICO',      'Dia economico',             'Dia economico tomado por el empleado.',                        'primary',   'fa-coins',          100),
    ('OTRO',               'Otro',                      'Cualquier otra incidencia no clasificada.',                    'dark',      'fa-circle-question',110)
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 2. Catalogo estatus_incidencia
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estatus_incidencia (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(30)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion TEXT,
    color_bs    VARCHAR(30)  NOT NULL DEFAULT 'secondary',
    orden       SMALLINT     NOT NULL DEFAULT 99,
    activo      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

INSERT INTO estatus_incidencia (clave, nombre, descripcion, color_bs, orden) VALUES
    ('REGISTRADA',         'Registrada',              'Incidencia registrada en el sistema, aun no se justifica.', 'info',      10),
    ('JUSTIFICADA',        'Justificada',             'Incidencia justificada formalmente.',                       'success',   20),
    ('NO_JUSTIFICADA',     'No justificada',          'Se decidio no justificar la incidencia.',                   'danger',    30),
    ('CANCELADA',          'Cancelada',               'Registro cancelado (no aplica).',                           'secondary', 40),
    ('PENDIENTE_REVISION', 'Pendiente de revisar',    'Datos incompletos o dudosos, requieren revision manual.',   'warning',   50)
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 3. Tabla incidencias
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incidencias (
    id                  SERIAL PRIMARY KEY,
    personal_id         INTEGER NOT NULL REFERENCES personal(id) ON DELETE RESTRICT,
    tipo_incidencia_id  INTEGER NOT NULL REFERENCES tipos_incidencia(id),
    estatus_id          INTEGER NOT NULL REFERENCES estatus_incidencia(id),

    -- Fechas
    fecha_incidencia    DATE,            -- FECHA DE INCIDENCIA
    fecha_inicio        DATE,            -- DIA A JUSTIFICAR (inicio)
    fecha_fin           DATE,            -- DIA A JUSTIFICAR (fin, o mismo que inicio)
    fecha_recibido_coord DATE,           -- RECIBIDO POR LA COORDINACION

    -- Periodo / clasificacion temporal
    periodo             VARCHAR(40),     -- texto libre: '1ER 2026', 'ABRIL 2026', etc
    anio                SMALLINT,
    mes                 SMALLINT,
    quincena            SMALLINT,        -- 1 o 2 (dentro del mes)

    -- Cantidades
    dias                SMALLINT NOT NULL DEFAULT 0,
    horas               SMALLINT NOT NULL DEFAULT 0,
    minutos             SMALLINT NOT NULL DEFAULT 0,

    -- Texto
    motivo              TEXT,            -- MOTIVO del Excel
    observaciones       TEXT,
    justificacion       TEXT,            -- texto de la justificacion formal

    -- Folio opcional del oficio justificativo
    folio_justificacion VARCHAR(60),

    -- Auditoria
    capturado_por_user_id INTEGER REFERENCES usuarios(id),
    fuente              VARCHAR(100),    -- 'BASE GENERAL.xlsx!INCIDENCIAS' o 'MANUAL'

    -- Control
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),

    -- CHECKs
    CONSTRAINT inc_fechas_chk   CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio),
    CONSTRAINT inc_dias_chk     CHECK (dias    >= 0 AND dias    <= 366),
    CONSTRAINT inc_horas_chk    CHECK (horas   >= 0 AND horas   <= 23),
    CONSTRAINT inc_minutos_chk  CHECK (minutos >= 0 AND minutos <= 59),
    CONSTRAINT inc_mes_chk      CHECK (mes IS NULL OR (mes BETWEEN 1 AND 12)),
    CONSTRAINT inc_quincena_chk CHECK (quincena IS NULL OR (quincena BETWEEN 1 AND 2))
);

CREATE INDEX IF NOT EXISTS idx_inc_personal  ON incidencias(personal_id);
CREATE INDEX IF NOT EXISTS idx_inc_tipo      ON incidencias(tipo_incidencia_id);
CREATE INDEX IF NOT EXISTS idx_inc_estatus   ON incidencias(estatus_id);
CREATE INDEX IF NOT EXISTS idx_inc_fecha     ON incidencias(fecha_incidencia);
CREATE INDEX IF NOT EXISTS idx_inc_anio_mes  ON incidencias(anio, mes);

-- Trigger updated_at (la funcion fn_updated_at ya existe desde 01_schema.sql)
DROP TRIGGER IF EXISTS trg_incidencias_updated_at ON incidencias;
CREATE TRIGGER trg_incidencias_updated_at
    BEFORE UPDATE ON incidencias
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- UNIQUE antiduplicado: mismo empleado, mismo tipo, mismas fechas.
CREATE UNIQUE INDEX IF NOT EXISTS idx_inc_unq
    ON incidencias (
        personal_id,
        tipo_incidencia_id,
        COALESCE(fecha_incidencia, DATE '1900-01-01'),
        COALESCE(fecha_inicio,     DATE '1900-01-01'),
        COALESCE(fecha_fin,        DATE '1900-01-01')
    );

-- ---------------------------------------------------------------------
-- 4. Staging import_incidencias_raw
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_incidencias_raw (
    id                  SERIAL PRIMARY KEY,
    fuente              VARCHAR(100) NOT NULL,
    fila_excel          INTEGER,

    -- Celdas RAW tal como vienen del Excel
    raw_cons            TEXT,
    raw_fecha_incid     TEXT,
    raw_num_empleado    TEXT,
    raw_nombre          TEXT,
    raw_sc              TEXT,   -- S/C
    raw_dia_justificar  TEXT,
    raw_e               TEXT,
    raw_s               TEXT,
    raw_dc              TEXT,
    raw_inc             TEXT,
    raw_motivo          TEXT,
    raw_recibido_coord  TEXT,
    raw_data            JSONB,

    -- Derivados
    fecha_incid_parsed   DATE,
    fecha_inicio_parsed  DATE,
    fecha_fin_parsed     DATE,
    fecha_recib_parsed   DATE,
    tipo_detectado       VARCHAR(40),    -- clave del tipo_incidencia

    -- Control estandar de staging
    procesado            BOOLEAN NOT NULL DEFAULT FALSE,
    incidencia_id        INTEGER REFERENCES incidencias(id),
    personal_id          INTEGER REFERENCES personal(id),
    accion               VARCHAR(30),        -- CREADO | OMITIDO_DUP | OMITIDO_VACIA | ERROR
    estado_revision      VARCHAR(30) NOT NULL DEFAULT 'OK'
                                   CHECK (estado_revision IN ('OK','PENDIENTE_REVISION','ERROR')),
    error_importacion    TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_imp_inc_procesado ON import_incidencias_raw(procesado);
CREATE INDEX IF NOT EXISTS idx_imp_inc_estado    ON import_incidencias_raw(estado_revision);

-- ---------------------------------------------------------------------
-- 5. Vistas de reporte
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_incidencias_resumen AS
SELECT
    i.id,
    i.personal_id,
    p.numero_empleado,
    p.nombre_completo,
    tp.clave             AS tipo_personal_clave,
    tp.nombre            AS tipo_personal_nombre,
    ti.id                AS tipo_incidencia_id,
    ti.clave             AS tipo_clave,
    ti.nombre            AS tipo_nombre,
    ti.color             AS tipo_color,
    ti.icono             AS tipo_icono,
    ei.id                AS estatus_id,
    ei.clave             AS estatus_clave,
    ei.nombre            AS estatus_nombre,
    ei.color_bs          AS estatus_color,
    i.fecha_incidencia,
    i.fecha_inicio,
    i.fecha_fin,
    i.fecha_recibido_coord,
    i.periodo,
    i.anio,
    i.mes,
    i.quincena,
    i.dias,
    i.horas,
    i.minutos,
    i.motivo,
    i.observaciones,
    i.justificacion,
    i.folio_justificacion,
    i.fuente,
    u.nombre             AS capturo_nombre,
    i.capturado_por_user_id,
    i.created_at,
    i.updated_at
FROM incidencias i
JOIN personal          p  ON p.id  = i.personal_id
JOIN tipos_personal    tp ON tp.id = p.tipo_personal_id
JOIN tipos_incidencia  ti ON ti.id = i.tipo_incidencia_id
JOIN estatus_incidencia ei ON ei.id = i.estatus_id
LEFT JOIN usuarios     u  ON u.id  = i.capturado_por_user_id;

-- Resumen por personal (ultimos 12 meses)
CREATE OR REPLACE VIEW vw_incidencias_por_personal AS
SELECT
    i.personal_id,
    p.numero_empleado,
    p.nombre_completo,
    COUNT(*)                                                        AS total,
    COUNT(*) FILTER (WHERE ei.clave = 'REGISTRADA')                 AS registradas,
    COUNT(*) FILTER (WHERE ei.clave = 'JUSTIFICADA')                AS justificadas,
    COUNT(*) FILTER (WHERE ei.clave = 'NO_JUSTIFICADA')             AS no_justificadas,
    COUNT(*) FILTER (WHERE ei.clave = 'CANCELADA')                  AS canceladas,
    COUNT(*) FILTER (WHERE ei.clave = 'PENDIENTE_REVISION')         AS pendientes,
    COUNT(*) FILTER (WHERE ti.clave = 'FALTA')                      AS faltas,
    COUNT(*) FILTER (WHERE ti.clave = 'OMISION_ENTRADA')            AS omision_entrada,
    COUNT(*) FILTER (WHERE ti.clave = 'OMISION_SALIDA')             AS omision_salida,
    COUNT(*) FILTER (WHERE ti.clave = 'RETARDO')                    AS retardos,
    COUNT(*) FILTER (WHERE ti.clave = 'COMISION')                   AS comisiones,
    MAX(i.fecha_incidencia)                                         AS ultima_fecha
FROM incidencias i
JOIN personal           p  ON p.id  = i.personal_id
JOIN tipos_incidencia   ti ON ti.id = i.tipo_incidencia_id
JOIN estatus_incidencia ei ON ei.id = i.estatus_id
GROUP BY i.personal_id, p.numero_empleado, p.nombre_completo;

-- Resumen por tipo (todas)
CREATE OR REPLACE VIEW vw_incidencias_por_tipo AS
SELECT
    ti.id            AS tipo_id,
    ti.clave,
    ti.nombre,
    ti.color,
    ti.icono,
    COUNT(i.id)      AS total,
    COUNT(i.id) FILTER (WHERE i.fecha_incidencia >= (CURRENT_DATE - INTERVAL '30 days'))  AS ultimos_30d,
    COUNT(i.id) FILTER (WHERE date_trunc('month', i.fecha_incidencia) = date_trunc('month', CURRENT_DATE)) AS mes_actual
FROM tipos_incidencia ti
LEFT JOIN incidencias i ON i.tipo_incidencia_id = ti.id
WHERE ti.activo = TRUE
GROUP BY ti.id, ti.clave, ti.nombre, ti.color, ti.icono, ti.orden
ORDER BY ti.orden;

-- ---------------------------------------------------------------------
-- 6. Extension de vw_importacion_errores (CREATE OR REPLACE)
--    Se agrega incidencias manteniendo personal/vacaciones/folios.
-- ---------------------------------------------------------------------
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
SELECT 'incidencias',
        ii.id,
        ii.fuente,
        ii.fila_excel,
        ii.estado_revision,
        ii.error_importacion,
        ii.created_at
  FROM  import_incidencias_raw ii
  WHERE ii.estado_revision <> 'OK' OR ii.error_importacion IS NOT NULL
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
-- Verificacion rapida
-- =====================================================================
SELECT 'tipos_incidencia'       AS tabla, COUNT(*) AS filas FROM tipos_incidencia
UNION ALL
SELECT 'estatus_incidencia',      COUNT(*) FROM estatus_incidencia
UNION ALL
SELECT 'incidencias',             COUNT(*) FROM incidencias
UNION ALL
SELECT 'import_incidencias_raw',  COUNT(*) FROM import_incidencias_raw;
