<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\UserActivityNotification;
use App\Notifications\UserCreatedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function payments(): View
    {
        return view('dashboard.payment');
    }

    public function impounding(): View
    {
        $vehicles = collect([
            ['reference' => 'IMP-2026-0042', 'owner' => 'Juan Dela Cruz', 'vehicle' => 'Honda Click 125i', 'plate' => '123 ABC', 'violation' => 'Illegal parking', 'date' => 'Jul 20, 2026', 'location' => 'Main Yard', 'status' => 'Impounded'],
            ['reference' => 'IMP-2026-0041', 'owner' => 'Maria Santos', 'vehicle' => 'Toyota Vios', 'plate' => 'NCR 4821', 'violation' => 'Road obstruction', 'date' => 'Jul 19, 2026', 'location' => 'Main Yard', 'status' => 'For release'],
            ['reference' => 'IMP-2026-0040', 'owner' => 'Carlo Reyes', 'vehicle' => 'Yamaha Mio i125', 'plate' => '456 DEF', 'violation' => 'No parking zone', 'date' => 'Jul 18, 2026', 'location' => 'Satellite Yard A', 'status' => 'Released'],
            ['reference' => 'IMP-2026-0039', 'owner' => 'Angela Lim', 'vehicle' => 'Mitsubishi Mirage', 'plate' => 'ABC 9087', 'violation' => 'Abandoned vehicle', 'date' => 'Jul 17, 2026', 'location' => 'Main Yard', 'status' => 'Impounded'],
            ['reference' => 'IMP-2026-0038', 'owner' => 'Miguel Garcia', 'vehicle' => 'Suzuki Raider 150', 'plate' => '789 GHI', 'violation' => 'Traffic obstruction', 'date' => 'Jul 16, 2026', 'location' => 'Satellite Yard B', 'status' => 'Released'],
        ]);

        return view('dashboard.impounding', compact('vehicles'));
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
            'password' => ['required', 'confirmed', 'min:8'],
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
            'password' => ['nullable', 'confirmed', 'min:8'],
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

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403);
    }
}
