<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PropertiesTechnicalDescriptionSyncClient
{
    public function reportProgress(
        string $syncRequestId,
        string $propertyCode,
        string $status,
        ?string $message,
        ?int $total,
        bool $requireSuccess = false,
    ): ?bool {
        try {
            $response = $this->client($syncRequestId)
                ->post($this->url('/api/technical-description-sync-progress'), [
                    'property_code' => $propertyCode,
                    'status' => $status,
                    'message' => $message,
                    'total' => $total,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Properties rejected sync progress.', [
                'sync_request_id' => $syncRequestId,
                'property_code' => $propertyCode,
                'progress_status' => $status,
                'http_status' => $response->status(),
                'response' => Str::limit($response->body(), 1000),
            ]);

            if ($requireSuccess || in_array($response->status(), [401, 403, 404, 409], true)) {
                return false;
            }

            return null;
        } catch (\Throwable $exception) {
            Log::warning('Unable to report technical description sync progress.', [
                'sync_request_id' => $syncRequestId,
                'property_code' => $propertyCode,
                'status' => $status,
                'message' => $exception->getMessage(),
            ]);

            return $requireSuccess ? false : null;
        }
    }

    public function complete(string $syncRequestId, array $payload): Response
    {
        return $this->client($syncRequestId)
            ->retry([1000, 3000, 5000], throw: false)
            ->post($this->url('/api/technical-description-sync-complete'), $payload);
    }

    private function client(string $syncRequestId): PendingRequest
    {
        return Http::acceptJson()
            ->withToken(config('storefront.property_api_key'))
            ->withHeader('X-Sync-Request-ID', $syncRequestId)
            ->connectTimeout(15)
            ->timeout(60);
    }

    private function url(string $path): string
    {
        return rtrim(config('storefront.property_url'), '/').$path;
    }
}
