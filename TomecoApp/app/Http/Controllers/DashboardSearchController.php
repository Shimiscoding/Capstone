<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ViolationRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSearchController extends Controller
{
    public function __invoke(Request $request, ViolationRecords $records): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer?->isAdmin() || $viewer?->isSupervisor(), 403);
        $input = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $query = trim($input['q'] ?? '');
        if ($query === '') {
            return response()->json(['results' => []])->header('Cache-Control', 'private, no-store');
        }

        $pages = [
            ['Home', 'Dashboard overview', 'dashboard'],
            ['Area Assignments', 'Assign operational areas to enforcers', 'area-assignments.index'],
            ['Location Monitoring', 'View enforcer locations', 'location-monitoring.index'],
            ['My Profile', 'Account and profile settings', 'profile.edit'],
        ];
        if ($viewer->isAdmin()) {
            $pages = array_merge($pages, [
                ['Analytics', 'Reports and insights', 'dashboard.analytics'],
                ['Violation Records', 'Tickets, motorists, plates and fines', 'dashboard.violation-records'],
                ['Supervisors', 'Manage supervisor accounts', 'dashboard.users.supervisors'],
                ['Enforcers', 'Manage enforcer accounts', 'dashboard.users.enforcers'],
                ['Admins', 'Manage administrator accounts', 'dashboard.users.admins'],
                ['Attendance', 'Enforcer attendance and schedules', 'dashboard.attendance'],
                ['Audit Log', 'Recorded administrator and staff activity', 'dashboard.audit-logs'],
                ['Settings', 'General, notifications and security', 'dashboard.settings'],
            ]);
        } else {
            $pages[] = ['My Team', 'Manage your assigned enforcers', 'supervisor.team'];
            $pages[] = ['Attendance', 'Review your team attendance', 'supervisor.enforcers.attendance.index'];
        }

        $terms = preg_split('/\s+/', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        $results = collect($pages)->filter(function ($page) use ($terms) {
            $text = mb_strtolower($page[0].' '.$page[1]);

            return collect($terms)->every(fn ($term) => str_contains($text, $term));
        })->take(8)->map(fn ($page) => [
            'kind' => 'Pages', 'label' => $page[0], 'description' => $page[1], 'url' => route($page[2]),
        ])->values();

        if (mb_strlen($query) >= 2) {
            $staff = User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERVISOR, User::ROLE_OFFICER])
                ->when($viewer->isSupervisor(), fn ($users) => $users->where('role', User::ROLE_OFFICER)->where('supervisor_id', $viewer->id));
            foreach ($terms as $term) {
                $staff->where(function ($users) use ($term) {
                    foreach (['firstName', 'middleName', 'lastName', 'nameExtension', 'username', 'email'] as $field) {
                        $users->orWhere($field, 'like', '%'.$term.'%');
                    }
                });
            }
            foreach ($staff->orderBy('firstName')->orderBy('lastName')->orderBy('id')->limit(5)->get() as $person) {
                $route = $viewer->isSupervisor() ? 'supervisor.enforcers.attendance' : match ($person->role) {
                    User::ROLE_SUPERVISOR => 'dashboard.users.supervisors.show',
                    User::ROLE_OFFICER => 'dashboard.users.enforcers.show',
                    default => 'dashboard.users.admins.show',
                };
                $results->push([
                    'kind' => 'Staff', 'label' => $person->fullName,
                    'description' => ($person->isOfficer() ? 'Enforcer' : ucfirst($person->role)).($person->username ? ' · @'.$person->username : ''),
                    'url' => route($route, $person),
                ]);
            }

            if ($viewer->isAdmin()) {
                // Reuse the existing encrypted-name/plate search rather than querying ciphertext.
                $recordRequest = $request->duplicate(['search' => $query, 'period' => 'all', 'page' => 1]);
                foreach ($records->build($recordRequest)['violations']->take(5) as $ticket) {
                    $results->push([
                        'kind' => 'Violation records', 'label' => 'Ticket #'.$ticket->id,
                        'description' => $ticket->full_name.' · '.($ticket->plate_number ?: 'No plate recorded'),
                        'url' => route('dashboard.violation-records.show', $ticket),
                    ]);
                }
            }
        }

        return response()->json(['results' => $results->values()])->header('Cache-Control', 'private, no-store');
    }
}
