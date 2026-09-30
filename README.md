# Municipal water client portal

Laravel API + React/TypeScript SPA with Bangla-first resident and compact staff interfaces. Implemented through Phase 8 against explicit local mocks, with Phase 9 checks and deployment guides. **Real collections remain disabled** until the billing contract, gateway, ownership delivery, hosting and operational controls are accepted.

Residents can register, sign in, verify one synthetic account, view paginated bills, print customer/bank copies, simulate a payment, access an immutable portal receipt, and submit local applications/complaints. Staff can review requests, inspect payments and audit logs; administrators can manage roles, reconcile collections and export scoped CSV.

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

## Local setup

Use MySQL 8.x with a dedicated `water_portal` database/user and utf8mb4. Actual MySQL connectivity was not tested on this host; automated tests use isolated SQLite.

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit the local environment with database credentials. For synthetic development only use `APP_ENV=local`, `BILLING_MODE=mock`, `NOTIFICATION_DRIVER=fake`, `PAYMENT_GATEWAY=fake`, `PAYMENT_MERCHANT_ID=demo-merchant`. Set `UPLOAD_SCANNER=fake` only for local upload exercises; this is a development content check, not antivirus.

```sh
php artisan migrate
php artisan portal:demo
php artisan serve --host=127.0.0.1 --port=8000
```

`portal:demo` prompts for your own password (12+ characters); creates `resident@example.invalid`, `support@example.invalid`, `admin@example.invalid`; leaves existing accounts unchanged. No preset passwords or real customer data are committed. Registration is also available. Accounts are not auto-linked. Synthetic external account `000007` belongs to customer `000042`; alternate `000008` belongs to `000043`. Local fake OTP is `123456` and is never delivered or logged. Fake providers are rejected outside local/testing.

In separate terminals:

```sh
cd frontend
npm ci
cp .env.example .env
npm run dev
```

```sh
cd backend
php artisan queue:work --tries=3 --timeout=60
```

```sh
cd backend
php artisan schedule:work
```

Open [the portal](http://127.0.0.1:5173). Stay on one hostname: Vite proxies `/api` and `/sanctum` to Laravel. No upstream URL or secret goes in frontend variables. Sanctum uses CSRF + HttpOnly session cookies, never localStorage bearer tokens.

If MySQL is unavailable, explicitly use a disposable SQLite database for a local smoke run (not a production fallback):

```sh
cd backend
touch /tmp/water-portal-local.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/water-portal-local.sqlite php artisan migrate
DB_CONNECTION=sqlite DB_DATABASE=/tmp/water-portal-local.sqlite php artisan portal:demo
DB_CONNECTION=sqlite DB_DATABASE=/tmp/water-portal-local.sqlite php artisan serve --host=127.0.0.1 --port=8000
```

Apply the same database override to the worker and scheduler. Do not run `migrate:fresh` against a database containing payment evidence.

## Try the local workflow

1. Sign in as the resident. Link `000007` using the fake OTP. Arbitrary identifiers cannot establish ownership.
2. Open Bills, filter by month/status or an exact ID, and view a bill. Use Print / Save as PDF for customer and bank copies. Print pagination still needs native browser acceptance testing; see the test report.
3. Pay an unpaid bill. The server obtains a fresh quote, stores a stable attempt and opens the explicitly labeled fake checkout. Simulate success, failure or cancellation. No real money moves.
4. On success the receipt exists immediately. The worker posts to the mock billing ledger; the UI distinguishes verified payment from billing sync. Stop the worker to observe pending sync, restart it to complete. Do not repay a verified collection because sync is pending.
5. Create a draft connection application or complaint. Submit and sign in as support/admin to review. Fields are proposed municipal fields, not approved official forms.

See [sandbox scenarios](docs/sandbox-testing.md) for callback and failure tests.

## Routes and structure

Client routes: `/`, `/login`, `/register`, `/reset-password`, `/link-account`, `/profile`, `/bills`, `/bills/:billId`, `/pay/:billId`, `/payments`, `/payments/:paymentId`, `/payments/:paymentId/receipt`, `/new-connection`, `/requests`, `/requests/:requestId`, `/more`.

Staff routes under `/admin`: overview, users, account-links, bills, payments, sync-failures, applications, complaints, audit-logs, integration-health. Client and staff share components and one build. Private routes are guarded in React and authorized independently by Laravel policies/gates.

- `backend/app/Contracts`: billing, gateway, notifications, scanner and future service-sync interfaces.
- `backend/app/Integrations`: validated canonical DTOs and HTTP/mock adapters.
- `backend/app/Services`: authentication, linking, account scope, payment initiation/finalization/sync, service workflows.
- `backend/app/Jobs`: database-queued sync, reconciliation and notification jobs.
- `backend/database`: migrations and synthetic reference-shaped fixtures.
- `frontend/src`: lazy pages, layouts, typed fetch APIs, reusable states and Bangla/English text.
- `docs`: contracts, requirements, decisions, operating/deployment guides and test evidence.

## Checks and production build

```sh
cd backend
composer validate --no-check-publish
composer check-platform-reqs
php artisan test
vendor/bin/pint --test
```

```sh
cd frontend
npm test
npm run typecheck
npm run format:check
npm run build
npm run preview
```

Production preview is [localhost:4173](http://127.0.0.1:4173). Generated service worker caches only hashed static assets and a generic bilingual offline page. It excludes API/auth responses, app navigation HTML, bills, receipts and private attachments. Offline authenticated screens show a reconnect message. No private data is stored in browser persistence. Installability needs HTTPS/localhost; no app-store download is required.

Production should use one HTTPS origin: static frontend at `/`, Laravel `/api/*` and `/sanctum/*` routed before SPA fallback. No public deployment has been performed. The readiness endpoint intentionally remains unavailable for real collection; an HTTP adapter exists only for the PROPOSED contract. Do not bypass this gate by choosing local mode on a public host.

## Documentation

- [Requirements and unknowns](docs/requirements.md), [architecture](docs/architecture.md), [canonical contracts](docs/api-contract.md), [progress](docs/progress.md).
- [Integration acceptance checklist](docs/integration-checklist.md).
- [Admin operating guide](docs/admin-guide.md).
- [Deployment, backup and rollback](docs/deployment-rollback.md).
- [Verification and measured bundle sizes](docs/test-report.md).

The chosen real gateway and sandbox credentials, notification provider, malware scanner, approved upstream API contract and actual hosting/MySQL environment remain external blockers. Refunds, reversals, partial/advance/multiple-bill payments and multiple linked accounts are not implemented. Portal collection totals exclude bank/offline collections.
