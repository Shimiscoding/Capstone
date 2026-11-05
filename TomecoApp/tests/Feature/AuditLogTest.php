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

        (new DatabaseNotification)->forceFill([
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

        foreach ([$supervisor, User::factory()->create(['role' => User::ROLE_OFFICER]), User::factory()->create(['role' => User::ROLE_CASHIER])] as $staff) {
            $this->actingAs($staff)->get(route('dashboard.audit-logs'))->assertForbidden();
        }

        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertOk()->assertDontSeeText('Audit Log');
        $this->get(route('dashboard.audit-logs', ['actor_role' => User::ROLE_OFFICER]))->assertForbidden();
    }

    public function test_created_records_show_saved_values_without_empty_comparisons(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AuditLog::query()->delete();
        AuditLog::create([
            'event' => 'created', 'auditable_type' => User::class, 'auditable_id' => '100',
            'subject_label' => 'Created sample account',
            'changes' => [
                'firstName' => ['old' => null, 'new' => 'Created value'],
                'middleName' => ['old' => null, 'new' => null],
                'nameExtension' => ['old' => null, 'new' => ''],
                'attendance_working_days' => ['old' => null, 'new' => []],
                'attendance_restrictions_enabled' => ['old' => null, 'new' => false],
                'sample_count' => ['old' => null, 'new' => 0],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.audit-logs'));
        $response->assertOk()->assertSeeText('Created user account')
            ->assertSeeText('View 3 recorded fields')->assertSeeText('Created value')
            ->assertSeeText('No')->assertSeeText('0')->assertDontSeeText('Empty')
            ->assertDontSeeText('Middle Name')->assertDontSeeText('Name Extension')
            ->assertDontSee('class="audit-old-value"', false)->assertDontSee('&rarr;', false);
    }

    public function test_deleted_records_show_removed_values_without_empty_comparisons(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AuditLog::query()->delete();
        AuditLog::create([
            'event' => 'deleted', 'auditable_type' => User::class, 'auditable_id' => '101',
            'subject_label' => 'Deleted sample account',
            'changes' => ['firstName' => ['old' => 'Removed value', 'new' => null]],
        ]);

        $this->actingAs($admin)->get(route('dashboard.audit-logs'))->assertOk()
            ->assertSeeText('Deleted user account')->assertSeeText('Removed value')
            ->assertSeeText('View 1 recorded field')->assertDontSeeText('Empty')
            ->assertDontSee('&rarr;', false);
    }

    public function test_role_filters_use_the_recorded_actor_and_combine_with_search_and_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $formerOfficer = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        AuditLog::query()->delete();
        foreach ([
            ['officer', 'updated', 'Officer match', 'Archived Enforcer'],
            ['officer', 'created', 'Officer create', 'Archived Enforcer'],
            ['supervisor', 'updated', 'Supervisor match', 'Supervisor Actor'],
            ['admin', 'updated', 'Admin match', 'Administrator Actor'],
        ] as [$role, $event, $subject, $name]) {
            AuditLog::create([
                'user_id' => $role === 'officer' ? $formerOfficer->id : null,
                'actor_name' => $name, 'actor_role' => $role, 'event' => $event,
                'auditable_type' => User::class, 'auditable_id' => (string) $admin->id,
                'subject_label' => $subject, 'changes' => ['area' => ['old' => 'Old area', 'new' => 'New area']],
            ]);
        }

        $this->actingAs($admin)->get(route('dashboard.audit-logs', [
            'actor_role' => 'officer', 'event' => 'updated', 'search' => 'Archived Enforcer',
        ]))->assertOk()->assertSeeText('Officer match')->assertDontSeeText('Officer create')
            ->assertDontSeeText('Supervisor match')->assertDontSeeText('Admin match')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);

        $formerOfficer->delete();
        $this->get(route('dashboard.audit-logs', ['actor_role' => 'officer', 'search' => 'Archived Enforcer']))
            ->assertOk()->assertSeeText('Officer match')->assertSeeText('Officer create');
        $this->get(route('dashboard.audit-logs', ['actor_role' => 'supervisor']))
            ->assertOk()->assertSeeText('Supervisor match')->assertDontSeeText('Officer match');
        $this->get(route('dashboard.audit-logs', ['actor_role' => 'invalid']))
            ->assertOk()->assertSeeText('Officer match')->assertSeeText('Admin match');
        $this->get(route('dashboard.audit-logs', ['actor_role' => 'supervisor', 'search' => 'no matching event']))
            ->assertOk()->assertSeeText('No activity found')->assertSeeText('Reset filters');
    }

    public function test_pagination_keeps_filters_and_clamps_out_of_range_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AuditLog::query()->delete();
        foreach (range(1, 21) as $index) {
            AuditLog::create([
                'actor_role' => User::ROLE_OFFICER, 'event' => 'created',
                'auditable_type' => User::class, 'auditable_id' => (string) $index,
                'subject_label' => 'Matching event '.$index, 'changes' => [],
                'created_at' => now()->startOfDay(),
            ]);
        }

        $filters = ['actor_role' => 'officer', 'event' => 'created', 'search' => 'Matching'];
        $this->actingAs($admin)->get(route('dashboard.audit-logs', $filters))
            ->assertOk()->assertViewHas('logs', function ($logs): bool {
                parse_str(parse_url($logs->nextPageUrl(), PHP_URL_QUERY), $query);

                return $logs->count() === 20 && $logs->total() === 21
                    && $logs->first()->subject_label === 'Matching event 21'
                    && $query === ['actor_role' => 'officer', 'event' => 'created', 'search' => 'Matching', 'page' => '2'];
            });
        $this->get(route('dashboard.audit-logs', $filters + ['page' => 999]))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->currentPage() === 2 && $logs->count() === 1);
    }
}
