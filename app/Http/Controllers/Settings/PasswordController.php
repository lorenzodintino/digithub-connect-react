<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    /**
     * Replace the password, once the current one has been typed.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);

        Toast::success(__('Password updated.'));

        return back();
    }
}
