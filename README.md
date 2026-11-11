# MerchantOps

[![CI](https://github.com/rvlasenko/merchant-ops/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/rvlasenko/merchant-ops/actions/workflows/ci.yml)

A learning project for practicing AI-assisted development and backend engineering: a service that syncs orders to an external order-processing service and records the result of every sync. The external service is simulated inside the application. MerchantOps is not an ERP or fulfillment system.

**In one picture.** Think of sending letters through a post office. Each order is a letter; the external service is the post office. Sometimes the post office refuses a letter: "too busy, come back later", "the address is wrong", "you have no contract with us". MerchantOps is the notebook where you record every trip to the post office — when you went, what they said, and whether going again makes sense — plus a button to try again. "Too busy" is worth retrying; "wrong address" is not, until someone fixes the address.

Stack: Laravel 13 (PHP 8.3), PostgreSQL 16, Docker. Docker is the only local requirement. A React frontend is planned.

## About

### Problem

Orders already exist in MerchantOps: they are created elsewhere, and MerchantOps never creates them (here they come only from the [demo data](#demo-data)). MerchantOps syncs them to an external service that processes them. Some syncs fail: the external service times out, is unavailable, rate-limits requests, rejects the order data or rejects the credentials. An operator needs to see which orders are stuck, why, whether a retry can help, and what happened on every previous attempt.

The project covers only the sync and its result. What the external service does with an order (warehouse, shipping, delivery), status updates coming back from it, and order line items are out of scope.

### What the service does

MerchantOps lets an operator:

- list orders with their current sync status (`pending`, `synced`, `failed`);
- show the history of sync attempts for an order, with the error code and message of each failure;
- tell whether an error is retryable (`timeout`, `unavailable`, `rate_limited`) or not (`validation_failed`, `unauthorized`);
- retry the sync of an order.

Today this works through the JSON [API](#api); a React UI is planned. See [Status](#status).

### Key concepts

- **Sync** — sending an order to the external service. The project uses this one term for it.
- **Order** — an order with its current sync state: status, last error, number of attempts, external order ID after a successful sync.
- **Sync attempt** — the record of one sync of an order. Attempts form an append-only log: rows are added, never changed, so the history stays accurate.
- **Simulated failure** — per-order setting of the simulated external service: which error to return and how many more times (or forever). This is a demo and test tool, not part of the product: it stands in for a real external service and would not exist in a real integration.

### Deterministic failure simulation

There is no real external service. Whether a sync attempt fails is decided only by the order's simulated failure setting, never by randomness. The same data always gives the same result, so every scenario can be reproduced by hand and in tests. This is why the demo data ties failures to specific orders (see [Demo data](#demo-data)).

### Status

| Task | Scope | State |
|---|---|---|
| 1.1 | Infrastructure, `GET /api/health` | done |
| 1.2 | Data model: migrations, enums, models, factories, seeder | done |
| 1.3 | Sync logic, controllers, API | done |
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

## API

JSON over HTTP at `http://localhost:8000/api`. No authentication. Full contract: [`docs/specs/1.3-sync-api.md`](docs/specs/1.3-sync-api.md) §6.

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/api/orders` | List orders, 15 per page, by `id`. Optional `?sync_status=pending\|synced\|failed` | 200 |
| `GET` | `/api/orders/{id}` | Order with its simulated failure and attempts, newest first | 200 |
| `POST` | `/api/orders/{id}/sync` | Sync the order (first send or retry) | 200 |
| `PUT` | `/api/orders/{id}/simulated-failure` | Set the simulated failure: `{"error_code": "timeout", "remaining": 2}` | 200 |
| `DELETE` | `/api/orders/{id}/simulated-failure` | Remove the simulated failure | 204 |

- A sync whose attempt failed still returns `200`: the attempt was made and recorded. Read `sync_status` and `attempts[0]` in the body.
- `409` — the order is already synced; nothing changes.
- `404` — unknown or non-numeric order ID. `422` — invalid input.
- `last_error_retryable` tells whether a retry can help. A retry of a non-retryable error is allowed, but it fails again until the cause is removed.
- `remaining` in `PUT` is required: an integer `>= 1`, or `null` for "fail on every sync".

### Try it

On freshly seeded data, where IDs 3 and 9 are `MO-0003` and `MO-0009`. This changes the demo data (see [Demo data](#demo-data)).

```sh
# MO-0003: timeout once, then success
curl -X POST localhost:8000/api/orders/3/sync   # 200, failed, timeout, retryable
curl -X POST localhost:8000/api/orders/3/sync   # 200, synced, EXT-MO-0003
curl -X POST localhost:8000/api/orders/3/sync   # 409

# MO-0009: validation_failed on every sync, until the simulation is removed
curl -X POST localhost:8000/api/orders/9/sync                  # 200, failed, retryable=false
curl -X DELETE localhost:8000/api/orders/9/simulated-failure   # 204
curl -X POST localhost:8000/api/orders/9/sync                  # 200, synced

curl localhost:8000/api/orders/3                       # 2 attempts, newest first
curl 'localhost:8000/api/orders?sync_status=failed'
```

## Demo data

`migrate --seed` in Getting started fills an empty database with a fixed data set: 20 pending orders `MO-0001` … `MO-0020` and 5 simulated external service failures. The data is the same on every run. Seeding a database that already has these orders fails on the unique order number.

| Order | Simulated error | Fails | Then |
|---|---|---|---|
| `MO-0003` | `timeout` | once | succeeds |
| `MO-0005` | `rate_limited` | twice | succeeds |
| `MO-0007` | `unavailable` | always | — |
| `MO-0009` | `validation_failed` | always | — |
| `MO-0011` | `unauthorized` | always | — |

All other orders succeed on the first sync. An order that fails "always" succeeds once its simulated failure is removed (`DELETE`, see [API](#api)).

Syncing changes the demo data: synced orders stay synced. The only way back to the initial state is the full reset below.

Full reset, only as a last resort — **deletes all data in the main database**:

```sh
docker compose exec backend php artisan migrate:fresh --seed
```

## Tests

```sh
docker compose exec backend php artisan test
```

Tests use a separate `merchantops_test` database. It is created by the script in `docker/postgres/init/` on the first start of an empty PostgreSQL volume, so after changing credentials in `.env` recreate the volume with `docker compose down -v` — **this deletes all data in the main database**.

Code style is checked with [Laravel Pint](https://laravel.com/docs/pint) (config: `backend/pint.json`):

```sh
docker compose exec backend composer lint     # check only
docker compose exec backend composer format   # fix in place
```

### CI

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push to `main` and every pull request:

- **Backend** — Pint, then `php artisan test` against a PostgreSQL 16 service container with only the `merchantops_test` database.
- **Frontend** — `npm ci`, `lint`, `typecheck`, `test`, `build`.

Composer and npm downloads are cached between runs, keyed by the lock files.

## Services

| Service | Port | Purpose |
|---|---|---|
| `db` | 5432 | PostgreSQL, credentials from the root `.env` |
| `backend` | 8000 | Laravel API (`php artisan serve`) |

PostgreSQL data (`pgdata`) and `vendor/` (`vendor`) live in named volumes and survive `docker compose down`.
Full reset, only as a last resort — **deletes all data in the main database and the `vendor` volume**: `docker compose down -v`.
