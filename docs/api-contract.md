# PROPOSED canonical integration contract v1

All routes, field names, enums, limits and behaviors in this document are PROPOSED development contracts, not documented billing-system endpoints. No real API specification has been provided. The live Laravel adapter must map vendor payloads to these shapes; mock mode implements these shapes for development. All example people, addresses and identifiers below are synthetic, independent of the supplied customer's data.

## Conventions and trust boundary

Canonical response envelope is `{data, meta}`; errors use `{error}`. `meta.mode` is `mock` or `live`, `fetched_at` is the portal fetch instant, and `source_updated_at` is the upstream update instant, never substituted with fetch time. Nullable upstream update timestamps remain null if unsupported. JSON property names are English; display labels support bn/en. All shown keys are required unless explicitly described as optional; null means unavailable/not applicable, never zero.

IDs are opaque case-sensitive nonempty strings, preserved exactly (including leading zeros); adapter-defined length bounds require vendor confirmation. Reject numeric IDs when their original representation cannot be proven, rather than guessing padding. URL-encode identifiers. Namespace upstream identifiers by configured billing system/municipality; do not assume global uniqueness.

All `*_minor` monetary values are decimal digit **strings** representing paisa, BDT scale 2, not JSON floats or formatted currency. Example `"25000"` means BDT 250.00. Use exact arithmetic, bound values to signed BIGINT storage capacity, reject exponent notation/fractional paisa and negative payable values. Credits are nonnegative magnitudes in named credit fields. Meter readings/pipe size use decimal strings, never currency arithmetic. Dates are `YYYY-MM-DD`, months `YYYY-MM`, instants RFC 3339 with explicit offset; use UTC storage and Asia/Dhaka for business dates. A due date alone does not prove a cutoff instant.

Amounts, relationships and ownership are resolved/validated by Laravel against upstream data. Every browser route requires session authentication and appropriate policy; gateway callbacks instead require documented provider authentication. Billing-write and reference-lookup contracts are server-to-server only.

## Identifier dictionary

| Identifier | Issuer / meaning | Example / invariant |
| --- | --- | --- |
| `portal_user_id` | Portal identity, unrelated to official customer record | `usr_demo_01`; generated opaque ID, serialize as string |
| `external_customer_id` | Billing customer | `DEMO-C-000042`; never sufficient ownership evidence |
| `external_account_id` | Billing account/connection | `DEMO-A-000007`; distinct from customer; preserve zeros |
| `external_bill_id` | Billing record identifier | `DEMO-B-202608-007`; may differ from printable `bill_number` |
| `portal_payment_id` | Portal payment attempt/collection record | `pay_demo_01`; created before gateway initiation; retained on failure |
| `gateway_transaction_id` | Gateway collection identifier | `DEMO-GTX-01`; unique within gateway/merchant namespace, null until assigned |
| `billing_system_payment_id` | Official upstream posting identifier | `DEMO-BPAY-01`; absent/null until posting is acknowledged |
| `external_transaction_reference` | Portal-issued stable reference passed to gateway and billing | `portal-demo-pay-01`; unique per attempt, immutable across retries; never a user-entered transaction code |
| `idempotency_key` | Portal-issued deduplication key for one write operation | `billing-post-demo-01`; persisted, never regenerated during retry |
| `quote_id` / `reservation_id` | Upstream quote/reservation references if supported | Separate from bill/payment IDs; nullable reservation does not imply reserved balance |

Payment, receipt, challenge and request IDs use UUID strings; portal users and account-link rows use local numeric primary keys, serialized as strings for users. Readable example labels are placeholders. A new attempt receives a new payment ID/reference; retries of the same operation keep its reference and key. Namespace uniqueness scope and retention must be agreed with both providers.

## PROPOSED route map

