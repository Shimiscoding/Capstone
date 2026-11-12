<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\UserActivityNotification;
use App\Notifications\UserCreatedNotification;
use App\Services\ProfilePhoto;
use App\Services\Settings;
use App\Support\StaffDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    private const ROLE_SECTIONS = [
        User::ROLE_SUPERVISOR => 'supervisors',
        User::ROLE_OFFICER => 'enforcers',
        User::ROLE_ADMIN => 'admins',
    ];

    public function supervisors(Request $request): View
    {
        return $this->userListing($request, User::ROLE_SUPERVISOR);
    }

    public function enforcers(Request $request): View
    {
        return $this->userListing($request, User::ROLE_OFFICER);
    }

    public function admins(Request $request): View
    {
        return $this->userListing($request, User::ROLE_ADMIN);
    }

    public function createSupervisor(Request $request): View
    {
        return $this->createStaff($request, User::ROLE_SUPERVISOR);
    }

    public function showSupervisor(Request $request, User $user): View
    {
        return $this->showStaff($request, $user, User::ROLE_SUPERVISOR, 'Supervisor');
    }

    public function showEnforcer(Request $request, User $user): View
    {
        return $this->showStaff($request, $user, User::ROLE_OFFICER, 'Enforcer');
    }

    public function showAdmin(Request $request, User $user): View
    {
        return $this->showStaff($request, $user, User::ROLE_ADMIN, 'Admin');
    }

    public function createEnforcer(Request $request): View
    {
        return $this->createStaff($request, User::ROLE_OFFICER);
    }

    public function createAdmin(Request $request): View
    {
        return $this->createStaff($request, User::ROLE_ADMIN);
    }

    public function createUser(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('dashboard.users.user-create', [
            'supervisors' => $this->supervisorOptions(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $attributes = $request->validate($this->accountRules());

        $attributes['supervisor_id'] = $attributes['role'] === User::ROLE_OFFICER ? ($attributes['supervisor_id'] ?? null) : null;
        if ($attributes['role'] !== User::ROLE_OFFICER) {
            $attributes['area'] = null;
        }

        $createdUser = app(ProfilePhoto::class)->save(new User, $attributes, $request);
        Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new UserCreatedNotification($createdUser));

        $destination = $this->listingRoute($createdUser, $attributes['user_section'] ?? null);

        return redirect()->route($destination)->with('success', 'User account created successfully.');
    }

    public function editUser(Request $request, User $user): View
    {
        $this->ensureAdmin($request);

        $requestedSection = $request->query('from');
        $userSection = in_array($requestedSection, self::ROLE_SECTIONS, true)
            ? $requestedSection
            : (self::ROLE_SECTIONS[$user->role] ?? null);
        $returnTo = $request->query('return_to') === 'detail' ? 'detail' : null;

        return view('dashboard.users.user-create', [
            'editedUser' => $user,
            'supervisors' => $this->supervisorOptions(),
            'userSection' => $userSection,
            'returnTo' => $returnTo,
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $attributes = $request->validate([
            ...$this->accountRules($user),
            'return_to' => ['nullable', Rule::in(['detail'])],
        ]);

        $attributes['supervisor_id'] = $attributes['role'] === User::ROLE_OFFICER ? ($attributes['supervisor_id'] ?? null) : null;
        if ($attributes['role'] !== User::ROLE_OFFICER) {
            $attributes['area'] = null;
            $attributes['additional_areas'] = null;
        }

        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        app(ProfilePhoto::class)->save($user, $attributes, $request);
        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new UserActivityNotification('updated', $user->fullName, $user->role),
        );

        if (($attributes['return_to'] ?? null) === 'detail') {
            $section = self::ROLE_SECTIONS[$user->role] ?? null;
            $destination = $section === null ? 'dashboard.users.supervisors' : "dashboard.users.{$section}.show";

            return redirect()->route($destination, $user)->with('success', 'User account updated successfully.');
        }

        $destination = $this->listingRoute($user, $attributes['user_section'] ?? null);

        return redirect()->route($destination)->with('success', 'User account updated successfully.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $destination = $this->listingRoute($user, $request->input('user_section'));

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

    private function userListing(Request $request, string $role): View
    {
        $this->ensureAdmin($request);

        $directory = new StaffDirectory($request);
        $joinedFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('joined_from'))
            ? (string) $request->query('joined_from')
            : '';
        $joinedTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('joined_to'))
            ? (string) $request->query('joined_to')
            : '';

        $query = User::query()->with('supervisor')
            ->where('role', $role)
            ->when($joinedFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $joinedFrom))
            ->when($joinedTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $joinedTo));
        $directory->filter($query, 'address', 'area');

        return view('dashboard.users.role-list', [
            'users' => $directory->order($query)->paginate(10)->withQueryString(),
            'userSection' => self::ROLE_SECTIONS[$role],
            'search' => $directory->search,
            'sort' => $directory->sort,
            'joinedFrom' => $joinedFrom,
            'joinedTo' => $joinedTo,
        ]);
    }

    private function createStaff(Request $request, string $role): View
    {
        return $this->createUser($request)->with('fixedRole', $role)->with('userSection', self::ROLE_SECTIONS[$role]);
    }

    private function showStaff(Request $request, User $user, string $role, string $sectionLabel): View
    {
        $this->ensureAdmin($request);
        abort_unless($user->role === $role, 404);

        return view('dashboard.users.staff-show', [
            'staffUser' => $role === User::ROLE_OFFICER ? $user->load('supervisor') : $user,
            'section' => self::ROLE_SECTIONS[$role],
            'sectionLabel' => $sectionLabel,
        ]);
    }

    private function listingRoute(User $user, mixed $requestedSection): string
    {
        $section = in_array($requestedSection, self::ROLE_SECTIONS, true)
            ? $requestedSection
            : (self::ROLE_SECTIONS[$user->role] ?? 'supervisors');

        return "dashboard.users.{$section}";
    }

    private function supervisorOptions(): Collection
    {
        return User::where('role', User::ROLE_SUPERVISOR)->orderBy('firstName')->orderBy('lastName')->get();
    }

    private function accountRules(?User $user = null): array
    {
        $unique = fn (string $column) => Rule::unique('users', $column)->ignore($user?->getKey());

        return [
            ...ProfilePhoto::rules(),
            'fullName' => ['sometimes', 'string', 'max:255'],
            'firstName' => ['required_without:fullName', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required_without:fullName', 'string', 'max:100'],
            'nameExtension' => ['nullable', 'string', 'max:20'],
            'username' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', $unique('username')],
            'address' => ['nullable', 'string', 'max:255'],
            'area' => ['exclude'],
            'supervisor_id' => ['exclude_unless:role,'.User::ROLE_OFFICER, 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_SUPERVISOR)],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', $unique('phoneNumber')],
            'email' => ['required', 'email', 'max:255', $unique('email')],
            'role' => ['required', Rule::in(User::ROLES)],
            'account_status' => [$user === null ? 'nullable' : 'sometimes', Rule::in(User::ACCOUNT_STATUSES)],
            'password' => [$user === null ? 'required' : 'nullable', 'confirmed', 'min:'.app(Settings::class)->get('security.password_min_length', 8)],
            'user_section' => ['nullable', Rule::in(array_values(self::ROLE_SECTIONS))],
        ];
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403);
    }
}
