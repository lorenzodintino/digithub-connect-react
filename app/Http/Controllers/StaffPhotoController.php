<?php

namespace App\Http\Controllers;

use App\Actions\Users\ReplaceAvatar;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StaffPhotoController extends Controller
{
    /**
     * The manager uploads the photo of one of their operators.
     */
    public function store(ProfilePhotoRequest $request, User $member, ReplaceAvatar $avatar): RedirectResponse
    {
        Gate::authorize('update', $member);

        $avatar->handle($member, $request->file('photo'));

        Toast::success(__('Photo updated.'));

        return back();
    }

    public function destroy(User $member, ReplaceAvatar $avatar): RedirectResponse
    {
        Gate::authorize('update', $member);

        $avatar->remove($member);

        Toast::success(__('Photo removed.'));

        return back();
    }
}