| Boundary | Operation | Proposed method/path |
| --- | --- | --- |
| Browser -> portal | Current profile and verified accounts | `GET /api/v1/profile` |
| Browser -> portal | Authorized bill search | `GET /api/v1/bills` |
| Browser -> portal | Bill detail | `GET /api/v1/bills/{external_bill_id}` |
| Browser -> portal | Obtain current quote | `POST /api/v1/bills/{external_bill_id}/quote` (empty body) |
| Laravel -> billing adapter | Customer/accounts | `GET /accounts/{external_account_id}/customer` |
| Laravel -> billing adapter | History | `GET /bills?external_account_id={external_account_id}` |
| Laravel -> billing adapter | Detail | `GET /bills/{external_bill_id}` |
| Laravel -> billing adapter | Quote | `POST /bills/{external_bill_id}/payable-quotes` |
| Laravel -> billing adapter | Register verified collection | `POST /payments` |
| Laravel -> billing adapter | Stable-reference lookup | `GET /payments/by-reference/{external_transaction_reference}` |

Upstream routes above are placeholders; React never sees their configured host or calls them. Portal user ID and link proof metadata are portal additions to normalized customer data, not claims that the billing system stores portal users. Quotes may have side effects if a reservation is supported, hence proposed POST. Ownership verification endpoints cannot be finalized until the verification mechanism is agreed.

## Customer profile and billing accounts

The first implementation supports one verified account. `GET /api/v1/profile` returns:

```json
{
  "data": {
    "external_customer_id": "000042",
    "external_account_id": "000007",
    "name": "ডেমো গ্রাহক",
    "address": {"line": "ডেমো ঠিকানা", "ward": "09", "holding_number": "DEMO-H-07"},
    "phone": null,
    "customer_type": {"code": "residential", "label_bn": "আবাসিক", "label_en": "Residential"},
    "currency": "BDT",
    "outstanding_balance_minor": "52600",
    "source_updated_at": "2026-09-30T10:00:00+06:00"
  },
  "meta": {"mode": "mock", "fetched_at": "2026-09-30T10:01:00+06:00", "is_snapshot": false}
}
```

Portal identity is separately returned by `/api/v1/session` as string `id`, `name`, `role`. `/api/v1/account-links` returns portal link ID, external customer/account IDs, verification and revocation metadata. Billing details are unavailable without a verified link. Supporting multiple linked accounts would require an approved requirement, schema/policy revision and contract version. Bill detail carries remaining official connection/guardian/meter fields. Portal preferences have their own endpoint and cannot update upstream identity.

## Paginated bill search/history

Proposed query parameters: `external_account_id`, `external_customer_id`, exact `external_bill_id`, `month_from`, `month_to`, `status`, `page` (default 1), `per_page` (default 10, max 50). All accounts must be authorized; only pass allowlisted validated filter keys upstream; reject invalid ranges and unsupported statuses. No unscoped customer-ID search. Stable order: bill month descending, then external bill ID descending. Upstream changes can shift pages; vendor cursor pagination may replace page pagination by explicit contract revision. Return a nullable total when upstream cannot reliably count; do not invent counts.

```json
{
  "data": [{
    "external_bill_id": "DEMO-B-202608-007",
    "bill_number": "DEMO-INV-007",
    "external_account_id": "DEMO-A-000007",
    "external_customer_id": "DEMO-C-000042",
    "type": {"code": "residential", "label_bn": "আবাসিক", "label_en": "Residential"},
    "bill_month": "2026-08",
    "issue_date": "2026-09-03",
    "due_date": "2026-09-28",
    "currency": "BDT",
    "amount_minor": "25000",
    "paid_amount_minor": "0",
    "paid_date": null,
    "outstanding_balance_minor": "25000",
    "status": "unpaid",
    "source_updated_at": "2026-09-03T09:00:00+06:00"
  }],
  "meta": {
    "mode": "mock",
    "fetched_at": "2026-09-10T10:00:00+06:00",
    "pagination": {"page": 1, "per_page": 10, "total": 1, "has_next": false}
  }
}
```

`amount_minor` means the issued before-deadline total; it is not a checkout amount. `paid_amount_minor` is upstream-applied money for this bill, not unposted portal collections. `paid_date` is the upstream bill-paid date if provided; null does not mean unpaid. Proposed statuses: unpaid, partially_paid, paid, void, unknown. Unknown upstream status remains unknown and disables quote/payment until mapped. UI actions never replace server authorization. There is no delete/cancel-payment action.

