-- =============================================================================
-- RESPALDO DE OFICIOS - Schema PostgreSQL
-- Versión 1.0 | Abril 2026
-- =============================================================================

-- Extensiones
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- =============================================================================
-- CATÁLOGOS BASE
-- =============================================================================

CREATE TABLE IF NOT EXISTS roles (
    id   SMALLINT PRIMARY KEY,
    nombre VARCHAR(20) NOT NULL UNIQUE
);

INSERT INTO roles (id, nombre) VALUES
    (1, 'GOD'),
    (2, 'Admin'),
    (3, 'User')
ON CONFLICT DO NOTHING;

-- =============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id               SERIAL PRIMARY KEY,
    nombre           VARCHAR(120) NOT NULL,
    username         VARCHAR(60)  NOT NULL UNIQUE,
    email            VARCHAR(120),
    password_hash    VARCHAR(255) NOT NULL,
    rol_id           SMALLINT NOT NULL DEFAULT 3 REFERENCES roles(id),
    activo           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_usuarios_username ON usuarios(username);
CREATE INDEX IF NOT EXISTS idx_usuarios_activo   ON usuarios(activo);

-- =============================================================================
CREATE TABLE IF NOT EXISTS dependencias (
    id       SERIAL PRIMARY KEY,
    nombre   VARCHAR(255) NOT NULL,
    clave    VARCHAR(80),
    activo   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_dependencias_activo ON dependencias(activo);
CREATE UNIQUE INDEX IF NOT EXISTS idx_dependencias_nombre_unique
    ON dependencias(LOWER(TRIM(nombre)));

-- =============================================================================
CREATE TABLE IF NOT EXISTS estados_oficio (
    id      SERIAL PRIMARY KEY,
    nombre  VARCHAR(80) NOT NULL UNIQUE,
    color   VARCHAR(30) NOT NULL DEFAULT 'secondary',   -- Bootstrap color class
    orden   SMALLINT NOT NULL DEFAULT 99,
    activo  BOOLEAN NOT NULL DEFAULT TRUE
);

-- =============================================================================
CREATE TABLE IF NOT EXISTS tipos_documento (
    id      SERIAL PRIMARY KEY,
    nombre  VARCHAR(80) NOT NULL UNIQUE,
    activo  BOOLEAN NOT NULL DEFAULT TRUE
);

-- =============================================================================
CREATE TABLE IF NOT EXISTS tipos_evidencia (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    activo      BOOLEAN NOT NULL DEFAULT TRUE
);

-- =============================================================================
-- TABLA PRINCIPAL: OFICIOS
-- =============================================================================

CREATE TABLE IF NOT EXISTS oficios (
    id                      SERIAL PRIMARY KEY,

    -- Folio Tesorería compuesto
    numero_folio            INTEGER NOT NULL,
    anio_folio              SMALLINT NOT NULL DEFAULT EXTRACT(YEAR FROM NOW()),
    folio_tesoreria         VARCHAR(60) GENERATED ALWAYS AS (
                                'TM/ECA/STIyC/' ||
                                LPAD(numero_folio::TEXT, 4, '0') || '/' ||
                                anio_folio::TEXT
                            ) STORED,

    -- Folios relacionados de origen
    folio_minutario         VARCHAR(30),
    folio_direccion         VARCHAR(100),   -- folio de la dirección emisora

    -- Relaciones
    dependencia_id          INTEGER REFERENCES dependencias(id) ON DELETE RESTRICT,
    estado_id               INTEGER NOT NULL REFERENCES estados_oficio(id) ON DELETE RESTRICT,
    tipo_documento_id       INTEGER REFERENCES tipos_documento(id),

    -- Datos del oficio
    asunto                  TEXT NOT NULL,
    descripcion             TEXT,
    observaciones           TEXT,

    -- Fechas clave
    fecha_recepcion         DATE NOT NULL,
    fecha_oficio_tics       DATE,           -- fecha del oficio de respuesta TICs
    fecha_acuse             DATE,           -- fecha acuse recibido por oficialía de tesorería
    fecha_compromiso        DATE,
    fecha_resolucion        DATE,

    -- Personal
    realizo                 VARCHAR(120),   -- nombre de quien realizó la gestión (campo libre histórico)
    usuario_capturo_id      INTEGER REFERENCES usuarios(id),
    usuario_responsable_id  INTEGER REFERENCES usuarios(id),

    -- Control
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_numero_anio_folio UNIQUE (numero_folio, anio_folio)
);

CREATE INDEX IF NOT EXISTS idx_oficios_estado     ON oficios(estado_id);
CREATE INDEX IF NOT EXISTS idx_oficios_dependencia ON oficios(dependencia_id);
CREATE INDEX IF NOT EXISTS idx_oficios_fecha_rec  ON oficios(fecha_recepcion);
CREATE INDEX IF NOT EXISTS idx_oficios_anio       ON oficios(anio_folio);
CREATE INDEX IF NOT EXISTS idx_oficios_folio_ts   ON oficios(folio_tesoreria);
CREATE INDEX IF NOT EXISTS idx_oficios_capturo    ON oficios(usuario_capturo_id);

-- =============================================================================
-- MOVIMIENTOS (historial de estado)
-- =============================================================================

CREATE TABLE IF NOT EXISTS movimientos_oficio (
    id                  SERIAL PRIMARY KEY,
    oficio_id           INTEGER NOT NULL REFERENCES oficios(id) ON DELETE CASCADE,
    estado_anterior_id  INTEGER REFERENCES estados_oficio(id),
    estado_nuevo_id     INTEGER NOT NULL REFERENCES estados_oficio(id),
    observacion         TEXT,
    usuario_id          INTEGER REFERENCES usuarios(id),
    fecha               TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_mov_oficio ON movimientos_oficio(oficio_id);
CREATE INDEX IF NOT EXISTS idx_mov_fecha  ON movimientos_oficio(fecha);

-- =============================================================================
-- EVIDENCIAS PDF
-- =============================================================================

CREATE TABLE IF NOT EXISTS evidencias_pdf (
    id                  SERIAL PRIMARY KEY,
    oficio_id           INTEGER NOT NULL REFERENCES oficios(id) ON DELETE CASCADE,
    tipo_evidencia_id   INTEGER REFERENCES tipos_evidencia(id),
    nombre_original     VARCHAR(255) NOT NULL,
    nombre_archivo      VARCHAR(255) NOT NULL UNIQUE,  -- nombre seguro en disco
    ruta                VARCHAR(500) NOT NULL,
    tamano_bytes        BIGINT,
    mime_type           VARCHAR(100) DEFAULT 'application/pdf',
    usuario_subio_id    INTEGER REFERENCES usuarios(id),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_evidencias_oficio ON evidencias_pdf(oficio_id);

-- =============================================================================
-- BITÁCORA DE AUDITORÍA
-- =============================================================================

CREATE TABLE IF NOT EXISTS bitacora (
    id          BIGSERIAL PRIMARY KEY,
    usuario_id  INTEGER REFERENCES usuarios(id),
    accion      VARCHAR(50) NOT NULL,   -- CREATE, UPDATE, DELETE, LOGIN, LOGOUT, UPLOAD, DOWNLOAD
    tabla       VARCHAR(60),
    registro_id INTEGER,
    datos_json  JSONB,
    ip          INET,
    user_agent  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_bitacora_usuario  ON bitacora(usuario_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_tabla    ON bitacora(tabla, registro_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_fecha    ON bitacora(created_at);

-- =============================================================================
-- TABLA DE STAGING PARA IMPORTACIÓN DEL EXCEL
-- =============================================================================

CREATE TABLE IF NOT EXISTS import_oficios_raw (
    id                      SERIAL PRIMARY KEY,
    raw_consecutivo         VARCHAR(20),
    raw_fecha_recibido      VARCHAR(50),
    raw_folio_minutario     VARCHAR(50),
    raw_nombre_direccion    TEXT,
    raw_folio_direccion     VARCHAR(100),
    raw_asunto              TEXT,
    raw_fecha_oficio_tics   VARCHAR(50),
    raw_folio_tesoreria     VARCHAR(100),
    raw_realizo             VARCHAR(120),
    raw_fecha_acuse         VARCHAR(50),
    raw_status              VARCHAR(100),
    raw_folio_interno_teso  VARCHAR(50),
    raw_capturo             VARCHAR(120),
    raw_observaciones       TEXT,
    -- procesado
    procesado               BOOLEAN DEFAULT FALSE,
    oficio_id               INTEGER REFERENCES oficios(id),
    error_msg               TEXT,
    importado_en            TIMESTAMPTZ DEFAULT NOW()
);

-- =============================================================================
-- FUNCIÓN: actualizar updated_at automáticamente
-- =============================================================================

CREATE OR REPLACE FUNCTION fn_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_oficios_updated_at
    BEFORE UPDATE ON oficios
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

CREATE TRIGGER trg_usuarios_updated_at
    BEFORE UPDATE ON usuarios
    FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- =============================================================================
-- VISTAS ÚTILES
-- =============================================================================

CREATE OR REPLACE VIEW v_oficios_completo AS
SELECT
    o.id,
    o.folio_tesoreria,
    o.numero_folio,
    o.anio_folio,
    o.folio_minutario,
    o.folio_direccion,
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
    d.nombre        AS dependencia_nombre,
    d.clave         AS dependencia_clave,
    e.nombre        AS estado_nombre,
    e.color         AS estado_color,
    uc.nombre       AS capturo_nombre,
    ur.nombre       AS responsable_nombre,
    (SELECT COUNT(*) FROM movimientos_oficio m WHERE m.oficio_id = o.id) AS total_movimientos,
    (SELECT COUNT(*) FROM evidencias_pdf ep WHERE ep.oficio_id = o.id)   AS total_evidencias
FROM oficios o
LEFT JOIN dependencias  d  ON d.id = o.dependencia_id
LEFT JOIN estados_oficio e  ON e.id = o.estado_id
LEFT JOIN usuarios       uc ON uc.id = o.usuario_capturo_id
LEFT JOIN usuarios       ur ON ur.id = o.usuario_responsable_id;

CREATE OR REPLACE VIEW v_dashboard_resumen AS
SELECT
    COUNT(*)                                                        AS total_oficios,
    COUNT(*) FILTER (WHERE e.nombre = 'Recibido')                  AS recibidos,
    COUNT(*) FILTER (WHERE e.nombre = 'En Proceso')                AS en_proceso,
    COUNT(*) FILTER (WHERE e.nombre = 'En Revisión')               AS en_revision,
    COUNT(*) FILTER (WHERE e.nombre = 'Contestado')                AS contestados,
    COUNT(*) FILTER (WHERE e.nombre = 'Archivado')                 AS archivados
FROM oficios o
JOIN estados_oficio e ON e.id = o.estado_id;
