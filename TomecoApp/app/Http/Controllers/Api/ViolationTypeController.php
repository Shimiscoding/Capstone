<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ViolationIssued;
use App\Models\ViolationType;
use App\Services\ViolationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViolationTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'form_group' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($validated['search'] ?? '');
        $query = ViolationType::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('description', 'like', "%{$search}%")
                ->orWhere('section_id', $search)))
            ->orderBy('section_id');
        // Filter sections by their related printed form option.
        if (filled($validated['form_group'] ?? null)) {
            $query->whereHas('formOption', fn ($options) => $options->where('label', $validated['form_group']));
        }
        $types = $query->paginate(100)->withQueryString();

        return response()->json(['success' => true, 'form_options' => [...ViolationCatalog::COMMON_GROUPS, 'Others'],
            'form_option_records' => ViolationIssued::query()->orderBy('sort_order')->get(),
            'data' => $types->items(), 'meta' => ['current_page' => $types->currentPage(), 'last_page' => $types->lastPage(), 'total' => $types->total()]]);
    }

    public function formOptions(): JsonResponse
    {
        return response()->json(['success' => true,
            'data' => ViolationIssued::query()->orderBy('sort_order')->get()]);
    }
}
