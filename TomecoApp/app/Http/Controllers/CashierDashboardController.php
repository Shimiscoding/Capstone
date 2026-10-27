<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashierDashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->isCashier(), 403);

        $tickets = Violation::query()->latest()->orderByDesc('id')->paginate(15);

        if ($tickets->currentPage() > $tickets->lastPage()) {
            return redirect()->route('cashier.dashboard', ['page' => $tickets->lastPage()]);
        }

        return view('cashier.index', compact('tickets'));
    }
}
