# Deployment and submission (Cytonn take-home)

This guide complements [README.md](../README.md) and [BACKEND_DOCUMENTATION.md](BACKEND_DOCUMENTATION.md). Use it when you are ready to **host** the app and **submit** your work.

---

## 1. Where to deploy for free (or low cost)

There is no single “always free forever” stack that gives **Laravel + managed MySQL** with generous limits. Practical options:

### A. Platform + database (recommended to try first)

| Option | Notes |
|--------|--------|
| **[Railway](https://railway.app/)** | Often includes trial credits; can deploy a Laravel service and add a **MySQL** plugin. Good DX; pricing changes—check current free tier / credits. |
| **[Render](https://render.com/)** | **Web Service** (Docker or native PHP build) + database. Free **PostgreSQL** is available; MySQL may be paid—see B. |
| **[Fly.io](https://fly.io/)** | Small free allowance; you deploy with a `Dockerfile` and attach a managed DB or external MySQL. |
| **[Koyeb](https://www.koyeb.com/)** | Free tier for small apps; pair with an external DB. |

### B. App on one host, MySQL elsewhere (fits “MySQL” requirement)

| Option | Notes |
|--------|--------|
| **[PlanetScale](https://planetscale.com/)** | **MySQL-compatible** serverless DB; free tier (limits apply). Point `DATABASE_URL` / Laravel `DB_*` at PlanetScale; run migrations from CI or locally against prod. |
| **[Aiven](https://aiven.io/)** | Sometimes free trials for MySQL. |
| **Cheap VPS** (Oracle Free Tier, etc.) | Full control: install MySQL + PHP + Nginx yourself—more work. |

### C. If you switch DB for deploy only

Some hosts only offer **PostgreSQL** on the free tier. Laravel supports `pgsql`; you would set `DB_CONNECTION=pgsql` and run migrations. **Only do this if the brief allows it**—the Cytonn challenge asks for **MySQL**; safest is **MySQL or MySQL-compatible (PlanetScale)** for production.

### What evaluators usually need

- A **public HTTPS URL** where the **web UI** loads (`/`).
- Same origin or CORS-aware API (`/api/*`) working with the SPA.
- **Swagger** at `/api/documentation` (run `php artisan l5-swagger:generate` in build or post-deploy).
- A **MySQL** (or documented compatible) database they could reason about from your migrations.

---

## 2. Environment variables (production checklist)

Set at least:

| Variable | Purpose |
|----------|---------|
| `APP_ENV=production` | |
| `APP_DEBUG=false` | Never leave `true` in production |
| `APP_URL` | Public base URL (including `https://`) |
| `APP_KEY` | `php artisan key:generate` (unique per env) |
| `JWT_SECRET` | Long random string (JWT signing) |
| `DB_*` | Host, port, database, username, password |
| `LOG_CHANNEL` | e.g. `stack` or host-specific |

Build frontend assets in CI or before upload:

```bash
npm ci
npm run build
```

Commit or upload `public/build` (or build on the server). Laravel serves the Vue app from `resources/views/app.blade.php` + Vite manifest.

---

## 3. Deploy steps (typical)

1. Create production MySQL (or PlanetScale) database; note connection details.
2. Set environment variables on the host (dashboard or `.env` on server).
3. Deploy code (Git push, Docker image, or SFTP—depends on host).
4. On the server (or in release job):

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan l5-swagger:generate
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. Optional: `php artisan db:seed --force` **only** if you intend demo users in production (often **omit** in real prod).
6. Ensure the HTTP server document root points to **`public/`** (standard Laravel).
7. HTTPS: use the host’s TLS or a reverse proxy (Cloudflare, etc.).

---

## 4. Submission checklist (Cytonn-style deliverables)

Confirm against the **official PDF/email** you received. Typical items:

| Item | Done? |
|------|--------|
| Source code (Git repo link or zip) | |
| **SQL dump** of the database (structure + seed data if required) | `mysqldump` or export from phpMyAdmin |
| **README** with local setup, deploy notes, example API calls | See [README.md](../README.md) |
| **Live URL** (hosted app + working API) | |
| **Swagger** reachable at `{APP_URL}/api/documentation` | |
| Short **cover note** (Word/PDF) if requested—architecture, trade-offs, what you’d improve | |

**Email / portal:** Use the address and deadline from the challenge brief (e.g. internshipapplications@cytonn.com)—verify in your copy of the PDF.

**Zip tips:** Exclude `node_modules`, `vendor` (or include `composer.lock` and let them run `composer install`), and `.env` (share `.env.example` only).

---

## 5. Smoke test before you submit

- [ ] Register and login from the UI; JWT stored and `/api/tasks` returns 200.
- [ ] Create task → advance status → **Archive** → see it under **Archived** → **Restore** or **Delete forever** (confirm dialog).
- [ ] Report runs for a date with tasks.
- [ ] Admin user can list users (if you demo admin).
- [ ] Open Swagger, authorize, call `GET /api/tasks` and `GET /api/tasks?archived=1`.

---

*Last updated to match soft deletes, archive/restore/force endpoints, Vue task components, and JWT-only API auth.*
