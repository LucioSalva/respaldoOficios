-- =====================================================================
-- 09_fix_folio_y_trazabilidad.sql
-- Remediación QA Extrema — FASE 1 (bloqueadores C1, C4)
--
-- Contenido:
--   1. Reemplazo de oficios.folio_tesoreria GENERATED STORED para que NO
--      trunque números >=10000 (CONOCIMIENTO 20000+, INTERNO 10000-19999).
--        Antes: LPAD(numero_folio::TEXT, 4, '0')  -> truncaba visualmente
--        Ahora: CASE WHEN numero_folio < 10000
--                    THEN LPAD(numero_folio::TEXT,4,'0')
--                    ELSE numero_folio::TEXT END
--   2. Recreación de vistas dependientes (DROP CASCADE controlado).
--   3. UNIQUE INDEX sobre folio_tesoreria (defensa en profundidad).
--   4. Repair C4.1: INTERNOs sin movimiento inicial -> se generan.
--   5. Repair C4.2: EXTERNOs con dependencia_id NULL -> se asignan
--      a dependencia 'NO IDENTIFICADA / PENDIENTE DE REVISIÓN' y se
--      prefijan observaciones con [PENDIENTE_REVISION_DEPENDENCIA].
--   6. CHECK oficios_tipo_consistencia_chk queda validado.
--
-- Idempotente. Transaccional. No destruye datos.
--
-- PREREQUISITO (se documenta, NO se ejecuta desde SQL):
--   pg_dump -h localhost -U postgres respaldooficios > backup_qa_fixes.sql
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Recrear folio_tesoreria sin truncamiento
--    PostgreSQL no permite ALTER sobre GENERATED STORED; la única vía
--    segura es DROP + ADD de la columna. Antes hay que eliminar vistas
--    dependientes y recrearlas al final.
--    Diagnóstico en BD real: 51 filas con folio visualmente duplicado.
-- ---------------------------------------------------------------------

-- 1.1 Vistas que dependen de oficios.folio_tesoreria (se recrean al final)
DROP VIEW IF EXISTS v_oficios_completo         CASCADE;
DROP VIEW IF EXISTS vw_oficios_conocimiento    CASCADE;
DROP VIEW IF EXISTS vw_oficios_por_tipo        CASCADE;

-- 1.2 Drop + re-add de la columna con la nueva expresión
ALTER TABLE oficios DROP COLUMN IF EXISTS folio_tesoreria;

ALTER TABLE oficios
    ADD COLUMN folio_tesoreria VARCHAR(60)
    GENERATED ALWAYS AS (
        'TM/ECA/STIyC/' ||
        CASE
            WHEN numero_folio < 10000 THEN LPAD(numero_folio::TEXT, 4, '0')
            ELSE numero_folio::TEXT
        END
        || '/' || anio_folio::TEXT
    ) STORED;

-- 1.3 Índices sobre la columna nueva
CREATE INDEX IF NOT EXISTS idx_oficios_folio_ts ON oficios(folio_tesoreria);

-- 1.4 UNIQUE index defensivo (evita duplicados visuales a futuro)
CREATE UNIQUE INDEX IF NOT EXISTS uq_oficios_folio_tesoreria
    ON oficios(folio_tesoreria);

-- ---------------------------------------------------------------------
-- 2. Recrear vistas afectadas con la misma semántica de 08_conocimiento.sql
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
       -- folio_display: ahora folio_tesoreria es fuente de verdad para EXTERNO y
       -- CONOCIMIENTO; solo INTERNO mantiene la preferencia por folio_interno_texto.
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

-- ---------------------------------------------------------------------
-- 3. Repair C4.1 — INTERNOs sin movimiento inicial
--    Se detectaron 42 INTERNOs huérfanos en movimientos_oficio.
--    Generamos un movimiento de trazabilidad sintético con:
--       estado_anterior_id = NULL
--       estado_nuevo_id    = oficio.estado_id
--       usuario_id         = NULL
--       fecha              = oficios.created_at
--       observacion        = 'Movimiento generado automáticamente por reparación de trazabilidad.'
-- ---------------------------------------------------------------------
INSERT INTO movimientos_oficio
    (oficio_id, estado_anterior_id, estado_nuevo_id, observacion, usuario_id, fecha, created_at)
SELECT o.id,
       NULL,
       o.estado_id,
       'Movimiento generado automáticamente por reparación de trazabilidad.',
       NULL,
       o.created_at,
       NOW()
FROM oficios o
LEFT JOIN movimientos_oficio m ON m.oficio_id = o.id
WHERE m.id IS NULL;

