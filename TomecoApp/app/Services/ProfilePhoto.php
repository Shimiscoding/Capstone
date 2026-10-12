<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhoto
{
    public static function rules(): array
    {
        return ['profile_photo' => ['nullable', 'prohibited_if:remove_profile_photo,1,true', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096'],
            'remove_profile_photo' => ['sometimes', 'boolean']];
    }

    public function save(User $user, array $attributes, Request $request): User
    {
        unset($attributes['profile_photo'], $attributes['remove_profile_photo']);
        $old = $user->profile_photo_path;
        $new = null;
        if ($request->hasFile('profile_photo')) {
            $new = $request->file('profile_photo')->store('profile-photos', 'local');
            if (! $new) {
                throw new \RuntimeException('Could not save the profile photo.');
            }
            $user->profile_photo_path = $new;
        } elseif ($request->boolean('remove_profile_photo')) {
            $user->profile_photo_path = null;
        }
        try {
            $user->fill($attributes)->save();
        } catch (\Throwable $error) {
            if ($new) {
                Storage::disk('local')->delete($new);
            }
            throw $error;
        }
        if ($old && $old !== $user->profile_photo_path) {
            Storage::disk('local')->delete($old);
        }

        return $user;
    }
}
