<?php

namespace App\Services;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnalyticsReport
{
    public function build(Request $request): array
    {
        $now = now('Asia/Manila');
        $input = collect($request->only(['summary', 'date_from', 'date_to', 'status', 'vehicle']))
            ->map(fn ($value) => is_string($value) ? $value : '')->all();
        if (($input['summary'] ?? '') === 'days') {
            $input['summary'] = 'week';
        }
        $validator = Validator::make($input, [
            'summary' => 'nullable|in:today,week,month,year,all,custom',
            'date_from' => 'exclude_unless:summary,custom|required|date_format:Y-m-d|before_or_equal:'.$now->format('Y-m-d'),
            'date_to' => 'exclude_unless:summary,custom|required|date_format:Y-m-d|after_or_equal:date_from|before_or_equal:'.$now->format('Y-m-d'),
            'status' => 'nullable|in:paid,unpaid',
            'vehicle' => 'nullable|string|max:1000',
        ], [
            'date_from.required' => 'Choose a start date for the custom period.',
            'date_to.required' => 'Choose an end date for the custom period.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);
        $filterErrors = $validator->errors();
        $range = $input['summary'] ?? 'today';
        if (! $range || $filterErrors->has('summary') || $filterErrors->has('date_from') || $filterErrors->has('date_to')) {
            $range = 'today';
        }
        $status = $filterErrors->has('status') ? '' : ($input['status'] ?? '');
        $vehicle = $filterErrors->has('vehicle') ? '' : ($input['vehicle'] ?? '');
        $firstTicket = $range === 'all' ? Violation::query()->min('created_at') : null;
        $start = match ($range) {
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
            'all' => $firstTicket ? Carbon::parse($firstTicket, config('app.timezone'))->setTimezone('Asia/Manila')->startOfDay() : $now->copy()->startOfDay(),
            'custom' => Carbon::parse($input['date_from'], 'Asia/Manila')->startOfDay(),
            default => $now->copy()->startOfDay(),
        };
        $end = $range === 'custom'
            ? Carbon::parse($input['date_to'], 'Asia/Manila')->endOfDay()->min($now)
            : $now->copy();
        $toStorage = fn (Carbon $date) => $date->copy()->setTimezone(config('app.timezone'));
        $base = Violation::query()
            ->when($vehicle !== '', fn ($q) => $q->where('vehicle_type_key', Violation::makeVehicleTypeKey($vehicle)))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'unpaid', fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', 'paid')->orWhereNull('status')));
        $tickets = (clone $base)->whereBetween('created_at', [$toStorage($start), $toStorage($end)]);
        $total = (clone $tickets)->count();
        $paid = (clone $tickets)->where('status', 'paid')->count();
        $unpaid = (clone $tickets)->where(fn ($q) => $q->where('status', '!=', 'paid')->orWhereNull('status'));
        $enforcerCount = (clone $tickets)->whereHas('enforcer', fn ($q) => $q->where('role', User::ROLE_OFFICER))
            ->distinct()->count('enforcer_id');

        // Use disjoint, equally long elapsed periods, including a partial current day.
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $start->copy()->subSeconds((int) $start->diffInSeconds($end) + 1);
        $previousTotal = $range === 'all' ? null : (clone $base)
            ->whereBetween('created_at', [$toStorage($previousStart), $toStorage($previousEnd)])->count();

        $days = $start->diffInDays($end);
        $granularity = $days < 1 && $start->isSameDay($end) ? 'hour' : ($days <= 62 ? 'day' : ($days <= 730 ? 'month' : 'year'));
        $keyFormat = match ($granularity) {
            'hour' => 'Y-m-d H', 'day' => 'Y-m-d', 'month' => 'Y-m', default => 'Y'
        };
        $labelFormat = match ($granularity) {
            'hour' => 'H:00', 'day' => 'M d', 'month' => 'M Y', default => 'Y'
        };
        $cursor = match ($granularity) {
            'hour' => $start->copy()->startOfHour(), 'month' => $start->copy()->startOfMonth(), 'year' => $start->copy()->startOfYear(), default => $start->copy()->startOfDay()
        };
        $buckets = [];
        while ($cursor->lte($end)) {
            $buckets[$cursor->format($keyFormat)] = ['label' => $cursor->format($labelFormat), 'date' => $cursor->format('M d, Y H:i'), 'value' => 0, 'paid' => 0, 'unpaid' => 0];
            match ($granularity) {
                'hour' => $cursor->addHour(), 'day' => $cursor->addDay(), 'month' => $cursor->addMonth(), default => $cursor->addYear()
            };
        }
        $locations = [];
        $aging = collect(['0–7 days', '8–30 days', '31–60 days', 'Over 60 days'])->mapWithKeys(fn ($label) => [$label => ['label' => $label, 'total' => 0, 'amount' => 0]])->all();
        // Decrypt only the location field in bounded chunks; never group encrypted ciphertext.
        (clone $tickets)->withoutEagerLoads()->select(['id', 'created_at', 'status', 'location', 'fine_amount'])
            ->chunkById(500, function ($rows) use (&$buckets, &$locations, &$aging, $now, $keyFormat) {
                foreach ($rows as $ticket) {
                    $issued = $ticket->created_at->copy()->setTimezone('Asia/Manila');
                    $key = $issued->format($keyFormat);
                    $buckets[$key]['value']++;
                    $buckets[$key][$ticket->status === 'paid' ? 'paid' : 'unpaid']++;
                    $location = preg_replace('/\s+/u', ' ', trim((string) $ticket->location)) ?: 'Location not recorded';
                    $locations[$location] = ($locations[$location] ?? 0) + 1;
                    if ($ticket->status !== 'paid') {
                        $age = (int) $issued->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());
                        $band = $age <= 7 ? '0–7 days' : ($age <= 30 ? '8–30 days' : ($age <= 60 ? '31–60 days' : 'Over 60 days'));
                        $aging[$band]['total']++;
                        $aging[$band]['amount'] += (float) $ticket->fine_amount;
                    }
                }
            });
        arsort($locations);
        $locationTrends = collect($locations)->map(fn ($count, $label) => (object) ['label' => $label, 'total' => $count])->values()->take(5);
        $violationTrends = DB::table('Ticket_issued as tickets')
            ->join('ticket_violation_items as offenses', 'offenses.ticket_issued_id', '=', 'tickets.id')
            ->join('violation_issued as options', 'options.id', '=', 'offenses.violation_issued_id')
            ->whereIn('tickets.id', (clone $tickets)->select('Ticket_issued.id')->toBase())
            ->selectRaw('options.label as violation_type, COUNT(DISTINCT tickets.id) as total')
            ->selectRaw("COUNT(DISTINCT CASE WHEN tickets.status = 'paid' THEN tickets.id END) as resolved")
            ->groupBy('options.id', 'options.label')->orderByDesc('total')->orderBy('options.label')->get();
        $page = max(1, min((int) $request->integer('page', 1), max(1, (int) ceil($violationTrends->count() / 10))));
        $violationPage = new LengthAwarePaginator($violationTrends->forPage($page, 10)->values(), $violationTrends->count(), 10, $page, ['path' => $request->url(), 'query' => $request->except('page')]);
        $vehicleGroups = (clone $tickets)->selectRaw('MIN(id) as representative_id, COUNT(*) as total')
            ->groupBy('vehicle_type_key')->toBase();
        $vehicleTrends = Violation::query()->withoutEagerLoads()
            ->joinSub($vehicleGroups, 'vehicle_groups', 'Ticket_issued.id', '=', 'vehicle_groups.representative_id')
            ->get(['Ticket_issued.vehicle_type', 'vehicle_groups.total'])
            ->sortBy([['total', 'desc'], ['vehicle_type', 'asc']])->values();
        $vehicleOptionIds = Violation::query()->whereNotNull('vehicle_type_key')
            ->where('vehicle_type_key', '!=', Violation::makeVehicleTypeKey(''))
            ->selectRaw('MIN(id)')->groupBy('vehicle_type_key')->toBase();
        $vehicleOptions = Violation::query()->whereIn('id', $vehicleOptionIds)->pluck('vehicle_type')
            ->sort(SORT_STRING | SORT_FLAG_CASE)->values();
        $enforcerTrends = (clone $tickets)->selectRaw('enforcer_id, COUNT(*) as total')
            ->whereHas('enforcer', fn ($q) => $q->where('role', User::ROLE_OFFICER))
            ->withOnly('enforcer')->groupBy('enforcer_id')->orderByDesc('total')->orderBy('enforcer_id')->limit(5)->get();

        return [
            'summaryRange' => $range, 'summaryDescription' => match ($range) {
                'week' => 'this week', 'month' => 'this month', 'year' => 'this year', 'all' => 'all time', 'custom' => 'in the selected period', default => 'today'
            },
            'summary' => ['total' => $total, 'resolved' => $paid, 'pending' => $total - $paid, 'enforcers' => $enforcerCount],
            'periodViolationTotal' => $total, 'activeEnforcers' => $enforcerCount, 'resolvedTickets' => $paid, 'pendingTickets' => $total - $paid,
            'outstandingFines' => (float) (clone $unpaid)->sum('fine_amount'),
            'assessedFines' => (float) (clone $tickets)->sum('fine_amount'),
            'settlementRate' => $total ? round($paid / $total * 100, 1) : 0,
            'dailyViolations' => collect(array_values($buckets)), 'granularity' => $granularity,
            'previousTotal' => $previousTotal, 'previousStart' => $previousStart, 'previousEnd' => $previousEnd,
            'ticketChange' => $previousTotal ? round(($total - $previousTotal) / $previousTotal * 100, 1) : null,
            'rangeStart' => $start, 'rangeEnd' => $end, 'generatedAt' => $now,
            'dateFrom' => $range === 'custom' || $filterErrors->has('date_from') ? ($input['date_from'] ?? '') : $start->format('Y-m-d'),
            'dateTo' => $range === 'custom' || $filterErrors->has('date_to') ? ($input['date_to'] ?? '') : $end->format('Y-m-d'),
            'statusFilter' => $status, 'vehicleFilter' => $vehicle, 'filterErrors' => $filterErrors,
            'vehicleOptions' => $vehicleOptions,
            'violationTrends' => $violationTrends, 'violationPage' => $violationPage,
            'vehicleTrends' => $vehicleTrends, 'locationTrends' => $locationTrends,
            'enforcerTrends' => $enforcerTrends, 'aging' => collect(array_values($aging)),
            'otherViolationTickets' => (clone $tickets)->whereDoesntHave('issuedViolations.formOption')->count(),
        ];
    }
}
