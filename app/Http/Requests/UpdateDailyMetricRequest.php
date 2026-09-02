<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyMetricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Training Validation
            'train_expected' => 'sometimes|integer|min:0',
            'train_completed' => 'sometimes|integer|min:0',
            'train_cancel_delay' => 'sometimes|integer|min:0',

            // Onboarding Validation
            'onboard_company_info' => 'sometimes|integer|min:0',
            'onboard_system_analysis' => 'sometimes|integer|min:0',
            'onboard_configure_hr' => 'sometimes|integer|min:0',
            'onboard_provide_lesson' => 'sometimes|integer|min:0',
            'onboard_success' => 'sometimes|integer|min:0',

            // Graduated Validation
            'grad_certificate' => 'sometimes|integer|min:0',
            'grad_hr_policy' => 'sometimes|integer|min:0',
            'grad_book' => 'sometimes|integer|min:0',

            // Text Validation
            'comment' => 'nullable|string',
        ];
    }
}
