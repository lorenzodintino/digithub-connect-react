<?php

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Services\LicenseService;
use App\Support\Activity;
use App\Support\PlanAttributes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncPlanCatalogue
{
    public function __construct(private readonly LicenseService $license) {}

    /**
     * Align the plans of this platform with the catalogue of the License Manager, by `code`.
     *
     * The License Manager is the only place where plans are defined: name, order, description,
     * whether they are on sale, the monthly price and what each one includes (see
     * App\Support\PlanAttributes). A plan that is no longer in the catalogue is not deleted, it is
     * taken off sale: portals may still be on it. When the License Manager cannot be read nothing is
     * touched: the last copy keeps serving, and the failure is told to the administrators.
     *
     * @return array{status: 'updated'|'unchanged'|'failed', error: string|null, created: int, updated: int, retired: int}
     */
    public function handle(bool $force = false): array
    {
        $answer = $this->license->catalogue($force);
        $result = ['status' => 'unchanged', 'error' => null, 'created' => 0, 'updated' => 0, 'retired' => 0];

        if ($answer['error'] !== null) {
            return [...$result, 'status' => 'failed', 'error' => $answer['error']];
        }

        if ($answer['not_modified']) {
            return $result;
        }

        DB::transaction(function () use ($answer, &$result): void {
            $codes = [];

            foreach ($answer['plans'] as $entry) {
                $codes[] = $entry['code'];
                $monthly = collect($entry['prices'])->firstWhere('months', 1);

                $plan = Plan::query()->firstOrNew(['code' => $entry['code']]);
                $isNew = ! $plan->exists;

                $plan->fill([
                    'name' => $entry['name'],
                    'description' => $entry['description'],
                    'position' => $entry['position'],
                    'is_active' => $entry['active'],
                    'price_cents' => $monthly['price_cents'] ?? null,
                    'currency' => $monthly['currency'] ?? 'EUR',
                    ...PlanAttributes::toPlan($entry['attributes']),
                ]);

                if ($isNew || $plan->isDirty()) {
                    $result[$isNew ? 'created' : 'updated']++;
                }

                $plan->synced_at = now();
                $plan->save();
            }

            $result['retired'] = Plan::query()->whereNotNull('code')->whereNotIn('code', $codes)->where('is_active', true)->update(['is_active' => false]);
        });

        if ($answer['etag'] !== null) {
            Cache::forever('license.catalogue.etag', $answer['etag']);
        }

        Cache::forever('license.catalogue.synced-at', now()->toIso8601String());

        $changed = $result['created'] + $result['updated'] + $result['retired'];

        if ($changed > 0) {
            Activity::log('plan.catalogue_synced', null, [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'retired' => $result['retired'],
            ]);
        }

        return [...$result, 'status' => $changed > 0 ? 'updated' : 'unchanged'];
    }
}
