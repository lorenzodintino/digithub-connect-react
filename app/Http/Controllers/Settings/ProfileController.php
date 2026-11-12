<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeleteAccountRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Timezones;
use App\Support\Toast;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Name, email, preferences and, for portal staff, the public profile.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'phone' => $user->phone ?? '',
                'job_title' => $user->job_title ?? '',
                'bio' => $user->bio ?? '',
            ],
            'timezones' => Timezones::options(),
            'hasPublicProfile' => $user->belongsToTenant(),
            'isBookable' => $user->is_bookable,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Save the profile. A new email address has to be verified again.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // The session copy wins over the account in SetLocale, so keep the two
        // aligned; the toast already speaks the language just chosen.
        $request->session()->put('locale', $user->locale);
        app()->setLocale($user->locale);

        Toast::success(__('Profile updated.'));

        return to_route('profile.edit');
    }

    /**
     * Close the account for good, after the password has been typed again.
     */
    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Auth::guard('web')->logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
