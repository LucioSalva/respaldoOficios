#!/usr/bin/env python3
"""
diagnose_uploads.py

Reporte READ-ONLY de consistencia entre el directorio de uploads y la
base de datos.

Chequeos:
    1. Archivos en disco que NO están registrados en evidencias_pdf
       (huérfanos físicos — candidatos a limpieza manual tras respaldo).
    2. Registros en evidencias_pdf cuyo archivo en disco NO existe
       (referencias rotas — bug potencial en delete o sync).
    3. Conteos agregados por subdirectorio.

Uso:
    python tools/diagnose_uploads.py [--uploads-dir /ruta/a/uploads]

Variables de entorno leídas desde .env (DB_HOST, DB_PORT, DB_NAME, DB_USER,
DB_PASS). Si hay un .env en la raíz del proyecto se carga con python-dotenv
si está disponible; en su ausencia se usan las variables del ambiente.

Este script NO elimina archivos. Solo imprime el reporte a stdout. Es seguro
correrlo en producción.
"""

from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

try:
    import psycopg2
    from psycopg2.extras import RealDictCursor
except ImportError:
    print("ERROR: falta psycopg2-binary. Instalar con: pip install psycopg2-binary",
          file=sys.stderr)
    sys.exit(2)

try:
    from dotenv import load_dotenv  # type: ignore
    load_dotenv()
except ImportError:
    pass


def parse_args() -> argparse.Namespace:
    root = Path(__file__).resolve().parent.parent
    default_uploads = root / "uploads"
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument(
        "--uploads-dir",
        default=str(default_uploads),
        help=f"Directorio de uploads (default: {default_uploads})",
    )
    return p.parse_args()


def get_conn():
    return psycopg2.connect(
        host=os.environ.get("DB_HOST", "localhost"),
        port=int(os.environ.get("DB_PORT", "5432")),
        dbname=os.environ.get("DB_NAME", "respaldo_oficios"),
        user=os.environ.get("DB_USER", "oficios_user"),
        password=os.environ.get("DB_PASS", ""),
    )


def listar_en_disco(base: Path) -> set[str]:
    resultado: set[str] = set()
    if not base.exists():
        return resultado
    for f in base.rglob("*"):
        if f.is_file():
            # Ruta relativa al directorio base, con separadores POSIX.
            rel = f.relative_to(base).as_posix()
            resultado.add(rel)
    return resultado


def main() -> int:
    args = parse_args()
    base = Path(args.uploads_dir).resolve()

    print("=" * 70)
    print(f"DIAGNOSE UPLOADS — base: {base}")
    print("=" * 70)

    if not base.exists():
        print(f"ERROR: el directorio de uploads no existe: {base}", file=sys.stderr)
        return 1

    archivos_disco = listar_en_disco(base)

    conn = get_conn()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cur:
            cur.execute(
                "SELECT id, oficio_id, archivo_disco, nombre_original, created_at "
                "FROM evidencias_pdf ORDER BY id"
            )
            filas = cur.fetchall()
    finally:
        conn.close()

    registrados = {(r["archivo_disco"] or "").replace("\\", "/").lstrip("/"): r
                   for r in filas}

    # Huérfanos en disco: archivos que no aparecen en evidencias_pdf.
    # Se comparan normalizando a ruta relativa desde $uploads.
    huerfanos_fisicos: list[str] = []
    for rel in sorted(archivos_disco):
        # Los archivos dentro de imports/ son el staging del Excel,
        # no son evidencias. Se reportan por separado.
        if rel.startswith("imports/"):
            continue
        if rel not in registrados:
            huerfanos_fisicos.append(rel)

    # Referencias rotas: filas en BD sin archivo en disco.
    referencias_rotas: list[dict] = []
    for rel, row in registrados.items():
        if rel not in archivos_disco:
            referencias_rotas.append(row)

    # Conteo por subdirectorio.
    por_subdir: dict[str, int] = {}
    for rel in archivos_disco:
        sub = rel.split("/", 1)[0] if "/" in rel else "(raíz)"
        por_subdir[sub] = por_subdir.get(sub, 0) + 1

    print(f"\nArchivos en disco                       : {len(archivos_disco)}")
    print(f"Registros en evidencias_pdf             : {len(registrados)}")
    print(f"Huérfanos físicos (no en BD)            : {len(huerfanos_fisicos)}")
    print(f"Referencias rotas (en BD sin archivo)   : {len(referencias_rotas)}")
    print("\nArchivos por subdirectorio:")
    for sub, n in sorted(por_subdir.items()):
        print(f"  {sub:30s} {n:>6d}")

    if huerfanos_fisicos:
        print("\n--- Huérfanos físicos (candidatos a borrado manual tras respaldo) ---")
        for rel in huerfanos_fisicos[:200]:
            print(f"  {rel}")
        if len(huerfanos_fisicos) > 200:
            print(f"  ... (+{len(huerfanos_fisicos) - 200} más)")

    if referencias_rotas:
        print("\n--- Referencias rotas (evidencias_pdf apunta a archivo inexistente) ---")
        for r in referencias_rotas[:200]:
            print(f"  id={r['id']:>6}  oficio_id={r['oficio_id']!s:>6}  "
                  f"archivo_disco={r['archivo_disco']}")
        if len(referencias_rotas) > 200:
            print(f"  ... (+{len(referencias_rotas) - 200} más)")

    print("\nReporte completado. No se modificó ningún archivo ni registro.")
    # Exit code 0 siempre que el script haya corrido bien, incluso si hay hallazgos:
    # el objetivo es diagnóstico, no gate.
    return 0


if __name__ == "__main__":
    sys.exit(main())
