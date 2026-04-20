#!/usr/bin/env python3
"""
Importa BASE LUCIO.xlsx directamente a la tabla oficios de PostgreSQL.
Uso: python tools/importar_excel.py
"""

import re
import sys
import os
from datetime import datetime
import openpyxl
import psycopg2

# ── Configuración ──────────────────────────────────────────────────────────────
DB = {
    'host':     os.environ.get('DB_HOST', 'localhost'),
    'port':     int(os.environ.get('DB_PORT', 5432)),
    'dbname':   os.environ.get('DB_NAME', 'oficios'),
    'user':     os.environ.get('DB_USER', 'postgres'),
    'password': os.environ.get('DB_PASS', 'admin'),
}

EXCEL_PATH = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    'BASE LUCIO.xlsx'
)

# Mapeo de variantes de estado a nombres normalizados
ESTADOS_MAP = {
    'RECIBIDO': 'Recibido',
    'EN PROCESO': 'En Proceso', 'PROCESO': 'En Proceso', 'EN REVISION': 'En Revisión',
    'TURNADO': 'Turnado', 'TURNARDO': 'Turnado',
    'CONTESTADO': 'Contestado',
    'ARCHIVADO': 'Archivado', 'ARCIVADO': 'Archivado', 'ARCHIVO': 'Archivado',
    'PENDIENTE': 'Recibido',
    'CANCELADO': 'Archivado',
}

# ── Helpers ────────────────────────────────────────────────────────────────────

def clean(val):
    if val is None:
        return None
    s = str(val).strip()
    return s if s not in ('', '-', 'nan', 'None') else None

def parse_date(val):
    if val is None:
        return None
    if isinstance(val, datetime):
        return val.date()
    s = str(val).strip()
    for fmt in ('%d/%m/%Y', '%Y-%m-%d', '%d-%m-%Y', '%d/%m/%y'):
        try:
            return datetime.strptime(s, fmt).date()
        except ValueError:
            continue
    return None

def parse_folio(raw):
    """Extrae numero_folio y anio_folio del texto TM/ECA/STIyC/XXXX/YYYY o número solo."""
    if not raw:
        return None, None
    m = re.search(r'(\d{1,4})[/\-](\d{4})$', raw.strip())
    if m:
        return int(m.group(1)), int(m.group(2))
    m2 = re.search(r'\b(\d{1,4})\b', raw.strip())
    if m2:
        return int(m2.group(1)), datetime.now().year
    return None, None

def normalize_estado(raw):
    if not raw:
        return 'Recibido'
    return ESTADOS_MAP.get(raw.strip().upper(), 'Recibido')

def fuzzy_dep(nombre, deps):
    """Busca dependencia por nombre, tolerando variaciones."""
    if not nombre:
        return None
    nombre_up = nombre.upper().strip()
    for dep_id, dep_nombre in deps.items():
        if dep_nombre.upper() in nombre_up or nombre_up in dep_nombre.upper():
            return dep_id
    for dep_id, dep_nombre in deps.items():
        palabras = dep_nombre.upper().split()
        if any(p in nombre_up for p in palabras if len(p) > 4):
            return dep_id
    return None

# ── Main ───────────────────────────────────────────────────────────────────────

