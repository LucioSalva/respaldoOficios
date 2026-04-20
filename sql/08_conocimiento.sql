-- =====================================================================
-- 08_conocimiento.sql
-- Migración aditiva para OFICIOS DE CONOCIMIENTO.
--
-- Fuente real: hoja 'OFICIOS DE CONOCIMIENTO' de BASE GENERAL.xlsx
-- (14 filas; 8 reales con status 'ARCHIVADO' + filas basura vacías).
--
-- Estrategia:
--   1. NO se crea tabla separada. Se extiende tipos_oficio con un 3er
--      tipo CONOCIMIENTO y con dos flags semánticos:
--          requiere_respuesta       BOOLEAN
--          requiere_pdf_contestado  BOOLEAN
--      Toda la lógica de UI/controladores los consulta, nunca hardcodea
--      "si clave=CONOCIMIENTO".
--   2. Se recrea el CHECK oficios_tipo_consistencia_chk para aceptar
--      las 3 ramas (EXTERNO / INTERNO / CONOCIMIENTO). Para CONOCIMIENTO
--      dependencia_id y area_interna_id son ambos OPCIONALES.
--   3. Se agrega el estado 'De Conocimiento' al catálogo estados_oficio
--      (color 'dark', orden 7) — el Excel marca todos como ARCHIVADO
--      pero semánticamente el flujo natural es archivado por ser solo
--      para conocimiento, así que aceptamos ambos estados.
--   4. Se crea staging import_oficios_conocimiento_raw con el patrón
--      estándar (raw_data JSONB, fila_excel, procesado, estado_revision,
--      accion, error_importacion).
--   5. Vistas:
--        vw_oficios_por_tipo
--        vw_oficios_conocimiento
--        vw_import_oficios_conocimiento_errores
--      y extensión (CREATE OR REPLACE) de vw_importacion_errores.
--
-- Compatibilidad:
--   - Los 203 oficios legacy son EXTERNO → quedan requiere_respuesta=TRUE,
--     requiere_pdf_contestado=TRUE. No se rompen.
--   - El CHECK se recrea como NOT VALID primero y luego se VALIDA para
--     detectar cualquier fila huérfana. Todos los EXTERNO pasan la rama
--     "tipo_oficio_id = EXTERNO".
--
-- Idempotente: IF NOT EXISTS, ON CONFLICT DO NOTHING, DROP ... IF EXISTS.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Flags en tipos_oficio
-- ---------------------------------------------------------------------
ALTER TABLE tipos_oficio
    ADD COLUMN IF NOT EXISTS requiere_respuesta      BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS requiere_pdf_contestado BOOLEAN NOT NULL DEFAULT TRUE;

-- Backfill explícito por seguridad: los tipos ya existentes reciben el
-- valor correcto aunque ya tuvieran el DEFAULT aplicado.
-- EXTERNO: requiere respuesta + PDF contestado (flujo tradicional).
-- INTERNO: requiere respuesta PERO no siempre se adjunta PDF contestado
--          (el acuse de oficialía basta; análisis de FOLIOS INTERNO SUB).
UPDATE tipos_oficio SET requiere_respuesta = TRUE,  requiere_pdf_contestado = TRUE  WHERE clave = 'EXTERNO';
UPDATE tipos_oficio SET requiere_respuesta = TRUE,  requiere_pdf_contestado = FALSE WHERE clave = 'INTERNO';

-- ---------------------------------------------------------------------
-- 2. Seed CONOCIMIENTO
-- ---------------------------------------------------------------------
INSERT INTO tipos_oficio (clave, nombre, descripcion, requiere_respuesta, requiere_pdf_contestado)
VALUES
    ('CONOCIMIENTO',
     'Oficio de Conocimiento',
     'Oficio recibido solo para enterar / conocimiento. No requiere contestación ni oficio de respuesta.',
     FALSE, FALSE)
ON CONFLICT (clave) DO UPDATE
   SET nombre                  = EXCLUDED.nombre,
       descripcion             = EXCLUDED.descripcion,
       requiere_respuesta      = FALSE,
       requiere_pdf_contestado = FALSE;

