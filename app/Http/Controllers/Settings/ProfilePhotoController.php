<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\ReplaceAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePhotoController extends Controller
{
    /**
     * Store the picked photo straight away, like the portal logo does.
     */
    public function store(ProfilePhotoRequest $request, ReplaceAvatar $avatar): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $avatar->handle($user, $request->file('photo'));

        Toast::success(__('Photo updated.'));

        return to_route('profile.edit');
    }

    /**
     * Drop the photo and go back to the coloured initials.
     */
    public function destroy(Request $request, ReplaceAvatar $avatar): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $avatar->remove($user);

        Toast::success(__('Photo removed.'));

        return to_route('profile.edit');
    }
}
