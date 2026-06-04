<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TrimsInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates partial issue updates while allowing clients to send only changed fields.
 */
class UpdateIssueRequest extends FormRequest
{
    use TrimsInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->trimFields(['title', 'description', 'priority', 'category', 'status']);
    }

    /**
     * Apply "sometimes" rules so absent fields are ignored and present fields are valid.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:open,in_progress,resolved'],
        ];
    }

    /**
     * Return validation failures in the shared API error envelope.
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
