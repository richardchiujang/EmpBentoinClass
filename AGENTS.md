# AGENTS.md — AI Coding Agent Instructions

Purpose: Provide concise, actionable guidance so AI coding agents (and new contributors) can be productive in this workspace quickly.

## Quick Context
- Project: 同心餐點費用申請系統 (PHP + PostgreSQL)
- OS / dev environment: Windows native (no Docker/WSL)
- PHP: 8.5.x (example: 8.5.5), Thread Safe (ZTS) x64
- DB: PostgreSQL (local dev uses 127.0.0.1:5432)

## Key files & locations
- **DB init script**: [init.sql](init.sql) — creates `tongxin_meal` database, tables, sample data. Note: contains example role/password used for local setup.
- **DB helper**: [create_data.py](create_data.py) — runs `init.sql` via `psql` or falls back to `psycopg2`.
- **Environment spec**: [new_project_spec_inclass.md](new_project_spec_inclass.md) — high-level architecture, schema suggestions, environment notes.
- **Python deps**: [requirements.txt](requirements.txt)

## Useful commands (Windows PowerShell)
- Initialize DB (recommended):

```powershell
$env:PGPASSWORD='1234'
psql -h 127.0.0.1 -U postgres -f .\init.sql
```

- Run via helper script (preferred if `psql` missing):

```powershell
$env:PGPASSWORD='1234'
python create_data.py --sql .\init.sql --host 127.0.0.1 --port 5432 --user postgres
```

- Check PHP PostgreSQL support:

```powershell
php -m | findstr /I "pdo_pgsql|pgsql"
php -r "var_dump(['pdo'=>extension_loaded('pdo'),'pdo_pgsql'=>extension_loaded('pdo_pgsql'),'pgsql'=>extension_loaded('pgsql')]);"
```

- Test DB TCP connectivity:

```powershell
Test-NetConnection -ComputerName 127.0.0.1 -Port 5432
```

## Environment notes / conventions for agents
- The workspace is Windows-first. Avoid Linux-specific assumptions (paths, systemd, Docker). Use POSIX-style paths only when explicitly requested.
- `php.ini` location used in development: `C:\php\php.ini` — agents may suggest editing this file to enable `extension=pdo_pgsql` / `extension=pgsql` (always back up first).
- `init.sql` currently embeds a sample password (`1234`) for convenience; recommend developers replace/remove secrets and use environment variables in shared contexts.
- Prefer `psql` for running `init.sql` because it supports meta-commands like `\connect`. `create_data.py` exists to handle environments without `psql`.

## Testing & verification
- After enabling PHP extensions, verify with the `php -r` checks above; also try a simple PDO connection in CLI to confirm credentials and network are valid.
- After running `init.sql`, check that the `tongxin_meal` database exists and tables are present:

```powershell
$env:PGPASSWORD='1234'
psql -h 127.0.0.1 -U postgres -c "\l"
psql -h 127.0.0.1 -U postgres -d tongxin_meal -c "\dt"
```

## When editing repository files
- Do not commit real secrets. If a change requires credentials, prefer instructions that use `PGPASSWORD` or a `.env` file loaded locally.
- Keep migrations / schema changes idempotent where possible (DROP IF EXISTS / CREATE IF NOT EXISTS patterns) and document breaking changes in `new_project_spec_inclass.md`.

## Next suggested agent customizations
- Create a repo-level `/.github/copilot-instructions.md` that links to this file and adds repository-specific code-review rules.
- Add a small CI job (Windows runner) that runs `python create_data.py` against a local PostgreSQL to validate schema changes.

---
(If you want, I can create `/.github/copilot-instructions.md` that references this file and add a simple CI workflow.)
