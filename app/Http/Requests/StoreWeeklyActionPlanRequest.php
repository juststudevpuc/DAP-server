<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Set to true assuming user is authenticated via middleware
        return true;
    }

    public function rules(): array
    {
        return [
            'week_number' => 'required|integer|min:1|max:52',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',

            // Targets must be 0 or higher
            'target_completed_training' => 'required|integer|min:0',
            'target_completed_onboarding' => 'required|integer|min:0',
            'target_graduated' => 'required|integer|min:0',
        ];
    }
}
