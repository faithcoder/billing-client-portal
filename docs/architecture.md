# Architecture and implementation plan

Status: Phase 1; all implementation choices and folder names below are PROPOSED.

## Environment and version decision

The initial repository has only Git metadata; no application files, manifests, installed framework, lockfiles or applicable AGENTS.md were found. Observed local CLI versions on 2026-09-30: PHP 8.5.3, Composer 2.10.2, Node 22.23.0, npm 10.9.8. MySQL CLI is absent from PATH; server availability is unverified. React, Laravel, Vite, TypeScript, Tailwind, Router and Sanctum are not installed.

Propose Laravel 13, conditional on deployment PHP/extensions and dependency resolution. Official [Laravel release policy](https://laravel.com/framework/docs/releases), reviewed 2026-09-30, lists PHP 8.3–8.5 and security support through March 17, 2028 for Laravel 13. Before scaffolding, confirm deployment compatibility, select stable compatible frontend/Sanctum versions, record exact resolved versions and commit lockfiles. No framework-specific implementation APIs have been assumed or installed in this phase.

## Boundaries

React calls only same-origin portal routes through a typed fetch wrapper. Laravel authorizes, validates, requests upstream data using `Illuminate\Support\Facades\Http`, normalizes to canonical DTOs and returns safe fields. Runtime validation is required: TypeScript types alone do not validate data. Configuration and credentials never enter Vite variables, browser bundles, JSON responses or logs.

Thin controllers use Form Requests, policies, focused application services and API resources. `BillingClient` has explicit mock and HTTP adapters sharing DTOs/validation. Separate gateway adapter handles provider-specific verification. No business calculations live in controllers or React. Server stores UTC timestamps and evaluates business dates in Asia/Dhaka; upstream-issued totals/cutoffs remain authoritative.

| Owner | Data |
| --- | --- |
| Existing system | Customers, connections/accounts, meter readings, tariff math, bills, late fees, paid/outstanding balances |
| Portal | Users/sessions, verified account links and proof metadata, attempts, confirmations, receipts, outbox/sync attempts, applications, complaints, append-only audit events |
| Portal snapshots | Versioned, timestamped display/payment evidence; never substituted for fresh payable quotes or official billing balances |

Proposed local tables: users, sessions, billing_account_links, bill_snapshots, payment_attempts, gateway_confirmations, receipts, billing_sync_outbox, billing_sync_attempts, connection_applications, complaints, audit_logs, jobs and failed_jobs. External references use strings and explicit namespaces; do not create local authoritative customer/bill tables. Unique constraints cover stable transaction reference, idempotency key and `(gateway_identifier, gateway_transaction_id)` for verified collections. Link cardinality and shared account access need confirmation. Monetary storage uses BIGINT minor units with checked exact conversion; JSON uses digit strings to avoid JavaScript precision loss.

## Authentication and privacy

Use Sanctum cookie SPA sessions, CSRF bootstrap/protection, credentialed fetch and exact allowed origins if separate subdomains are necessary. Prefer same-origin reverse proxy. Secure/HttpOnly session cookies, session rotation on login, logout invalidation, rate-limited login/link verification and least-privilege admin policies. Authentication/recovery channel remains to be selected.

Every profile, bill, quote, receipt and history request checks an active verified account link; admin access requires a separate permission. Search is restricted to linked accounts, never global customer discovery. Return non-enumerating not-found errors for inaccessible objects. Ownership proofs must be scoped to the account and user, expiring and single-use. Portal contact changes do not grant ownership.

PWA service worker allowlists only versioned public static assets and a generic offline shell. All API, auth, print and receipt requests bypass caches; sensitive HTTP responses use `Cache-Control: private, no-store`. No personal data in localStorage/IndexedDB, navigation caches or offline shell. Clear in-memory data on logout. Self-host a subsetted Bangla font where licensing permits; use at least 44px touch targets, semantic forms, visible focus and localized error messages.

## Planned payment reliability (design only)

1. Authorize linked account and resolve bill/customer/account on the server. Get a fresh authoritative quote, validating expiry/version and supported payment rules. Freeze an evidence snapshot and server-created attempt/reference. Browser submits only bill/quote selection and an opaque retry token, never trusted monetary or customer data.
2. Initiate gateway checkout with server-resolved amount/currency/reference. Redirect return only opens a pending-status page. Verify a callback using the selected gateway's documented signature and/or server-side transaction query, checking merchant, reference, terminal success, amount, currency and transaction uniqueness.
3. Atomically persist verified confirmation, immutable payment evidence, receipt identity and a unique outbox entry. Only then acknowledge durable processing. Duplicate/out-of-order notifications do not duplicate money, receipts or posting. An abandoned checkout or user cancellation cannot overwrite a verified success; contradictions go to review.
4. A database-queue worker drains the transactional outbox. Post the same immutable body and key each time. Lookup by stable reference after a timeout/ambiguous outcome before deciding to retry. Bound retries with backoff; keep dead-letter/manual-review state and audit each attempt.
5. Mark upstream synchronization confirmed only after validated acknowledgement/lookup matches identifiers and amount. Refresh official bill data; never locally set the authoritative bill to paid merely because a gateway succeeded. Show “payment verified; billing update pending” during uncertainty. Do not invite a second payment for a bill with unresolved verified collection.

Proposed collection states: created, pending, verified, failed, expired, review_required. Sync states independently: not_ready, pending, in_flight, confirmed, retryable_failure, review_required. Missing callbacks require server reconciliation; duplicate workers require database constraints/locks. Stale quotes, counter payments, delayed captures and expiry races need vendor reservation/amount-guarantee semantics; absent such guarantees, live collection remains disabled until a documented reconciliation policy is agreed. Refunds/reversals use separate audited records, never deletion.

## Integration configuration and validation

Proposed `BILLING_MODE=mock|live`, with explicit selection required. Mock is permitted only in local/test environments and visibly labeled. Production rejects mock, missing live URL/credentials or incompatible required capabilities at startup/readiness; request failures return unavailable errors, never fixtures. A billing outage must not prevent durable storage of valid gateway confirmations; upstream write-back remains pending. Gateway configuration has a separate fail-closed collection gate.

Use bounded HTTP timeouts, TLS verification, configured host only, secret redaction and correlation IDs. Validate external IDs as nonempty strings, relationships, BDT money precision, enums, dates, timestamps, pagination and required fields. Reject malformed payable data; do not silently replace missing balances with zero. Unknown optional data may be null with explicit provenance. Do not parse or expose upstream HTML/errors directly. Retrying reads is bounded; retrying payment writes requires proven deduplication semantics. Log metadata and status, not customer payloads or secrets.

## Suggested structure

```text
/backend
  app/Http/{Controllers,Requests,Resources}/
  app/Policies/
  app/Services/{AccountLinking,Billing,Payments,Applications,Complaints}/
  app/Integrations/Billing/{Contracts,DTOs,Http,Mock}/
  app/Integrations/Gateways/{Contracts,Mock}/
  app/Jobs/
  app/Models/
  config/{billing.php,payments.php}
  database/{migrations,factories,seeders}/
  routes/{api.php,web.php}
  tests/{Unit,Feature}/
/frontend
  src/api/{client.ts,types.ts}
  src/app/{router.tsx,providers.tsx}
  src/features/{auth,dashboard,profile,bills,payments,applications,complaints,admin}/
  src/components/
  src/i18n/{bn,en}/
  src/styles/
  public/{manifest.webmanifest,icons,offline.html}
  tests/
/docs
  requirements.md
  architecture.md
  api-contract.md
  progress.md
```

Only directory placeholders and documentation are created now. Later phases populate this structure as needed.

## Phased delivery and exit checks

| Phase | Scope | Checks / exit gate |
| --- | --- | --- |
| 1 (current) | Inspect references/repository/tools; requirements, contracts, unknowns and plan | Review field coverage, parse JSON examples, synthetic-data check, documentation links and diff |
| 2 | Confirm deployment versions; scaffold Laravel/React/MySQL, session auth, language shell, explicit mock adapter/config guards | Backend/frontend build and lint, auth/CSRF tests, mock/live fail-closed tests, reproducible lockfiles |
| 3 | Verified linking, profile/dashboard, bill search/detail, HTML print and mock scenarios | Ownership/IDOR tests, leading zeros, invalid payloads, exact money, all reference fields, pagination and 320px/print checks |
| 4 | Gateway sandbox, fresh quotes, single-bill full-payment flow, verification, receipts and outbox/reconciliation | Vendor capability agreement; forged redirects/webhooks, replay, timeout, concurrency, late-cutoff and recovery tests; no production collection yet |
| 5 | Payment history/admin operations, applications/complaints, audit views and controlled sync retries | Role/policy coverage, workflow/validation tests, immutable payments and no duplicate write-back; agree local vs external workflows |
| 6 | PWA/production hardening, live vendor adapters, notifications, deployment and operational handover | Real sandbox contracts, gateway reconciliation, cache privacy, accessibility/performance, backup/restore, workers, secrets, monitoring and production readiness sign-off |

At each phase state scope first, make working changes, run relevant checks, then update all affected planning documents and summarize remaining integration dependencies. Phase 1 completion does not authorize claiming later phases implemented.

## Phase 2 implementation decisions (2026-09-30)

The repository now contains runnable foundations; the preceding structure/plan records the Phase 1 proposal. See README for exact installed versions, commands and deployment assumptions. Laravel 13.34.0 / Sanctum 4.3.3 and React 19.3.0 / Vite 8.3.1 are locked. Current transitive PHP dependencies require >=8.4.1; local PHP 8.5.3 passes platform checks. Frontend development uses Node 22.23.0. MySQL is the application default; SQLite is explicitly selected for tests only because no local MySQL server is available.

Session login/logout routes run through Laravel web middleware for mandatory CSRF/session handling; session/admin API routes use Sanctum and the admin gate. No token issuance or public registration is implemented. Local account creation is an interactive, local-only command with no seeded password. Thin controllers delegate login/logout to SessionService. An IntegrationReadiness service accepts explicit mock only in local/testing; real billing operations are absent, and even configured live readiness returns 503 until a vendor adapter exists. This replaces any expectation that Phase 2 would already implement mock bill operations; those remain Phase 3.

Global request context generates request IDs and private/no-store headers. Exception responses strip stack traces and raw messages and use the safe envelope documented in api-contract.md. MySQL sessions/cache/jobs migrations are present; the queue dispatches after commit. App storage timestamps remain UTC and business formatting uses Asia/Dhaka.

React uses route-level lazy imports, in-memory session state, a same-origin credentialed fetch wrapper with runtime response guards, and no personal browser persistence. Public placeholder pages expose only static explanatory content; the admin layout additionally checks role, and backend admin access independently enforces authorization. Future data endpoints still require verified account policies. Fonts are self-hosted. The build generates a service worker allowlist from hashed assets plus a versioned bilingual offline page; private documents and APIs bypass interception. No application HTML is cached.


## Implemented design, Phases 3–9 (2026-10-01)

The earlier phase plan is now implemented for local mocks. Domain interfaces are in `backend/app/Contracts`, HTTP/mock billing DTOs/adapters in `Integrations/Billing`, gateway providers in `Integrations/Gateways`, focused services in `Services`, authorized HTTP entry points in `Http/Controllers`, policies in `Policies`, queued work in `Jobs`. Frontend routes consume only relative Laravel URLs.

Auth: Sanctum first-party session cookies; mutations use web middleware so CSRF is enforced even without an Origin header. Sessions regenerate on sign-in, expire after120minutes, encrypt in database and invalidate on logout/reset. Passwords are hashed; registration never accepts roles. Client/support/admin gates independently protect backend routes. Login and verification throttles use IP plus identity/user limits. Account verification uses an encrypted upstream-held destination, hashed single-use code, five-minute expiry, five-attempt budget and generic unknown-account challenge. Fake delivery is explicitly local/testing only; production recovery/linking remains unavailable until a provider is reviewed.

Data ownership: verified single-account links gate profile, bill list/detail and quote requests. UI filters cannot expand ownership. Upstream bill DTOs are display data; payment attempts preserve historical snapshots. The server does not recalculate tariffs, fees or balances. Portal preferences and correction requests never overwrite upstream profile details. IDs remain strings at boundaries; money uses nonnegative digit strings (at most18digits) and BIGINT local storage; React formatting uses BigInt.

Payment sequence: retrieve linked authoritative bill and fresh quote → validate full-one-bill/BDT/zero-fee/expiry/amount agreement → lock user and claim unique active bill key → persist attempt → create gateway session outside transaction. Duplicate requests reuse the attempt. An uncertain session result remains reconcilable, not silently failed. Gateway evidence is validated by provider before shared finalizer verifies merchant/reference/currency/amount/transaction/status. Finalizer locks attempt and commits success, immutable receipt and unique outbox together, then queues write-back. Non-success notifications cannot overwrite success.

Gateway status and billing sync status are independent. Sync claims a timed lease, increments a bounded attempt counter, looks up reference where supported, and persists a potentially-sent marker before POST. A worker crash or lost response cannot cause an unmarked non-idempotent replay. Lookup is attempted after ambiguity; absent/uncertain evidence with no idempotency stops for review. Exact payload/key is retained. The scheduler scans bounded batches; the worker performs network I/O outside DB transactions. UI polls only visible pending pages (20requests maximum, 5s intervals) and never asks for repayment because sync failed.

Single municipality/provider namespace is configured for this repository. Multi-tenant provider namespaces are not implemented; do not mix independent billing systems whose identifiers collide. Database uniqueness protects active bill attempts and provider transaction references within that configured deployment.

Applications and complaints use local UUID records and append-only events, owner policies and staff transition checks. Uploads are restricted, scanned before storage, quota checked under the request lock, and served privately after authorization. The local scanner is only a development content check, not malware protection; production upload is unavailable. Future ServiceRequestSync currently reports unsupported rather than guessing endpoints. Notifications carry generic references only and production delivery is unconfigured.

Admin summaries distinguish verified and upstream-posted portal collections from gateway pending and sync pending. They do not represent all municipal collections. Search, role/link changes, finance actions and scoped CSV exports are audited. CSV formula prefixes are neutralized; exports cap1000rows. Staff list endpoints paginate server-side, as do client bills/payments/requests. Timelines are per-request bounded by operational retention policy still to be approved; pagination for very long individual discussions is a future scaling task.

PWA: only content-versioned static JS/CSS/WOFF2 and a generic offline page may be cached. API/auth/private downloads bypass service worker. App navigation HTML is never saved. Cache headers are private/no-store at Laravel. One-origin deployment avoids cross-origin auth complexity; actual host config still needs validation.
