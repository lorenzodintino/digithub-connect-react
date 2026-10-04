<?php

namespace App\Actions\Tenants;

use App\Models\ActivityLog;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\BookingPhase;
use App\Models\Customer;
use App\Models\PortalClosure;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServicePackageItem;
use App\Models\ServiceQuestion;
use App\Models\ServiceResourceRequirement;
use App\Models\ServiceSession;
use App\Models\Tenant;
use App\Models\TimeBlock;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Support\Activity;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ExportPortal
{
    /**
     * Columns of the portal row that are platform credentials, not portal data.
     *
     * @var array<int, string>
     */
    private const SECRET_TENANT_COLUMNS = ['mail_username', 'mail_password'];

    /**
     * Pack everything a portal owns into a ZIP file and return its path.
     *
     * This is what the portal, as data controller, gets back when the contract
     * ends (art. 28 GDPR): one JSON file per kind of record, with encrypted
     * notes and answers already readable, and the files it uploaded. Passwords,
     * two-factor secrets, passkeys and SMTP credentials stay behind: they let
     * somebody in, they are not data about the business.
     *
     * The caller owns the returned file and must delete it once it is sent.
     */
    public function handle(Tenant $tenant, User $actor): string
    {
        $path = tempnam(sys_get_temp_dir(), 'portal-export-');

        if ($path === false) {
            throw new RuntimeException('The export file could not be created.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The export file could not be opened.');
        }

        $parts = [];
        $counts = [];

        try {
            foreach ($this->sections($tenant) as $name => $rows) {
                $part = $this->writeJson($rows);
                $parts[] = $part['path'];
                $counts[$name] = $part['count'];
                $zip->addFile($part['path'], $name.'.json');
            }

            $files = $this->addUploadedFiles($zip, $tenant);

            $zip->addFromString('export.json', (string) json_encode([
                'portal' => $tenant->slug,
                'exported_at' => now()->toIso8601ZuluString(),
                'exported_by' => $actor->email,
                'records' => $counts,
                'files' => $files,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            // The parts are read when the archive is closed, not when they are added.
            $zip->close();
        } finally {
            foreach ($parts as $part) {
                @unlink($part);
            }
        }

        Activity::log('portal.exported', $tenant, ['records' => array_sum($counts), 'files' => count($files)], tenant: $tenant, actor: $actor);

        return $path;
    }

    /**
     * Every kind of record the portal owns, as rows ready to encode.
     *
     * @return array<string, Closure(): iterable<int, array<string, mixed>>>
     */
    private function sections(Tenant $tenant): array
    {
        $staff = User::query()->where('tenant_id', $tenant->getKey())->select('id');
        $services = Service::query()->where('tenant_id', $tenant->getKey())->select('id');
        $bookings = Booking::query()->where('tenant_id', $tenant->getKey())->select('id');
        $sessions = ServiceSession::query()->where('tenant_id', $tenant->getKey())->select('id');
        $requirements = ServiceResourceRequirement::query()->where('tenant_id', $tenant->getKey())->select('id');

        return [
            'portal' => $this->models(Tenant::query()->whereKey($tenant->getKey()), hidden: self::SECRET_TENANT_COLUMNS),
            'staff' => $this->models(User::query()->where('tenant_id', $tenant->getKey())),
            'services' => $this->models(Service::query()->where('tenant_id', $tenant->getKey())),
            'service-operators' => $this->rows(DB::table('service_user')->whereIn('service_id', $services)),
            'service-questions' => $this->models(ServiceQuestion::query()->whereIn('service_id', $services)),
            'service-package-items' => $this->models(ServicePackageItem::query()->where('tenant_id', $tenant->getKey())),
            'service-resource-requirements' => $this->models(ServiceResourceRequirement::query()->where('tenant_id', $tenant->getKey())),
            'service-resource-requirement-options' => $this->rows(DB::table('resource_service_requirement')->whereIn('service_resource_requirement_id', $requirements)),
            'availability-rules' => $this->models(AvailabilityRule::query()->whereIn('user_id', $staff)),
            'availability-exceptions' => $this->models(AvailabilityException::query()->whereIn('user_id', $staff)),
            'time-blocks' => $this->models(TimeBlock::query()->whereIn('user_id', $staff)),
            'portal-closures' => $this->models(PortalClosure::query()->where('tenant_id', $tenant->getKey())),
            'resources' => $this->models(Resource::query()->where('tenant_id', $tenant->getKey())),
            'resource-blocks' => $this->models(ResourceBlock::query()->where('tenant_id', $tenant->getKey())),
            'resource-allocations' => $this->models(ResourceAllocation::query()->where('tenant_id', $tenant->getKey())),
            'service-sessions' => $this->models(ServiceSession::query()->where('tenant_id', $tenant->getKey())),
            'service-session-staff' => $this->rows(DB::table('service_session_user')->whereIn('service_session_id', $sessions)),
            'customers' => $this->models(Customer::query()->where('tenant_id', $tenant->getKey())),
            'bookings' => $this->models(Booking::query()->where('tenant_id', $tenant->getKey())),
            'booking-answers' => $this->models(BookingAnswer::query()->whereIn('booking_id', $bookings)),
            'booking-phases' => $this->models(BookingPhase::query()->where('tenant_id', $tenant->getKey())),
            'booking-resource-allocations' => $this->rows(DB::table('booking_resource_allocation')->whereIn('booking_id', $bookings)),
            'waiting-list' => $this->models(WaitingListEntry::query()->where('tenant_id', $tenant->getKey())),
            'activity-log' => $this->models(ActivityLog::query()->where('tenant_id', $tenant->getKey())),
        ];
    }

    /**
     * Rows read through the model, so casts decrypt and hidden columns stay out.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $hidden  extra columns to leave out
     * @return Closure(): iterable<int, array<string, mixed>>
     */
    private function models(Builder $query, array $hidden = []): Closure
    {
        return fn () => $query->lazyById(200)->map(fn (Model $model): array => $model->makeHidden($hidden)->toArray());
    }

    /**
     * Rows of a pivot table, which has no model and nothing to decrypt.
     *
     * @return Closure(): iterable<int, array<string, mixed>>
     */
    private function rows(QueryBuilder $query): Closure
    {
        return fn () => $query->cursor()->map(fn (object $row): array => (array) $row);
    }

    /**
     * Stream rows into a temporary JSON array, one record at a time.
     *
     * @param  Closure(): iterable<int, array<string, mixed>>  $rows
     * @return array{path: string, count: int}
     */
    private function writeJson(Closure $rows): array
    {
        $path = tempnam(sys_get_temp_dir(), 'portal-export-part-');
        $handle = $path === false ? false : fopen($path, 'w');

        if ($path === false || $handle === false) {
            throw new RuntimeException('A part of the export could not be written.');
        }

        $count = 0;
        fwrite($handle, '[');

        foreach ($rows() as $row) {
            fwrite($handle, ($count === 0 ? "\n" : ",\n").json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $count++;
        }

        fwrite($handle, "\n]\n");
        fclose($handle);

        return ['path' => $path, 'count' => $count];
    }

    /**
     * Copy the logo, the legal documents and the staff avatars into the archive.
     *
     * @return array<int, string> the archive paths of the copied files
     */
    private function addUploadedFiles(ZipArchive $zip, Tenant $tenant): array
    {
        $disk = Storage::disk('public');

        $uploads = collect([
            'logo' => $tenant->logo_path,
            'privacy-policy' => $tenant->privacy_policy_path,
            'terms' => $tenant->terms_path,
        ]);

        User::query()->where('tenant_id', $tenant->getKey())->whereNotNull('avatar_path')
            ->each(function (User $user) use ($uploads): void {
                $uploads->put('avatars/'.$user->getKey(), $user->avatar_path);
            });

        return $uploads
            ->filter(fn (?string $path): bool => $path !== null && $disk->exists($path))
            ->map(function (string $path, string $name) use ($zip, $disk): string {
                $entry = 'files/'.$name.'.'.pathinfo($path, PATHINFO_EXTENSION);
                $zip->addFile($disk->path($path), $entry);

                return $entry;
            })
            ->values()
            ->all();
    }
}