## Bill detail

```json
{
  "data": {
    "external_bill_id": "DEMO-B-202608-007",
    "bill_number": "DEMO-INV-007",
    "bill_month": "2026-08",
    "municipality": {"id": "DEMO-MUNI", "name_bn": "ডেমো পৌরসভা", "name_en": "Demo Municipality", "website": null},
    "customer": {
      "external_customer_id": "DEMO-C-000042",
      "name": "ডেমো গ্রাহক",
      "guardian_name": "ডেমো অভিভাবক",
      "address": {"line": "ডেমো ঠিকানা", "ward": "09", "holding_number": "DEMO-H-07"},
      "mobile_number": null,
      "old_customer_reference": "DEMO-OLD-042"
    },
    "connection": {
      "external_account_id": "DEMO-A-000007",
      "meter_number": "DEMO-M-008",
      "customer_type": {"code": "residential", "label_bn": "আবাসিক", "label_en": "Residential"},
      "category": "metered",
      "pipe_size": {"value": "0.50", "unit": null},
      "service_address": {"line": "ডেমো ঠিকানা", "ward": "09", "holding_number": "DEMO-H-07"}
    },
    "meter_readings": {
      "previous_date": "2026-07-15",
      "current_date": "2026-08-15",
      "previous_reading": "100.00",
      "current_reading": "120.00",
      "old_meter_units": "0.00",
      "used_units": "20.00",
      "unit": null
    },
    "currency": "BDT",
    "breakdown": {
      "current_charges_minor": "25000",
      "arrears_minor": "0",
      "arrears_surcharge_minor": "0",
      "rebate_minor": "0",
      "advance_minor": "0",
      "late_fee_minor": "1300"
    },
    "deadlines": {
      "issue_date": "2026-09-03",
      "due_date": "2026-09-28",
      "payment_cutoff_at": null,
      "timezone": "Asia/Dhaka"
    },
    "authoritative_totals": {
      "before_deadline_minor": "25000",
      "after_deadline_minor": "26300",
      "paid_amount_minor": "0",
      "outstanding_balance_minor": "25000",
      "as_of": "2026-09-10T10:00:00+06:00"
    },
    "previous_payment": {"payment_date": "2026-08-10", "amount_minor": "22000", "billing_system_payment_id": null},
    "status": "unpaid",
    "source_updated_at": "2026-09-03T09:00:00+06:00",
    "source_version": "demo-v1"
  },
  "meta": {"mode": "mock", "fetched_at": "2026-09-10T10:00:00+06:00"}
}
```

Source timestamps/version may be null if unavailable; `as_of` denotes the upstream balance observation time, not a quote guarantee. Readings or previous payment may be null when not applicable; optional identity/contact attributes may be null. Missing essential IDs/currency/totals are upstream contract errors. Do not reconstruct authoritative totals by summing breakdown fields: rounding, adjustments and arrears inclusion require vendor definitions. Preserve unexplained differences and block unsafe checkout. Late fee displayed in the breakdown may apply only after deadline; the example is synthetic, not a fee formula. The previous-payment record is account history, not proof this bill is paid. QR, barcode and signature fields are intentionally absent until approved official data/assets are supplied.

## Current payable quote

```json
{
  "data": {
    "quote_id": "DEMO-Q-001",
    "reservation_id": null,
    "external_customer_id": "DEMO-C-000042",
    "external_account_id": "DEMO-A-000007",
    "external_bill_id": "DEMO-B-202608-007",
    "currency": "BDT",
    "outstanding_balance_minor": "25000",
    "payable_amount_minor": "25000",
    "collection_fee_minor": "0",
    "total_charge_minor": "25000",
    "payment_mode": "full_single_bill",
    "eligible": true,
    "issued_at": "2026-09-10T10:00:00+06:00",
    "expires_at": "2026-09-10T10:05:00+06:00",
        "source_version": "demo-v1",
    "source_updated_at": "2026-09-03T09:00:00+06:00"
  },
  "meta": {"mode": "mock", "fetched_at": "2026-09-10T10:00:00+06:00"}
}
```

