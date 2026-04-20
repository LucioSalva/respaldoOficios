-- =====================================================================
-- 05_personal.sql
-- Migración aditiva para el MÓDULO PERSONAL.
--
-- Objetivo:
--   - Catálogo formal tipos_personal (SINDICALIZADO / CONFIANZA).
--   - Tabla personal (maestro operativo, distinto de usuarios/login).
--   - Staging import_personal_raw (preserva datos crudos por fila).
--   - Vista vw_personal_resumen.
--
-- Reglas:
--   - personal.numero_empleado es UNIQUE cuando no es NULL
--     (se permite NULL para casos históricos sin número).
--   - Matching en importador:
--       1) por numero_empleado si existe
--       2) si no, por nombre_normalizado
--       3) si hay ambigüedad => queda en staging con flag de revisión.
--   - No se modifica la tabla usuarios. La vinculación usuario<->personal
--     es opcional vía usuario_id (puede ser NULL).
--
-- Ejecutar una sola vez, después de 04_internos.sql.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Catálogo tipos_personal
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_personal (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(20)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion TEXT,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO tipos_personal (clave, nombre, descripcion) VALUES
    ('SINDICALIZADO', 'Sindicalizado',
     'Personal con plaza sindicalizada (nómina S).'),
    ('CONFIANZA',     'Confianza',
     'Personal de confianza (nómina C).')
ON CONFLICT (clave) DO NOTHING;

-- ---------------------------------------------------------------------
-- 2. Tabla personal
--    Diseño normalizado; separada de usuarios (que es auth/login).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS personal (
    id                 SERIAL PRIMARY KEY,
    numero_empleado    VARCHAR(20),          -- p.ej. '92300'
    nombre_completo    VARCHAR(180) NOT NULL, -- tal cual se captura
    nombre_normalizado VARCHAR(180) NOT NULL, -- upper/unaccent/sin dobles-espacios, para matching
    tipo_personal_id   INTEGER NOT NULL REFERENCES tipos_personal(id) ON DELETE RESTRICT,

    -- Contextuales (opcionales; se importan cuando existen)
    categoria          VARCHAR(120),         -- AUXILIAR, COORDINADOR, ASISTENTE D, etc.
    horario            VARCHAR(120),
    situacion_medica   TEXT,
    curp               VARCHAR(20),
    rfc                VARCHAR(15),
    clave_issemym      VARCHAR(40),
    grado_estudios     VARCHAR(80),
    carrera            VARCHAR(120),

    -- Vinculación opcional con usuarios (login)
    usuario_id         INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,

    -- Control
    activo             BOOLEAN NOT NULL DEFAULT TRUE,
    observaciones      TEXT,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- numero_empleado único cuando no es nulo (evita choques por nulos).
CREATE UNIQUE INDEX IF NOT EXISTS idx_personal_num_emp_unique
    ON personal(numero_empleado)
    WHERE numero_empleado IS NOT NULL;

-- nombre_normalizado debe ser único (segunda llave de matching).
CREATE UNIQUE INDEX IF NOT EXISTS idx_personal_nombre_norm_unique
    ON personal(nombre_normalizado);

CREATE INDEX IF NOT EXISTS idx_personal_tipo   ON personal(tipo_personal_id);
CREATE INDEX IF NOT EXISTS idx_personal_activo ON personal(activo);

DROP TRIGGER IF EXISTS trg_personal_updated_at ON personal;
CREATE TRIGGER trg_personal_updated_at
    BEFORE UPDATE ON personal
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- ---------------------------------------------------------------------
-- 3. Staging import_personal_raw
--    Todo TEXT. Conserva la fila original completa en raw_data JSONB.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_personal_raw (
    id                SERIAL PRIMARY KEY,
    fuente            VARCHAR(80) NOT NULL,   -- 'BASE VACACIONES 2026.xlsx!PERSONAL' etc
    fila_excel        INTEGER,
    raw_numero        TEXT,
    raw_nombre        TEXT,
    raw_tipo          TEXT,                   -- 'S' / 'C' (o texto suelto)
    raw_categoria     TEXT,
    raw_horario       TEXT,
    raw_sit_medica    TEXT,
    raw_curp          TEXT,
    raw_rfc           TEXT,
    raw_clave_issemym TEXT,
    raw_grado_estudios TEXT,
    raw_carrera       TEXT,
    raw_data          JSONB,                  -- fila completa tal cual

    -- Procesamiento
    procesado         BOOLEAN NOT NULL DEFAULT FALSE,
    personal_id       INTEGER REFERENCES personal(id),
    accion            VARCHAR(20),            -- CREADO / ACTUALIZADO / OMITIDO
    estado_revision   VARCHAR(30) NOT NULL DEFAULT 'OK',
                          -- OK | PENDIENTE_REVISION | ERROR
    error_importacion TEXT,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_imp_personal_procesado ON import_personal_raw(procesado);
CREATE INDEX IF NOT EXISTS idx_imp_personal_estado    ON import_personal_raw(estado_revision);

-- ---------------------------------------------------------------------
-- 4. Vista resumen
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_personal_resumen AS
SELECT
    p.id,
    p.numero_empleado,
    p.nombre_completo,
    tp.clave  AS tipo_clave,
    tp.nombre AS tipo_nombre,
    p.categoria,
    p.horario,
    p.activo,
    p.usuario_id,
    u.username      AS usuario_username,
    p.created_at,
    p.updated_at
FROM personal p
JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
LEFT JOIN usuarios u   ON u.id  = p.usuario_id;

COMMIT;

-- =====================================================================
-- Verificación rápida
-- =====================================================================
SELECT 'tipos_personal'       AS tabla, COUNT(*) AS filas FROM tipos_personal
UNION ALL
SELECT 'personal',               COUNT(*) FROM personal
UNION ALL
SELECT 'import_personal_raw',    COUNT(*) FROM import_personal_raw;
