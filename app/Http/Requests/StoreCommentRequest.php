<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TrimsInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates comment creation input for immutable issue comments.
 */
class StoreCommentRequest extends FormRequest
{
    use TrimsInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->trimFields(['author_name', 'body']);
    }

    /**
     * Require a visible author name and body after trimming.
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
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
