<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        $temperature = $this->input('temperature', $this->input('temperatura'));
        $humidity = $this->input('humidity', $this->input('umidita'));

        if ($temperature !== null) {
            $normalized['temperature'] = $temperature;
        }

        if ($humidity !== null) {
            $normalized['humidity'] = $humidity;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'temperature' => ['required', 'numeric', 'between:-40,120'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
