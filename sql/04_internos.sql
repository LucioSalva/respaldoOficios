-- =====================================================================
-- 04_internos.sql
-- Migración aditiva para dar soporte a OFICIOS INTERNOS
-- (hoja "FOLIOS INTERNO SUB" del Excel BASE GENERAL.xlsx).
--
-- Estrategia:
--   - Se agrega catálogo tipos_oficio (EXTERNO / INTERNO).
--   - Se agrega catálogo areas_internas (áreas internas de Tesorería).
--   - Se agregan columnas a oficios: tipo_oficio_id, area_interna_id,
--     folio_interno_texto.
--   - Se respeta folio_tesoreria GENERATED (no se altera).
--     Para oficios INTERNOS cuyo folio no cumple el formato TM/ECA/STIyC
--     se usa folio_interno_texto y la vista expone folio_display.
--   - Backfill: los 203 oficios existentes quedan como EXTERNO.
--   - CHECK: si tipo = INTERNO => area_interna_id NOT NULL.
--            si tipo = EXTERNO => dependencia_id NOT NULL.
--   - Se crea staging table import_folios_interno_sub_raw (todo TEXT).
--   - Se refresca la vista v_oficios_completo para exponer tipo y folio_display.
--
-- Ejecutar una sola vez.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Catálogo tipos_oficio
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_oficio (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(20)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion TEXT,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO tipos_oficio (clave, nombre, descripcion)
VALUES
    ('EXTERNO', 'Oficio Externo',
     'Oficios recibidos de otras dependencias externas al H. Ayuntamiento de Tesorería.'),
    ('INTERNO', 'Oficio Interno',
     'Oficios generados al interior de la Tesorería Municipal (áreas internas).')
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 2. Catálogo areas_internas
--    Seed basado en análisis de la hoja FOLIOS INTERNO SUB.
--    Se normalizan duplicados por acento (SUBDIRECCION / SUBDIRECCIÓN).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS areas_internas (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(255) NOT NULL UNIQUE,
    clave       VARCHAR(30),
    descripcion TEXT,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

DROP TRIGGER IF EXISTS trg_areas_internas_updated_at ON areas_internas;
CREATE TRIGGER trg_areas_internas_updated_at
    BEFORE UPDATE ON areas_internas
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

INSERT INTO areas_internas (nombre, clave) VALUES
    ('TESORERÍA MUNICIPAL',                               'TM'),
    ('COORDINACIÓN ADMINISTRATIVA DE TESORERÍA',          'CAT'),
    ('SUBDIRECCIÓN DE EGRESOS',                           'SE'),
    ('SUBDIRECCIÓN DE JURÍDICO DE TESORERÍA',             'SJT'),
    ('SUBDIRECCIÓN DE PROGRAMAS FEDERALES Y ESTATALES',   'SPFYE'),
    ('DEPARTAMENTO DE CONTROL DE CAJAS Y RECEPTORÍAS EXTERNAS', 'DCCRE'),
    ('COORDINACIÓN DE OFICIALÍAS DE REGISTRO CIVIL',      'CORC')
ON CONFLICT (nombre) DO NOTHING;

-- ---------------------------------------------------------------------
-- 3. Modificación de oficios
-- ---------------------------------------------------------------------
ALTER TABLE oficios
    ADD COLUMN IF NOT EXISTS tipo_oficio_id       INTEGER,
    ADD COLUMN IF NOT EXISTS area_interna_id      INTEGER,
    ADD COLUMN IF NOT EXISTS folio_interno_texto  VARCHAR(100);

-- Backfill: todos los oficios existentes quedan como EXTERNO.
UPDATE oficios o
   SET tipo_oficio_id = (SELECT id FROM tipos_oficio WHERE clave = 'EXTERNO')
 WHERE o.tipo_oficio_id IS NULL;

-- Ahora sí, NOT NULL + default + FK
ALTER TABLE oficios
    ALTER COLUMN tipo_oficio_id SET NOT NULL;

-- DEFAULT apuntando al id literal de EXTERNO (PostgreSQL no acepta subquery en DEFAULT).
DO $$
DECLARE
    v_id INT;
BEGIN
    SELECT id INTO v_id FROM tipos_oficio WHERE clave = 'EXTERNO';
    EXECUTE format('ALTER TABLE oficios ALTER COLUMN tipo_oficio_id SET DEFAULT %s', v_id);
END$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
         WHERE conname = 'oficios_tipo_oficio_fk'
    ) THEN
        ALTER TABLE oficios
            ADD CONSTRAINT oficios_tipo_oficio_fk
            FOREIGN KEY (tipo_oficio_id) REFERENCES tipos_oficio(id);
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
         WHERE conname = 'oficios_area_interna_fk'
    ) THEN
        ALTER TABLE oficios
            ADD CONSTRAINT oficios_area_interna_fk
            FOREIGN KEY (area_interna_id) REFERENCES areas_internas(id);
    END IF;
END$$;

-- CHECK constraint coherencia tipo ↔ referencia.
-- Regla: si tipo INTERNO => area_interna_id NOT NULL (obligatorio).
--        si tipo EXTERNO => dependencia_id puede ser NULL (legado histórico:
--                           hay 18 oficios importados sin dependencia asignada).
-- La obligatoriedad de dependencia en NUEVOS externos la impone el controlador PHP.
-- NOT VALID: no re-valida registros existentes (protegemos 18 legacy).
DO $$
DECLARE
    v_ext INT;
    v_int INT;
