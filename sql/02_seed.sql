-- =============================================================================
-- RESPALDO DE OFICIOS - Datos Semilla
-- =============================================================================

-- Estados del oficio (en orden de flujo)
INSERT INTO estados_oficio (nombre, color, orden) VALUES
    ('Recibido',   'primary',   1),
    ('En Proceso', 'warning',   2),
    ('En Revisión','info',      3),
    ('Turnado',    'secondary', 4),
    ('Contestado', 'success',   5),
    ('Archivado',  'dark',      6)
ON CONFLICT (nombre) DO NOTHING;

-- Tipos de evidencia
INSERT INTO tipos_evidencia (nombre, descripcion) VALUES
    ('Oficio Recibido',    'Oficio original recibido de la dependencia'),
    ('Acuse de Recibido',  'Acuse de recibido por oficialía de tesorería'),
    ('Oficio de Respuesta','Oficio de respuesta emitido por TICs'),
    ('Contestado',         'Documento con la respuesta formal enviada'),
    ('Otro',               'Otro tipo de documento de soporte')
ON CONFLICT (nombre) DO NOTHING;

-- Tipos de documento
INSERT INTO tipos_documento (nombre) VALUES
    ('Oficio'),
    ('Memorándum'),
    ('Circular'),
    ('Solicitud'),
    ('Informe')
ON CONFLICT (nombre) DO NOTHING;

-- =============================================================================
-- DEPENDENCIAS (normalizadas a partir del análisis del Excel)
-- =============================================================================
INSERT INTO dependencias (nombre, clave) VALUES
    ('Dirección de Administración',                                            'DA'),
    ('Dirección de Desarrollo Urbano',                                         'DDU'),
    ('Dirección General de Seguridad Ciudadana y Tránsito Municipal',          'DGSCYTM'),
    ('Órgano Interno de Control Municipal',                                    'OICM'),
    ('Secretario del H. Ayuntamiento',                                         'SHA'),
    ('Dirección de Medio Ambiente y Ecología',                                 'DMAE'),
    ('Defensoría Municipal de Derechos Humanos',                               'DMDH'),
    ('Coordinación Técnica de Presidencia',                                    'CTP'),
    ('Dirección de Servicios Públicos',                                        'DSP'),
    ('Dirección de Obras Públicas',                                            'DOP'),
    ('Dirección de Protección Civil y Bomberos',                               'DPCB'),
    ('Instituto Municipal de las Mujeres e Igualdad de Género',               'IMMIG'),
    ('Instituto de la Juventud',                                               'IJ'),
    ('Dirección de Mercados, Tianguis y Vía Pública',                         'DMTVP'),
    ('Dirección de Desarrollo Económico',                                      'DDE'),
    ('Secretaría de Innovación Gubernamental',                                 'SIG'),
    ('Dirección de Gobierno',                                                  'DG'),
    ('Unidad de Transparencia y Acceso a la Información Pública',             'UTAIP'),
    ('Secretario Técnico del Consejo Municipal de Seguridad Pública',         'STCMSP'),
    ('Primer Síndico Municipal',                                               'PSM'),
    ('Segundo Síndico Municipal',                                              'SSM'),
    ('Dirección de Cultura',                                                   'DC'),
    ('Dirección de Bienestar, Participación Ciudadana y Territorial',         'DBPCT'),
    ('Registro Civil',                                                         'RC'),
    ('Dirección de Educación',                                                 'DE'),
    ('Subdirección de Juzgados Cívicos',                                       'SJC'),
    ('Coordinación Jurídica de Seguridad Pública',                            'CJSP'),
    ('IMCUFIDEEM',                                                             'IMCUFIDEEM'),
    ('UIPPE',                                                                  'UIPPE'),
    ('Computo C4',                                                             'C4'),
    ('TELMEX',                                                                 'TELMEX'),
    ('Dirección de Estrategia de Seguridad Ciudadana',                        'DESC'),
    ('Dirección de Diversidad Sexual',                                         'DDS'),
    ('Agencia Digital del Estado de México',                                   'ADEM'),
    ('Otra Dependencia',                                                       'OTRO')
ON CONFLICT DO NOTHING;

-- =============================================================================
-- SEMILLA DE USUARIOS
--
-- IMPORTANTE:
--   - Este seed NO deja usuarios activos con contraseñas conocidas.
--   - Los registros 'admin' y 'usuario' se insertan DESACTIVADOS y solo
--     sirven para que las FKs de bitácora/pruebas no queden huérfanas.
--   - La migración 10 (sql/10_fix_constraints_y_uniques.sql) elimina o
--     desactiva estos registros dependiendo de si tienen actividad.
--   - Para crear el primer usuario GOD real, consulta el README.md
--     (sección "Primer Acceso"): se genera un hash con
--     password_hash() desde PHP y se inserta manualmente con
--     INSERT INTO usuarios(..., activo=TRUE).
-- =============================================================================

INSERT INTO usuarios (nombre, username, email, password_hash, rol_id, activo) VALUES
    (
        'Semilla Administrador (INACTIVO)',
        'admin',
        'admin@tesoreria.gob.mx',
        -- Hash placeholder; la contraseña original ya no aplica porque activo=FALSE.
        '$2y$12$rZPbA5O4wJVN5HxSu3Lpye.1VNPqYsIKxnSwKohURT93YhGYbVKpa',
        1,
        FALSE
    )
ON CONFLICT (username) DO NOTHING;

INSERT INTO usuarios (nombre, username, email, password_hash, rol_id, activo) VALUES
    (
        'Semilla Usuario Demo (INACTIVO)',
        'usuario',
        'usuario@tesoreria.gob.mx',
        '$2y$12$8AUQzluRSVPMJEC.mTRzE.QkviJjWTrwntAPyMeHJwLOprj9CAuza',
        3,
        FALSE
    )
ON CONFLICT (username) DO NOTHING;
