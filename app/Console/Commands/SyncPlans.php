<?php

namespace App\Console\Commands;

use App\Actions\Plans\SyncPlanCatalogue;
use App\Enums\LicenseEnforcement;
use App\Services\LicenseService;
use Illuminate\Console\Command;

class SyncPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:sync {--force : Read the whole catalogue even if the copy is up to date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Align the plans of this platform with the catalogue of the License Manager';

    /**
     * Read the catalogue and update the local plans by code.
     *
     * Like the licence refresh it works only while enforcement observes or is on: with `off`
     * nothing is called on its own and the plans are the ones defined here.
     */
    public function handle(LicenseService $license, SyncPlanCatalogue $sync): int
    {
        if ($license->enforcement() === LicenseEnforcement::Off && ! $this->option('force')) {
            $this->info('Enforcement is off: the plans are the local ones, nothing to sync.');

            return self::SUCCESS;
        }

        $result = $sync->handle((bool) $this->option('force'));

        if ($result['status'] === 'failed') {
            $this->warn("The catalogue could not be read ({$result['error']}): the last copy stays in use.");

            return self::FAILURE;
        }

        $this->info($result['status'] === 'unchanged'
            ? 'The plans are up to date.'
            : "Plans aligned: {$result['created']} created, {$result['updated']} updated, {$result['retired']} taken off sale.");

        return self::SUCCESS;
    }
}