BEGIN
    SELECT id INTO v_ext FROM tipos_oficio WHERE clave = 'EXTERNO';
    SELECT id INTO v_int FROM tipos_oficio WHERE clave = 'INTERNO';

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'oficios_tipo_consistencia_chk'
    ) THEN
        EXECUTE format(
            'ALTER TABLE oficios ADD CONSTRAINT oficios_tipo_consistencia_chk '
         || 'CHECK ( tipo_oficio_id = %s OR '
         || '        (tipo_oficio_id = %s AND area_interna_id IS NOT NULL) ) NOT VALID',
            v_ext, v_int
        );
    END IF;
END$$;

-- Validamos el CHECK: pasa porque todos los 203 son EXTERNO.
ALTER TABLE oficios VALIDATE CONSTRAINT oficios_tipo_consistencia_chk;

-- Índices
CREATE INDEX IF NOT EXISTS idx_oficios_tipo       ON oficios(tipo_oficio_id);
CREATE INDEX IF NOT EXISTS idx_oficios_area_int   ON oficios(area_interna_id);
CREATE INDEX IF NOT EXISTS idx_oficios_folio_int  ON oficios(folio_interno_texto);

-- ---------------------------------------------------------------------
-- 4. Staging para importación de la hoja FOLIOS INTERNO SUB
--    Todas las columnas como TEXT para preservar datos tal cual.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_folios_interno_sub_raw (
    id                  SERIAL PRIMARY KEY,
    fila_excel          INTEGER,
    fecha_recibido      TEXT,
    folio_minutario     TEXT,
    nombre_direccion    TEXT,
    folio_direccion     TEXT,
    asunto              TEXT,
    fecha_oficio_tics   TEXT,
    folio_oficio_tics   TEXT,
    realizo             TEXT,
    acuse_recibido      TEXT,
    status              TEXT,
    capturo             TEXT,
    procesado           BOOLEAN NOT NULL DEFAULT FALSE,
    oficio_id           INTEGER REFERENCES oficios(id),
    error_mensaje       TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 5. Recrear vista v_oficios_completo con columnas nuevas
-- ---------------------------------------------------------------------
DROP VIEW IF EXISTS v_oficios_completo;

CREATE VIEW v_oficios_completo AS
SELECT
    o.id,
    o.folio_tesoreria,
    o.numero_folio,
    o.anio_folio,
    o.folio_minutario,
    o.folio_direccion,
    o.folio_interno_texto,
    o.asunto,
    o.descripcion,
    o.observaciones,
    o.fecha_recepcion,
    o.fecha_oficio_tics,
    o.fecha_acuse,
    o.fecha_compromiso,
    o.fecha_resolucion,
    o.realizo,
    o.created_at,
    o.updated_at,
    o.tipo_oficio_id,
    t.clave   AS tipo_oficio_clave,
    t.nombre  AS tipo_oficio_nombre,
    o.dependencia_id,
    d.nombre  AS dependencia_nombre,
    d.clave   AS dependencia_clave,
    o.area_interna_id,
    ai.nombre AS area_interna_nombre,
    ai.clave  AS area_interna_clave,
    o.estado_id,
    e.nombre  AS estado_nombre,
    e.color   AS estado_color,
    uc.nombre AS capturo_nombre,
    ur.nombre AS responsable_nombre,
    -- Folio de presentación: para INTERNO usa folio_interno_texto si existe,
    -- si no, usa folio_direccion; si no, usa folio_tesoreria.
    CASE
        WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_interno_texto, '') <> ''
             THEN o.folio_interno_texto
        WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_direccion, '') <> ''
             THEN o.folio_direccion
        ELSE o.folio_tesoreria
    END AS folio_display,
    (SELECT COUNT(*) FROM movimientos_oficio m WHERE m.oficio_id = o.id) AS total_movimientos,
    (SELECT COUNT(*) FROM evidencias_pdf ep WHERE ep.oficio_id = o.id)   AS total_evidencias
FROM oficios o
LEFT JOIN tipos_oficio    t  ON t.id  = o.tipo_oficio_id
LEFT JOIN dependencias    d  ON d.id  = o.dependencia_id
LEFT JOIN areas_internas  ai ON ai.id = o.area_interna_id
LEFT JOIN estados_oficio  e  ON e.id  = o.estado_id
LEFT JOIN usuarios        uc ON uc.id = o.usuario_capturo_id
LEFT JOIN usuarios        ur ON ur.id = o.usuario_responsable_id;

COMMIT;

-- =====================================================================
-- Verificación rápida
-- =====================================================================
SELECT 'tipos_oficio' AS tabla, COUNT(*) AS filas FROM tipos_oficio
UNION ALL
SELECT 'areas_internas',            COUNT(*) FROM areas_internas
UNION ALL
SELECT 'oficios_total',             COUNT(*) FROM oficios
UNION ALL
SELECT 'oficios_externos',          COUNT(*) FROM oficios o
       JOIN tipos_oficio t ON t.id = o.tipo_oficio_id WHERE t.clave = 'EXTERNO'
UNION ALL
SELECT 'oficios_internos',          COUNT(*) FROM oficios o
       JOIN tipos_oficio t ON t.id = o.tipo_oficio_id WHERE t.clave = 'INTERNO'
UNION ALL
SELECT 'import_folios_interno_sub_raw', COUNT(*) FROM import_folios_interno_sub_raw;