def main():
    print(f"Leyendo: {EXCEL_PATH}")
    if not os.path.exists(EXCEL_PATH):
        print(f"ERROR: No se encuentra el archivo Excel: {EXCEL_PATH}")
        sys.exit(1)

    wb = openpyxl.load_workbook(EXCEL_PATH, data_only=True)
    ws = wb.active
    rows = list(ws.iter_rows(values_only=True))
    header_row = rows[0]
    data_rows  = rows[1:]
    print(f"Filas de datos: {len(data_rows)}")

    print("Conectando a PostgreSQL...")
    try:
        conn = psycopg2.connect(**DB)
        cur  = conn.cursor()
    except Exception as e:
        print(f"ERROR de conexión: {e}")
        sys.exit(1)

    # Cargar catálogos
    cur.execute("SELECT id, nombre FROM dependencias")
    deps = {r[0]: r[1] for r in cur.fetchall()}

    cur.execute("SELECT id, nombre FROM estados_oficio")
    estados = {r[1]: r[0] for r in cur.fetchall()}

    cur.execute("SELECT id FROM usuarios WHERE username = 'lucio' LIMIT 1")
    row = cur.fetchone()
    usuario_id = row[0] if row else None
    if not usuario_id:
        cur.execute("SELECT id FROM usuarios LIMIT 1")
        usuario_id = cur.fetchone()[0]

    # Folio fallback: empezar desde 9000 + línea
    cur.execute("SELECT COALESCE(MAX(numero_folio), 9000) FROM oficios WHERE anio_folio = %s", (datetime.now().year,))
    max_folio = cur.fetchone()[0]

    insertados = 0
    omitidos   = 0
    errores    = []

    for i, row in enumerate(data_rows, start=2):
        # Columnas por posición (0-based)
        # 0:consecutivo 1:fecha_recibido 2:folio_minutario 3:nombre_direccion
        # 4:folio_direccion 5:asunto 6:fecha_oficio_tics 7:folio_tesoreria
        # 8:realizo 9:fecha_acuse 10:status 11:folio_interno_teso 12:capturo 13:observaciones
        if len(row) < 5 or all(v is None for v in row):
            continue

        fecha_recibido   = parse_date(row[1] if len(row) > 1 else None) or datetime.now().date()
        folio_minutario  = clean(row[2] if len(row) > 2 else None)
        nombre_dir       = clean(row[3] if len(row) > 3 else None)
        folio_dir        = clean(row[4] if len(row) > 4 else None)
        asunto           = clean(row[5] if len(row) > 5 else None)
        fecha_tics       = parse_date(row[6] if len(row) > 6 else None)
        folio_raw        = clean(row[7] if len(row) > 7 else None)
        realizo          = clean(row[8] if len(row) > 8 else None)
        fecha_acuse      = parse_date(row[9] if len(row) > 9 else None)
        status_raw       = clean(row[10] if len(row) > 10 else None)
        folio_interno    = clean(row[11] if len(row) > 11 else None)
        capturo          = clean(row[12] if len(row) > 12 else None)
        observaciones    = clean(row[13] if len(row) > 13 else None)

        if not asunto and not nombre_dir:
            omitidos += 1
            continue

        # Dependencia
        dep_id = fuzzy_dep(nombre_dir, deps)

        # Estado
        estado_nombre = normalize_estado(status_raw)
        estado_id     = estados.get(estado_nombre, estados.get('Recibido'))

        # Folio tesorería
        num_folio, anio_folio = parse_folio(folio_raw)
        if num_folio is None:
            max_folio += 1
            num_folio  = max_folio
            anio_folio = datetime.now().year

        # Verificar duplicado por folio
        cur.execute(
            "SELECT id FROM oficios WHERE numero_folio = %s AND anio_folio = %s",
            (num_folio, anio_folio)
        )
        if cur.fetchone():
            max_folio += 1
            num_folio  = max_folio
            anio_folio = datetime.now().year

        try:
            cur.execute("""
                INSERT INTO oficios (
                    numero_folio, anio_folio,
                    fecha_recepcion, folio_minutario,
                    dependencia_id, folio_direccion,
                    asunto, fecha_oficio_tics, fecha_acuse,
                    estado_id, observaciones, realizo, usuario_capturo_id
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
                ) RETURNING id
            """, (
                num_folio, anio_folio,
                fecha_recibido, folio_minutario,
                dep_id, folio_dir,
                asunto or '(Sin asunto)', fecha_tics, fecha_acuse,
                estado_id, observaciones, realizo, usuario_id
            ))
            oficio_id = cur.fetchone()[0]

            # Movimiento inicial
            cur.execute("""
                INSERT INTO movimientos_oficio (oficio_id, estado_nuevo_id, usuario_id, observacion)
                VALUES (%s, %s, %s, %s)
            """, (oficio_id, estado_id, usuario_id, 'Importado desde BASE LUCIO.xlsx'))

            insertados += 1
        except Exception as e:
            errores.append(f"Fila {i}: {e}")
            conn.rollback()
            # Re-abrir transacción
            continue

        if insertados % 20 == 0:
            conn.commit()
            print(f"  {insertados} registros insertados...")

    conn.commit()
    cur.close()
    conn.close()

    print(f"\n{'='*50}")
    print(f"Importacion completada:")
    print(f"  Insertados : {insertados}")
    print(f"  Omitidos   : {omitidos} (filas vacias)")
    print(f"  Errores    : {len(errores)}")
    if errores:
        for e in errores[:10]:
            print(f"  {e}")

if __name__ == '__main__':
    main()
