<?php

namespace App\Integrations\Billing;

use App\Contracts\BillingSystemClient;
use App\Exceptions\PortalException;
use App\Integrations\Billing\DTO\BillData;
use App\Integrations\Billing\DTO\CustomerData;
use App\Integrations\Billing\DTO\QuoteData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HttpBillingSystemClient implements BillingSystemClient
{
    private function send(string $method, string $path, array $payload = [], bool $safeRead = true): ?array
    {
        $url = config('billing.url');
        $host = parse_url((string) $url, PHP_URL_HOST);
        if (config('billing.mode') !== 'live' || ! config('billing.contract_approved') || ! config('billing.token') || parse_url((string) $url, PHP_URL_SCHEME) !== 'https' || ! in_array($host, config('billing.allowed_hosts', []), true) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) {
            throw new PortalException('BILLING_NOT_CONFIGURED');
        }
        for ($attempt = 0; $attempt < ($safeRead ? 3 : 1); $attempt++) {
            try {
                $http = Http::baseUrl(rtrim($url, '/'))->withToken(config('billing.token'))->acceptJson()->connectTimeout(3)->timeout(10)->withOptions(['verify' => true, 'allow_redirects' => false])->withHeaders(['X-Request-ID' => request()->attributes->get('request_id', 'background')]);
                if (isset($payload['idempotency_key'])) {
                    $http = $http->withHeaders(['Idempotency-Key' => $payload['idempotency_key']]);
                }
                $response = $http->send($method, $path, [$method === 'GET' ? 'query' : 'json' => $payload]);
            } catch (ConnectionException $e) {
                $this->log('transport', $attempt);
                if ($safeRead && $attempt < 2) {
                    usleep(100000 * (2 ** $attempt));

                    continue;
                }
                throw new PortalException($safeRead ? 'BILLING_UNAVAILABLE' : 'WRITE_RESULT_UNKNOWN');
            }
            if ($response->status() === 404) {
                return null;
            }
            if ($response->successful()) {
                $body = $response->json();
                if (! is_array($body) || ! array_key_exists('data', $body)) {
                    throw new PortalException('UPSTREAM_SCHEMA_INVALID', 502);
                }

                return $body;
            }
            $this->log('http_'.$response->status(), $attempt);
            if ($safeRead && in_array($response->status(), [429, 500, 502, 503, 504]) && $attempt < 2) {
                usleep(100000 * (2 ** $attempt));

                continue;
            }
            throw new PortalException(match (true) {
                in_array($response->status(), [401, 403]) => 'BILLING_AUTH_FAILED',$response->status() === 409 => 'BILLING_CONFLICT',! $safeRead && $response->serverError() => 'WRITE_RESULT_UNKNOWN',default => 'BILLING_UNAVAILABLE'
            });
        }
        throw new PortalException('BILLING_UNAVAILABLE');
    }

    private function log(string $reason, int $attempt): void
    {
        Log::warning('billing.request_failed', ['reason' => $reason, 'attempt' => $attempt + 1, 'request_id' => request()->attributes->get('request_id')]);
    }

    public function verificationContact(string $accountId): ?array
    {
        $body = $this->send('GET', '/accounts/'.rawurlencode($accountId).'/verification-contact');
        if (! $body) {
            return null;
        }$d = $body['data'];
        if (! is_array($d) || ! is_string($d['external_account_id'] ?? null) || $d['external_account_id'] !== $accountId || ! is_string($d['external_customer_id'] ?? null) || ! is_string($d['phone'] ?? null)) {
            throw new PortalException('UPSTREAM_SCHEMA_INVALID', 502);
        }

        return $d;
    }

    public function customer(string $accountId): CustomerData
    {
        $body = $this->send('GET', '/accounts/'.rawurlencode($accountId).'/customer');
        abort_if(! $body, 404);
        $dto = new CustomerData($body['data']);
        if ($dto->data['external_account_id'] !== $accountId) {
            throw new PortalException('UPSTREAM_RELATIONSHIP_INVALID', 502);
        }

        return $dto;
    }

    public function bills(array $filters): array
    {
        $body = $this->send('GET', '/bills', $filters);
        if (! $body || ! is_array($body['data'])) {
            throw new PortalException('UPSTREAM_SCHEMA_INVALID', 502);
        }
        $pagination = $body['meta']['pagination'] ?? [];
        if (Validator::make($pagination, ['page' => 'required|integer|min:1', 'per_page' => 'required|integer|min:1|max:50', 'total' => 'present|nullable|integer|min:0', 'has_next' => 'required|boolean'])->fails() || count($body['data']) > ($filters['per_page'] ?? 10) || ($pagination['page'] !== ($filters['page'] ?? 1))) {
            throw new PortalException('UPSTREAM_PAGINATION_INVALID', 502);
        }
        // PROPOSED vendor contract: normalized full bill objects per page, bounded at 50.
        $rows = array_map(function ($row) use ($filters) {
            $dto = new BillData($row);
            if (isset($filters['external_account_id']) && $row['connection']['external_account_id'] !== $filters['external_account_id']) {
                throw new PortalException('UPSTREAM_RELATIONSHIP_INVALID', 502);
            }

            return $dto->summary();
        }, $body['data']);

        return ['data' => $rows, 'meta' => ['pagination' => $pagination]];
    }

    public function bill(string $billId, ?string $accountId = null): BillData
    {
        $body = $this->send('GET', '/bills/'.rawurlencode($billId), $accountId ? ['external_account_id' => $accountId] : []);
        abort_if(! $body, 404);
        $dto = new BillData($body['data']);
        if ($dto->data['external_bill_id'] !== $billId || ($accountId && $dto->data['connection']['external_account_id'] !== $accountId)) {
            throw new PortalException('UPSTREAM_RELATIONSHIP_INVALID', 502);
        }

        return $dto;
    }

    public function quote(string $billId, ?string $accountId = null): QuoteData
    {
        // POST may reserve money: deliberately not retried.
        $body = $this->send('POST', '/bills/'.rawurlencode($billId).'/payable-quotes', $accountId ? ['external_account_id' => $accountId] : [], false);
        abort_if(! $body, 404);
        $dto = new QuoteData($body['data']);
        if ($dto->data['external_bill_id'] !== $billId || ($accountId && $dto->data['external_account_id'] !== $accountId)) {
            throw new PortalException('UPSTREAM_RELATIONSHIP_INVALID', 502);
        }

        return $dto;
    }

    public function registerPayment(array $payload): array
    {
        $body = $this->send('POST', '/payments', $payload, false);
        if (! $body) {
            throw new PortalException('WRITE_RESULT_UNKNOWN');
        }

        return $this->payment($body['data']);
    }

    public function lookupPayment(string $reference): ?array
    {
        $body = $this->send('GET', '/payments/by-reference/'.rawurlencode($reference));
        if (! $body) {
            return null;
        }

        return $this->payment($body['data']);
    }

    private function payment(mixed $d): array
    {
        if (! is_array($d)) {
            throw new PortalException('UPSTREAM_SCHEMA_INVALID', 502);
        }foreach (['billing_system_payment_id', 'external_transaction_reference', 'external_bill_id', 'external_account_id', 'external_customer_id', 'verified_amount_minor', 'currency', 'gateway_identifier', 'gateway_transaction_id', 'idempotency_key', 'status'] as $k) {
            if (! is_string($d[$k] ?? null)) {
                throw new PortalException('UPSTREAM_SCHEMA_INVALID', 502);
            }
        }

        return $d;
    }

    public function capabilities(): array
    {
        return ['idempotent_posting' => (bool) config('billing.idempotent_posting'), 'reference_lookup' => (bool) config('billing.reference_lookup'), 'atomic_settlement' => (bool) config('billing.atomic_settlement')];
    }

    public function health(): array
    {
        $body = $this->send('GET', '/health');
        if (! $body || ($body['data']['status'] ?? null) !== 'ready') {
            throw new PortalException('BILLING_UNAVAILABLE');
        }

        return ['status' => 'ready', 'mode' => 'live'];
    }
}
