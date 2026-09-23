<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $viewer = $this->user();
        $staff = $this->route('staff');

        return $viewer instanceof User
            && $staff instanceof User
            && ($staff->isOfficer() || $staff->isSupervisor())
            && ($viewer->isAdmin() || ($viewer->isSupervisor() && $staff->isOfficer() && $staff->supervisor_id === $viewer->id));
    }

    public function rules(): array
    {
        return ['area' => ['nullable', 'string', 'max:255', Rule::exists('operational_areas', 'name')]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['area' => filled($this->input('area')) ? trim((string) $this->input('area')) : null]);
    }
}
