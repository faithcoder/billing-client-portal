# Verification report — 2026-10-01

Scope: local Laravel + React implementation through Phases3–8, with Phase9 verification/documentation. This is **not a certification for live municipal collection**. External systems and production hosting are untested.

## Automated checks

| Check | Actual result |
| --- | --- |
| `php artisan test` | 40 tests, 170 assertions passed on SQLite in memory |
| `vendor/bin/pint --test` | Passed |
| `composer validate --no-check-publish` | Valid |
| `composer check-platform-reqs` | Passed on PHP8.5.3 |
| Laravel route cache/clear | Passed; cache cleared for continuing local development |
| `npm test` | 5 tests passed |
| `npm run build` | TypeScript compilation + Vite build + service-worker generation passed |
| `npm run format:check` | Passed |
| Canonical JSON examples | All fenced JSON examples parsed |
| Explicit local SQLite migrations/worker | Migrations applied; SyncPayment job processed successfully |

Backend coverage includes auth/CSRF/logout/role tampering, safe errors/request IDs, production mock and fake recovery rejection, login/verification limits, unknown-account challenges, single-use/expired OTP, cross-account list/detail/quote restrictions, changed customer-to-account relationship, IDs with leading zeros, pagination with upstream total20043, malformed money, restricted host, timeout retry bounds, auth failure, non-retried writes, stale/changed quotes, submitted amount tampering, active-attempt uniqueness, signed/unsigned/repeated callbacks, authoritative gateway fetch, out-of-order and late success, mismatched amount/reference, receipt ownership/immutability, upstream outage/lost response, ambiguous non-idempotent worker recovery, scoped exports/formula injection, application/complaint role permissions, unsafe/oversized files and private downloads.

Concurrency verification covers database uniqueness and locking invariants with sequential scenarios. True concurrent MySQL processes/worker stress testing has not been run. SQLite does not establish MySQL lock/deadlock behavior. No fake response proves vendor capability.

Frontend tests cover exact minor-unit money beyond JS safe integers, Dhaka calendar/date validation, runtime session guards, dictionary parity and service-worker cache exclusions. Browser tests below exercise connected flows; there is no claim of an exhaustive component or end-to-end suite.

## Browser verification

Observed in the in-app browser against the local Laravel server and explicit disposable SQLite database:

- Resident cookie login, single-account OTP link, readonly billing profile, scoped bill history and structured bill detail.
- Fake gateway session/success; immutable receipt visible while billing sync pending; queued mock write-back; status changes to verified and synced; balance reduced in mock billing ledger.
- Proposed multi-step application completed/submitted; timeline rendered; admin saw and moved it to under_review with a preserved review note.
- Admin overview distinguishes verified portal collections/upstream posting counts and explicitly excludes bank/offline totals. Payment evidence and attempt-history controls inspected.
- Bangla labels/font/wrapping, English switch, responsive navigation and large controls inspected. Browser interactions use labeled semantic controls; focus styles/skip navigation are implemented.

For each of dashboard, login, bill history, bill detail, receipt, new connection, complaints and admin overview, actual `innerWidth` and document `scrollWidth` were equal at **320,375,390,768,1440px**. Admin payment operations were additionally checked at320px. Login worked at375x400 as a reduced-height viewport exercise; this is not a physical phone keyboard test. Mobile dashboard screenshot saved as the task artifact `portal-dashboard-mobile.png`.

A4 CSS is implemented with10mm margins, separate customer/bank copy page breaks, wrapping identifiers and break-inside avoidance for critical rows/totals. Screen bill/receipt content and print action were inspected. **Native print/PDF pagination was not visually verified**: the in-app browser lacks print-to-PDF/media emulation, and computer-use access to the Codex native print dialog was denied by the tool's safety restriction. No bypass was attempted. Print long synthetic names/addresses in actual Chrome/Safari, select A4, disable browser headers/footers, and inspect both copies and receipt before production acceptance.

Offline behavior has automated service-worker exclusion checks and a generic reconnect page; full physical-device offline/install/update behavior remains untested. Reduced-height/width checks cannot certify mobile OS safe-area behavior, keyboard scrolling, screen readers or hardware touch experience. Native print, physical320/375/390px devices and accessibility acceptance remain release checklist items.

## Measured build and representative response behavior

Final production build: main JS337.62kB (106.82kB gzip), CSS23.03kB (6.20kB gzip). Largest lazy page chunk is Admin9.22kB (3.30kB gzip); bill detail6.81kB (2.33kB gzip). Two local WOFF2 Bangla weights total91,988bytes. Removed redundant WOFF copies. Generated service worker allowlists32 hashed static assets plus one generic versioned offline page.

Measured totals across all JS assets (including lazily loaded routes, not initial transfer):

```json
{
  "js_raw_bytes": 413643,
  "js_gzip_bytes": 137578,
  "woff2_bytes": 91988,
  "lazy_and_shared_chunks": 28
}
```

Unthrottled loopback HTTP samples: production-preview root200 in2.614ms, proxied readiness200 in7.729ms, Laravel liveness200 in13.695ms; separate dev readiness sample200 in7.381ms. These single local samples are not LCP, mobile network timings, concurrency/load benchmarks or production SLO evidence. Measure real latency, compression/cache headers, LCP/INP and queue delay on the accepted host/network.

Route modules are lazy-loaded; data endpoints are paginated. Service-worker install prefetches versioned public static assets only, so its background download includes lazy routes. No polling on inactive payment pages; visible payment polling is capped at20requests with5s intervals and single-flight protection. Profile/bill/receipt/auth/API data never enters the service-worker cache.

Build emits React Router `use client` module-directive warnings. Build succeeds; this SPA does not use SSR/server-component boundaries. No other build failure remains.

## Outstanding integration / deployment checks

Actual MySQL migrations, simultaneous settlement/worker tests, Nginx/Supervisor target-host validation, backup restore, public HTTPS/callback reachability, real gateway signature/merchant/status semantics, real billing mappings/idempotency/lookup/reservation behavior, SMS/email delivery, malware scanning, native printing and physical-device PWA acceptance are untested. No gateway was selected and no sandbox credentials were supplied. Production initiation, fake OTP/gateway/mock use and unconfigured uploads fail closed. See integration-checklist and deployment-rollback before live enablement. Nothing was deployed publicly.
