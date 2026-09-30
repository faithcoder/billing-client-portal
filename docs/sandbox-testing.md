# Local simulated payment and sandbox acceptance

The current fake gateway moves no money. It is usable only with local/testing + explicit fake gateway and mock billing. No real gateway sandbox has been configured or tested.

Follow README setup. Sign in, verify synthetic account `000007` with local OTP `123456`, open unpaid bill `000007-2026-08`, and continue to the fake checkout. Simulate success to persist the verified payment, immutable receipt and outbox atomically. Run the worker and confirm billing sync and mock balance reduction. Revisit the same bill/attempt: the existing verified attempt is reused, not a new charge.

To see pending sync, stop only your development worker before fake success. View the receipt while status says billing update pending, then resume the worker. Failure/cancellation simulation changes gateway status but never deletes records. A late success uses the same finalizer and is tested automatically. Do not use browser redirects or developer-edited amounts as evidence of success.

The synthetic callback endpoint is `POST /api/v1/gateway/notifications`. Its fake-only verifier requires `X-Fake-Signature`, HMAC-SHA256 over the exact raw JSON body using the local APP_KEY string, then fetches the stored fake transaction for authoritative evidence. Do not reuse this fake signature design for a real gateway. The fake checkout invokes the provider simulator through an owner-authorized CSRF-protected local endpoint; it never purports to be a real callback.

```sh
cd backend
php artisan test --filter=PaymentsTest
php artisan test --filter=BillingIntegrationTest
```

Automated cases cover duplicate and out-of-order finalization, incorrect amount, unsigned callback, late success after cancellation plus a new attempt, stale/changed quotes, active-attempt uniqueness, successful payment during upstream outage, lost write response after commit, reference lookup recovery and non-idempotent crash recovery. Tests assert one receipt/outbox and preservation of payment evidence.

Real sandbox acceptance after gateway selection must include documented signatures, wrong merchant/reference/currency/amount, notification replay, gateway lookup outage, session timeout with later success, provider-specific checkout redirect allowlist, callback public reachability, concurrent MySQL workers, bank/offline balance changes, reconciliation, queue restarts, scanner integration and rollback. Record gateway test transaction IDs privately; never commit sandbox secrets. Live enablement requires separate authorization and checklist completion.
