<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateAreaAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $viewer = $this->user();
        $staff = $this->route('staff');

        return $viewer instanceof User
            && $staff instanceof User
            && $staff->isOfficer()
            && ($viewer->isAdmin() || ($viewer->isSupervisor() && $staff->isOfficer() && $staff->supervisor_id === $viewer->id));
    }

    public function rules(): array
    {
        $availableAreas = DB::table('operational_areas')->pluck('name')
            ->merge($this->route('staff')->assignedAreas())->all();

        return [
            'areas' => ['bail', 'present', 'array', 'list', 'max:'.User::MAX_ASSIGNED_AREAS],
            'areas.*' => ['bail', 'nullable', 'string', 'distinct:ignore_case', Rule::in($availableAreas)],
        ];
    }

    public function messages(): array
    {
        return [
            'areas.max' => 'An enforcer can have up to '.User::MAX_ASSIGNED_AREAS.' duty areas.',
            'areas.*.distinct' => 'Select each duty area only once.',
            'areas.*.in' => 'Select a duty area from the list.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('areas')) {
            $this->merge(['areas' => [$this->input('area')]]);
        }
    }
}
