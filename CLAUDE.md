# MerchantOps — project rules

See `README.md` for setup, commands and services. Do not duplicate them here.

- Stack is fixed: Laravel, React, PostgreSQL, Docker. Do not add other technologies (queues, Redis, nginx, auth, state managers, routers) without asking.
- Run PHP, Composer and Artisan only inside the container: `docker compose exec backend ...`. Do not install PHP on the host.
- Secrets live only in `.env` files (git-ignored). Commit `.env.example` with demo values.
- Synchronization errors are simulated deterministically. Never use randomness in sync logic or tests.
- Tests must run against test database, never the main database.
- Do not commit without showing the file list to the user first.
- Change only files relevant to the approved task.
- Do not refactor unrelated code without asking.
- When functionality changes, check which related documents (`README.md`, `docs/specs/`) need updating and list them in the summary. Do not rewrite them automatically unless necessary.
- After implementation, run relevant tests and summarize changed files, test results, and known limitations.

## Language

- Communicate with the user in Russian.
- Write plans, explanations, and review summaries in Russian.
- Keep code, code comments, documentation, and Git commit messages in English.
- Do not translate technical identifiers or commands.
