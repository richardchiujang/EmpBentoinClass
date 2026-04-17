#!/usr/bin/env python3
"""
create_data.py
Run init.sql against a PostgreSQL server.
Behavior:
 - Prefers to call the `psql` CLI (recommended, supports psql meta-commands like \connect).
 - If `psql` is not available, falls back to using psycopg2 to execute SQL statements (will skip psql backslash meta-commands).

Usage example:
  Set environment variable or pass arguments. Example using PowerShell:

  $env:PGPASSWORD='1234'
  python create_data.py --sql ./init.sql --host 127.0.0.1 --port 5432 --user postgres

Options:
  --sql      Path to SQL file (default: ./init.sql)
  --host     PostgreSQL host (default: 127.0.0.1)
  --port     PostgreSQL port (default: 5432)
  --user     PostgreSQL user (default: postgres)
  --password Password (overrides PGPASSWORD env var)
  --psql     Path to psql binary (optional)

Note: This script will not echo sensitive values. Use with care.
"""

import os
import sys
import argparse
import shutil
import subprocess


def run_with_psql(psql_path, sql_file, host, port, user, password):
    env = os.environ.copy()
    if password is not None:
        env['PGPASSWORD'] = password
    cmd = [psql_path, '-h', host, '-p', str(port), '-U', user, '-f', sql_file]
    print('Running psql:', ' '.join(cmd))
    try:
        subprocess.run(cmd, check=True, env=env)
        print('psql finished successfully.')
        return 0
    except subprocess.CalledProcessError as e:
        print('psql failed with return code', e.returncode)
        return e.returncode


def run_with_psycopg(sql_file, host, port, user, password):
    try:
        import psycopg2
    except Exception as e:
        print('psycopg2 not available:', e)
        return 2

    # Read SQL and filter out psql meta-commands (lines starting with backslash)
    with open(sql_file, 'r', encoding='utf-8') as f:
        raw = f.read()

    lines = [l for l in raw.splitlines() if not l.lstrip().startswith('\\')]
    sql = '\n'.join(lines)

    # Connect to default 'postgres' database first (needed to create role/db)
    conn_postgres = None
    conn_target = None
    try:
        conn_postgres = psycopg2.connect(host=host, port=port, user=user, password=password, dbname='postgres')
        conn_postgres.autocommit = True
        cur = conn_postgres.cursor()

        # Split by semicolon and execute statements that are non-empty
        statements = [s.strip() for s in sql.split(';') if s.strip()]

        current_conn = conn_postgres
        current_cursor = cur
        current_db = 'postgres'

        for stmt in statements:
            s = stmt.strip()
            if not s:
                continue

            # Detect CREATE DATABASE and execute on postgres connection,
            # then open a new connection to the created DB for subsequent statements.
            lower = s.lower()
            try:
                if lower.startswith('create database'):
                    # execute on postgres
                    current_cursor.execute(s)

                    # attempt to extract the database name to switch into it
                    # naive parsing: CREATE DATABASE dbname OWNER ...
                    parts = s.split()
                    dbname = None
                    if len(parts) >= 3:
                        dbname = parts[2].strip('"')
                    if dbname:
                        # Try to connect to the created database, retry a few times if needed
                        import time
                        connected = False
                        for attempt in range(5):
                            try:
                                conn_target = psycopg2.connect(host=host, port=port, user=user, password=password, dbname=dbname)
                                conn_target.autocommit = True
                                # close previous cursor and set new
                                try:
                                    current_cursor.close()
                                except Exception:
                                    pass
                                current_conn = conn_target
                                current_cursor = conn_target.cursor()
                                current_db = dbname
                                print(f"Switched execution to database: {dbname}")
                                connected = True
                                break
                            except Exception:
                                time.sleep(0.5)
                        if not connected:
                            print(f"Created database {dbname}, but could not open connection immediately.")
                    else:
                        print('Created database (name parsing failed).')
                else:
                    # Execute other statements on the current connection (target DB if switched)
                    current_cursor.execute(s)
            except Exception as e:
                print('Failed statement (continuing):')
                print(s[:200])
                print('Error:', e)

        # Close current cursor
        try:
            current_cursor.close()
        except Exception:
            pass

        print('psycopg2 execution completed (skipped psql meta-commands).')
        return 0
    except Exception as e:
        print('psycopg2 execution failed:', e)
        return 3
    finally:
        if conn_postgres:
            try:
                conn_postgres.close()
            except Exception:
                pass
        if conn_target and conn_target != conn_postgres:
            try:
                conn_target.close()
            except Exception:
                pass


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--sql', default='init.sql', help='Path to SQL file')
    p.add_argument('--host', default='127.0.0.1')
    p.add_argument('--port', default=5432, type=int)
    p.add_argument('--user', default='postgres')
    p.add_argument('--password', default=None)
    p.add_argument('--psql', default=None, help='Optional path to psql binary')
    args = p.parse_args()

    sql_file = args.sql
    if not os.path.exists(sql_file):
        print('SQL file not found:', sql_file)
        sys.exit(1)

    password = args.password or os.environ.get('PGPASSWORD')

    # Determine psql path
    psql_path = args.psql or shutil.which('psql')

    if psql_path:
        rc = run_with_psql(psql_path, sql_file, args.host, args.port, args.user, password)
        if rc == 0:
            sys.exit(0)
        else:
            print('psql failed, trying psycopg2 fallback...')

    # Fallback
    rc = run_with_psycopg(sql_file, args.host, args.port, args.user, password)
    sys.exit(rc)


if __name__ == '__main__':
    main()