-- ---------------------------------------------------------------------
-- 3. Estado 'De Conocimiento' en estados_oficio
-- ---------------------------------------------------------------------
-- 'Archivado' ya existe en el seed base. Agregamos 'De Conocimiento'
-- con color dark y orden 7 (va después de Archivado).
INSERT INTO estados_oficio (nombre, color, orden)
VALUES ('De Conocimiento', 'dark', 7)
ON CONFLICT (nombre) DO NOTHING;

-- ---------------------------------------------------------------------
-- 4. Recrear CHECK oficios_tipo_consistencia_chk para las 3 ramas
-- ---------------------------------------------------------------------
-- Regla:
--   EXTERNO       → sin restricción de FK adicional (la obligatoriedad
--                   de dependencia_id la impone el controlador PHP).
--   INTERNO       → area_interna_id NOT NULL.
--   CONOCIMIENTO  → sin restricción (dependencia_id y area_interna_id
--                   son ambos OPCIONALES).
DO $$
DECLARE
    v_ext INT;
    v_int INT;
    v_con INT;
BEGIN
    SELECT id INTO v_ext FROM tipos_oficio WHERE clave = 'EXTERNO';
    SELECT id INTO v_int FROM tipos_oficio WHERE clave = 'INTERNO';
    SELECT id INTO v_con FROM tipos_oficio WHERE clave = 'CONOCIMIENTO';

    IF v_ext IS NULL OR v_int IS NULL OR v_con IS NULL THEN
        RAISE EXCEPTION 'No se encontraron los 3 tipos de oficio requeridos';
    END IF;

    -- Drop del CHECK previo (04_internos.sql lo dejó VALIDATED con 2 ramas)
    ALTER TABLE oficios DROP CONSTRAINT IF EXISTS oficios_tipo_consistencia_chk;

    -- Recrear con las 3 ramas
    EXECUTE format(
        'ALTER TABLE oficios ADD CONSTRAINT oficios_tipo_consistencia_chk '
     || 'CHECK ( tipo_oficio_id = %s '
     || '     OR (tipo_oficio_id = %s AND area_interna_id IS NOT NULL) '
     || '     OR  tipo_oficio_id = %s ) NOT VALID',
        v_ext, v_int, v_con
    );
END$$;

-- Validar la CHECK con todas las filas existentes (203 EXTERNO + N INTERNO).
ALTER TABLE oficios VALIDATE CONSTRAINT oficios_tipo_consistencia_chk;

