# Task Management API

Laravel 13 + MySQL API for the Cytonn Software Engineering internship take-home: tasks CRUD, status rules, daily report (bonus), **Swagger UI**, a **Vue 3 + Tailwind** web UI at `/`, **JWT (Bearer)** authentication (`firebase/php-jwt`), simple **RBAC** (user / admin), **soft-deleted “archived” tasks** with restore and permanent delete, and a **modular** frontend under `resources/js/components/tasks/`.

The `laravel/sanctum` package remains in `composer.json` but **API auth uses JWT only** for protected routes.

**Documentation index**

| Document | Purpose |
|----------|---------|
| [docs/BACKEND_DOCUMENTATION.md](docs/BACKEND_DOCUMENTATION.md) | Requirements traceability, API contract, data model |

**IDs:** `users.id` and `tasks.id` are **UUIDs**. After schema changes, run `php artisan migrate` (or `migrate:fresh --seed` in local dev).

---

## What is included vs what you must install

This repository is shipped **without** `vendor/` (Composer) and **without** `node_modules/` (npm), so the archive stays small. **They are not missing by mistake** — restore them before running the app:

| You need on your machine | Purpose |
|--------------------------|---------|
| **PHP** (8.2+), **Composer** | `composer install` recreates `vendor/` |
| **Node.js**, **npm** | `npm install` recreates `node_modules/`; `npm run build` compiles the Vue frontend into `public/build/` |

After `composer install`, all `php artisan …` commands work. After `npm install` and `npm run build`, the web UI and Vite assets load correctly.

---

---

## Features (summary)

| Area | What you get |
|------|----------------|
| **Tasks** | Create, list (sorted by priority then due date), optional `status` filter |
| **Archive** | `DELETE /api/tasks/{id}` **soft-deletes** (archives). Unique `(title, due_date)` applies to **non-archived** rows only |
| **Restore** | `POST /api/tasks/{id}/restore` |
| **Permanent delete** | `DELETE /api/tasks/{id}/force` (archived rows only; same business rules as before for role/status) |
| **List** | `GET /api/tasks?archived=true|false` + response **`counts.active`** and **`counts.archived`** (pending / in_progress / done / total per bucket) |
| **Report (bonus)** | `GET /api/tasks/report?date=YYYY-MM-DD` — non-archived tasks only |
| **Web UI** | Login/register, full-width header, **Active / Archived** tabs with status badges, task **table**, light/dark theme, report, admin user list |
| **Swagger** | OpenAPI + UI at `/api/documentation` |
| **Auth** | JWT in `Authorization: Bearer` — set `JWT_SECRET` in `.env` |

---

## Swagger UI

1. Run the app (see below).
2. Open **[http://127.0.0.1:8000/api/documentation](http://127.0.0.1:8000/api/documentation)** (or your deployed URL + `/api/documentation`).
3. Obtain a JWT via **POST `/api/register`** or **POST `/api/login`**.
4. **Authorize** with `Bearer <jwt>`.
5. Try **Tasks** (and **Admin** if you use an admin account).

After changing controllers or OpenAPI attributes:

```bash
php artisan l5-swagger:generate
```

---

## Local setup (XAMPP / MySQL)

1. **Install PHP dependencies:** from the project root, run `composer install` (creates `vendor/`).
2. **Install JS dependencies:** run `npm install` (creates `node_modules/`).
3. Create a MySQL database (e.g. `task_management`).
4. Copy `.env.example` to `.env`, run `php artisan key:generate`, set **`JWT_SECRET`** (long random string), and set `DB_*` for MySQL.
5. Build frontend assets and run migrations:

```bash
npm run build
php artisan migrate --seed
php artisan serve
```

Open **http://127.0.0.1:8000** for the UI. API docs: **`/api/documentation`**.

**Hot reload (dev):** run `npm run dev` in another terminal alongside `php artisan serve`.

---

## Demo users (after seed)

| Email             | Password | Role  |
|------------------|----------|-------|
| admin@example.com | password | admin |
| user@example.com  | password | user  |

---

## Core API (challenge + archive)

| Method | Path | Notes |
|--------|------|--------|
| POST | `/api/tasks` | Unique active `(title, due_date)`; `due_date` ≥ today; priority enum |
| GET | `/api/tasks` | Sort: priority high → low, then `due_date` ASC; optional `status`; optional `archived` (boolean via query string); returns **`counts`** |
| PATCH | `/api/tasks/{id}/status` | Only `pending` → `in_progress` → `done` |
| DELETE | `/api/tasks/{id}` | **Soft delete (archive)** — only if status is **`done`** (else **403**; all roles) |
| POST | `/api/tasks/{id}/restore` | Restore from archive |
| DELETE | `/api/tasks/{id}/force` | **Permanent** delete (must be archived; **`done` only** or **403**) |
| GET | `/api/tasks/report?date=YYYY-MM-DD` | Counts per priority × status for that **due_date** (non-archived tasks) |

---

## Frontend layout

| Path | Role |
|------|------|
| `resources/js/TaskApp.vue` | Shell: auth, theme, nav, report, admin |
| `resources/js/components/tasks/TaskNewForm.vue` | New task form |
| `resources/js/components/tasks/TaskListPanel.vue` | Tabs, filters, table wrapper |
| `resources/js/components/tasks/TaskTabs.vue` | Active / Archived |
| `resources/js/components/tasks/TaskStatusBadges.vue` | Pending / In progress / Done counts |
| `resources/js/components/tasks/TaskTable.vue` | Task rows and actions |

---

## Deployment & submission

On any server or PaaS (e.g. Railway, Render): set the same `.env` variables (MySQL, `APP_KEY`, `JWT_SECRET`), run `composer install --no-dev`, `npm ci` (or `npm install`), `npm run build`, then `php artisan migrate --seed` (or migrate only in production). Ensure the document root points at `public/`. Include **`composer.lock`** in the repo so `composer install` resolves the same versions.

---

## Example requests

```bash
# Login
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# List active tasks (replace TOKEN)
curl "http://127.0.0.1:8000/api/tasks" \
  -H "Authorization: Bearer TOKEN"

# List archived tasks
curl "http://127.0.0.1:8000/api/tasks?archived=1" \
  -H "Authorization: Bearer TOKEN"
```
