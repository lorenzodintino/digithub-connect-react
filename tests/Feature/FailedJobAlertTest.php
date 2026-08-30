<?php

use App\Mail\Customer\BookingConfirmedMail;
use App\Mail\Platform\FailedJobsReportMail;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->admin = User::factory()->admin()->create(['name' => 'Platform Admin']);
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * Fire the event the queue fires when a job gives up.
 */
function failJob(string $displayName = 'App\Mail\Customer\BookingConfirmedMail', string $message = '550 recipient rejected', ?string $command = null): void
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => $displayName,
        'data' => ['command' => $command],
    ]);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('resolveName')->andReturn($displayName);

    event(new JobFailed('database', $job, new RuntimeException($message)));
}

test('a job that gives up leaves a line in the register', function () {
    failJob();

    $log = ActivityLog::query()->where('action', 'job.failed')->sole();

    expect($log->context['job'])->toBe('BookingConfirmedMail')
        ->and($log->context['queue'])->toBe('default')
        ->and($log->context['error'])->toContain('550 recipient rejected');
});

test('the line says which portal it was about when the job knows', function () {
    $service = Service::factory()->for($this->tenant)->create();
    $operator = User::factory()->operator($this->tenant)->create();

    $booking = Booking::factory()
        ->forSlot($service, $operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create();

    // What a queued mailable really carries: the mail object with the booking.
    $mail = new BookingConfirmedMail($booking);

    failJob(command: serialize($mail));

    expect(ActivityLog::query()->where('action', 'job.failed')->sole()->tenant_id)->toBe($this->tenant->id);
});

test('an unreadable job payload does not stop the register', function () {
    failJob(command: 'questo non è un oggetto serializzato');

    expect(ActivityLog::query()->where('action', 'job.failed')->sole()->tenant_id)->toBeNull();
});

test('the hourly report reaches the administrators once', function () {
    failJob();
    failJob('App\Mail\Customer\BookingReminderMail', 'Connection refused');

    $this->artisan('jobs:report-failures')->assertSuccessful();

    Mail::assertSent(FailedJobsReportMail::class, 1);
    Mail::assertSent(FailedJobsReportMail::class, fn ($mail) => $mail->hasTo($this->admin->email));

    expect(ActivityLog::query()->where('action', 'job.reported')->sole()->context['failures'])->toBe(2);
});

test('a quiet hour sends nothing', function () {
    ActivityLog::factory()->create([
        'action' => 'job.failed',
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('jobs:report-failures')->assertSuccessful();

    Mail::assertNothingSent();
});

test('the administrator sees the failed jobs and can retry one', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => 'aaaa-bbbb',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\Mail\Customer\BookingConfirmedMail']),
        'exception' => "RuntimeException: 550 recipient rejected\n#0 stack",
        'failed_at' => now(),
    ]);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.queue');

    $component->assertSee('BookingConfirmedMail')->assertSee('550 recipient rejected');

    $component->call('forget', 'aaaa-bbbb');

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('the queue page is closed to a manager', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::admin.queue')
        ->assertForbidden();

    $this->actingAs($this->manager)->get(route('admin.queue'))->assertForbidden();
});
