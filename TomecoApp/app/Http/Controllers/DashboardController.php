<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('fullName', 'like', "%{$search}%")
                        ->orWhere('badgeNumber', 'like', "%{$search}%")
                        ->orWhere('phoneNumber', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.users.user', compact('users', 'search'));
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
            'badgeNumber' => ['required', 'string', 'max:50', 'unique:users,badgeNumber'],
            'phoneNumber' => ['required', 'string', 'max:30', 'unique:users,phoneNumber'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        User::create($attributes);

        return redirect()->route('dashboard.users')->with('success', 'User account created successfully.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403);
    }
}
