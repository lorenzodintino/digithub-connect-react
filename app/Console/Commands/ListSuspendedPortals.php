<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\LicenseService;
use Illuminate\Console\Command;

class ListSuspendedPortals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'licenses:suspended-portals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the portals switched off (is_active = false) and whether the old subscription check did it; changes nothing';

    /**
     * Read-only. Before turning the licence on, the administrator goes through
     * what is listed: a portal marked as suspended for payment was switched off
     * by the old `portals:check-subscriptions`, one with no mark was switched
     * off by hand. Nothing is reactivated here.
     */
    public function handle(LicenseService $license): int
    {
        $portals = Tenant::query()->with('license')->where('is_active', false)->orderBy('id')->get();

        if ($portals->isEmpty()) {
            $this->info('No portal is switched off.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Slug', 'Name', 'Paid until', 'Suspended for payment on', 'Exempt', 'License (last answer)'],
            $portals->map(fn (Tenant $portal): array => [
                $portal->id,
                $portal->slug,
                $portal->name,
                $portal->paid_until?->toDateString() ?? '-',
                $portal->subscription_suspended_at?->toDateString() ?? '- (by hand)',
                $portal->license_exempt ? 'yes' : 'no',
                $this->describe($license, $portal),
            ])->all(),
        );

        $forPayment = $portals->whereNotNull('subscription_suspended_at')->count();
        $this->info("{$portals->count()} portals switched off: {$forPayment} by the old subscription check, ".($portals->count() - $forPayment).' by hand. Nothing was changed.');

        return self::SUCCESS;
    }

    private function describe(LicenseService $license, Tenant $portal): string
    {
        $status = $license->fromCopy($portal->license);

        if ($status->checkedAt === null) {
            return 'never answered';
        }

        return $status->outcome->value.($status->reason !== null ? ' / '.$status->reason->value : '').' ('.$status->checkedAt->toDateString().')';
    }
}
