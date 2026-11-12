<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\DeleteAccount;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Search;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Every account on the platform, across all portals.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        /** @var User $admin */
        $admin = $request->user();
        $search = $this->filter($request, 'q');
        $role = UserRole::tryFrom($this->filter($request, 'role')) !== null ? $this->filter($request, 'role') : 'all';
        $portal = ctype_digit($this->filter($request, 'portal')) ? $this->filter($request, 'portal') : 'all';

        $accounts = User::query()
            ->with('tenant')
            ->withCount(['bookings as upcoming_count' => fn ($query) => $query->blocking()->upcoming()])
            ->when($search !== '', fn ($query) => Search::contains($query, ['name', 'email'], $search))
            ->when($role !== 'all', fn ($query) => $query->where('role', $role))
            ->when($portal !== 'all', fn ($query) => $query->where('tenant_id', $portal))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'initials' => $account->initials(),
                'avatar' => $account->avatarUrl(),
                'role' => $account->role->label(),
                'role_color' => $account->role->color(),
                'portal' => $account->tenant?->name,
                'is_active' => $account->is_active,
                'is_self' => $account->is($admin),
                'upcoming_count' => (int) $account->getAttribute('upcoming_count'),
            ]);

        return Inertia::render('admin/users', [
            'accounts' => $accounts,
            'filters' => ['q' => $search, 'role' => $role, 'portal' => $portal],
            'roles' => collect(UserRole::cases())->map(fn (UserRole $case) => ['id' => $case->value, 'name' => $case->label()])->all(),
            'portals' => Tenant::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant) => ['id' => (string) $tenant->id, 'name' => $tenant->name])->all(),
        ]);
    }

    /**
     * Disable or enable an account; never one's own.
     */
    public function toggle(Request $request, User $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        abort_if($account->is($request->user()), 403);

        $account->update(['is_active' => ! $account->is_active]);

        Toast::success($account->is_active ? __('Account enabled.') : __('Account disabled.'));

        return back();
    }

    /**
     * Issue a new temporary password and hand it over once, like a new account.
     */
    public function resetPassword(User $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $password = Str::password(14, symbols: false);

        $account->update(['password' => $password]);

        Inertia::flash('credentials', ['email' => $account->email, 'password' => $password]);

        return back();
    }

    public function destroy(User $account, DeleteAccount $delete): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $delete->handle($account);

        Toast::success(__('Account deleted.'));

        return back();
    }
}
