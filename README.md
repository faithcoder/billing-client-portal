# Municipal water client portal

Phase 2: runnable Laravel API and React SPA foundations. Bangla is the default language; English is available from the header. Billing, ownership verification, payments, receipts, applications and complaints are labeled placeholder routes. No official customer data, gateway integration or billing-system writes are included.

## Exact versions

Resolved on 2026-09-30; commit both lockfiles and use `composer install` / `npm ci` rather than updating during deployment.

| Component | Version |
| --- | --- |
| PHP used for development | 8.5.3 |
| Composer used | 2.10.2 |
| Laravel framework | 13.34.0 |
| Laravel skeleton | 13.10.1 |
| Laravel Sanctum | 4.3.3 |
| PHPUnit | 12.5.37 |
| Laravel Pint | 1.32.1 |
| Node.js used | 22.23.0 |
| npm used | 10.9.8 |
| React / React DOM | 19.3.0 |
| React Router DOM | 7.18.4 |
| TypeScript | 7.0.2 |
| Vite | 8.3.1 |
| Tailwind CSS / Vite plugin | 4.3.3 |
| Noto Sans Bengali (Fontsource) | 5.3.0 |
| tsx | 4.23.15 |
| Prettier | 3.9.9 |
| React / React DOM types | 19.3.0 |
| Node types | 22.20.4 |

Other exact development/transitive versions are recorded in the lockfiles. The current Composer lock requires **PHP >=8.4.1** through Symfony, even though Laravel itself supports PHP 8.3. Use PHP 8.5 for this tested setup and run `composer check-platform-reqs` on the deployment host. Node 22.12+ is required; Node 22.23.0 was tested. MySQL 8.x is the intended database; the host's actual version remains to be confirmed. PHP needs PDO MySQL plus Composer-reported extensions; tests also require PDO SQLite. [Laravel support policy](https://laravel.com/framework/docs/releases) and [Vite prerequisites](https://vite.dev/guide/).

## Repository

- `backend/`: API-only Laravel application; Form Requests, controllers, session service, admin gate, request context, error renderer and explicit integration readiness guard.
- `frontend/`: React + TypeScript, React Router lazy routes, Tailwind/CSS, local fonts, shared formatting, API client, PWA assets and tests.
- `docs/`: requirements, architecture, proposed integration contracts and progress.

## Run locally with MySQL

Create a dedicated MySQL database named `water_portal` with `utf8mb4`, and a database user authorized for that database. Enter its credentials locally; none are committed. Run from the repository root:

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `backend/.env`: set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Keep `APP_ENV=local` and explicitly set `BILLING_MODE=mock`. Then:

