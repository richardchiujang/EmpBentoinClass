#!/usr/bin/env python3
import sys, os
try:
    import psycopg2
except Exception as e:
    print('psycopg2 not available:', e)
    sys.exit(1)

if len(sys.argv) < 2:
    print('Usage: apply_sql_to_db.py <sql-file> [host] [port] [user] [password] [dbname]')
    sys.exit(1)

sql_file = sys.argv[1]
host = sys.argv[2] if len(sys.argv) > 2 else '127.0.0.1'
port = int(sys.argv[3]) if len(sys.argv) > 3 else 5432
user = sys.argv[4] if len(sys.argv) > 4 else 'postgres'
password = sys.argv[5] if len(sys.argv) > 5 else os.environ.get('PGPASSWORD')
dbname = sys.argv[6] if len(sys.argv) > 6 else 'tongxin_meal'

if not os.path.exists(sql_file):
    print('SQL file not found:', sql_file)
    sys.exit(1)

with open(sql_file, 'r', encoding='utf-8') as f:
    raw = f.read()

# remove psql meta-commands
lines = [l for l in raw.splitlines() if not l.lstrip().startswith('\\')]
sql = '\n'.join(lines)
statements = [s.strip() for s in sql.split(';') if s.strip()]

try:
    conn = psycopg2.connect(host=host, port=port, user=user, password=password, dbname=dbname)
    conn.autocommit = True
    cur = conn.cursor()
    for s in statements:
        try:
            cur.execute(s)
        except Exception as e:
            print('Failed statement (continuing):')
            print(s[:200])
            print('Error:', e)
    cur.close()
    conn.close()
    print('Finished executing SQL against', dbname)
except Exception as e:
    print('Connection/execution failed:', e)
    sys.exit(1)
