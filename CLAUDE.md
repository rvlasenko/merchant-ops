# MerchantOps — project rules

See `README.md` for setup, commands and services. Do not duplicate them here.

- Stack is fixed: Laravel, React, PostgreSQL, Docker. Do not add other technologies (queues, Redis, nginx, auth, state managers, routers) without asking.
- Run PHP, Composer and Artisan only inside the container: `docker compose exec backend ...`. Do not install PHP on the host.
- Secrets live only in `.env` files (git-ignored). Commit `.env.example` with demo values.
- Synchronization errors are simulated deterministically. Never use randomness in sync logic or tests.
- Tests must run against `merchantops_test`, never the main database.
- Documentation and code comments are written in English.
- Do not commit without showing the file list to the user first.
