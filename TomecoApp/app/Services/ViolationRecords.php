<?php

namespace App\Services;

use App\Models\Violation;
use App\Models\ViolationIssued;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ViolationRecords
{
    public function build(Request $request): array
    {
        $now = now('Asia/Manila');
        $input = collect($request->only(['search', 'violation_type', 'status', 'period', 'sort', 'date_from', 'date_to']))
            ->map(fn ($value) => is_string($value) ? trim($value) : '')->all();
        if (! isset($input['period']) && (filled($input['date_from'] ?? null) || filled($input['date_to'] ?? null))) {
            $input['period'] = 'custom';
        }
        $validator = Validator::make($input, [
            'search' => 'nullable|string|max:160', 'violation_type' => 'nullable|string|max:255',
            'status' => 'nullable|in:paid,unpaid', 'period' => 'nullable|in:today,week,month,all,custom',
            'sort' => 'nullable|in:newest,oldest,fine',
            'date_from' => 'exclude_unless:period,custom|nullable|date_format:Y-m-d',
            'date_to' => 'exclude_unless:period,custom|nullable|date_format:Y-m-d'.(filled($input['date_from'] ?? null) ? '|after_or_equal:date_from' : ''),
        ], ['date_to.after_or_equal' => 'The end date must be on or after the start date.']);
        $errors = $validator->errors();
        $value = fn ($key, $fallback = '') => $errors->has($key) ? $fallback : ($input[$key] ?? $fallback);
        $search = $value('search');
        $type = $value('violation_type');
        $status = $value('status');
        $period = $value('period', 'month') ?: 'month';
        $sort = $value('sort', 'newest') ?: 'newest';
        if ($errors->has('date_from') || $errors->has('date_to')) {
            $period = 'all';
        }
        $from = $period === 'custom' ? $value('date_from') : '';
        $to = $period === 'custom' ? $value('date_to') : '';
        $start = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->subDays(6)->startOfDay(),
            'month' => $now->copy()->startOfMonth(),
            'custom' => $from ? Carbon::parse($from, 'Asia/Manila')->startOfDay() : null,
            default => null,
        };
        $end = $period === 'custom'
            ? ($to ? Carbon::parse($to, 'Asia/Manila')->endOfDay() : null)
            : ($period === 'all' ? null : $now);
        $query = Violation::query()
            ->when($type !== '', fn ($q) => $q->whereHas('issuedViolations.formOption', fn ($options) => $options->where('label', $type)))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'unpaid', fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', 'paid')->orWhereNull('status')))
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start->copy()->setTimezone(config('app.timezone'))))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end->copy()->setTimezone(config('app.timezone'))));

        if ($search !== '') {
            // Names and plates are encrypted. Search decrypted values in bounded chunks,
            // never ciphertext with LIKE, and never persist decrypted search indexes.
            $matchingIds = (clone $query)->where(fn ($matches) => $matches
                ->where('citation_number', strtoupper(ltrim($search, '#')))
                ->orWhereHas('issuedViolations', fn ($items) => $items
                    ->where('description_snapshot', 'like', '%'.$search.'%')
                    ->orWhereHas('formOption', fn ($options) => $options->where('label', 'like', '%'.$search.'%'))
                    ->orWhereHas('type', fn ($types) => $types->where('section_id', $search))))
                ->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->all();
            $needle = mb_strtolower($search);
            $plateNeedle = mb_strtolower(preg_replace('/[^\pL\pN]/u', '', $search));
            $ticketId = preg_match('/^#?\d+$/', $search) ? (int) ltrim($search, '#') : null;
            (clone $query)->withoutEagerLoads()->select(['id', 'first_name', 'middle_name', 'last_name', 'plate_number'])
                ->chunkById(500, function ($tickets) use (&$matchingIds, $needle, $plateNeedle, $ticketId) {
                    foreach ($tickets as $ticket) {
                        $plate = mb_strtolower((string) $ticket->plate_number);
                        if ($ticket->id === $ticketId || str_contains(mb_strtolower($ticket->full_name), $needle)
                            || str_contains($plate, $needle)
                            || ($plateNeedle !== '' && str_contains(preg_replace('/[^\pL\pN]/u', '', $plate), $plateNeedle))) {
                            $matchingIds[$ticket->id] = true;
                        }
                    }
                });
            $query->whereIntegerInRaw('id', array_keys($matchingIds));
        }
        $totals = (clone $query)->toBase()->selectRaw("COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid, COALESCE(SUM(fine_amount), 0) AS fines")->first();
        $page = max(1, min($request->integer('page', 1), max(1, (int) ceil($totals->total / 15))));
        $query->with('enforcer');
        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'fine' => $query->orderByDesc('fine_amount')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return [
            'violations' => $query->paginate(15, ['*'], 'page', $page)->withQueryString(),
            'search' => $search, 'violationType' => $type, 'statusFilter' => $status,
            'period' => $period, 'sortOrder' => $sort, 'filterErrors' => $errors,
            'dateFrom' => $input['date_from'] ?? '', 'dateTo' => $input['date_to'] ?? '',
            'violationTypes' => ViolationIssued::query()->whereHas('ticketItems')->orderBy('sort_order')->pluck('label'),
            'recordSummary' => ['total' => (int) $totals->total, 'paid' => (int) $totals->paid,
                'unpaid' => (int) $totals->total - (int) $totals->paid, 'fines' => (float) $totals->fines],
        ];
    }
}
