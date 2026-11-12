<?php

namespace App\Console\Commands;

use App\Enums\LicenseEnforcement;
use App\Models\Tenant;
use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshLicenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'licenses:refresh {--limit=100 : Most portals checked in one run} {--tenant= : Check only the portal with this id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ask the License Manager about the portals, so a request almost always finds a copy';

    /**
     * Refresh the portals whose answer is the oldest, a hundred at most per run.
     *
     * It works only while enforcement observes or is on: with `off` nothing is
     * called on its own. A hundred calls a run keeps the key's 120 a minute
     * safe; on a rate limit the run stops and the portals wait for the next one.
     */
    public function handle(LicenseService $license): int
    {
        if ($license->enforcement() === LicenseEnforcement::Off) {
            $this->info('Enforcement is off: nothing to refresh.');

            return self::SUCCESS;
        }

        if (($problem = $license->configurationProblem()) !== null) {
            $this->error("The License Manager is not configured safely ({$problem}): nothing was called.");

            return self::FAILURE;
        }

        $tenant = $this->option('tenant');

        $portals = Tenant::query()
            ->with('license')
            ->where('license_exempt', false)
            ->when($tenant !== null, fn ($query) => $query->whereKey((int) $tenant))
            ->get()
            // Never asked first, then the oldest attempt; one asked a minute ago (a manual check) can wait.
            ->reject(fn (Tenant $portal): bool => $portal->license?->last_attempt_at?->gt(now()->subMinute()) ?? false)
            ->sortBy(fn (Tenant $portal): int => $portal->license?->last_attempt_at?->getTimestamp() ?? 0)
            ->take(max(1, (int) $this->option('limit')));

        $checked = 0;
        $failed = 0;

        foreach ($portals as $portal) {
            if (Cache::has('license.backoff')) {
                $this->warn('The License Manager asked to slow down: the rest waits for the next run.');

                break;
            }

            $status = $license->refresh($portal);
            $checked++;

            if ($status->error !== null) {
                $failed++;
            }
        }

        $this->info("Checked {$checked} portals, {$failed} without a valid answer.");

        return self::SUCCESS;
    }
}
