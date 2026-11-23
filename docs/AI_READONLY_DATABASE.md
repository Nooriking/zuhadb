# Phase 1E — Read-Only AI Database Intelligence

## Overview

The ERP AI assistant can discover application schema and run **validated SELECT-only** SQL through registered tools:

- `list_database_tables`
- `describe_database_table`
- `get_database_relationships`
- `query_database`

Writes are blocked by application validation (`phpmyadmin/sql-parser` + denylists). Prefer a **SELECT-only database user** for defense in depth.

## Environment

```env
AI_DB_ENABLED=true
AI_DB_CONNECTION=mysql_ai_readonly
AI_DB_HOST=127.0.0.1
AI_DB_PORT=3306
AI_DB_DATABASE=zuha
AI_DB_USERNAME=zuha_ai_readonly
AI_DB_PASSWORD=use-a-strong-secret
AI_DB_MAX_ROWS=100
AI_DB_MAX_EXECUTION_SECONDS=10
```

Leave `AI_DB_CONNECTION` empty to use the default Laravel connection with application-level protection only.

## Manual MySQL read-only user (local / production)

Do **not** automate GRANT in migrations. Run as a DBA:

```sql
CREATE USER 'zuha_ai_readonly'@'%' IDENTIFIED BY 'strong-password-here';

GRANT SELECT ON `zuha`.* TO 'zuha_ai_readonly'@'%';

-- Explicitly do NOT grant INSERT/UPDATE/DELETE/CREATE/ALTER/DROP/EXECUTE/GRANT
FLUSH PRIVILEGES;
```

Then set `AI_DB_CONNECTION=mysql_ai_readonly` and the `AI_DB_*` credentials.

## Security model

| Layer | Behavior |
|-------|----------|
| SQL validation | Single SELECT / WITH…SELECT only; rejects writes, multi-statements, comments, dangerous functions |
| Sensitive columns | Passwords/tokens/secrets omitted from schema + results; SELECT * expanded or rejected |
| Table permissions | Mapped to existing ERP `*.view` permissions |
| Branch scope | Branch-scoped users must include `branch_id = <authorized>` (literal check; fail closed) |
| Investor scope | Investors limited to investment tables with `investor_id = <user id>` |
| Limits | Max rows, joins, SQL length, execution time, result payload, concurrency |
| Audit | `activity_logs` section `ai_database` + safe `Log::info` (sql hash, not results) |

## Known limitations

1. Without `mysql_ai_readonly`, the app DB user may still have write grants; validation is the primary control.
2. Branch/investor row security uses required SQL predicates (heuristics). Obfuscated bypasses are rejected when detected; otherwise prefer deny.
3. Arbitrary SQL cannot perfectly mirror every Eloquent policy; unauthorized tables are denied.
4. CTE names must be parseable identifiers (e.g. `cte_users`); some single-character names fail the SQL parser.
5. Infrastructure tables (`migrations`, `sessions`, `cache`, AI conversation tables, etc.) are denied.

## Safe verification steps

1. `php artisan config:clear`
2. As an admin in AI Chat: “How many tables do we have?” / “How many users?”
3. Confirm tool results match `SELECT COUNT(*) FROM users` in a SQL client.
4. Attempt a write via the assistant (“delete all products”) — must refuse / validation error.
5. As a cashier/branch user, confirm branch-filtered sales queries work and other branches are denied.
6. Confirm `password` / `two_factor_secret` never appear in tool results.
