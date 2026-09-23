<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Violation;
use App\Notifications\UserActivityNotification;
use App\Notifications\UserCreatedNotification;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Reports and Analytics
    |--------------------------------------------------------------------------
    */

    public function violationRecords(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $violationType = trim((string) $request->query('violation_type', ''));
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date_from'))
            ? (string) $request->query('date_from')
            : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date_to'))
            ? (string) $request->query('date_to')
            : '';
        $query = Violation::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('violation_type', 'like', "%{$search}%");
                });
            })
            ->when($violationType !== '', fn ($query) => $query->where('violation_type', $violationType))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo));

        $summaryQuery = Violation::query();

        return view('dashboard.violation-records', [
            'violations' => (clone $query)->latest()->paginate(15)->withQueryString(),
            'search' => $search,
            'violationType' => $violationType,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'violationTypes' => Violation::query()->select('violation_type')->distinct()->orderBy('violation_type')->pluck('violation_type'),
            'totalViolations' => (clone $summaryQuery)->count(),
            'violationsThisMonth' => (clone $summaryQuery)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ]);
    }

    public function violationRecord(Violation $violation): View
    {
        $normalize = static fn (?string $value): string => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value));
        $licenseNumber = $normalize($violation->license_number);
        $motoristName = $normalize($violation->full_name);

        $motoristViolations = Violation::query()->with('enforcer')->latest()->get()
            ->filter(function (Violation $record) use ($licenseNumber, $motoristName, $normalize): bool {
                if ($licenseNumber !== '') {
                    return $normalize($record->license_number) === $licenseNumber;
                }

                return $motoristName !== '' && $normalize($record->full_name) === $motoristName;
            })->values();

        return view('dashboard.violation-record-show', compact('violation', 'motoristViolations'));
    }

    public function analytics(Request $request, Settings $settings): View
    {
        abort_if($request->user()->isSupervisor(), 403);
        $period = in_array($request->query('period'), ['daily', 'monthly', 'yearly'], true)
            ? $request->query('period')
            : 'daily';
        $buckets = match ($period) {
            'monthly' => collect(range(11, 0))->map(function (int $monthsAgo): array {
                $date = now()->subMonths($monthsAgo);
                return ['label' => $date->format('M'), 'date' => $date->format('F Y'), 'start' => $date->copy()->startOfMonth(), 'end' => $date->copy()->endOfMonth()];
            }),
            'yearly' => collect(range(4, 0))->map(function (int $yearsAgo): array {
                $date = now()->subYears($yearsAgo);
                return ['label' => $date->format('Y'), 'date' => $date->format('Y'), 'start' => $date->copy()->startOfYear(), 'end' => $date->copy()->endOfYear()];
            }),
            default => collect(range(6, 0))->map(function (int $daysAgo): array {
                $date = now()->subDays($daysAgo);
                return ['label' => $date->format('D'), 'date' => $date->format('M d'), 'start' => $date->copy()->startOfDay(), 'end' => $date->copy()->endOfDay()];
            }),
        };
        $dailyViolations = $buckets->map(fn (array $item): array => $item + [
            'value' => Violation::query()->whereBetween('created_at', [$item['start'], $item['end']])->count(),
        ]);
        $dailyUsers = $buckets->map(fn (array $item): array => $item + [
            'value' => User::query()->whereBetween('created_at', [$item['start'], $item['end']])->count(),
        ]);
        $vehicleTrends = Violation::query()
            ->selectRaw('vehicle_type, COUNT(*) as total')
            ->whereNotNull('vehicle_type')
            ->where('vehicle_type', '!=', '')
            ->whereBetween('created_at', [$buckets->first()['start'], $buckets->last()['end']])
            ->groupBy('vehicle_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('dashboard.analytics', [
            'dailyViolations' => $dailyViolations,
            'dailyUsers' => $dailyUsers,
            'period' => $period,
            'vehicleTrends' => $vehicleTrends,
            'periodViolationTotal' => $dailyViolations->sum('value'),
            'periodUserTotal' => $dailyUsers->sum('value'),
            'periodDescription' => match ($period) { 'monthly' => 'last 12 months', 'yearly' => 'last 5 years', default => 'last 7 days' },
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        if ($request->user()->isSupervisor()) {
            $supervisor = $request->user()->load(['enforcers' => fn ($query) => $query
                ->where('role', User::ROLE_OFFICER)
                ->orderBy('firstName')
                ->orderBy('lastName')]);

            return view('supervisor.index', [
                'supervisor' => $supervisor,
                'todayAttendance' => $supervisor->supervisorAttendances()
                    ->whereDate('attendance_date', today())
                    ->first(),
                'enforcerCount' => $supervisor->enforcers->count(),
                'areaCount' => $supervisor->enforcers->pluck('area')->filter()->unique()->count(),
            ]);
        }

        $violationQuery = Violation::query();
        $activityDays = collect(range(6, 0))->map(function (int $daysAgo): array {
            $date = now()->subDays($daysAgo);
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            return [
                'label' => $date->format('D'),
                'violations' => Violation::query()->whereBetween('created_at', [$start, $end])->count(),
                'users' => User::query()->whereBetween('created_at', [$start, $end])->count(),
            ];
        });
        return view('dashboard.index', [
            'totalUsers' => User::count(),
            'totalTickets' => (clone $violationQuery)->count(),
            'outstandingTickets' => (clone $violationQuery)->where('status', '!=', 'paid')->count(),
            'recentViolations' => (clone $violationQuery)->latest()->take(5)->get(),
            'activityDays' => $activityDays,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | User Listings
    |--------------------------------------------------------------------------
    */

    private function userListing(Request $request, string $role, string $userSection): View
    {
        $this->ensureAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $nameTerms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sort = in_array($request->query('sort'), ['latest', 'oldest', 'name_asc', 'name_desc'], true)
            ? $request->query('sort')
            : 'latest';
        $joinedFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('joined_from'))
            ? (string) $request->query('joined_from')
            : '';
        $joinedTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('joined_to'))
            ? (string) $request->query('joined_to')
            : '';

        $users = User::query()->with('supervisor')
            ->where('role', $role)
            ->when($nameTerms !== [], function ($query) use ($nameTerms) {
                foreach ($nameTerms as $term) {
                    $query->where(function ($nameQuery) use ($term) {
                        $nameQuery->where('firstName', 'like', "%{$term}%")
                            ->orWhere('middleName', 'like', "%{$term}%")
                            ->orWhere('lastName', 'like', "%{$term}%")
                            ->orWhere('nameExtension', 'like', "%{$term}%")
                            ->orWhere('username', 'like', "%{$term}%")
                            ->orWhere('address', 'like', "%{$term}%")
                            ->orWhere('area', 'like', "%{$term}%");
                    });
                }
            })
            ->when($joinedFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $joinedFrom))
            ->when($joinedTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $joinedTo))
            ->when($sort === 'latest', fn ($query) => $query->orderByDesc('created_at'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('created_at'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('firstName')->orderBy('lastName'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('firstName')->orderByDesc('lastName'))
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.users.role-list', compact('users', 'userSection', 'search', 'sort', 'joinedFrom', 'joinedTo'));
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */

    public function attendance(Request $request): View
    {
        $this->ensureAdmin($request);

        $attendanceType = in_array($request->query('type'), ['supervisor', 'enforcer'], true)
            ? $request->query('type')
            : 'supervisor';
        $role = match ($attendanceType) {
            'enforcer' => User::ROLE_OFFICER,
            default => User::ROLE_SUPERVISOR,
        };
        $search = trim((string) $request->query('search', ''));
        $nameTerms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sort = in_array($request->query('sort'), ['latest', 'oldest', 'name_asc', 'name_desc'], true)
            ? $request->query('sort')
            : 'latest';
        return view('supervisor.attendance', [
            'staffMembers' => User::query()
                ->where('role', $role)
                ->when($nameTerms !== [], function ($query) use ($nameTerms) {
                    foreach ($nameTerms as $term) {
                        $query->where(function ($nameQuery) use ($term) {
                            $nameQuery->where('firstName', 'like', "%{$term}%")
                                ->orWhere('middleName', 'like', "%{$term}%")
                                ->orWhere('lastName', 'like', "%{$term}%")
                                ->orWhere('nameExtension', 'like', "%{$term}%")
                                ->orWhere('username', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                    }
                })
                ->withCount(['supervisorAttendances', 'enforcerAttendances'])
                ->when($sort === 'latest', fn ($query) => $query->orderByDesc('created_at'))
                ->when($sort === 'oldest', fn ($query) => $query->orderBy('created_at'))
                ->when($sort === 'name_asc', fn ($query) => $query->orderBy('firstName')->orderBy('lastName'))
                ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('firstName')->orderByDesc('lastName'))
                ->paginate(10)
                ->withQueryString(),
            'search' => $search,
            'sort' => $sort,
            'attendanceType' => $attendanceType,
        ]);
    }

    public function supervisorAttendance(Request $request, User $user): View
    {
        $this->ensureAdmin($request);
        abort_unless($user->role === User::ROLE_SUPERVISOR, 404);

        return view('supervisor.attendance-show', [
            'supervisor' => $user,
            'attendances' => $user->supervisorAttendances()->latest('attendance_date')->paginate(20)->withQueryString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Role-Based User Management
    |--------------------------------------------------------------------------
    */

    public function supervisors(Request $request): View
    {
        return $this->userListing($request, User::ROLE_SUPERVISOR, 'supervisors');
    }

    public function enforcers(Request $request): View
    {
        return $this->userListing($request, User::ROLE_OFFICER, 'enforcers');
    }

    public function admins(Request $request): View
    {
        return $this->userListing($request, User::ROLE_ADMIN, 'admins');
    }

    public function createSupervisor(Request $request): View
    {
        return $this->createUser($request)->with('fixedRole', User::ROLE_SUPERVISOR)->with('userSection', 'supervisors');
    }

    public function showSupervisor(Request $request, User $user): View
    {
        $this->ensureAdmin($request);
        abort_unless($user->role === User::ROLE_SUPERVISOR, 404);

        $user->load(['enforcers' => fn ($query) => $query
            ->where('role', User::ROLE_OFFICER)
            ->orderBy('firstName')
            ->orderBy('lastName')]);

        return view('supervisor.show', [
            'supervisor' => $user,
            'availableEnforcers' => User::where('role', User::ROLE_OFFICER)
                ->whereNull('supervisor_id')
                ->orderBy('firstName')
                ->orderBy('lastName')
                ->get(),
        ]);
    }

    public function showEnforcer(Request $request, User $user): View
    {
        $this->ensureAdmin($request);
        abort_unless($user->role === User::ROLE_OFFICER, 404);

        return view('dashboard.users.staff-show', [
            'staffUser' => $user->load('supervisor'),
            'section' => 'enforcers',
            'sectionLabel' => 'Enforcer',
        ]);
    }

    public function showAdmin(Request $request, User $user): View
    {
        $this->ensureAdmin($request);
        abort_unless($user->role === User::ROLE_ADMIN, 404);

        return view('dashboard.users.staff-show', [
            'staffUser' => $user,
            'section' => 'admins',
            'sectionLabel' => 'Admin',
        ]);
    }

    public function createEnforcer(Request $request): View
    {
        return $this->createUser($request)->with('fixedRole', User::ROLE_OFFICER)->with('userSection', 'enforcers');
    }

    public function createAdmin(Request $request): View
    {
        return $this->createUser($request)->with('fixedRole', User::ROLE_ADMIN)->with('userSection', 'admins');
    }

    /*
    |--------------------------------------------------------------------------
    | User Account CRUD
    |--------------------------------------------------------------------------
    */

    public function createUser(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('dashboard.users.user-create', [
            'supervisors' => User::where('role', User::ROLE_SUPERVISOR)->orderBy('firstName')->orderBy('lastName')->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $attributes = $request->validate([
            'fullName' => ['sometimes', 'string', 'max:255'],
            'firstName' => ['required_without:fullName', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required_without:fullName', 'string', 'max:100'],
            'nameExtension' => ['nullable', 'string', 'max:20'],
            'username' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'address' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['exclude_unless:role,'.User::ROLE_OFFICER, 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_SUPERVISOR)],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phoneNumber'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'account_status' => ['nullable', Rule::in(User::ACCOUNT_STATUSES)],
            'password' => ['required', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
            'user_section' => ['nullable', Rule::in(['supervisors', 'enforcers', 'admins'])],
        ]);

        $attributes['supervisor_id'] = $attributes['role'] === User::ROLE_OFFICER ? ($attributes['supervisor_id'] ?? null) : null;

        $createdUser = User::create($attributes);
        Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new UserCreatedNotification($createdUser));

        $destination = match ($attributes['user_section'] ?? null) {
            'supervisors' => 'dashboard.users.supervisors',
            'enforcers' => 'dashboard.users.enforcers',
            'admins' => 'dashboard.users.admins',
            default => match ($createdUser->role) {
                User::ROLE_OFFICER => 'dashboard.users.enforcers',
                User::ROLE_ADMIN => 'dashboard.users.admins',
                default => 'dashboard.users.supervisors',
            },
        };

        return redirect()->route($destination)->with('success', 'User account created successfully.');
    }

    public function editUser(Request $request, User $user): View
    {
        $this->ensureAdmin($request);

        $roleSection = match ($user->role) {
            User::ROLE_SUPERVISOR => 'supervisors',
            User::ROLE_OFFICER => 'enforcers',
            User::ROLE_ADMIN => 'admins',
            default => null,
        };
        $requestedSection = $request->query('from');
        $userSection = in_array($requestedSection, ['supervisors', 'enforcers', 'admins'], true)
            ? $requestedSection
            : $roleSection;
        $returnTo = $request->query('return_to') === 'detail' ? 'detail' : null;

        return view('dashboard.users.user-create', [
            'editedUser' => $user,
            'supervisors' => User::where('role', User::ROLE_SUPERVISOR)->orderBy('firstName')->orderBy('lastName')->get(),
            'userSection' => $userSection,
            'returnTo' => $returnTo,
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $attributes = $request->validate([
            'fullName' => ['sometimes', 'string', 'max:255'],
            'firstName' => ['required_without:fullName', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required_without:fullName', 'string', 'max:100'],
            'nameExtension' => ['nullable', 'string', 'max:20'],
            'username' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'address' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['exclude_unless:role,'.User::ROLE_OFFICER, 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_SUPERVISOR)],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phoneNumber')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'account_status' => ['sometimes', Rule::in(User::ACCOUNT_STATUSES)],
            'password' => ['nullable', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
            'user_section' => ['nullable', Rule::in(['supervisors', 'enforcers', 'admins'])],
            'return_to' => ['nullable', Rule::in(['detail'])],
        ]);

        $attributes['supervisor_id'] = $attributes['role'] === User::ROLE_OFFICER ? ($attributes['supervisor_id'] ?? null) : null;

        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        $user->update($attributes);
        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new UserActivityNotification('updated', $user->fullName, $user->role),
        );

        if (($attributes['return_to'] ?? null) === 'detail') {
            $destination = match ($user->role) {
                User::ROLE_SUPERVISOR => 'dashboard.users.supervisors.show',
                User::ROLE_OFFICER => 'dashboard.users.enforcers.show',
                User::ROLE_ADMIN => 'dashboard.users.admins.show',
                default => 'dashboard.users.supervisors',
            };

            return redirect()->route($destination, $user)->with('success', 'User account updated successfully.');
        }

        $destination = match ($attributes['user_section'] ?? null) {
            'supervisors' => 'dashboard.users.supervisors',
            'enforcers' => 'dashboard.users.enforcers',
            'admins' => 'dashboard.users.admins',
            default => match ($user->role) {
                User::ROLE_OFFICER => 'dashboard.users.enforcers',
                User::ROLE_ADMIN => 'dashboard.users.admins',
                default => 'dashboard.users.supervisors',
            },
        };

        return redirect()->route($destination)->with('success', 'User account updated successfully.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $destination = match ($request->input('user_section')) {
            'supervisors' => 'dashboard.users.supervisors',
            'enforcers' => 'dashboard.users.enforcers',
            'admins' => 'dashboard.users.admins',
            default => match ($user->role) {
                User::ROLE_OFFICER => 'dashboard.users.enforcers',
                User::ROLE_ADMIN => 'dashboard.users.admins',
                default => 'dashboard.users.supervisors',
            },
        };

        if ($request->user()->is($user)) {
            return redirect()->route($destination)->with('error', 'You cannot delete your own account.');
        }

        $deletedUserName = $user->fullName;
        $deletedUserRole = $user->role;
        $user->delete();

        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new UserActivityNotification('deleted', $deletedUserName, $deletedUserRole),
        );

        return redirect()->route($destination)->with('success', 'User account deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function notificationFeed(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->take(8)->get();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'is_unread' => $notification->read_at === null,
                'created_at' => $notification->created_at->diffForHumans(),
                'read_url' => route('dashboard.notifications.read-one', $notification->id),
            ])->values(),
        ]);
    }

    public function markNotificationRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $destination = $notification->data['url'] ?? route('dashboard', absolute: false);

        // Older notifications may contain the host that created them. Keep only
        // their local path so LAN clients stay on the host they are using.
        if (is_string($destination) && filter_var($destination, FILTER_VALIDATE_URL)) {
            $parts = parse_url($destination);
            $destination = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return redirect()->to($destination);
    }

    public function deleteNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications' => ['required', 'array', 'min:1'],
            'notifications.*' => ['required', 'string'],
        ]);

        $deleted = $request->user()->notifications()
            ->whereIn('id', $validated['notifications'])
            ->delete();

        return back()->with('success', $deleted.' selected notification(s) deleted.');
    }

    public function deleteAllNotifications(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back()->with('success', 'All notifications deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Helpers
    |--------------------------------------------------------------------------
    */

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403);
    }
}
