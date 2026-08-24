<?php

namespace App\Http\Controllers;

use App\Models\ImpoundedVehicle;
use App\Models\Payment;
use App\Models\User;
use App\Models\Violation;
use App\Notifications\UserActivityNotification;
use App\Notifications\UserCreatedNotification;
use App\Services\ImpoundedVehicleImporter;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function driverViolators(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['paid', 'unpaid', 'pending'], true)
            ? $request->query('status')
            : '';
        $isDriver = $request->user()->isDriver();

        $query = Violation::query()
            ->with('user')
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('violation_type', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('firstName', 'like', "%{$search}%")
                            ->orWhere('middleName', 'like', "%{$search}%")
                            ->orWhere('lastName', 'like', "%{$search}%")
                            ->orWhere('nameExtension', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status));

        $summaryQuery = Violation::query()
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id));

        return view('dashboard.driver-violators', [
            'violations' => (clone $query)->latest()->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'totalViolations' => (clone $summaryQuery)->count(),
            'unpaidViolations' => (clone $summaryQuery)->where('status', '!=', 'paid')->count(),
            'totalFines' => (clone $summaryQuery)->sum('fine_amount'),
            'isDriverView' => $isDriver,
        ]);
    }

    public function payments(Request $request, Settings $settings): View
    {
        abort_unless($settings->allows($request->user(), 'view_payments'), 403);
        $isDriver = $request->user()->role === User::ROLE_DRIVER;
        $paymentQuery = Payment::query()
            ->with(['violation', 'user'])
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id));
        $violationQuery = Violation::query()
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id));

        $paid = (clone $paymentQuery)->where('status', 'paid');
        $pending = (clone $paymentQuery)->where('status', 'pending');
        $dailyCollections = collect(range(6, 0))->map(function (int $daysAgo) use ($paymentQuery): array {
            $day = now()->subDays($daysAgo);

            return [
                'label' => $day->format('D'),
                'amount' => (clone $paymentQuery)->where('status', 'paid')->whereDate('paid_at', $day)->sum('amount'),
            ];
        });
        $maxDaily = max(1, (int) $dailyCollections->max('amount'));

        return view('dashboard.payment', [
            'transactions' => (clone $paymentQuery)->latest()->take(20)->get(),
            'unpaidViolations' => (clone $violationQuery)->where('status', '!=', 'paid')->latest()->get(),
            'totalCollected' => (clone $paid)->sum('amount'),
            'successfulCount' => (clone $paid)->count(),
            'pendingCount' => (clone $pending)->count(),
            'pendingAmount' => (clone $pending)->sum('amount'),
            'failedCount' => (clone $paymentQuery)->where('status', 'failed')->count(),
            'dailyCollections' => $dailyCollections,
            'maxDaily' => $maxDaily,
        ]);
    }

    public function impounding(): View
    {
        $vehicles = ImpoundedVehicle::latest('impounded_at')->get();

        return view('dashboard.impounding', [
            'vehicles' => $vehicles,
            'vehicleTypes' => ImpoundedVehicle::query()->distinct()->orderBy('type')->pluck('type'),
            'availableYears' => ImpoundedVehicle::query()->selectRaw('YEAR(impounded_at) as year')->distinct()->orderBy('year')->pluck('year'),
        ]);
    }

    public function exportImpounding(Request $request, Settings $settings): Response
    {
        abort_unless($settings->allows($request->user(), 'export'), 403);
        $filters = $request->validate([
            'vehicle_type' => ['nullable', 'string', Rule::in(['Car', 'Motorcycle'])],
            'year_from' => ['required', 'integer', 'between:2000,2100'],
            'year_to' => ['required', 'integer', 'between:2000,2100', 'gte:year_from'],
        ]);

        $vehicles = ImpoundedVehicle::query()
            ->when($filters['vehicle_type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->whereYear('impounded_at', '>=', $filters['year_from'])
            ->whereYear('impounded_at', '<=', $filters['year_to'])
            ->orderByDesc('impounded_at')
            ->get();
        $filename = 'impounded-vehicles-'.$filters['year_from'].'-'.$filters['year_to'].'.xls';

        return response(view('dashboard.exports.impounded-vehicles', compact('vehicles', 'filters'))->render(), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    public function importImpounding(Request $request, ImpoundedVehicleImporter $importer, Settings $settings): RedirectResponse
    {
        abort_unless($settings->allows($request->user(), 'import'), 403);
        $request->validate([
            'excel_file' => [
                'required',
                'file',
                'max:'.((int) $settings->get('data.max_upload_mb', 5) * 1024),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! in_array(strtolower($value->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) {
                        $fail('The Excel file must have an .xlsx, .xls, or .csv extension.');
                    }
                },
            ],
        ]);

        try {
            $result = $importer->import($request->file('excel_file'));
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('dashboard.impounding')->with('error', 'Import failed: '.$exception->getMessage());
        }

        $message = "Import complete: {$result['imported']} added and {$result['updated']} updated.";

        return to_route('dashboard.impounding')
            ->with('success', $message)
            ->with('import_errors', $result['errors']);
    }

    public function index(Request $request): View
    {
        if ($request->user()->isSupervisor()) {
            $supervisor = $request->user()->load(['enforcers' => fn ($query) => $query
                ->where('role', User::ROLE_OFFICER)
                ->orderBy('firstName')
                ->orderBy('lastName')]);

            return view('dashboard.supervisor', [
                'supervisor' => $supervisor,
                'enforcerCount' => $supervisor->enforcers->count(),
                'barangayCount' => $supervisor->enforcers->pluck('barangay')->filter()->unique()->count(),
                'areaCount' => $supervisor->enforcers->pluck('area')->filter()->unique()->count(),
                'todayAttendance' => $supervisor->supervisorAttendances()->whereDate('attendance_date', today())->first(),
                'recentAttendances' => $supervisor->supervisorAttendances()->latest('attendance_date')->take(7)->get(),
            ]);
        }

        $isDriver = $request->user()->isDriver();
        $violationQuery = Violation::query()
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id));
        $impoundedQuery = ImpoundedVehicle::query()
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id));

        return view('dashboard.index', [
            'totalUsers' => User::count(),
            'totalTickets' => (clone $violationQuery)->count(),
            'outstandingTickets' => (clone $violationQuery)->where('status', '!=', 'paid')->count(),
            'impoundedVehicles' => (clone $impoundedQuery)->where('status', '!=', 'Released')->count(),
            'forReleaseVehicles' => (clone $impoundedQuery)->where('status', 'For release')->count(),
            'recentViolations' => (clone $violationQuery)->with('user')->latest()->take(5)->get(),
            'recentImpoundedVehicles' => (clone $impoundedQuery)->latest('impounded_at')->take(5)->get(),
            'isDriverDashboard' => $isDriver,
            'supervisorAttendances' => $request->user()->isAdmin()
                ? \App\Models\SupervisorAttendance::with('user')->latest('attendance_date')->latest('time_in')->take(20)->get()
                : collect(),
        ]);
    }

    public function users(Request $request): View
    {
        $this->ensureAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $nameTerms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $role = in_array($request->query('role'), User::ROLES, true)
            ? $request->query('role')
            : '';
        $sort = in_array($request->query('sort'), ['latest', 'oldest', 'name_asc', 'name_desc'], true)
            ? $request->query('sort')
            : 'latest';

        $users = User::query()->with('supervisor')
            ->when($nameTerms !== [], function ($query) use ($nameTerms) {
                foreach ($nameTerms as $term) {
                    $query->where(function ($nameQuery) use ($term) {
                        $nameQuery->where('firstName', 'like', "%{$term}%")
                            ->orWhere('middleName', 'like', "%{$term}%")
                            ->orWhere('lastName', 'like', "%{$term}%")
                            ->orWhere('nameExtension', 'like', "%{$term}%")
                            ->orWhere('username', 'like', "%{$term}%")
                            ->orWhere('address', 'like', "%{$term}%")
                            ->orWhere('area', 'like', "%{$term}%")
                            ->orWhere('barangay', 'like', "%{$term}%");
                    });
                }
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($sort === 'latest', fn ($query) => $query->orderByDesc('created_at'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('created_at'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('firstName')->orderBy('lastName'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('firstName')->orderByDesc('lastName'))
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.users.user', compact('users', 'search', 'role', 'sort'));
    }

    public function attendance(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('dashboard.attendance', [
            'attendances' => \App\Models\SupervisorAttendance::with('user')
                ->latest('attendance_date')
                ->latest('time_in')
                ->paginate(20),
        ]);
    }

    public function supervisors(Request $request): View
    {
        $request->query->set('role', User::ROLE_SUPERVISOR);
        $view = $this->users($request);
        $view->with('userSection', 'supervisors');

        return $view;
    }

    public function enforcers(Request $request): View
    {
        $request->query->set('role', User::ROLE_OFFICER);
        $view = $this->users($request);
        $view->with('userSection', 'enforcers');

        return $view;
    }

    public function admins(Request $request): View
    {
        $request->query->set('role', User::ROLE_ADMIN);
        $view = $this->users($request);
        $view->with('userSection', 'admins');

        return $view;
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

        return view('dashboard.users.supervisor-show', ['supervisor' => $user]);
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
            'barangay' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['exclude_unless:role,'.User::ROLE_OFFICER, 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_SUPERVISOR)],
            'plateNumber' => ['exclude_unless:role,'.User::ROLE_DRIVER, 'required', 'string', 'max:50', 'unique:users,plateNumber'],
            'driverLicense' => ['exclude_unless:role,'.User::ROLE_DRIVER, 'nullable', 'string', 'max:255', 'unique:users,driverLicense'],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phoneNumber'],
            'email' => ['nullable', 'required_unless:demo_account,1', 'email', 'max:255', 'unique:users,email'],
            'demo_account' => ['nullable', 'boolean'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
            'user_section' => ['nullable', Rule::in(['supervisors', 'enforcers', 'admins'])],
        ]);

        $attributes['plateNumber'] = $attributes['role'] === User::ROLE_DRIVER ? $attributes['plateNumber'] : null;
        $attributes['driverLicense'] = $attributes['role'] === User::ROLE_DRIVER ? ($attributes['driverLicense'] ?? null) : null;
        $attributes['supervisor_id'] = $attributes['role'] === User::ROLE_OFFICER ? ($attributes['supervisor_id'] ?? null) : null;

        if ($request->boolean('demo_account')) {
            $demoName = filled($attributes['username'] ?? null)
                ? Str::lower($attributes['username'])
                : 'demo-user-'.Str::lower(Str::random(8));
            $attributes['email'] = $demoName.'@demo.tomeco.local';
        }

        $createdUser = User::create($attributes);
        if ($request->boolean('demo_account')) {
            $createdUser->forceFill(['email_verified_at' => now()])->save();
        }
        Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new UserCreatedNotification($createdUser));

        $destination = match ($attributes['user_section'] ?? null) {
            'supervisors' => 'dashboard.users.supervisors',
            'enforcers' => 'dashboard.users.enforcers',
            'admins' => 'dashboard.users.admins',
            default => 'dashboard.users',
        };

        return redirect()->route($destination)->with('success', 'User account created successfully.');
    }

    public function editUser(Request $request, User $user): View
    {
        $this->ensureAdmin($request);

        $userSection = match ($user->role) {
            User::ROLE_SUPERVISOR => 'supervisors',
            User::ROLE_OFFICER => 'enforcers',
            User::ROLE_ADMIN => 'admins',
            default => null,
        };

        return view('dashboard.users.user-create', [
            'editedUser' => $user,
            'supervisors' => User::where('role', User::ROLE_SUPERVISOR)->orderBy('firstName')->orderBy('lastName')->get(),
            'userSection' => $userSection,
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
            'barangay' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['exclude_unless:role,'.User::ROLE_OFFICER, 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_SUPERVISOR)],
            'plateNumber' => ['exclude_unless:role,'.User::ROLE_DRIVER, 'required', 'string', 'max:50', Rule::unique('users', 'plateNumber')->ignore($user)],
            'driverLicense' => ['exclude_unless:role,'.User::ROLE_DRIVER, 'nullable', 'string', 'max:255', Rule::unique('users', 'driverLicense')->ignore($user)],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phoneNumber')->ignore($user)],
            'email' => ['nullable', 'required_unless:demo_account,1', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'demo_account' => ['nullable', 'boolean'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['nullable', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
            'user_section' => ['nullable', Rule::in(['supervisors', 'enforcers', 'admins'])],
        ]);

        $attributes['plateNumber'] = $attributes['role'] === User::ROLE_DRIVER ? $attributes['plateNumber'] : null;
        $attributes['driverLicense'] = $attributes['role'] === User::ROLE_DRIVER ? ($attributes['driverLicense'] ?? null) : null;
        $attributes['supervisor_id'] = $attributes['role'] === User::ROLE_OFFICER ? ($attributes['supervisor_id'] ?? null) : null;

        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        if ($request->boolean('demo_account')) {
            $demoName = filled($attributes['username'] ?? null)
                ? Str::lower($attributes['username'])
                : 'demo-user-'.$user->id;
            $attributes['email'] = $demoName.'@demo.tomeco.local';
        }

        $user->update($attributes);
        if ($request->boolean('demo_account')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new UserActivityNotification('updated', $user->fullName, $user->role),
        );

        $destination = match ($attributes['user_section'] ?? null) {
            'supervisors' => 'dashboard.users.supervisors',
            'enforcers' => 'dashboard.users.enforcers',
            'admins' => 'dashboard.users.admins',
            default => 'dashboard.users',
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
            default => 'dashboard.users',
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

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function markNotificationRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('dashboard'));
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

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403);
    }
}