Five-minute validity and zero fee are mock choices only. Production expiry/fee/reservation semantics are unknown. Distinguish bill-applied payable amount from total gateway charge. Initial flow permits no fee unless agreed; server refuses unsupported fees, currencies, zero balances, expired quotes or missing authority. A quote is not a reservation. Do not synthesize a production quote from cached detail or local deadline arithmetic. If upstream has no quote API, agree an equivalent authoritative read/version/reservation strategy before enabling live collection. Bind the quote to user, bill and attempt server-side; revalidate at initiation and reconcile if state changes before settlement.

## Register verified payment in billing system

PROPOSED server-only `POST /payments`, header `Idempotency-Key: billing-post-demo-01`. Persist header/body key equivalence. All body values are derived from stored server state and verified gateway evidence, not browser input.

```json
{
  "external_transaction_reference": "portal-demo-pay-01",
  "portal_payment_id": "pay_demo_01",
  "external_bill_id": "DEMO-B-202608-007",
  "external_account_id": "DEMO-A-000007",
  "external_customer_id": "DEMO-C-000042",
  "verified_amount_minor": "25000",
  "currency": "BDT",
  "verified_payment_at": "2026-09-10T10:02:00+06:00",
  "gateway_identifier": "mock_gateway",
  "gateway_transaction_id": "DEMO-GTX-01",
  "idempotency_key": "billing-post-demo-01",
  "quote_id": "DEMO-Q-001",
  "reservation_id": null
}
```

`verified_payment_at` is the provider-confirmed successful payment instant; local verification/receipt timestamps are stored separately. `verified_amount_minor` is the verified bill allocation; it equals the gateway charge in the first, no-fee flow. Never silently deduct or add gateway fees. No portal user ID is needed upstream. Gateway transaction scope includes configured merchant identity; the billing system must validate bill/account/customer relationships and atomically apply money at most once.

Expected first success: HTTP 201 after committed posting. Expected exact replay: HTTP 200, same payment ID/immutable posting evidence, `duplicate: true`; never another posting. Proposed response:

```json
{
  "data": {
    "billing_system_payment_id": "DEMO-BPAY-01",
    "external_transaction_reference": "portal-demo-pay-01",
    "portal_payment_id": "pay_demo_01",
    "external_bill_id": "DEMO-B-202608-007",
    "external_account_id": "DEMO-A-000007",
    "external_customer_id": "DEMO-C-000042",
    "verified_amount_minor": "25000",
    "currency": "BDT",
    "verified_payment_at": "2026-09-10T10:02:00+06:00",
    "gateway_identifier": "mock_gateway",
    "gateway_transaction_id": "DEMO-GTX-01",
    "idempotency_key": "billing-post-demo-01",
    "status": "posted",
    "posted_at": "2026-09-10T10:02:05+06:00",
    "source_updated_at": "2026-09-10T10:02:05+06:00"
  },
  "meta": {"duplicate": false}
}
```

Same key OR external reference with different immutable payload must return HTTP 409 `IDEMPOTENCY_CONFLICT` with no additional money applied. Concurrent equal requests resolve to one committed record. Unique reference protection must outlive HTTP retry windows; retention is a vendor gate. A 202/processing response is not proof of posting. Timeout or 5xx may occur after commit: move to reconciliation, lookup first, and never issue a new key merely to force success. Do not mutate the payload to fit a changed balance; mismatches require review.

## Payment lookup by stable reference

PROPOSED server-only `GET /payments/by-reference/portal-demo-pay-01` returns HTTP 200 with the same `data` object as registration above and `meta: {"lookup": true}`. It must include the official payment ID, immutable references, amount/currency, gateway identity/transaction, status and posting timestamp, sufficient to validate that this is the expected collection. Do not match only by amount or customer.

Expected absent response: HTTP 404 `PAYMENT_NOT_FOUND`. Proposed processing response: HTTP 202 with `data.external_transaction_reference` and `data.status: "processing"`, and optional `Retry-After`; it is never treated as posted. Vendor must document consistency delay and safe replay behavior: a temporary 404 after timeout does not prove absence. Retry only the original immutable request/key once safe deduplication is confirmed; without it keep manual review and live payments disabled until an agreed recovery procedure exists. Reference collision or mismatched lookup evidence is a conflict, not success.

