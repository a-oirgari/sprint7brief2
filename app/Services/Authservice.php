<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function register(array $data): array
    {
        $data['password'] = Hash::make($data['password']);
        $data['is_minor'] = Carbon::parse($data['date_of_birth'])->age < 18;

        $user  = User::create($data);
        $token = JWTAuth::fromUser($user);

        return $this->tokenResponse($token, $user);
    }

    public function login(array $credentials): ?array
    {
        $token = auth('api')->attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        if (!$token) {
            return null;
        }

        return $this->tokenResponse($token, auth('api')->user());
    }

    public function refresh(): array
    {
        $newToken = auth('api')->refresh();
        return $this->tokenResponse($newToken, auth('api')->user());
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    private function tokenResponse(string $token, User $user): array
    {
        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60, 
            'user'         => $user,
        ];
    }
}