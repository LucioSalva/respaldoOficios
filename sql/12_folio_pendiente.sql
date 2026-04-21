-- =====================================================================
-- 12_folio_pendiente.sql
-- Permite registrar oficios SIN número de folio de Tesorería
-- (queda como "pendiente" hasta que se captura después).
--
-- Cambios:
--   1. oficios.numero_folio pasa a NULLABLE.
--   2. folio_tesoreria (GENERATED STORED) se reemplaza para devolver
--      'TM/ECA/STIyC/PENDIENTE/<anio>' cuando numero_folio IS NULL.
--      (Postgres no permite ALTER sobre GENERATED STORED: DROP + ADD.)
--   3. UNIQUE de folio_tesoreria se vuelve parcial (solo cuando el
--      número ya fue asignado); esto permite múltiples pendientes por año.
--   4. uq_numero_anio_folio se mantiene (Postgres trata NULL como distinto
--      por defecto en UNIQUE; múltiples (NULL, anio) coexisten sin chocar).
--   5. Se recrean las vistas dependientes.
--
-- Idempotente. Transaccional.
-- =====================================================================

BEGIN;

-- Vistas dependientes de folio_tesoreria
DROP VIEW IF EXISTS v_oficios_completo         CASCADE;
DROP VIEW IF EXISTS vw_oficios_conocimiento    CASCADE;
DROP VIEW IF EXISTS vw_oficios_por_tipo        CASCADE;

-- Reemplazo de la columna GENERATED
ALTER TABLE oficios DROP COLUMN IF EXISTS folio_tesoreria;

-- Permitir NULL en numero_folio
ALTER TABLE oficios ALTER COLUMN numero_folio DROP NOT NULL;

-- Re-crear folio_tesoreria soportando el estado "PENDIENTE"
ALTER TABLE oficios
    ADD COLUMN folio_tesoreria VARCHAR(60)
    GENERATED ALWAYS AS (
        CASE
            WHEN numero_folio IS NULL
                THEN 'TM/ECA/STIyC/PENDIENTE/' || anio_folio::TEXT
            WHEN numero_folio < 10000
                THEN 'TM/ECA/STIyC/' || LPAD(numero_folio::TEXT, 4, '0') || '/' || anio_folio::TEXT
            ELSE 'TM/ECA/STIyC/' || numero_folio::TEXT || '/' || anio_folio::TEXT
        END
    ) STORED;

CREATE INDEX IF NOT EXISTS idx_oficios_folio_ts ON oficios(folio_tesoreria);

-- UNIQUE parcial: solo aplica cuando ya hay número asignado.
DROP INDEX IF EXISTS uq_oficios_folio_tesoreria;
CREATE UNIQUE INDEX IF NOT EXISTS uq_oficios_folio_tesoreria
    ON oficios(folio_tesoreria)
    WHERE numero_folio IS NOT NULL;

-- Recrear v_oficios_completo
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
           WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_interno_texto,'') <> ''
                THEN o.folio_interno_texto
           WHEN t.clave = 'INTERNO' AND COALESCE(o.folio_direccion,'') <> ''
                THEN o.folio_direccion
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

CREATE OR REPLACE VIEW vw_oficios_por_tipo AS
SELECT
    t.id                     AS tipo_id,
    t.clave                  AS tipo_clave,
    t.nombre                 AS tipo_nombre,
    t.requiere_respuesta,
    t.requiere_pdf_contestado,
    COUNT(o.id)              AS total,
    COUNT(o.id) FILTER (WHERE e.nombre = 'Archivado')       AS archivados,
    COUNT(o.id) FILTER (WHERE e.nombre = 'De Conocimiento') AS de_conocimiento,
    COUNT(o.id) FILTER (WHERE e.nombre = 'Contestado')      AS contestados,
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

COMMIT;

-- Verificación
SELECT 'numero_folio_nullable' AS metrica,
       CASE WHEN is_nullable = 'YES' THEN 'OK' ELSE 'FALLA' END AS valor
  FROM information_schema.columns
 WHERE table_name = 'oficios' AND column_name = 'numero_folio'
UNION ALL
SELECT 'oficios_pendientes_folio',
       COUNT(*)::text FROM oficios WHERE numero_folio IS NULL;