## Errors and mock scenarios

```json
{
  "error": {
    "code": "QUOTE_EXPIRED",
    "message_key": "payments.quote_expired",
    "retryable": false,
    "request_id": "demo-request-01",
    "details": {}
  }
}
```

Proposed portal statuses: 401 unauthenticated; 404 missing/inaccessible object; 422 invalid filters/payload; 409 quote changed, already-paid or idempotency conflict; 429 throttled; 502 malformed upstream payload; 503 upstream unavailable/missing capability. Error details must not disclose secrets, foreign customer data or raw upstream responses. Money/ID contract errors cannot be coerced to valid empty responses. Browser can reacquire an expired quote but cannot blindly retry a collection.

Development adapter and injected test doubles cover representative cases from: verified/unverified ownership, leading-zero IDs, multiple accounts, empty/multi-page history, paid/unpaid/partially-paid/unknown bills, nullable metadata, invalid money and malformed responses, before/after deadline, stale quote, no reservation, external counter payment, duplicate registration, payload conflict, timeout after committed write, delayed lookup visibility and persistent outage. Mock behavior is simulation, never evidence of vendor support. Cases not listed in the test report are integration acceptance scenarios, not claims of automated coverage.

## Phase 2 implemented portal foundation endpoints

Historical Phase 2 foundation endpoints are below. The Phase 3–9 implementation now includes mock and proposed HTTP billing adapters; live collection remains blocked.

| Method/path | Behavior |
| --- | --- |
| `GET /sanctum/csrf-cookie` | Sanctum bootstrap; 204 and CSRF/session cookies |
| `POST /api/v1/auth/login` | `{email,password}` body; CSRF and login throttle; regenerate session; return `data:{id:string,name:string,role:"client"|"admin"}` |
| `GET /api/v1/session` | Authenticated session; same minimal user data; 401 if absent |
| `POST /api/v1/auth/logout` | CSRF and session authentication; invalidate session; return `data:null` |
| `GET /api/v1/status` | Mock local/testing readiness: `data:{status:"ready",mode:"mock",phase:9}`; 503 for missing mode, production mock or unaccepted live collection configuration |
| `GET /api/v1/admin/status` | Same readiness response after Sanctum and admin authorization; 401/403 otherwise |

Every API response has a server-generated `X-Request-ID` and `Cache-Control: private, no-store`. Foundation errors use `error:{code,message_key,request_id,retryable,details}`. **Contract refinement:** `request_id` replaces the earlier proposed `correlation_id`; future billing endpoints should use `request_id` too. Status mapping includes 401 UNAUTHENTICATED, 403 FORBIDDEN, 404 NOT_FOUND, 405 METHOD_NOT_ALLOWED, 419 CSRF_EXPIRED, 422 VALIDATION_FAILED, 429 RATE_LIMITED, 503 SERVICE_UNAVAILABLE and 500 SERVER_ERROR. Validation details contain field errors; other details are empty. No raw exception messages or traces are sent even in debug mode. Clients translate local messages and can display the request ID for support. Laravel login-throttle responses retain Retry-After. These session `id` values are portal user IDs, never billing customer IDs.


## Implemented portal endpoints (Phases 3–9)

These are actual portal routes, not official billing-system documentation. JSON envelopes use `data`; paginated routes additionally use `meta.pagination`. Mutations live in Laravel web middleware for mandatory CSRF and sessions; reads require Sanctum except public readiness. Client objects are owner-scoped; support/admin reads and finance/role writes have independent gates.

