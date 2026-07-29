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
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function payments(Request $request, Settings $settings): View
    {
        abort_unless($settings->allows($request->user(), 'view_payments'), 403);
        $isDriver = $request->user()->role === User::ROLE_DRIVER;
        $paymentQuery = Payment::query()
            ->with(['violation', 'user'])
            ->when($isDriver, fn ($query) => $query->where('user_id', $request->user()->id));
        $violationQuery = Violation::query()
            ->when($isDriver, fn ($query) => $query->where('plate_number', $request->user()->plateNumber));

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

    public function index(): View
    {
        return view('dashboard.index', [
            'totalUsers' => User::count(),
            'newUsersThisMonth' => User::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'recentUsers' => User::latest()->take(5)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $this->ensureAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $role = in_array($request->query('role'), User::ROLES, true)
            ? $request->query('role')
            : '';
        $sort = in_array($request->query('sort'), ['latest', 'oldest', 'name_asc', 'name_desc'], true)
            ? $request->query('sort')
            : 'latest';

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('fullName', 'like', "%{$search}%");
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($sort === 'latest', fn ($query) => $query->orderByDesc('created_at'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('created_at'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('fullName'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('fullName'))
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.users.user', compact('users', 'search', 'role', 'sort'));
    }

    public function createUser(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('dashboard.users.user-create');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $attributes = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'badgeNumber' => ['exclude_if:role,'.User::ROLE_DRIVER, 'required', 'string', 'max:50', 'unique:users,badgeNumber'],
            'plateNumber' => ['exclude_unless:role,'.User::ROLE_DRIVER, 'required', 'string', 'max:50', 'unique:users,plateNumber'],
            'phoneNumber' => ['required', 'string', 'max:30', 'unique:users,phoneNumber'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
        ]);

        $attributes['badgeNumber'] = $attributes['role'] === User::ROLE_DRIVER ? null : $attributes['badgeNumber'];
        $attributes['plateNumber'] = $attributes['role'] === User::ROLE_DRIVER ? $attributes['plateNumber'] : null;

        $createdUser = User::create($attributes);
        Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new UserCreatedNotification($createdUser));

        return redirect()->route('dashboard.users')->with('success', 'User account created successfully.');
    }

    public function editUser(Request $request, User $user): View
    {
        $this->ensureAdmin($request);

        return view('dashboard.users.user-create', ['editedUser' => $user]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $attributes = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'badgeNumber' => ['exclude_if:role,'.User::ROLE_DRIVER, 'required', 'string', 'max:50', Rule::unique('users', 'badgeNumber')->ignore($user)],
            'plateNumber' => ['exclude_unless:role,'.User::ROLE_DRIVER, 'required', 'string', 'max:50', Rule::unique('users', 'plateNumber')->ignore($user)],
            'phoneNumber' => ['required', 'string', 'max:30', Rule::unique('users', 'phoneNumber')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['nullable', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
        ]);

        $attributes['badgeNumber'] = $attributes['role'] === User::ROLE_DRIVER ? null : $attributes['badgeNumber'];
        $attributes['plateNumber'] = $attributes['role'] === User::ROLE_DRIVER ? $attributes['plateNumber'] : null;

        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        $user->update($attributes);
        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new UserActivityNotification('updated', $user->fullName, $user->role),
        );

        return redirect()->route('dashboard.users')->with('success', 'User account updated successfully.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        if ($request->user()->is($user)) {
            return redirect()->route('dashboard.users')->with('error', 'You cannot delete your own account.');
        }

        $deletedUserName = $user->fullName;
        $deletedUserRole = $user->role;
        $user->delete();

        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new UserActivityNotification('deleted', $deletedUserName, $deletedUserRole),
        );

        return redirect()->route('dashboard.users')->with('success', 'User account deleted successfully.');
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notifications marked as read.');
    }

    public function markNotificationRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('dashboard'));
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403);
    }
}
