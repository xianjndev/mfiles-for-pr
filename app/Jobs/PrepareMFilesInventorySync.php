<?php

namespace App\Jobs;

use App\Http\Controllers\MfilesController;
use App\Services\MFilesInventorySyncState;
use App\Services\PropertiesTechnicalDescriptionSyncClient;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrepareMFilesInventorySync implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $syncRequestId,
        public ?string $projectCode,
        public ?string $propertyCode,
        public array $propertyCodes = [],
    ) {}

    public function handle(MfilesController $mfiles, PropertiesTechnicalDescriptionSyncClient $properties, MFilesInventorySyncState $state): void
    {
        $total = 0;

        try {
            if ($this->propertyCodes !== []) {
                $inventoryList = $this->propertyCodes;
            } else {
                $request = Request::create('/', 'GET');
                $token = $mfiles->get_token($request);

                if (! is_array($token) || ! isset($token['token'])) {
                    throw new \RuntimeException('Unable to authenticate with M-Files.');
                }

                $inventoryList = $mfiles->get_inventory_list([
                    'x-authentication' => $token['token'],
                    'Content-Type' => 'application/json',
                    'Cookie' => $token['setCookie'],
                ], $this->projectCode, $this->propertyCode);
            }

            // Test 5 items
            // $inventoryList = array_slice($inventoryList, 0, 5);

            if ($inventoryList === []) {
                $this->dispatchFinalizer(null, 0, 'completed_with_errors', 'No inventories matched the supplied filter.');

                return;
            }

            $total = count($inventoryList);

            if ($properties->reportProgress($this->syncRequestId, $inventoryList[0], 'pending', null, $total, true) !== true) {
                $this->dispatchFinalizer(null, $total, 'stopped', 'Properties rejected or could not validate the sync request.');

                return;
            }

            $jobs = collect($inventoryList)
                ->map(fn (string $inventory): ProcessMFilesInventory => new ProcessMFilesInventory(
                    $this->syncRequestId,
                    $inventory,
                    $this->projectCode,
                    $total,
                ))
                ->all();
            $syncRequestId = $this->syncRequestId;

            $batch = Bus::batch($jobs)
                ->name("M-Files inventory sync {$syncRequestId}")
                ->allowFailures()
                ->finally(function (Batch $batch) use ($syncRequestId, $total): void {
                    FinalizeMFilesInventorySync::dispatch($syncRequestId, $batch->id, $total)
                        ->onConnection('database-mfiles')
                        ->onQueue('mfiles-sync');
                })
                ->onConnection('database-mfiles')
                ->onQueue('mfiles-sync')
                ->dispatch();

            $state->attachBatch($this->syncRequestId, $batch->id);
        } catch (Throwable $exception) {
            $this->dispatchFinalizer(null, $total, 'failed', $exception->getMessage());
            Log::error('Unable to prepare the M-Files inventory sync.', [
                'sync_request_id' => $this->syncRequestId,
                'exception' => $exception,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->dispatchFinalizer(
            null,
            0,
            'failed',
            $exception?->getMessage() ?? 'The M-Files sync preparation job failed.',
        );
    }

    private function dispatchFinalizer(?string $batchId, int $total, ?string $forcedStatus = null, ?string $message = null): void
    {
        FinalizeMFilesInventorySync::dispatch($this->syncRequestId, $batchId, $total, $forcedStatus, $message)
            ->onConnection('database-mfiles')
            ->onQueue('mfiles-sync');
    }
}
