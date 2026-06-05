<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoginRequest;
use App\Http\Requests\StoreRegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles account registration, login, and token revocation.
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Create a new user account and return an API token.
     */
    public function register(StoreRegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success('Account created', [
            'token' => $token,
            'user'  => $user,
        ], 201);
    }

    /**
     * Verify credentials and return an API token on success.
     */
    public function login(StoreLoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->error('Invalid credentials', 401);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success('Login successful', [
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * Revoke the token that was used to authenticate this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('Logged out successfully');
    }
}
