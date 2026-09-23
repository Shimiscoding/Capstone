<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $search = trim((string) $request->query('search', ''));
        $event = in_array($request->query('event'), ['created', 'updated', 'deleted'], true)
            ? (string) $request->query('event')
            : '';

        $logs = AuditLog::query()
            ->with('user')
            ->whereIn('auditable_type', AuditLogger::AUDITABLE_TYPES)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('subject_label', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('firstName', 'like', "%{$search}%")
                        ->orWhere('lastName', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.audit-logs', compact('logs', 'search', 'event'));
    }
}