-- ---------------------------------------------------------------------
-- 5. Staging import_oficios_conocimiento_raw
--    Todas las columnas como TEXT para preservar datos tal cual.
--    Incluye raw_data JSONB con la fila completa para trazabilidad.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_oficios_conocimiento_raw (
    id                  SERIAL PRIMARY KEY,
    fuente              VARCHAR(120) NOT NULL DEFAULT 'BASE GENERAL.xlsx!OFICIOS DE CONOCIMIENTO',
    fila_excel          INTEGER,

    -- Celdas RAW tal como vienen del Excel
    raw_cons            TEXT,
    raw_fecha_recibido  TEXT,
    raw_nombre_direccion TEXT,
    raw_folio_envia     TEXT,
    raw_asunto          TEXT,
    raw_folio_tics      TEXT,
    raw_realizo         TEXT,
    raw_acuse_recibido  TEXT,
    raw_status          TEXT,
    raw_data            JSONB,

    -- Derivados (parseo)
    fecha_recibido_parsed DATE,
    fecha_acuse_parsed    DATE,
    dependencia_id_match  INTEGER REFERENCES dependencias(id),
    estado_id_match       INTEGER REFERENCES estados_oficio(id),

    -- Control estándar de staging
    procesado           BOOLEAN NOT NULL DEFAULT FALSE,
    oficio_id           INTEGER REFERENCES oficios(id),
    accion              VARCHAR(30),   -- CREADO | OMITIDO_DUP | OMITIDO_VACIA | ERROR
    estado_revision     VARCHAR(30) NOT NULL DEFAULT 'OK'
                                CHECK (estado_revision IN ('OK','PENDIENTE_REVISION','ERROR')),
    error_importacion   TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_imp_conoc_procesado ON import_oficios_conocimiento_raw(procesado);
CREATE INDEX IF NOT EXISTS idx_imp_conoc_estado    ON import_oficios_conocimiento_raw(estado_revision);

-- ---------------------------------------------------------------------
-- 6. Vistas de reporte
-- ---------------------------------------------------------------------

-- Conteo por tipo de oficio (dashboard)
CREATE OR REPLACE VIEW vw_oficios_por_tipo AS
SELECT
    t.id                     AS tipo_id,
    t.clave                  AS tipo_clave,
    t.nombre                 AS tipo_nombre,
    t.requiere_respuesta,
    t.requiere_pdf_contestado,
    COUNT(o.id)              AS total,
    COUNT(o.id) FILTER (WHERE e.nombre = 'Archivado')      AS archivados,
    COUNT(o.id) FILTER (WHERE e.nombre = 'De Conocimiento') AS de_conocimiento,
    COUNT(o.id) FILTER (WHERE e.nombre = 'Contestado')     AS contestados,
    COUNT(o.id) FILTER (
        WHERE t.requiere_respuesta = TRUE
          AND e.nombre NOT IN ('Contestado','Archivado','De Conocimiento')
    )                        AS pendientes_por_responder
FROM tipos_oficio t
LEFT JOIN oficios        o ON o.tipo_oficio_id = t.id
LEFT JOIN estados_oficio e ON e.id = o.estado_id
WHERE t.activo = TRUE
GROUP BY t.id, t.clave, t.nombre, t.requiere_respuesta, t.requiere_pdf_contestado
ORDER BY t.clave;

-- Listado de oficios CONOCIMIENTO con joins
CREATE OR REPLACE VIEW vw_oficios_conocimiento AS
SELECT
    o.id,
    o.folio_tesoreria,
    o.folio_direccion,
    o.asunto,
    o.observaciones,
    o.fecha_recepcion,
    o.fecha_acuse,
    o.created_at,
    o.updated_at,
    t.clave                AS tipo_clave,
    t.nombre               AS tipo_nombre,
    d.id                   AS dependencia_id,
    d.nombre               AS dependencia_nombre,
    d.clave                AS dependencia_clave,
    e.id                   AS estado_id,
    e.nombre               AS estado_nombre,
    e.color                AS estado_color,
    uc.nombre              AS capturo_nombre
FROM oficios o
JOIN tipos_oficio    t  ON t.id  = o.tipo_oficio_id
LEFT JOIN dependencias   d  ON d.id  = o.dependencia_id
LEFT JOIN estados_oficio e  ON e.id  = o.estado_id
LEFT JOIN usuarios       uc ON uc.id = o.usuario_capturo_id
WHERE t.clave = 'CONOCIMIENTO';

-- Errores de staging de conocimiento
CREATE OR REPLACE VIEW vw_import_oficios_conocimiento_errores AS
SELECT
    ic.id              AS staging_id,
    ic.fuente,
    ic.fila_excel,
    ic.raw_fecha_recibido,
    ic.raw_nombre_direccion,
    ic.raw_folio_envia,
    ic.raw_asunto,
    ic.raw_status,
    ic.estado_revision,
    ic.accion,
    ic.error_importacion,
    ic.created_at
FROM import_oficios_conocimiento_raw ic
WHERE ic.estado_revision <> 'OK'
   OR ic.error_importacion IS NOT NULL
ORDER BY ic.fila_excel;

-- ---------------------------------------------------------------------
-- 6.1 Fix de folio_display en v_oficios_completo
--
-- Problema detectado: folio_tesoreria es una GENERATED column que aplica
-- LPAD(numero_folio::text, 4, '0'). Con CONOCIMIENTO usando rango 20000+,
-- LPAD TRUNCA a 4 chars y produce colisiones ('2000' para 20000..20009).
-- INTERNO tampoco se ve afectado visualmente porque la UI usa
-- folio_interno_texto vía folio_display.
--
-- Solución: extender folio_display para que CONOCIMIENTO muestre
-- folio_direccion (el folio del emisor) cuando exista, o un folio
-- sintético 'CON/numero_folio/anio' en su defecto. Se preserva el
-- comportamiento legacy para EXTERNO/INTERNO.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_oficios_completo AS
SELECT o.id,
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
       t.clave  AS tipo_oficio_clave,
       t.nombre AS tipo_oficio_nombre,
       o.dependencia_id,
       d.nombre AS dependencia_nombre,
       d.clave  AS dependencia_clave,
       o.area_interna_id,
       ai.nombre AS area_interna_nombre,
       ai.clave  AS area_interna_clave,
       o.estado_id,
       e.nombre AS estado_nombre,
       e.color  AS estado_color,
       uc.nombre AS capturo_nombre,
       ur.nombre AS responsable_nombre,
       CASE
           -- INTERNO: prioriza folio_interno_texto
           WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_interno_texto,'') <> ''
                THEN o.folio_interno_texto
           WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_direccion,'') <> ''
                THEN o.folio_direccion
           -- CONOCIMIENTO: usa folio_direccion del emisor o sintetiza
           WHEN t.clave = 'CONOCIMIENTO' AND COALESCE(o.folio_direccion,'') <> ''
                THEN o.folio_direccion
           WHEN t.clave = 'CONOCIMIENTO'
                THEN 'CON/' || o.numero_folio::text || '/' || o.anio_folio::text
           ELSE o.folio_tesoreria
       END AS folio_display,
       ( SELECT count(*) FROM movimientos_oficio m WHERE m.oficio_id = o.id) AS total_movimientos,
       ( SELECT count(*) FROM evidencias_pdf ep    WHERE ep.oficio_id = o.id) AS total_evidencias
  FROM oficios o
  LEFT JOIN tipos_oficio    t  ON t.id  = o.tipo_oficio_id
  LEFT JOIN dependencias    d  ON d.id  = o.dependencia_id
  LEFT JOIN areas_internas  ai ON ai.id = o.area_interna_id
  LEFT JOIN estados_oficio  e  ON e.id  = o.estado_id
  LEFT JOIN usuarios        uc ON uc.id = o.usuario_capturo_id
  LEFT JOIN usuarios        ur ON ur.id = o.usuario_responsable_id;

