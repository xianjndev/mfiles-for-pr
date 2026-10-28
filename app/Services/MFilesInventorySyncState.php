<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MFilesInventorySyncState
{
    private const TTL_DAYS = 7;

    public function accept(string $syncRequestId, ?string $projectCode, ?string $propertyCode): bool
    {
        return Cache::lock($this->lockKey($syncRequestId), 10)->block(5, function () use ($syncRequestId, $projectCode, $propertyCode): bool {
            if (Cache::has($this->key($syncRequestId))) {
                return false;
            }

            Cache::put($this->key($syncRequestId), [
                'status' => 'queued',
                'project_code' => $projectCode,
                'property_code' => $propertyCode,
                'batch_id' => null,
            ], now()->addDays(self::TTL_DAYS));

            return true;
        });
    }

    public function attachBatch(string $syncRequestId, string $batchId): void
    {
        $this->update($syncRequestId, [
            'status' => 'running',
            'batch_id' => $batchId,
        ]);
    }

    public function markFinished(string $syncRequestId, string $status): void
    {
        $this->update($syncRequestId, ['status' => $status]);
    }

    public function snapshot(string $syncRequestId): ?array
    {
        $state = Cache::get($this->key($syncRequestId));

        return is_array($state) ? $state : null;
    }

    public function forget(string $syncRequestId): void
    {
        Cache::forget($this->key($syncRequestId));
    }

    private function update(string $syncRequestId, array $attributes): void
    {
        Cache::lock($this->lockKey($syncRequestId), 10)->block(5, function () use ($syncRequestId, $attributes): void {
            $state = $this->snapshot($syncRequestId) ?? [];
            Cache::put($this->key($syncRequestId), [...$state, ...$attributes], now()->addDays(self::TTL_DAYS));
        });
    }

    private function key(string $syncRequestId): string
    {
        return "mfiles-inventory-sync:{$syncRequestId}";
    }

    private function lockKey(string $syncRequestId): string
    {
        return "mfiles-inventory-sync-lock:{$syncRequestId}";
    }
}
