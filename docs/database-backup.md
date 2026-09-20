# Database Backup & Migration Safety

This document describes how to safely back up the PostgreSQL database and run
migrations without risking data loss.

> **Lesson learned:** a column-adding migration never deletes rows, but
> `php artisan migrate:fresh` drops **every** table and recreates them empty.
> Always back up before touching the schema.

## When to Back Up

- **Before running any migration** that modifies existing tables
  (`add_*` / `change` migrations).
- Before running `php artisan migrate:fresh`, `migrate:refresh`, or `db:wipe`
  (these are destructive and should rarely be used on a live database).
- Periodically (e.g., end of each working day).

## Requirements

- PostgreSQL is installed. Binaries (Windows) live at:
  `C:\Program Files\PostgreSQL\18\bin\`
- Connection details (from `.env`):
  - Host: `127.0.0.1`
  - Port: `5432`
  - Database: `shipping-management`
  - User: `postgres`

## Full Backup (pg_dump)

Open **PowerShell** and run (from the project root):

```powershell
$env:PGPASSWORD="postgres"
$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
& "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe" `
  -h 127.0.0.1 -U postgres -d shipping-management `
  -f "backups\db_$stamp.sql"
```

- The dump is a plain SQL file, e.g. `backups/db_20260818_153000.sql`.
- Restore from it anytime with `psql` (see below).

> **Tip:** use `--schema-only` instead of a full dump when you only need to
> inspect the structure, e.g. before deciding on a migration.

## Restore

A plain `pg_dump` file contains `CREATE TABLE` statements but **no** `DROP
TABLE`. Running it against a database that already has the tables fails with
"relation already exists". So you must restore into a **fresh** database:

```powershell
$env:PGPASSWORD="postgres"
$pg = "C:\Program Files\PostgreSQL\18\bin"

# 1. Disconnect any app connections
& "$pg\psql.exe" -h 127.0.0.1 -U postgres -d postgres -c `
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'shipping-management' AND pid <> pg_backend_pid();"

# 2. Drop and recreate the database (deletes its current contents)
& "$pg\psql.exe" -h 127.0.0.1 -U postgres -d postgres -c `
  "DROP DATABASE IF EXISTS `"shipping-management`";"
& "$pg\psql.exe" -h 127.0.0.1 -U postgres -d postgres -c `
  "CREATE DATABASE `"shipping-management`";"

# 3. Restore from the dump
& "$pg\psql.exe" -h 127.0.0.1 -U postgres -d shipping-management `
  -v ON_ERROR_STOP=1 -f "backups\db_20260818_153000.sql"
```

> Restoring overwrites existing rows in the tables present in the dump.

## Safe Migration Workflow

1. Back up the database (command above).
2. Run the migration: `php artisan migrate`.
3. Verify: `php artisan migrate:status` — new migrations appear in the **next
   batch number**, and your row counts are unchanged.
4. If something goes wrong: restore the dump, then re-run the migration after
   fixing the issue.

## Rules to Avoid Data Loss

| Do                                              | Don't                                        |
| ----------------------------------------------- | -------------------------------------------- |
| Use `php artisan migrate`                       | Run `migrate:fresh` on a live database       |
| Add columns as `nullable`                       | Drop columns that still hold data            |
| Back up before any schema change                | Reset the DB just to test something          |
| Write migrations that add columns/table only    | Delete migrations from an already-run batch  |