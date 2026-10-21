<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MfilesApiLogHelper
{
    /**
     * Log an M-Files API request.
     *
     * @param string      $apiCalled
     * @param string      $httpMethod
     * @param array       $requestBody
     * @param array       $response
     * @param int|null    $statusCode
     * @param int         $durationMs
     * @return void
     */
    public static function log(
        string $apiCalled,
        string $httpMethod,
        array $requestBody = [],
        array $response = [],
        ?int $statusCode = null,
        int $durationMs = 0
    ): void {
        try {
            $client = new Client();

            $client->post(
                config('mfiles.match_api_log.url').'/api/mfiles-api-logs',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . config('mfiles.match_api_log.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'api_called' => $apiCalled,
                        'user' => auth()->user()?->name ?? '',
                        'http_method' => strtoupper($httpMethod),
                        'request_body' => $requestBody,
                        'response' => $response,
                        'status_code' => $statusCode,
                        'duration_ms' => $durationMs,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            /*
             * Logging should never cause the main API
             * request to fail.
             */
            Log::error('M-Files API logging failed', [
                'api_called' => $apiCalled,
                'http_method' => $httpMethod,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