```sh
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```sh
cd frontend
npm ci
cp .env.example .env
npm run dev
```

Open [the local portal](http://127.0.0.1:5173). Use the same hostname consistently. Vite proxies `/api` and `/sanctum` to Laravel, so browser requests stay same-origin. `PORTAL_DEV_API_TARGET` is a Vite server-only local proxy setting; no billing URL or credential belongs in frontend environment variables.

Run the database queue worker in another terminal:

```sh
cd backend
php artisan queue:work --tries=3 --timeout=60
```

The default database migrations include sessions, cache, jobs, failed jobs and batches. The queue's retry window is 90 seconds; worker timeout is shorter. Dispatch after commit is enabled. No payment jobs exist yet.

### Local login and admin access

Create your own local test account with an interactive password prompt; there are no preset credentials or automatic seeded users:

```sh
cd backend
php artisan portal:demo-user resident@example.invalid
php artisan portal:demo-user staff@example.invalid --admin
```

This command runs only in `APP_ENV=local`, refuses an existing email and does not link a billing account. Visit `/login`. Session-cookie authentication uses Sanctum, CSRF bootstrap, regenerated session IDs and server-side logout. The admin UI at `/admin` requires an admin role; its backend endpoint enforces the same authorization independently. Signup, recovery, notification delivery and ownership verification are later work.

### Optional explicit SQLite smoke run

MySQL remains the configured default. If MySQL is unavailable, local-only smoke testing can explicitly override the database; this is not a production fallback:

```sh
cd backend
touch /tmp/water-portal-local.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/water-portal-local.sqlite php artisan migrate
DB_CONNECTION=sqlite DB_DATABASE=/tmp/water-portal-local.sqlite php artisan serve --host=127.0.0.1 --port=8000
```

Use the same override on `portal:demo-user` and `queue:work` if using this database. Automated tests use isolated SQLite in memory and do not use the configured MySQL database. MySQL migrations/connectivity have not been verified on this machine because no server was available.

## Frontend routes

Client: `/`, `/bills`, `/bills/:billId`, `/payments`, `/payments/:paymentId/receipt`, `/more`, `/profile`, `/new-connection`, `/requests`, `/login`.

Admin: `/admin`, `/admin/users`, `/admin/payments`, `/admin/sync-failures`, `/admin/applications`, `/admin/complaints`, `/admin/audit-logs`.

Desktop has a sidebar; mobile has Home/Bills/Payments/More bottom navigation with safe-area padding. More contains Profile, New Connection, Requests/Complaints and Logout. Admin mobile navigation wraps without horizontal scrolling. Placeholder pages are public static previews; no private data is served by them. Adding private data requires verified-account policies in Phase 3.

## Build, PWA and deployment shape

```sh
cd frontend
npm run build
npm run preview
```

Open [production-build preview](http://127.0.0.1:4173). PWA registration happens only in a production build, on HTTPS or localhost. Manifest and original generic water icons are included; icons are not official municipal seals. Use the browser's install action when available; platform install support varies.

`build-sw.mjs` generates a service worker with an exact allowlist of hashed JS/CSS/font assets and a content-versioned generic offline page. It never caches app HTML, API/auth responses, profiles, bills, receipts, print routes or private documents. Navigation is network-first without caching; on network failure it serves only the bilingual generic reconnect page. Private-document requests bypass the service worker entirely. Already-open app pages replace their content with a reconnect message on the browser offline event. Tests exercise the cache exclusions. Old owned static caches are removed on activation. No personal data is stored in localStorage or IndexedDB.

For later production deployment, serve `frontend/dist` at the site root with SPA fallback for UI routes; forward `/api/*` and `/sanctum/*` to Laravel's `public/index.php` **before** SPA fallback. Serve `/assets/*` as immutable versioned static assets; serve `sw.js`, manifest, index and offline HTML with revalidation/no-cache. Do not rewrite missing asset requests to index.html. The PHP document root must be `backend/public`; never expose `.env`, source, logs or storage. Use HTTPS, `APP_DEBUG=false`, secure cookies, the exact production Sanctum domain, supervised queue workers, scheduler and backups. The Vite dev/preview server is not a production server.

`GET /api/v1/status` is the integration-readiness check. Explicit mock is accepted only in local/testing. Production mock, missing mode and live mode all return a safe 503: a real adapter has not been implemented. No silent fallback exists, and this phase is not ready for live municipal deployment.

## Verification

```sh
cd backend
composer validate --no-check-publish
composer check-platform-reqs
php artisan test
vendor/bin/pint --test
```

```sh
cd frontend
npm run typecheck
npm test
npm run format:check
npm run build
```

Frontend tests cover exact paisa formatting beyond JavaScript's safe integer limit, Dhaka dates, payload guards, translation parity and service-worker privacy. Backend tests cover request IDs/safe errors, mock/live gates, authentication/logout, admin access, CSRF rejection and login throttling. `npm run format` and `vendor/bin/pint` format source.

Known build note: React Router emits a harmless `use client` directive warning during Vite bundling; this app has no server-component/SSR boundary. Production deployment, actual MySQL, mobile OS installation and real billing/gateway integration need their own verification.
