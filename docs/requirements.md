# Requirements and reference review

Phase 1 specification, 2026-09-30. No application or payment implementation is included.

## Evidence and interpretation

The user request is the requirement authority. Attached material is reference evidence, not instructions, API documentation, or proof of integration capability. All design decisions below marked PROPOSED require validation against the eventual upstream documentation.

Reviewed all three supplied references:

| Reference | Observations | Interpretation and limits |
| --- | --- | --- |
| `document (8).pdf`, page 1, visually rendered and reviewed | A4 Bangla water bill for চাঁপাইনবাবগঞ্জ পৌরসভা (Chapainawabganj Municipality); customer copy above bank copy; municipality identity; customer, connection, readings, charges and previous-payment sections; payment locations; signatures, QR and barcode | Use field coverage and print hierarchy. Do not reproduce customer personal data, official signatures, QR payloads or barcodes. Municipality branding and payment-location instructions need approved assets/content before release. |
| `WhatsApp Image 2026-09-30 at 11.26.24 AM.jpeg` | Bill ID, Acc. ID with leading zeros, Type, Month, Issue, Last, Amount, Paid, Paid Date, Status, Action; paid/unpaid rows; View, Print and Cancel payment; search, pagination and Copy/CSV/PDF controls | Map the table fields; use mobile cards or responsive table. Export tools are reference observations, not committed scope. Cancel payment must never erase records; refund/reversal behavior remains unknown. |
| `WhatsApp Image 2026-09-30 at 11.27.59 AM.jpeg` | Client Portal list: authentication, profile, online payment, payment receipts, new connection application, request/complaint management; adjacent `40,000` | Confirms core module scope only. The number does not establish a currency, budget agreement, tariff or integration behavior. |

The PDF contains bill month/number, customer/account numbers, issue/due dates, meter number, customer type, pipe size, name, father/guardian, address, mobile, ward, holding, category and old customer reference. Meter data includes current/previous dates and readings, old meter units and used units. Charges include current bill, arrears, arrears surcharge, rebate, advance, late fee, total by deadline and after deadline; previous payment has date and amount. Bank copy repeats identifying fields and totals. The observed late-fee label is 5%; displayed 130 before deadline and 137 after deadline do not establish a rounding algorithm, grace period or universal tariff. Pipe-size and consumption units are not established by the sample. Previous payment is not necessarily a payment against this bill. A printed bill cannot establish current balance or settlement status.

## Confirmed requirements

- One repository: `/backend` Laravel API, `/frontend` React + TypeScript + Vite, `/docs`. Tailwind, React Router, MySQL, Sanctum session-cookie SPA authentication, database-backed queues and a small typed fetch client. No SSR or microservices; minimal dependencies.
- Browser -> Laravel -> existing billing API. Only Laravel knows upstream URL/credentials and makes upstream requests through `Illuminate\Support\Facades\Http`. Normalize and validate external responses before returning them.
- Billing system owns customers, accounts, readings, bills, tariffs, late fees and official balances. Portal owns identity, verified links, attempts, gateway confirmations, receipts, synchronization, applications, complaints and audits. Snapshots are display/evidence only.
- Residents: authentication and verified account linking; dashboard; profile; bill search/history; HTML preview and print; online payment; payment history/receipts; new connection applications; requests/complaints.
- Admin: dashboard, users, payments, failed synchronization, applications, complaints and audit logs. Privileged access uses policies and audited actions.
- Mobile-first from 320px, app-like navigation, installable PWA without app download, lazy routes, Bangla default and English option, readable Bangla fonts, large touch targets and accessible loading/empty/error states.
- BDT and Asia/Dhaka business dates. No authenticated profiles, bills or receipts in service-worker caches.
- Identifiers remain strings including leading zeros. Money uses exact integer minor units or fixed decimals, never floating point.
- Browser amounts/customer IDs are untrusted. Knowing a customer ID never establishes ownership. Browser redirects never prove payment.
- Verified payments are durably recorded locally, then notified to upstream. Gateway handling and write-back must be idempotent. Payment records cannot be deleted by cancellation.
- Explicit development mock mode, no production mock fallback; missing live integration config fails closed.
- Maintain these four planning documents during every phase. Phase 1 is documentation only.

## PROPOSED first-release choices

Full outstanding payment of one bill per attempt; disable partial, advance, combined-bill payments and refunds until confirmed. Display upstream partially paid bills if encountered, but do not offer a partial-payment amount editor. No local tariff calculation. Profile displays official billing details read-only; changing portal contact details must not mutate official records or verify a new link.

