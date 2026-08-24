<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorTeamController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return view('supervisor.team', [
            'teamMembers' => User::where('role', User::ROLE_OFFICER)->where('supervisor_id', $request->user()->id)->orderBy('firstName')->orderBy('lastName')->get(),
            'availableEnforcers' => User::where('role', User::ROLE_OFFICER)->whereNull('supervisor_id')->orderBy('firstName')->orderBy('lastName')->get(),
        ]);
    }

    public function assign(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_unless($user->role === User::ROLE_OFFICER, 404);

        if ($user->supervisor_id !== null && $user->supervisor_id !== $request->user()->id) {
            return back()->with('team_error', 'This enforcer is already assigned to another supervisor.');
        }

        $user->update(['supervisor_id' => $request->user()->id]);

        return back()->with('team_success', $user->fullName.' was added to your team.');
    }

    public function remove(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_unless($user->role === User::ROLE_OFFICER && $user->supervisor_id === $request->user()->id, 404);

        $user->update(['supervisor_id' => null]);

        return back()->with('team_success', $user->fullName.' was removed from your team.');
    }

    public function adminAssign(Request $request, User $supervisor, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($supervisor->isSupervisor() && $user->isOfficer(), 404);

        if ($user->supervisor_id !== null && $user->supervisor_id !== $supervisor->id) {
            return back()->with('team_error', 'This enforcer is already assigned to another supervisor.');
        }

        $user->update(['supervisor_id' => $supervisor->id]);

        return back()->with('team_success', $user->fullName.' was added to '.$supervisor->fullName.'\'s team.');
    }

    public function adminRemove(Request $request, User $supervisor, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($supervisor->isSupervisor() && $user->isOfficer() && $user->supervisor_id === $supervisor->id, 404);

        $user->update(['supervisor_id' => null]);

        return back()->with('team_success', $user->fullName.' was removed from '.$supervisor->fullName.'\'s team.');
    }
}
