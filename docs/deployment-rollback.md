# Deployment, backup and rollback guide

No public deployment is authorized or performed. These are proposed host templates; configuration syntax, paths and operations must be validated on the selected host. Real payment initiation/OTP delivery/uploads remain closed until their approved adapters exist. Never deploy public services under `APP_ENV=local` to bypass the gates.

## Requirements and environment

Use the exact lockfiles. Tested PHP 8.5.3; current Composer lock requires PHP >=8.4.1 plus reported extensions, including PDO MySQL. Node >=22.12 for build (tested22.23), MySQL8.x utf8mb4/InnoDB, HTTPS, private writable storage and reliable queues/scheduler. Run `composer check-platform-reqs` on the target. MySQL is configured but was unavailable in the development environment; test migrations/concurrency there before acceptance.

Use a release directory outside the web root; point Nginx at `frontend/dist`, and only the explicit FastCGI handler at `backend/public/index.php`. [Nginx example](deployment/nginx.conf.example) routes `/api/*` (including authentication, callback, export, attachment paths) and `/sanctum/*` before the SPA fallback. `/up` is Laravel liveness. `/assets/*` misses return404. Never expose `.env`, source, private storage, logs, or vendor. Do not use Vite dev/preview in production. Validate the actual manifest name and server socket on the host.

Environment, supplied through a private server secret store/file:

| Setting | Production requirement |
| --- | --- |
| APP_ENV / APP_DEBUG / APP_URL | production / false / exact HTTPS origin |
| APP_KEY | Generated once, backed up encrypted; never commit or rotate casually |
| DB_CONNECTION, HOST, PORT, DATABASE, USERNAME, PASSWORD | mysql; dedicated least-privilege runtime user; migration privileges separate where possible |
| SESSION_DRIVER, ENCRYPT, SECURE_COOKIE, SAME_SITE, DOMAIN | database, true, true, lax, null for host-only cookie |
| SANCTUM_STATEFUL_DOMAINS | Exact host (and port if applicable); no scheme/wildcard |
| QUEUE_CONNECTION / CACHE_STORE | database / database |
| BILLING_MODE | live only after reviewed vendor contract; missing mode never a mock fallback |
| BILLING_API_URL / TOKEN / ALLOWED_HOSTS | HTTPS URL, server-only secret, exact restricted upstream host |
| BILLING_CONTRACT_APPROVED / capability flags | false until signed-off integration evidence; flags do not enable a gateway |
| PAYMENT_GATEWAY / MERCHANT_ID | Approved real provider binding not yet supplied; never fake |
| NOTIFICATION_DRIVER / UPLOAD_SCANNER | Approved production implementations not yet supplied; never fake |
| LOG_CHANNEL / LOG_LEVEL | daily / warning or approved operational level; private log files |

Prefer one origin; leave CORS closed. If a separate SPA origin is unavoidable, review exact credentialed CORS and Sanctum configuration; never use wildcard credentials. Trust only the known reverse proxy when applicable, enforce HTTPS at the edge and verify Secure/HttpOnly/SameSite cookies and CSRF419 behavior through the deployed proxy. Set PHP upload limits to permit 5MB plus multipart overhead; backend still validates each file. Keep storage outside the web root; do not create a public storage link for request documents.

## Release steps after acceptance

1. Build an immutable release from a reviewed commit: `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`. Run tests in CI with development dependencies before release.
2. Attach private environment and shared persistent storage (`storage/app/private`, logs, sessions/database). Set only storage and bootstrap/cache writable by PHP. Do not copy demo databases or fixtures into production data tables.
3. Back up database and private storage consistently, including APP_KEY in separate encrypted key backup. Test restore in an isolated environment.
4. Run `php artisan migrate --force` using the maintenance procedure agreed for schema changes. Do not run destructive down migrations to roll back payment data.
5. Run `php artisan config:cache` and `php artisan route:cache`; point the current symlink at the release; reload FPM to clear stale opcode cache. Keep old hashed static assets available through the asset retention window so open tabs continue to load their lazy chunks.
6. Run `php artisan queue:restart`; supervised workers restart with new code/config. Confirm queue/scheduler and health as below. Keep maintenance on until acceptance checks pass if a change is incompatible.

## Worker and scheduler

Use [Supervisor example](deployment/supervisor.conf.example). Worker timeout60 is below database retry_after90. Sync job timeout45, lease60 and pre-call potentially-sent marker protect crash recovery; writes never blindly retry after an uncertain non-idempotent outcome. Sync application budget is five attempts; job worker retries do not bypass it.

Run scheduler every minute as the application user (replace paths):

```cron
* * * * * cd /srv/water/current/backend && /usr/bin/php artisan schedule:run >> /var/log/water/scheduler.log 2>&1
```

If the host cannot supervise workers, a scheduler/cron-driven alternative is a non-overlapping `queue:work database --stop-when-empty --max-time=50 --timeout=45 --tries=3` each minute, protected by a host lock such as flock. The supervisor and cron worker alternatives must not accidentally multiply workers without acceptance testing. Expect at least minute-scale delay plus backlog/provider retries; do not claim real-time billing sync. If the host cannot reliably execute jobs at all, real collections must stay disabled.

## Monitoring and maintenance

Check `/up` for process boot, `/api/v1/status` for collection readiness (live intentionally503 today), and authenticated `/api/v1/admin/integration-health` for upstream connectivity/capabilities. Separately monitor database, oldest jobs/outbox age, failed jobs, pending gateway age, leases, receipt count versus verified payments, TLS expiry and private disk capacity. Alert on `needs_review`, sustained pending sync, queue/scheduler stoppage and backup failure. Readiness is not gateway settlement confirmation.

Notification URL is `https://<origin>/api/v1/gateway/notifications`; the real gateway must validate signatures/status and merchant per its official docs. No CSRF applies to this provider endpoint; all resident mutations use session+CSRF. Return/failure/cancel navigation should point to the authenticated payment status route; none may mutate payment success.

Rotate private logs (Laravel daily with a confirmed retention duration, plus host logrotate for Nginx/PHP/scheduler). Avoid request bodies, OTPs, credentials, raw upstream payloads and sensitive query strings in access logs/APM. Restrict audit/financial logs and back them up. Keep immutable financial evidence; retention/anonymization rules require municipal approval. Notification messages include only generic status/reference, not full bills.

## Backup, restore and rollback

Take encrypted database backups and private file backups on an agreed schedule; use MySQL consistent snapshot/binlog strategy for accepted RPO/RTO. Preserve APP_KEY to decrypt challenges/sessions, plus provider credentials separately. Test restoration of users, links, receipts, outbox, events and attachment downloads. Disable callback/collection writes and workers while restoring; reconcile gateway/upstream transactions across the recovery boundary before reopening collections. Never restore stale payment data into a live accepting system.

For code rollback, pause new initiation, drain or pause workers safely, capture pending references/outbox state, switch to the previous compatible release, restore its config cache and restart workers/FPM. Keep the database and payment evidence; favor forward schema fixes. If the old code cannot read the migrated schema, keep collections unavailable and perform a reviewed forward repair. Existing verified receipts must remain accessible where safe. If asset versions changed, retain previous static assets for open clients; generic service-worker cache rotation cannot substitute for server asset retention.

Sandbox-to-live is a separate signed-off release: complete integration checklist, use production merchant/credentials and exact callback URLs, verify provider IP/signature requirements, run approved low-value acceptance with finance, monitor settlement/reconciliation, then explicitly authorize public deployment. None of this has been executed here.