-- ---------------------------------------------------------------------
-- 4. Repair C4.2 — EXTERNOs sin dependencia
--    1) Crear dependencia 'NO IDENTIFICADA / PENDIENTE DE REVISIÓN' si no existe.
--    2) Asignarla a los 18 EXTERNOs que tienen dependencia_id IS NULL.
--    3) Prefijar observaciones con [PENDIENTE_REVISION_DEPENDENCIA].
-- ---------------------------------------------------------------------
INSERT INTO dependencias (nombre, clave, activo)
VALUES ('NO IDENTIFICADA / PENDIENTE DE REVISIÓN', 'NOIDENT', TRUE)
ON CONFLICT DO NOTHING;

DO $$
DECLARE
    v_dep_id INT;
    v_ext_id INT;
BEGIN
    SELECT id INTO v_dep_id FROM dependencias
     WHERE LOWER(TRIM(nombre)) = 'no identificada / pendiente de revisión'
     LIMIT 1;

    SELECT id INTO v_ext_id FROM tipos_oficio WHERE clave = 'EXTERNO';

    IF v_dep_id IS NULL THEN
        RAISE EXCEPTION 'No se pudo resolver la dependencia NO IDENTIFICADA.';
    END IF;

    UPDATE oficios
       SET dependencia_id = v_dep_id,
           observaciones  = CASE
               WHEN COALESCE(observaciones,'') = ''
                    THEN '[PENDIENTE_REVISION_DEPENDENCIA]'
               WHEN observaciones LIKE '[PENDIENTE_REVISION_DEPENDENCIA]%'
                    THEN observaciones
               ELSE '[PENDIENTE_REVISION_DEPENDENCIA] ' || observaciones
           END
     WHERE tipo_oficio_id = v_ext_id
       AND dependencia_id IS NULL;
END$$;

-- ---------------------------------------------------------------------
-- 5. CHECK: extensión para incluir la invariante "EXTERNO => dependencia_id NOT NULL".
--    (Después del repair ya no hay filas violadoras.)
--    Se reemplaza oficios_tipo_consistencia_chk para cubrir la regla nueva.
-- ---------------------------------------------------------------------
DO $$
DECLARE
    v_ext INT;
    v_int INT;
    v_con INT;
BEGIN
    SELECT id INTO v_ext FROM tipos_oficio WHERE clave = 'EXTERNO';
    SELECT id INTO v_int FROM tipos_oficio WHERE clave = 'INTERNO';
    SELECT id INTO v_con FROM tipos_oficio WHERE clave = 'CONOCIMIENTO';

    ALTER TABLE oficios DROP CONSTRAINT IF EXISTS oficios_tipo_consistencia_chk;

    EXECUTE format(
        'ALTER TABLE oficios ADD CONSTRAINT oficios_tipo_consistencia_chk '
     || 'CHECK ( (tipo_oficio_id = %s AND dependencia_id IS NOT NULL) '
     || '     OR (tipo_oficio_id = %s AND area_interna_id IS NOT NULL) '
     || '     OR  tipo_oficio_id = %s ) NOT VALID',
        v_ext, v_int, v_con
    );
END$$;

-- Validar contra todas las filas (ya no debería haber violaciones).
ALTER TABLE oficios VALIDATE CONSTRAINT oficios_tipo_consistencia_chk;

COMMIT;

-- =====================================================================
-- Verificación post-migración (debe retornar todo en 0 donde aplique)
-- =====================================================================
SELECT 'folios_duplicados_visuales' AS metrica, COUNT(*) AS valor
  FROM ( SELECT folio_tesoreria
           FROM oficios
          GROUP BY folio_tesoreria
         HAVING COUNT(*) > 1 ) x
UNION ALL
SELECT 'oficios_sin_movimiento_inicial',
       (SELECT COUNT(*) FROM oficios o
          LEFT JOIN movimientos_oficio m ON m.oficio_id = o.id
         WHERE m.id IS NULL)
UNION ALL
SELECT 'externos_sin_dependencia',
       (SELECT COUNT(*) FROM oficios o
          JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
         WHERE t.clave = 'EXTERNO' AND o.dependencia_id IS NULL)
UNION ALL
SELECT 'folios_muestra_7',
       (SELECT numero_folio FROM oficios WHERE numero_folio = 7 LIMIT 1)
UNION ALL
SELECT 'folios_muestra_10001',
       (SELECT numero_folio FROM oficios WHERE numero_folio = 10001 LIMIT 1);
