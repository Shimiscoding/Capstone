<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_replace_and_remove_photo_preserve_private_access(): void
    {
        Storage::fake('local');
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        Sanctum::actingAs($officer);
        $this->postJson('/api/auth/profile-photo', ['remove_profile_photo' => '0', 'profile_photo' => UploadedFile::fake()->image('portrait.png', 100, 100)])
            ->assertOk();
        $path = $officer->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($path);
        $this->get($officer->fresh()->profile_photo_url)->assertOk();
        $this->postJson('/api/auth/profile-photo', ['profile_photo' => UploadedFile::fake()->image('replacement.jpg', 100, 100)])->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertArrayNotHasKey('profile_photo_path', $officer->fresh()->toArray());
        $this->postJson('/api/auth/profile-photo', ['remove_profile_photo' => true])->assertOk()->assertJsonPath('profile_photo_url', null);
        $this->assertNull($officer->fresh()->profile_photo_path);
    }

    public function test_my_profile_form_can_save_a_photo_without_changing_area(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'username' => 'my.profile']);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('multipart/form-data', false);
        $this->put(route('profile.update'), ['firstName' => $user->firstName, 'lastName' => $user->lastName,
            'username' => $user->username, 'address' => 'Test street', 'phoneNumber' => $user->phoneNumber,
            'email' => $user->email, 'profile_photo' => UploadedFile::fake()->image('self.png', 100, 100)])
            ->assertSessionHasNoErrors()->assertRedirect();
        Storage::disk('local')->assertExists($user->fresh()->profile_photo_path);
    }

    public function test_invalid_uploads_do_not_replace_existing_photo(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER]));
        $this->postJson('/api/auth/profile-photo', ['profile_photo' => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml')])->assertUnprocessable();
        $this->postJson('/api/auth/profile-photo', ['profile_photo' => UploadedFile::fake()->image('large.jpg')->size(2049)])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_admin_can_upload_an_enforcer_photo_and_other_teams_cannot_view_it(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id]);
        $this->actingAs($admin)->put(route('dashboard.users.update', $officer), [
            'firstName' => $officer->firstName, 'lastName' => $officer->lastName, 'phoneNumber' => $officer->phoneNumber,
            'email' => $officer->email, 'role' => $officer->role, 'supervisor_id' => $supervisor->id,
            'profile_photo' => UploadedFile::fake()->image('portrait.png', 100, 100),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $url = $officer->fresh()->profile_photo_url;
        $this->actingAs($admin)->get(route('dashboard.users.enforcers'))->assertOk()->assertSee($url, false);
        $this->actingAs($supervisor)->get($url)->assertOk();
        $other = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->actingAs($other)->get($url)->assertForbidden();
    }
}