-- ---------------------------------------------------------------------
-- 7. Extensión de vw_importacion_errores (CREATE OR REPLACE)
--    Se agrega conocimiento manteniendo personal/vacaciones/incidencias/
--    folios_interno_sub.
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
  WHERE fs.error_mensaje IS NOT NULL
UNION ALL
SELECT 'oficios_conocimiento',
        ic.id,
        ic.fuente,
        ic.fila_excel,
        ic.estado_revision,
        ic.error_importacion,
        ic.created_at
  FROM  import_oficios_conocimiento_raw ic
  WHERE ic.estado_revision <> 'OK' OR ic.error_importacion IS NOT NULL;

COMMIT;

-- =====================================================================
-- Verificación rápida
-- =====================================================================
SELECT 'tipos_oficio'                       AS tabla, COUNT(*) AS filas FROM tipos_oficio
UNION ALL
SELECT 'tipos_oficio_conocimiento',           COUNT(*) FROM tipos_oficio WHERE clave='CONOCIMIENTO'
UNION ALL
SELECT 'estados_oficio_de_conocimiento',      COUNT(*) FROM estados_oficio WHERE nombre='De Conocimiento'
UNION ALL
SELECT 'oficios_total',                       COUNT(*) FROM oficios
UNION ALL
SELECT 'oficios_conocimiento',                COUNT(*) FROM oficios o
       JOIN tipos_oficio t ON t.id = o.tipo_oficio_id WHERE t.clave='CONOCIMIENTO'
UNION ALL
SELECT 'import_oficios_conocimiento_raw',     COUNT(*) FROM import_oficios_conocimiento_raw;
