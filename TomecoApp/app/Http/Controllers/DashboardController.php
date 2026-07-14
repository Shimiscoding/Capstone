<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function payments(): View
    {
        return view('dashboard.payment');
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
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('fullName', 'like', "%{$search}%")
                        ->orWhere('badgeNumber', 'like', "%{$search}%")
                        ->orWhere('phoneNumber', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.user', compact('users', 'search'));
    }
}
