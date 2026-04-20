"""Aplica sql/07_incidencias.sql usando psycopg2."""
import os, sys, psycopg2

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SQL  = os.path.join(ROOT, "sql", "07_incidencias.sql")

with open(SQL, "r", encoding="utf-8") as fh:
    script = fh.read()

conn = psycopg2.connect(host="localhost", port=5432, dbname="respaldooficios",
                        user="postgres", password="admin")
conn.autocommit = True
try:
    with conn.cursor() as cur:
        cur.execute(script)
        # drain ultimo SELECT de verificacion si existe
        try:
            while True:
                rows = cur.fetchall()
                if rows:
                    for r in rows:
                        print(r)
                if not cur.nextset():
                    break
        except psycopg2.ProgrammingError:
            pass
    print("OK: migracion 07_incidencias.sql aplicada.")
finally:
    conn.close()
