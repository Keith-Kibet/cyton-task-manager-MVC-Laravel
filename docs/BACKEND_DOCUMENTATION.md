# Backend documentation — Task Management API

This document ties **interviewer requirements** (Cytonn Software Engineering internship take-home, MySQL + Laravel) to the **implemented API** in this repository. Use it for submission notes, interviews, and onboarding.

**Interactive API reference:** Swagger UI at `GET /api/documentation` (regenerate spec: `php artisan l5-swagger:generate`).

---

## 1. Interviewer requirements → implementation

| # | Requirement (take-home) | Implementation |
|---|-------------------------|----------------|
| 1 | MySQL database | `DB_CONNECTION=mysql` in `.env`; migrations target MySQL-compatible DDL |
| 2 | Migration files for easy setup | `database/migrations/*` — `users`, `tasks` (+ soft deletes), Sanctum `personal_access_tokens` |
| 3 | Table `tasks`: `id`, `title`, `due_date`, `priority`, `status`, timestamps | `2026_04_01_000002_create_tasks_table.php` + `2026_04_02_000001_add_soft_deletes_to_tasks_table.php` (`deleted_at`) |
| 4 | **POST `/api/tasks`**: no duplicate same `title` + same `due_date` for **active** tasks; `due_date` ≥ today; priority enum | `StoreTaskRequest` — unique rule scopes to `whereNull('deleted_at')`; DB unique on `(title, due_date)` removed in favor of app validation + archives |
| 5 | **GET `/api/tasks`**: sort priority high → low, then `due_date` ASC; optional `status`; meaningful JSON when empty | `TaskController@index`; optional `archived` query; response includes **`counts.active`** / **`counts.archived`** |
| 6 | **PATCH `/api/tasks/{id}/status`**: only `pending → in_progress → done` | `TaskController@updateStatus` |
| 7 | **DELETE `/api/tasks/{id}`**: may remove only **`done`** tasks (**403** otherwise) | **Soft delete** = archive. No role bypass; `TaskPolicy` (visibility) + controller (`status === done`); `SoftDeletes` |
| 8 | **Bonus:** **GET `/api/tasks/report?date=`** counts per priority × status | `TaskController@report`; excludes soft-deleted rows by default |
| 9 | README: local run, deploy, example requests | [README.md](../README.md) (includes first-time `composer install` / `npm install` because `vendor/` and `node_modules/` are not in the archive) |
| 10 | Host online with MySQL for testing | Candidate deploys; options documented |

**Extensions (beyond minimal PDF):** JWT auth, RBAC, Swagger UI, Vue UI, **archive / restore / permanent delete**, UUID keys, modular Vue components.

---

## 2. Technology stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 13 |
| Database | MySQL (required by brief); PlanetScale MySQL-compatible acceptable for hosting |
| HTTP API | JSON, route prefix `/api` |
| Auth | **JWT** (`firebase/php-jwt`, HS256). `JWT_SECRET` in `.env`. `AuthenticateJwt` middleware sets `Auth::user()` for policies. `laravel/sanctum` installed but **not** used for Bearer API auth. |
| API docs | `darkaonline/l5-swagger` (OpenAPI 3, Swagger UI) |

---

## 3. Data model

### 3.1 Table `users`

| Column | Type | Notes |
|--------|------|--------|
| `id` | UUID (PK) | `HasUuids` |
| `name`, `email`, `password` | | Standard |
| `role` | string | `user` (default) or `admin` |
| `timestamps` | | |

### 3.2 Table `tasks`

| Column | Type | Notes |
|--------|------|--------|
| `id` | UUID (PK) | `HasUuids` |
| `user_id` | UUID FK → `users.id` | Ownership; scoped per user (admin sees all) |
| `title` | string | |
| `due_date` | date | |
| `priority` | enum | `low`, `medium`, `high` |
| `status` | enum | `pending`, `in_progress`, `done` |
| `deleted_at` | timestamp nullable | **Soft deletes** — “archived” tasks |
| `created_at`, `updated_at` | timestamp | |

**Uniqueness:** Duplicate `(title, due_date)` is enforced in **`StoreTaskRequest`** for rows with `deleted_at` null (archived copies do not block re-use of the same title+date).

### 3.3 Table `personal_access_tokens` (unused for API Bearer auth)

Sanctum migration remains; protected routes use **JWT**.

---

## 4. API specification

**Base URL:** `{APP_URL}/api`.

