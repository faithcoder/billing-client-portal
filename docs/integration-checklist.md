# Integration acceptance checklist

Status: local development implementation; **not approved for real collections**. Do not invent vendor answers or select live capability flags from assumptions.

| Decision / evidence required | Current behavior |
| --- | --- |
| Billing base URL, TLS, authentication, allowed host, endpoint examples and schema/version | `HttpBillingSystemClient` implements explicitly PROPOSED routes. Contract flag defaults false; no silent mock fallback. |
| Ownership verification destination and delivery provider; lost-phone alternatives | Only upstream-held phone may receive linking OTP. Development uses synthetic data and fake provider. Production issuance is closed. Any alternative requires explicit approval and an audited process. |
| Account-sharing / multiple-account requirements | One verified account per portal user, one owner per external account. Revoked mappings retained; transfers require a future audited workflow. |
| Quote, validity, cutoff/timezone, reservation/atomic settlement, changed balance semantics | Fresh BDT full-single-bill quote required, exact outstanding amount, zero collection fee. Quote/bill disagreement rejects initiation. No cross-channel reservation guarantee. |
| Idempotent posting / stable reference lookup / definitive not-found timing | Flags false until vendor proves semantics. Ambiguous non-idempotent writes stop for review. Persist potentially-sent state before network call. |
| Gateway choice, merchant account, official current API docs, sandbox keys, signature verification, status lookup | Interface and development fake only. Live initiation is explicitly blocked in code. |
| Partial, advance, multi-bill, refund/reversal, fees | Unsupported. No delete/toggle-paid operation exists. Design coordinated append-only reversals only after approval. |
| Application/complaint official fields, document requirements, review policy, external sync | Proposed fields shown as such. Records stay local behind future sync interface. |
| SMS/email recovery and delivery, retry policy, rate-limits and retention | Provider interface; fake local only. Do not use mail-log delivery for OTPs. |
| Malware scanner, file retention, storage encryption, incident response | PDF/JPEG/PNG only, 5 MB each / five per request, private authorized download. Development content check is not antivirus; production upload fails closed. |
| Municipality branding, signatures, QR/barcode payload and permission to reproduce | Generic portal icons and synthetic municipality only. No invented official authentication marks. |
| Runtime, MySQL, DNS/TLS, proxy trust, queue/scheduler, backups and restore exercise | Documented template; needs host acceptance. |

Before live: obtain sanitized examples for missing/null fields, leading-zero IDs, >20,000-row pagination, paid/partial/void bills, stale quotes, before/after cutoff totals, multi-channel collection, duplicate posts, accepted-with-lost-response and reference lookup. Replace vendor-to-canonical mappings in Laravel, retain contract tests, and run MySQL concurrent worker/attempt tests.

A staged acceptance release must include a reviewed real notification provider and gateway adapter implemented against its official current documentation, tested credentials stored server-side, approved merchant/checkout-host allowlist, real webhook verification, real scanner, accepted settlement capability semantics and observed queue operation. Only then change the production readiness and initiation gates in a reviewed code change. Merely setting environment flags cannot enable real payment processing.

Bank/offline race policy: when posting reports a changed balance, duplicate collection or mismatched references/amounts, preserve verified payment and receipt and mark `needs_review`. Operators compare gateway and upstream ledgers, contact the responsible finance team and follow the approved refund/reallocation process. No automatic second collection or guessed refund is performed. Lack of upstream reservations means the portal cannot promise prevention of these races.
