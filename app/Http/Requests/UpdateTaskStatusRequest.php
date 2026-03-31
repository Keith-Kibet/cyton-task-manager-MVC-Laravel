<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the requested `status` enum; the controller enforces the one-step transition
 * (pending → in_progress → done) and ownership via {@see \App\Policies\TaskPolicy}.
 */
class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'in_progress', 'done'])],
        ];
    }
}
