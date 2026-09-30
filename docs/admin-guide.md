# Administrator operating guide

Sign in through the same cookie-authenticated portal and open `/admin`. `support` may view operational records, search upstream bills and manage request progress; `admin` may additionally change roles, revoke links, export finance data, reconcile and retry sync, and approve/reject applications. The final administrator cannot demote themselves out of the last admin role. Do not use development accounts in production.

## Collections and reconciliation

The overview distinguishes gateway-verified portal collections, upstream-posted portal collections, gateway pending attempts, and verified payments awaiting sync. These are **portal-only** figures. `upstream_billing_totals` is unavailable until an authoritative aggregate API is agreed; bank/offline collections are not included.

1. Inspect payment reference, immutable receipt, gateway status, sync status and reason.
2. If gateway is pending, use Reconcile to query the provider. Redirects are not payment proof. Success received after failure/cancellation is handled by the same finalizer.
3. If verified but sync pending/failed, retain receipt and tell the resident payment is verified. Never instruct repayment solely because sync failed.
4. Reconcile on a successful payment performs reference lookup only. A matching upstream posting marks it synced; a missing result does not itself authorize a new non-idempotent write.
5. Retry is available only for successful payments with failed sync and remaining retry budget. It queues the original immutable payload/key; it does not edit amounts or reset attempt history. Automatic backoff: 30, 120, 600, 1800 seconds, maximum five claimed attempts.
6. `needs_review` requires manual investigation. Possible reasons include amount/reference mismatch, changed balance, possible duplicate collection, ambiguous non-idempotent post, or exhausted retries. No force-post, erase, paid/unpaid toggle or refund action is provided.

Inspect attempt history and audit logs before intervening. Run queue/scheduler continuously. Investigate stalled leases, queue lag and failed jobs; do not reset attempt counters to circumvent safeguards. An uncertain gateway result older than a day is escalated for review when no transaction can be found.

## Clients, services and exports

Account links require resident ownership verification. Admin revocation preserves history and blocks future bill access; it does not destroy existing verified receipts. Reassignment or account sharing is not implemented. Role changes, link changes, upstream searches, exports and finance actions are audited. Search audit stores filter names, avoiding unnecessary personal search values.

Applications: draft → submitted → under_review → approved/rejected, with more_information_required returning to submission after resident updates. Proposed applicant/contact/address/connection fields require municipal approval. Complaints: submitted → under_review/in_progress → resolved → closed; additional information may be requested. Replies and status events are retained. Attachments stay private and require authorization on every download.

Payment CSV is capped at 1,000 filtered rows per download and contains portal payment records only. Client export is scoped to that client's data; finance-wide export requires admin. Cells starting with spreadsheet formula/control prefixes are neutralized. Leading-zero IDs remain textual CSV values; import columns as text in spreadsheet software. Narrow filters when the cap is reached. Exports and local copies must follow the municipality's retention policy.

Integration Health reports connectivity/capabilities without credentials. Public `/api/v1/status` is the collection-readiness gate; `/up` is process liveness. A functioning web server does not establish settlement readiness.
