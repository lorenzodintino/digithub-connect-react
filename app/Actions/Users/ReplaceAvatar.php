<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReplaceAvatar
{
    /**
     * Store a new photo for the person and delete the one it replaces.
     *
     * Used by the person on their own profile and by the manager on the staff
     * screen: half of the operators never log in, and the one who set them up
     * is the one with the photo to hand.
     */
    public function handle(User $user, UploadedFile $photo): void
    {
        $path = $photo->store('avatars/'.$user->getKey(), 'public');

        $this->forget($user->avatar_path);

        $user->forceFill(['avatar_path' => $path])->save();
    }

    /**
     * Drop the photo and go back to the coloured initials.
     */
    public function remove(User $user): void
    {
        $this->forget($user->avatar_path);

        $user->forceFill(['avatar_path' => null])->save();
    }

    /**
     * Delete a stored photo, ignoring one that was never there.
     */
    private function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