### 4.1 Authentication

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/register` | No | `role=user`; returns JWT + user |
| POST | `/login` | No | Returns JWT + user |

**Header:** `Authorization: Bearer {jwt}`

### 4.2 Create task

| | |
|--|--|
| **POST** | `/tasks` |
| **Body** | `title`, `due_date` (≥ today), `priority` |
| **Rules** | Unique among **non-archived** tasks for same `(title, due_date)`; `user_id` = current user |
| **Success** | `201` + task JSON |

### 4.3 List tasks

| | |
|--|--|
| **GET** | `/tasks` |
| **Query** | Optional `status`; optional **`archived`** — use `true`/`false`, `1`/`0`, or omit (defaults to active only). Parsed with `$request->boolean('archived')` (query strings are strings). |
| **Sort** | Priority high → medium → low, then `due_date` ASC |
| **Scope** | User: own tasks. Admin: all. |
| **Success** | `200` + `{ "tasks": [...], "counts": { "active": { "pending", "in_progress", "done", "total" }, "archived": { ... } } }` — optional `message` when list empty |

### 4.4 Update task status

| | |
|--|--|
| **PATCH** | `/tasks/{id}/status` |
| **Body** | `{ "status": "pending" \| "in_progress" \| "done" }` — only forward transitions |
| **Scope** | Non-trashed task only (implicit route model binding) |

### 4.5 Delete (archive) task

| | |
|--|--|
| **DELETE** | `/tasks/{id}` |
| **Behavior** | **Soft delete** (`deleted_at` set). |
| **Rule** | Only if `status === done` (else **403**) — **all** authenticated users, including admin. |
| **Success** | `204` |

### 4.6 Restore archived task

| | |
|--|--|
| **POST** | `/tasks/{id}/restore` |
| **Behavior** | Clears `deleted_at`. |
| **Auth** | `TaskPolicy@restore` (same visibility as `view`). |

### 4.7 Permanently delete archived task

| | |
|--|--|
| **DELETE** | `/tasks/{id}/force` |
| **Behavior** | **`forceDelete`** — row removed from DB. |
| **Rules** | Task must be **archived** (soft-deleted). Permanent delete only if `status === done` (else **403**), same as archive. |

### 4.8 Daily report (bonus)

| | |
|--|--|
| **GET** | `/tasks/report?date=YYYY-MM-DD` |
| **Scope** | Non-archived tasks only; user vs admin as before |

**Example shape:**

```json
{
  "date": "2026-03-28",
  "summary": {
    "high": { "pending": 0, "in_progress": 0, "done": 0 },
    "medium": { "pending": 0, "in_progress": 0, "done": 0 },
    "low": { "pending": 0, "in_progress": 0, "done": 0 }
  }
}
```

### 4.9 Admin endpoints

| Method | Path | Auth |
|--------|------|------|
| GET | `/admin/users` | Admin |
| PATCH | `/admin/users/{id}/role` | Admin |

---

## 5. Authorization model (RBAC)

| Role | Task list | Status update | Archive (soft delete) | Report | Admin routes |
|------|-----------|----------------|------------------------|--------|--------------|
| `user` | Own | Own | Own; only `done` unless admin | Own | No |
| `admin` | All | All | All statuses | All | Yes |

`TaskPolicy` + `EnsureUserIsAdmin` + `AuthenticateJwt` (sets `Auth::user()` for Gate).

---

## 6. Evaluation criteria — mapping

| Criterion | Notes |
|-----------|--------|
| Business rules | Form requests, policies, soft delete, archive/restore/force |
| Laravel usage | Eloquent, SoftDeletes, migrations, policies, middleware |
| Readability | Controllers, requests, policies, Vue components split by concern |

---

## 7. Code map

| Area | Location |
|------|----------|
| Routes | `routes/api.php` |
| Tasks | `app/Http/Controllers/Api/TaskController.php` |
| Auth | `app/Http/Controllers/Api/AuthController.php` |
| Admin | `app/Http/Controllers/Api/AdminUserController.php` |
| JWT middleware | `app/Http/Middleware/AuthenticateJwt.php` |
| Validation | `StoreTaskRequest`, `UpdateTaskStatusRequest` |
| Policies | `app/Policies/TaskPolicy.php` |
| Models | `Task` (SoftDeletes), `User` |
| OpenAPI | `app/OpenApi/ApiSpec.php` + controller attributes |
| Vue shell | `resources/js/TaskApp.vue` |
| Vue tasks | `resources/js/components/tasks/*.vue` |

---

## 8. Submission

See **[README.md](../README.md)** (section **Deployment & submission**) for hosting notes and env expectations. After unzipping, run **`composer install`** and **`npm install`** first — **`vendor/`** and **`node_modules/`** are omitted from the archive.

Swagger UI is the **machine-readable** contract; this document is the **human-readable** traceability matrix.
