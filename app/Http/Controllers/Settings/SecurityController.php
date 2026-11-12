<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class SecurityController extends Controller
{
    /**
     * Password, two-factor authentication and passkeys. The two-factor and
     * passkey buttons talk to Fortify's own routes, which answer back here.
     */
    public function edit(Request $request, DisableTwoFactorAuthentication $disableTwoFactorAuthentication): Response
    {
        /** @var User $user */
        $user = $request->user();

        $twoFactor = null;

        if (Features::canManageTwoFactorAuthentication()) {
            $this->dropAbandonedTwoFactorSetup($request, $user, $disableTwoFactorAuthentication);

            $twoFactor = [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            ];
        }

        return Inertia::render('settings/security', [
            'twoFactor' => $twoFactor,
            'passkeys' => Features::canManagePasskeys() ? $this->passkeys($user) : null,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * A setup that was started and never confirmed is thrown away — except
     * right after Fortify enabled it, or while a wrong code is being typed
     * again: then the setup is still in progress.
     */
    private function dropAbandonedTwoFactorSetup(Request $request, User $user, DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        if (! Fortify::confirmsTwoFactorAuthentication() || $user->two_factor_confirmed_at !== null) {
            return;
        }

        $session = $request->session();

        if ($session->get('status') === Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED || $session->hasOldInput('code')) {
            return;
        }

        $disableTwoFactorAuthentication($user);
    }

    /**
     * @return array<int, array{id: int, name: string, authenticator: string|null, created_at_diff: string, last_used_at_diff: string|null}>
     */
    private function passkeys(User $user): array
    {
        return $user->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}
