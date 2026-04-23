<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Doctor) ?? false;
    }

    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        $allowedRoles = $this->user()?->hasRole(UserRole::Admin)
            ? [UserRole::Doctor->value, UserRole::Caregiver->value]
            : [UserRole::Caregiver->value];

        return [
            'role' => ['sometimes', 'string', Rule::in($allowedRoles)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => 'ruolo',
            'date_of_birth' => 'data di nascita',
            'is_active' => 'stato account',
        ];
    }
}