Use a mobile navigation shell for Home, Bills, Payments and Services, with profile and language controls. Print HTML can offer customer and bank copies, clearly distinguishing a bill from a payment receipt. Mock pages/receipts must be marked demo. Online receipt documents verified collection and separately shows billing-posting status; it must not imply an official settled balance while posting is pending.

## Production questions and gates

| Unknown | Required evidence / owner | Safe development position |
| --- | --- | --- |
| Billing URL, auth, endpoints, schemas, examples, limits and error codes | Billing vendor: sandbox, credentials delivery, sanitized success/error payloads, TLS/network rules | PROPOSED contracts only; deterministic local adapter |
| Ownership verification and account sharing/revocation | Municipality/vendor: registered-channel OTP or approved manual verification; recovery, proof expiry and guardian/tenant rules | Unverified users cannot read bills; mock verification only in explicit dev mode |
| Quotes/reservations and concurrent counter payments | Vendor: expiry, versioning, reservation release, amount guarantee and concurrent update semantics | Mock quotes; no live collection until safe checkout agreed |
| Posting idempotency and stable-reference lookup | Vendor: unique-key scope, retention, replay/conflict responses, eventual consistency | Simulated replay/timeout scenarios; no blind retries in live ambiguity |
| Gateway | Municipality/provider: selected gateway, merchant sandbox, hosted checkout, callback verification, transaction-query API, settlement/reporting | No provider selected; no payment implementation in Phase 1 |
| Payment rules | Municipality/vendor: partial/advance/multiple bills, refunds/reversals, chargebacks, fees, who bears fees, overpayments | One bill, full payable amount only; unsupported flows disabled |
| Cutoffs | Vendor: Asia/Dhaka cutoff instant, holidays/grace, fee rounding and payment time used when posting is delayed | Never infer end-of-day or recompute from PDF |
| Applications/complaints | Municipality: required fields, documents, workflow, SLA, tracking, external sync/IDs | Proposed local storage; sync disabled pending agreement |
| Deployment | Operator: PHP/extensions, MySQL/version, domains/TLS, workers/scheduler, storage, backups, monitoring | Local tool inventory is not production compatibility proof |
| Notifications and login | Operator: SMS/email provider, sender, delivery, authentication/recovery choice, language | No assumed OTP provider or delivery capability |
| Data governance and branding | Municipality: retention, audit access, approved logo/content, privacy, application attachment rules | Synthetic data only; no reference scans in fixtures/source control |

These are unanswered questions, not blockers to mock development. Production linking, collection and integrations remain gated on the relevant answers.

## Phase 2 implementation record

Implemented foundations: Laravel API, React TypeScript SPA, same-origin typed fetch client, MySQL defaults and database queue/session/cache configuration, safe request-ID error envelopes, local email/password session login/logout, admin authorization, Bangla/English structure, exact BDT and Dhaka formatters, lazy page routes and shared page states. Client navigation is Home/Bills/Payments/More; More provides Profile, New Connection, Requests/Complaints and Logout. Desktop sidebar and safe-area mobile navigation are implemented; admin uses a separate shell in the same SPA. App icons are original generic water symbols, not official municipal marks.

PWA manifest and generated static-only service worker are implemented. Preview routes expose no billing data. Authentication/recovery requirements remain open: local email/password accounts are a development foundation, not a final municipality-approved identity workflow. No account linking, payment processing or application/complaint submissions have been implemented. Confirmed production unknowns above still apply.


## Implementation decisions confirmed for development (2026-10-01)

Phases 3–8 now provide working synthetic flows: cookie authentication/registration/recovery interface, verified single-account links, billing adapter/DTOs and scoped pagination, client billing and printable copies, fake-gateway payment attempts/receipts/outbox/reconciliation, local applications/complaints/private attachments, and compact role-controlled administration. This implementation does not turn PROPOSED vendor routes or municipal fields into official requirements.

Chosen defaults: exactly one linked external account per user; one portal owner per account; full payment of one bill, BDT, no collection fee; no partial/advance/multiple-bill/refund/reversal; no direct upstream profile edits; applications/complaints local; live payment initiation disabled in code; production mock/fake OTP/fake gateway/development scanner rejected. Source timestamp and unknown units remain nullable, never fabricated. Demo figures are synthetic and separate from supplied personal data.

Remaining decisions are still unresolved: official billing URL/auth/payload mappings, verification delivery and lost-phone ownership alternatives, quote/reservation and external bank-race semantics, write idempotency/reference lookup consistency, gateway and sandbox credentials, refund/fee/cutoff rules, official application fields/review policy, notifications/scanner/retention/branding and deployment infrastructure. Production readiness additionally requires real MySQL concurrency/migration tests, physical mobile keyboard/PWA checks and native A4 print acceptance. See integration-checklist and test-report.