| Routes | Behavior |
| --- | --- |
| `GET /sanctum/csrf-cookie`; `POST /api/v1/auth/{register,login,logout}` | Cookie auth; rotate session on login/registration and invalidate on logout. No token issuance. |
| `POST /api/v1/auth/reset/{start,finish}` | Generic challenge; development provider only. Start email; finish challenge_id/code/password/password_confirmation. |
| `POST /api/v1/account-links/challenges`, `/verify`; `GET /api/v1/account-links`; `DELETE /api/v1/account-links/{link}` | Start external_account_id; verify challenge_id/code. Single-use five-minute code, max five attempts. Unknown account returns indistinguishable challenge. |
| `GET/PATCH /api/v1/preferences` | Portal language and notification preferences only. |
| `GET /api/v1/profile`, `/bills`, `/bills/{bill}`; `POST /bills/{bill}/quote` | Fresh scoped normalized billing reads. No bill HTML or authoritative local tariff calculation. |
| `POST /api/v1/bills/{bill}/payments` | Empty body; server bill/quote selects all IDs and amounts. Returns stable local attempt with checkout_url (200/202). |
| `GET /api/v1/payments`, `/{payment}`, `/{payment}/receipt` | Owner/staff-authorized payment evidence. Receipt snapshot immutable; current sync status/upstream receipt separately returned. |
| `POST /api/v1/gateway/notifications` | Provider verification, no session/CSRF; fake HMAC + authoritative fake lookup only in development. |
| `POST /api/v1/dev/gateway/{payment}/complete` | Owner/session/CSRF plus local fake gate; `outcome` succeeded/failed/cancelled. |
| `GET/POST /api/v1/service-requests`; `GET/PATCH /{id}`; `POST /{id}/{submit,replies,attachments}` | Local drafts, submission, replies and private uploads. No external service endpoints invented. |
| `GET /api/v1/attachments/{id}` | Authorized forced download, private/no-store, no public storage URL. |
| `GET /api/v1/admin/{summary,integration-health,bills}`; `/bills/{bill}` | Staff role, audited bill searches/views, no secrets in health. |
| `GET /api/v1/admin/data/{module}` | modules users/account-links/payments/service-requests/audit-logs; page15, q/status/sync_status/kind/role filters. |
| `PATCH /api/v1/admin/users/{user}/role`; `POST /admin/account-links/{link}/revoke` | Admin role and audit; no account reassignment endpoint. |
| `POST /api/v1/admin/payments/{id}/{retry,reconcile}`; `GET /{id}/events` | Admin financial actions; stable payload/key, paginated attempt events. |
| `POST /api/v1/admin/service-requests/{id}/review` | Controlled transition + message; support review, admin-only approval/rejection. |
| `GET /api/v1/exports/payments`, `/api/v1/admin/exports/payments` | Self/admin scope, neutralized CSV cells, max1000 rows, audited. |

### PROPOSED HTTP adapter details

Server-only verification contact: `GET /accounts/{id}/verification-contact` → `data:{external_account_id,external_customer_id,phone}`; phone is trusted upstream-held delivery destination, never accepted from browser. Health: `GET /health` → `data:{status:"ready"}`. These endpoints require vendor agreement.

Upstream `GET /bills` currently expects **bounded full canonical BillData objects** with pagination, then maps each to the summary shape above. Page/per_page and filters are forwarded; max50. This choice is a development contract; a real summary endpoint should be mapped after examples are supplied. Empty data is `[]` with valid metadata. No all-bill download occurs. A malformed row fails the page safely.

HTTP GET retries: at most three for transport/429/500/502/503/504, connection3s/response10s, short exponential delay. Auth/schema failures do not retry. POST quote/payment sends once. HTTPS certificate validation is enabled, redirects disabled, URL host must exactly match configured allowlist, all paths constructed server-side from encoded identifiers. No live requests have been tested.

Payment acknowledgement minimum accepted by sync: string official payment ID, matching stable reference, customer/account/bill IDs, verified amount, currency, gateway identifier/transaction ID, idempotency key and `status:"posted"`. Optional official receipt reference is displayed separately. Gateway evidence is already verified locally; final vendor contract should additionally confirm posting timestamps as proposed above. Duplicate mock posts return the same stored result; its in-process interface does not use HTTP201/200 metadata. The HTTP adapter accepts both201/200 and validates data. A202 processing result is never marked synced; unsupported processing payloads fail closed for review.

Receipt stores `billing_sync_status_at_issue` inside immutable evidence and returns mutable current `sync_status` separately. `verified_at` records provider-confirmed payment time; receipt `created_at` is local issuance time. No refunds/reversals or amount editing are exposed.
