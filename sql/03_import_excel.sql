-- =============================================================================
-- SCRIPT DE IMPORTACIÓN DEL EXCEL BASE_LUCIO.xlsx
-- Ejecutar DESPUÉS de levantar el sistema y verificar catálogos
-- =============================================================================
-- INSTRUCCIONES:
-- 1. Usar el script Python en tools/importar_excel.py para cargar import_oficios_raw
-- 2. Luego ejecutar este script para procesar los datos
-- =============================================================================

-- Ver datos en staging antes de procesar
-- SELECT * FROM import_oficios_raw LIMIT 10;

-- Ejemplo de procesamiento manual desde staging a oficios
-- (Ajustar dependencia_id según la normalización de dependencias)
/*
INSERT INTO oficios (
    numero_folio, anio_folio,
    folio_minutario, folio_direccion,
    dependencia_id, estado_id,
    asunto, observaciones,
    fecha_recepcion, fecha_oficio_tics, fecha_acuse,
    realizo, usuario_capturo_id
)
SELECT
    -- Extraer número del folio de tesorería (si tiene formato TM/ECA/STIyC/NNNN/YYYY)
    CASE
        WHEN raw_folio_tesoreria ~ 'STIyC/(\d+)/(\d{4})' THEN
            (regexp_match(raw_folio_tesoreria, 'STIyC/(\d+)/(\d{4})'))[1]::INTEGER
        ELSE NULL
    END AS numero_folio,
    CASE
        WHEN raw_folio_tesoreria ~ 'STIyC/(\d+)/(\d{4})' THEN
            (regexp_match(raw_folio_tesoreria, 'STIyC/(\d+)/(\d{4})'))[2]::SMALLINT
        ELSE 2026
    END AS anio_folio,
    NULLIF(TRIM(raw_folio_minutario), '-'),
    NULLIF(TRIM(raw_folio_direccion), ''),
    -- Mapear dependencia (requiere normalización previa)
    (SELECT id FROM dependencias WHERE LOWER(nombre) ILIKE '%' || LOWER(TRIM(r.raw_nombre_direccion)) || '%' LIMIT 1),
    -- Estado según STATUS del Excel
    CASE TRIM(UPPER(raw_status))
        WHEN 'ARCHIVADO' THEN (SELECT id FROM estados_oficio WHERE nombre = 'Archivado')
        WHEN 'ARCIVADO'  THEN (SELECT id FROM estados_oficio WHERE nombre = 'Archivado')
        ELSE                  (SELECT id FROM estados_oficio WHERE nombre = 'Recibido')
    END,
    TRIM(raw_asunto),
    NULLIF(TRIM(raw_observaciones), ''),
    raw_fecha_recibido::DATE,
    NULLIF(raw_fecha_oficio_tics, '')::DATE,
    NULLIF(raw_fecha_acuse, '')::DATE,
    NULLIF(TRIM(raw_realizo), '-'),
    (SELECT id FROM usuarios WHERE email = 'admin@tesoreria.gob.mx' LIMIT 1)
FROM import_oficios_raw r
WHERE procesado = FALSE
  AND raw_folio_tesoreria ~ 'STIyC/\d+/\d{4}'
  AND raw_asunto IS NOT NULL
  AND raw_fecha_recibido IS NOT NULL;
*/

-- Nota: Este script es una plantilla. La importación real requiere
-- verificar y normalizar los datos del Excel antes de ejecutar.
-- Ver herramienta en tools/importar_excel.py para carga automática.
