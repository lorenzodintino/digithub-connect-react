<?php

namespace App\Http\Controllers;

use App\Actions\Staff\DeleteOperator;
use App\Actions\Staff\SaveOperator;
use App\Http\Requests\StaffRequest;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Timezones;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    /**
     * The people who deliver the services of the portal and receive bookings.
     */
    public function index(Request $request): Response
    {
        /** @var User $manager */
        $manager = $request->user();
        $tenant = $this->tenant($manager);

        $operators = $tenant->operators()
            ->with('services')
            ->withCount([
                'availabilityRules',
                'bookings as upcoming_count' => fn ($query) => $query->blocking()->upcoming(),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $operator) => [
                'id' => $operator->id,
                'name' => $operator->name,
                'email' => $operator->email,
                'initials' => $operator->initials(),
                'avatar' => $operator->avatarUrl(),
                'phone' => $operator->phone ?? '',
                'job_title' => $operator->job_title ?? '',
                'bio' => $operator->bio ?? '',
                'timezone' => $operator->timezone,
                'locale' => $operator->locale,
                'is_bookable' => $operator->is_bookable,
                'is_active' => $operator->is_active,
                'service_ids' => $operator->services->pluck('id')->all(),
                'service_names' => $operator->services->pluck('name')->all(),
                'weekly_slots' => (int) $operator->getAttribute('availability_rules_count'),
                'upcoming_count' => (int) $operator->getAttribute('upcoming_count'),
            ])
            ->values()
            ->all();

        return Inertia::render('app/staff', [
            'operators' => $operators,
            'services' => $tenant->services()->orderBy('name')->get()->map(fn (Service $service) => ['id' => $service->id, 'name' => $service->name])->all(),
            'managerBookable' => $manager->is_bookable,
            'defaults' => ['timezone' => $tenant->timezone, 'locale' => $tenant->locale],
            'timezones' => Timezones::options(),
        ]);
    }

    /**
     * Create the operator and hand the manager the credentials, once: flash
     * data never lands in the browser history.
     */
    public function store(StaffRequest $request, SaveOperator $save): RedirectResponse
    {
        /** @var User $manager */
        $manager = $request->user();

        ['member' => $member, 'password' => $password] = $save->create($this->tenant($manager), $request->validated());

        Inertia::flash('credentials', ['email' => $member->email, 'password' => $password]);

        return back();
    }

    public function update(StaffRequest $request, User $member, SaveOperator $save): RedirectResponse
    {
        $save->update($member, $request->validated());

        Toast::success(__('Operator updated.'));

        return back();
    }

    public function destroy(User $member, DeleteOperator $delete): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $delete->handle($member);

        Toast::success(__('Operator deleted.'));

        return back();
    }

    /**
     * Whether the manager also takes bookings, like an operator.
     */
    public function toggleBookable(Request $request): RedirectResponse
    {
        /** @var User $manager */
        $manager = $request->user();

        $manager->update(['is_bookable' => ! $manager->is_bookable]);

        Toast::success($manager->is_bookable ? __('You are now bookable.') : __('You no longer receive bookings.'));

        return back();
    }

    private function tenant(User $user): Tenant
    {
        abort_if($user->tenant === null, 404);

        return $user->tenant;
    }
}
