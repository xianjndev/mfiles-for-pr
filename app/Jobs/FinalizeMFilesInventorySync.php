<?php

namespace App\Jobs;

use App\Services\MFilesInventorySyncState;
use App\Services\PropertiesTechnicalDescriptionSyncClient;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class FinalizeMFilesInventorySync implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $syncRequestId,
        public ?string $batchId,
        public int $total,
        public ?string $forcedStatus = null,
        public ?string $message = null,
    ) {}

    public function handle(PropertiesTechnicalDescriptionSyncClient $properties, MFilesInventorySyncState $state): void
    {
        $batch = $this->batchId === null ? null : Bus::findBatch($this->batchId);
        [$status, $success, $failed] = $this->result($batch);
        $message = $this->message ?: match ($status) {
            'completed' => 'Property synchronization completed.',
            'completed_with_errors' => $this->total === 0
                ? 'No inventories matched the supplied filter.'
                : 'Property synchronization completed with errors.',
            'stopped' => 'Property synchronization stopped because Properties rejected the active run.',
            default => 'Property synchronization failed.',
        };
        $response = $properties->complete($this->syncRequestId, [
            'status' => $status,
            'success' => $success,
            'failed' => $failed,
            'total' => $this->total,
            'message' => $message,
        ]);

        if ($response->failed()) {
            Log::warning('Properties rejected the M-Files sync completion callback.', [
                'sync_request_id' => $this->syncRequestId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            $response->throw();
        }

        $state->markFinished($this->syncRequestId, $status);
    }

    private function result(?Batch $batch): array
    {
        if ($this->forcedStatus !== null) {
            return [$this->forcedStatus, 0, 0];
        }

        if ($batch === null) {
            return ['failed', 0, 0];
        }

        if ($batch->cancelled()) {
            return ['stopped', 0, $batch->failedJobs];
        }

        $failed = $batch->failedJobs;

        return [
            $failed > 0 ? 'completed_with_errors' : 'completed',
            max(0, $this->total - $failed),
            $failed,
        ];
    }
}
