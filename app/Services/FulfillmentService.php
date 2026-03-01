<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FulfillmentService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.fulfillment.base_url');
        $this->apiKey = (string) config('services.fulfillment.api_key');
    }

    /**
     * @param array<string, mixed> $orderData
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function notifyOrder(array $orderData): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->post("{$this->baseUrl}/orders", $orderData);

        $response->throw();

        return $response->json();
    }
}





