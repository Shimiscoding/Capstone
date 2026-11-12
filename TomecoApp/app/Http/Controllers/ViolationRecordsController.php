<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Services\AnalyticsReport;
use App\Services\ViolationRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ViolationRecordsController extends Controller
{
    public function violationRecords(Request $request, ViolationRecords $records): View
    {
        return view('dashboard.violation-records', $records->build($request));
    }

    public function violationRecord(Violation $violation): View
    {
        $violation->load('enforcer');

        return view('dashboard.violation-ticket-show', compact('violation'));
    }

    public function motoristRecord(Violation $violation): View
    {
        $motoristKey = $violation->motorist_key ?: Violation::makeMotoristKey(
            $violation->license_number,
            $violation->full_name,
        );
        $motoristViolations = Violation::query()
            ->with('enforcer')
            ->where('motorist_key', $motoristKey)
            ->latest()
            ->paginate(10);

        abort_if($motoristViolations->isEmpty(), 404);

        return view('dashboard.motorist-violation-history', [
            'motorist' => $violation,
            'motoristViolations' => $motoristViolations,
            'totalAssessedFines' => Violation::query()->where('motorist_key', $motoristKey)->sum('fine_amount'),
        ]);
    }

    public function analytics(Request $request, AnalyticsReport $report): View
    {
        abort_if($request->user()->isSupervisor(), 403);

        return view('dashboard.analytics', $report->build($request));
    }
}
