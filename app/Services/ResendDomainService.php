<?php

namespace App\Services;

use App\Exceptions\ResendException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ResendDomainService
{
    public function validate(string $apiKey): array
    {
        return $this->request($apiKey, 'get', '/domains', ['limit' => 1]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDomains(string $apiKey): array
    {
        $response = $this->request($apiKey, 'get', '/domains', ['limit' => 100]);

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDomain(string $apiKey, string $domain): ?array
    {
        $domain = strtolower(trim($domain));

        foreach ($this->listDomains($apiKey) as $item) {
            if (strtolower((string) ($item['name'] ?? '')) === $domain) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createDomain(
        string $apiKey,
        string $domain,
        string $region = 'sa-east-1',
    ): array {
        return $this->request($apiKey, 'post', '/domains', [
            'name' => strtolower(trim($domain)),
            'region' => $region,
            'open_tracking' => false,
            'click_tracking' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyDomain(string $apiKey, string $domainId): array
    {
        return $this->request($apiKey, 'post', '/domains/'.rawurlencode($domainId).'/verify');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDomain(string $apiKey, string $domainId): array
    {
        return $this->request($apiKey, 'get', '/domains/'.rawurlencode($domainId));
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::baseUrl((string) config('services.resend.base_url', 'https://api.resend.com'))
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $apiKey, string $method, string $path, array $payload = []): array
    {
        $response = $this->client($apiKey)->{$method}($path, $payload);

        if ($response->failed()) {
            throw $this->exceptionFor($response);
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    private function exceptionFor(Response $response): ResendException
    {
        if (in_array($response->status(), [401, 403], true)) {
            return ResendException::invalidCredentials();
        }

        if ($response->status() === 429) {
            return ResendException::rateLimited();
        }

        $message = $response->json('message');

        return ResendException::requestFailed(
            is_string($message) && trim($message) !== ''
                ? $message
                : 'O Resend recusou a operação.',
        );
    }
}
