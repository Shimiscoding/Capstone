<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_record_the_actor_fields_old_values_new_values_and_time(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'firstName' => 'Old',
            'lastName' => 'Name',
            'area' => 'Area One',
        ]);

        $this->actingAs($admin);
        $supervisor->update(['firstName' => 'New', 'area' => 'Area Two']);

        $log = AuditLog::where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($admin->fullName, $log->actor_name);
        $this->assertSame(User::ROLE_ADMIN, $log->actor_role);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame((string) $supervisor->id, $log->auditable_id);
        $this->assertSame('Old', $log->changes['firstName']['old']);
        $this->assertSame('New', $log->changes['firstName']['new']);
        $this->assertSame('Area One', $log->changes['area']['old']);
        $this->assertSame('Area Two', $log->changes['area']['new']);
        $this->assertNotNull($log->created_at);
        $this->assertStringNotContainsString('Area Two', DB::table('audit_logs')->find($log->id)->changes);

        $this->get(route('dashboard.audit-logs'))
            ->assertOk()
            ->assertSeeText($admin->fullName)
            ->assertSeeText('Admin')
            ->assertSeeText('Updated user account')
            ->assertSeeText('Changed First Name, Area')
            ->assertSeeText('Old')
            ->assertSeeText('New')
            ->assertSeeText('Area One')
            ->assertSeeText('Area Two');
    }

    public function test_infrastructure_records_are_not_added_to_the_website_audit_log(): void
    {
        $user = User::factory()->create();
        $notificationId = (string) Str::uuid();

        (new DatabaseNotification())->forceFill([
            'id' => $notificationId,
            'type' => 'TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test'],
        ])->save();

        $this->assertDatabaseMissing('audit_logs', [
            'auditable_type' => DatabaseNotification::class,
            'auditable_id' => $notificationId,
        ]);
    }

    public function test_only_admins_can_view_the_audit_log_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($admin)->get(route('dashboard.audit-logs'))
            ->assertOk()
            ->assertSeeText('Audit Log')
            ->assertSeeText('Changed by')
            ->assertSeeText('What they did')
            ->assertDontSeeText('IP address')
            ->assertDontSeeText('Changes');

        $this->actingAs($supervisor)->get(route('dashboard.audit-logs'))->assertForbidden();
    }
}
