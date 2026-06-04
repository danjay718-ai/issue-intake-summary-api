<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TrimsInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates issue creation input before the controller persists the issue.
 */
class StoreIssueRequest extends FormRequest
{
    use TrimsInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->trimFields(['title', 'description', 'priority', 'category']);
    }

    /**
     * Require the core fields needed to create and triage an issue.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high'],
            'category' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Override Laravel's default validation body with the project's API error shape.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
