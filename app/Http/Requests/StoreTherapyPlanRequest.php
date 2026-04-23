<?php

namespace App\Http\Requests;

use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTherapyPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Doctor) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:users,id'],
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'dose_amount' => ['required', 'numeric', 'gt:0'],
            'dose_unit' => ['required', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['required', 'boolean'],
            'schedules' => ['required', 'array', 'min:1', 'max:1'],
            'schedules.*' => ['required', 'date_format:H:i'],
        ];
    }
}
