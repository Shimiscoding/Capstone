<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfilePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        abort_unless($viewer->is($user) || $viewer->isAdmin()
            || ($viewer->isSupervisor() && $user->isOfficer() && $user->supervisor_id === $viewer->id), 403);
        abort_unless($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path), 404);

        return response()->file(Storage::disk('local')->path($user->profile_photo_path),
            ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, ProfilePhoto $photos)
    {
        $attributes = $request->validate(ProfilePhoto::rules());
        abort_unless($request->hasFile('profile_photo') || $request->boolean('remove_profile_photo'), 422);
        $user = $photos->save($request->user(), $attributes, $request);

        return response()->json(['success' => true, 'profile_photo_url' => $user->profile_photo_url]);
    }
}
