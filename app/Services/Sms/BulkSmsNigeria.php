<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends SMS through BulkSMS Nigeria (REST API v2, https://www.bulksmsnigeria.com/api).
 * Numbers are given as +234XXXXXXXXXX (how users.phone is stored) and sent as 234XXXXXXXXXX.
 */
class BulkSmsNigeria
{
    public function isConfigured(): bool
    {
        return filled(config('services.bulksmsnigeria.token'));
    }

    /**
     * Send one message. Throws when the provider does not accept it, so nothing is marked as sent.
     *
     * @return array{message_id: ?string, cost: ?float}
     */
    public function send(string $phone, string $message): array
    {
        $response = $this->request()->post('/sms', [
            'from' => (string) config('services.bulksmsnigeria.sender'),
            'to' => ltrim($phone, '+'),
            'body' => $message,
            'gateway' => (string) config('services.bulksmsnigeria.gateway'),
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            $error = $response->json('error.message') ?? $response->json('message') ?? $response->body();
            $code = $response->json('code') ?? $response->json('error.code') ?? 'HTTP '.$response->status();

            throw new RuntimeException("BulkSMS Nigeria refused the message ({$code}): ".mb_strimwidth((string) $error, 0, 200, '…'));
        }

        return [
            'message_id' => $response->json('data.message_id') ?? $response->json('data.id'),
            'cost' => is_numeric($response->json('data.cost')) ? (float) $response->json('data.cost') : null,
        ];
    }

    /** Total wallet balance in naira, or null when the provider does not say. */
    public function balance(): ?float
    {
        $response = $this->request()->get('/balance');
        $total = $response->json('balance.total_balance');

        return $response->successful() && is_numeric($total) ? (float) $total : null;
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Set BULKSMSNIGERIA_API_TOKEN in .env first.');
        }

        return Http::baseUrl(rtrim((string) config('services.bulksmsnigeria.url'), '/'))
            ->withToken((string) config('services.bulksmsnigeria.token'))
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 2000, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
