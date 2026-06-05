<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates input for new account registration.
 */
class StoreRegisterRequest extends FormRequest
{
    /**
     * All registration attempts are allowed — no prior auth required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Require a unique email and a confirmed password of at least 8 characters.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
