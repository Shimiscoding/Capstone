<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAreaAssignmentRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        abort_unless($viewer->isAdmin() || $viewer->isSupervisor() || $viewer->isOfficer(), 403);
        $canManage = $viewer->isAdmin() || $viewer->isSupervisor();

        $search = trim((string) $request->query('search', ''));
        $area = trim((string) $request->query('area', ''));
        $type = $viewer->isAdmin() && $request->query('type') === User::ROLE_SUPERVISOR ? User::ROLE_SUPERVISOR : User::ROLE_OFFICER;
        $officerQuery = User::query()
            ->where('role', $type)
            ->when($viewer->isSupervisor(), fn ($query) => $query->where('supervisor_id', $viewer->id))
            ->when($viewer->isOfficer(), fn ($query) => $query->whereKey($viewer->id))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('firstName', 'like', "%{$search}%")
                        ->orWhere('middleName', 'like', "%{$search}%")
                        ->orWhere('lastName', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            });

        if ($area === '__unassigned') {
            $officerQuery->where(fn ($query) => $query->whereNull('area')->orWhere('area', ''));
        } elseif ($area !== '') {
            $officerQuery->whereRaw('LOWER(TRIM(area)) = ?', [mb_strtolower($area)]);
        }

        $officers = $officerQuery
            ->with('supervisor:id,firstName,middleName,lastName,nameExtension')
            ->orderBy('firstName')->orderBy('lastName')
            ->paginate(20)->withQueryString();

        $areas = DB::table('operational_areas')->orderBy('name')->pluck('name');

        return view('area-assignments.index', compact('officers', 'areas', 'search', 'area', 'canManage', 'type'));
    }

    public function update(UpdateAreaAssignmentRequest $request, User $staff): RedirectResponse
    {
        $staff->update($request->validated());

        return back()->with('area_assignment_success', $staff->fullName.' area assignment was updated.');
    }

    public function storeArea(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isSupervisor(), 403);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $attributes = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:operational_areas,name']]);
        DB::table('operational_areas')->insert([
            'name' => $attributes['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('area_assignment_success', $attributes['name'].' was added to the area list.');
    }
}
