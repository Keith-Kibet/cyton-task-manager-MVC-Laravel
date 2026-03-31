<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for {@see \App\Http\Controllers\Api\TaskController::store}.
 *
 * Duplicate (title + due_date) is enforced only among **non-archived** rows so archived
 * tasks do not block reusing the same title on the same calendar day.
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks', 'title')->where(function ($query) {
                    $query->where('due_date', $this->input('due_date'))->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
