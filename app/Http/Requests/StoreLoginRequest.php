<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of a login request before credential checking.
 */
class StoreLoginRequest extends FormRequest
{
    /**
     * All login attempts are allowed — no prior auth required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Require a valid email format and a non-empty password.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
