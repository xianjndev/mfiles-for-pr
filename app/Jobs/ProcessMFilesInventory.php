<?php

namespace App\Jobs;

use App\Http\Controllers\MfilesController;
use App\Services\PropertiesTechnicalDescriptionSyncClient;
use App\Support\EncryptedRouteParameter;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ProcessMFilesInventory implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $syncRequestId,
        public string $propertyCode,
        public ?string $projectCode,
        public int $total,
    ) {}

    public function handle(MfilesController $mfiles, PropertiesTechnicalDescriptionSyncClient $properties): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($properties->reportProgress($this->syncRequestId, $this->propertyCode, 'processing', null, $this->total) === false) {
            $this->batch()?->cancel();
            throw new RuntimeException('Properties rejected the active sync run.');
        }

        try {
            $request = Request::create('/', 'GET');
            $request->name = $this->propertyCode;
            $request->property_ids = [
                1105, 1024, 1290, 1050, 1029,
                1107, 1109, 1203, 1204, 1202, 1285,
            ];
            $propData = $mfiles->get_document_property($request, 119, 1105);

            if (isset($propData['_error'])) {
                throw new RuntimeException((string) $propData['_error']);
            }

            $resolvedProjectCode = $this->projectCode ?: ($propData['Project Code'] ?? null);

            if (blank($resolvedProjectCode)) {
                throw new RuntimeException("Unable to resolve the project code for property {$this->propertyCode}.");
            }

            $request->name = $resolvedProjectCode;
            $request->property_ids = [1293, 1294];
            $projData = $mfiles->get_document_property($request, 101, 1050);

            if (isset($projData['_error'])) {
                throw new RuntimeException((string) $projData['_error']);
            }

            $response = $mfiles->update_properties(
                json_encode($this->payload($propData, $projData), JSON_THROW_ON_ERROR),
                EncryptedRouteParameter::fromConfig()->encrypt($this->propertyCode),
                $this->syncRequestId,
            );
            $itemStatus = $response['data']['status'] ?? null;

            if (in_array($itemStatus, ['created', 'updated', 'unchanged'], true)) {
                return;
            }

            $error = (string) ($response['error'] ?? $response['data']['message'] ?? 'Property update failed.');

            if (in_array($response['status'] ?? null, [401, 403, 404, 409], true)) {
                $this->batch()?->cancel();
            }

            throw new RuntimeException($error);
        } catch (Throwable $exception) {
            $properties->reportProgress(
                $this->syncRequestId,
                $this->propertyCode,
                'failed',
                $exception->getMessage(),
                $this->total,
            );

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(PropertiesTechnicalDescriptionSyncClient::class)->reportProgress(
            $this->syncRequestId,
            $this->propertyCode,
            'failed',
            $exception?->getMessage() ?? 'The inventory queue job failed.',
            $this->total,
        );
    }

    private function payload(array $property, array $project): array
    {
        return [
            'company_name' => $this->value($project, 'Project Developer'),
            'registry_of_deeds' => $this->value($project, 'Deed of Registry'),
            'tct' => $this->value($property, 'TCT No.'),
            'vsr' => $this->value($property, 'VSR'),
            'old_technical_desc' => $this->value($property, 'Technical Description'),
            'survey_plan_no' => $this->value($property, 'VSR'),
            'block_no' => $this->value($property, 'Block No.'),
            'lot_no' => $this->value($property, 'Lot'),
            'portion_of_lot' => $this->value($property, 'portion_of_lot'),
            'lrc_record_no' => $this->value($property, 'lrc_record_no'),
            'land_owner_claimant' => $this->value($property, 'land_owner_claimant'),
            'location' => $this->value($property, 'location'),
            'area' => $this->value($property, 'area'),
            'description_of_corners' => $this->value($property, 'description_of_corners'),
            'bearings' => $this->bearingsValue($property),
            'original_date_of_survey' => $this->value($property, 'original_date_of_survey'),
            'date_of_survey' => $this->value($property, 'date_of_survey'),
            'date_approved' => $this->value($property, 'date_approved'),
            'geodetic_engineer' => $this->value($property, 'geodetic_engineer'),
        ];
    }

    private function value(array $data, string $key): mixed
    {
        return ! empty($data[$key]) ? $data[$key] : '-';
    }

    private function bearingsValue(array $property): mixed
    {
        if (! array_key_exists('bearings', $property)) {
            return '-';
        }

        $value = $property['bearings'];

        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) {
            return '-';
        }

        // Preserve explicit false, 0, and "0" values; empty() would turn them into "-".
        return $value;
    }
}
