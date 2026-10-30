# MerchantOps

A learning project: a diagnostics service for order synchronization with an external marketplace. The marketplace is simulated inside the application.

Stack: Laravel 13 (PHP 8.3), PostgreSQL 16, Docker. Docker is the only local requirement. A React frontend is planned.

## About

### Problem

A shop sends its orders to a marketplace. Some orders fail to sync: the marketplace times out, is unavailable, rate-limits requests, rejects the order data or rejects the credentials. An operator needs to see which orders are stuck, why, whether a retry can help, and what happened on every previous attempt.

### What the service does

For an operator, MerchantOps is meant to:

- list orders with their current sync status (`pending`, `synced`, `failed`);
- show the history of sync attempts for an order, with the error code and message of each failure;
- tell whether an error is retryable (`timeout`, `unavailable`, `rate_limited`) or not (`validation_failed`, `unauthorized`);
- retry synchronization of an order.

Only the data model exists today; see [Status](#status).

### Key concepts

- **Order** — a shop order with its current sync state: status, last error, number of attempts, marketplace order ID after a successful sync.
- **Sync attempt** — one try to send an order to the marketplace. Attempts form an append-only log: rows are added, never changed, so the history stays accurate.
- **Simulated failure** — per-order setting of the simulated marketplace: which error to return and how many more times (or forever).

### Deterministic failure simulation

There is no real marketplace. Whether a sync attempt fails is decided only by the order's simulated failure setting, never by randomness. The same data always gives the same result, so every scenario can be reproduced by hand and in tests. This is why the demo data ties failures to specific orders (see [Demo data](#demo-data)).

### Learning goals

- Design a PostgreSQL schema where the database itself enforces consistency (CHECK constraints, foreign keys, unique indexes).
- Build a Laravel API around a domain with explicit states and error classification.
- Make failure scenarios reproducible and covered by tests.
- Run the whole stack in Docker with no local PHP.

### Status

| Task | Scope | State |
|---|---|---|
| 1.1 | Infrastructure, `GET /api/health` | done |
| 1.2 | Data model: migrations, enums, models, factories, seeder | done |
| 1.3 | Sync logic, controllers, API | planned |
| — | React frontend | planned |

Specifications live in [`docs/specs/`](docs/specs/).

## Getting started

```sh
cp .env.example .env   # set POSTGRES_PASSWORD
docker compose up -d
docker compose exec backend php artisan migrate --seed
```

The root `.env` holds the PostgreSQL credentials; `docker-compose.yml` passes them to both `db` and `backend`.

On first start the `backend` container installs Composer dependencies and, if `backend/.env` is missing, creates it from `.env.example` and generates `APP_KEY`.

Check:

```sh
curl http://localhost:8000/api/health
# {"status":"ok","database":"ok","timestamp":"2026-01-01T12:00:00+00:00"}
```

`timestamp` is the current server time in ISO 8601. If the database is unreachable, the endpoint returns `503` with `{"status":"error","database":"unavailable","timestamp":"..."}`.

## Demo data

`migrate --seed` in Getting started fills an empty database with a fixed data set: 20 pending orders `MO-0001` … `MO-0020` and 5 simulated marketplace failures (`MO-0003`, `MO-0005`, `MO-0007`, `MO-0009`, `MO-0011`). The data is the same on every run. Seeding a database that already has these orders fails on the unique order number.

Full reset, only as a last resort — **deletes all data in the main database**:

```sh
docker compose exec backend php artisan migrate:fresh --seed
```

## Tests

```sh
docker compose exec backend php artisan test
```

Tests use a separate `merchantops_test` database. It is created by the script in `docker/postgres/init/` on the first start of an empty PostgreSQL volume, so after changing credentials in `.env` recreate the volume with `docker compose down -v`.

## Services

| Service | Port | Purpose |
|---|---|---|
| `db` | 5432 | PostgreSQL, credentials from the root `.env` |
| `backend` | 8000 | Laravel API (`php artisan serve`) |

PostgreSQL data (`pgdata`) and `vendor/` (`vendor`) live in named volumes and survive `docker compose down`.
Full reset: `docker compose down -v`.
