# MerchantOps

A learning project: a diagnostics service for order synchronization with an external marketplace. The marketplace is simulated inside the application.

Stack: Laravel 13 (PHP 8.3), PostgreSQL 16, Docker. Docker is the only local requirement.

## Getting started

```sh
cp .env.example .env   # set POSTGRES_PASSWORD
docker compose up -d
docker compose exec backend php artisan migrate
```

The root `.env` holds the PostgreSQL credentials; `docker-compose.yml` passes them to both `db` and `backend`.

On first start the `backend` container installs Composer dependencies and, if `backend/.env` is missing, creates it from `.env.example` and generates `APP_KEY`.

Check:

```sh
curl http://localhost:8000/api/health
# {"status":"ok","database":"ok","timestamp":"2026-01-01T12:00:00+00:00"}
```

`timestamp` is the current server time in ISO 8601. If the database is unreachable, the endpoint returns `503` with `{"status":"error","database":"unavailable","timestamp":"..."}`.

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
