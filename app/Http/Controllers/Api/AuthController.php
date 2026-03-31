<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

/**
 * Registration and login; both return a JWT and a minimal user object for the SPA.
 */
class AuthController extends Controller
{
    #[
        OA\Post(
            path: '/api/register',
            summary: 'Register',
            tags: ['Auth'],
            security: [],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name', 'email', 'password'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'password', type: 'string', format: 'password'),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 201,
                    description: 'Created',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'token', type: 'string', description: 'JWT (Bearer)'),
                            new OA\Property(
                                property: 'user',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'email', type: 'string'),
                                    new OA\Property(property: 'role', type: 'string', example: 'user'),
                                ],
                                type: 'object',
                            ),
                        ],
                    ),
                ),
                new OA\Response(response: 422, description: 'Validation error'),
            ],
        )
    ]
    /** Create a user with role `user` and return a signed JWT. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
        ]);

        return response()->json([
            'token' => JwtToken::forUser($user),
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ], 201);
    }

    #[
        OA\Post(
            path: '/api/login',
            summary: 'Login (Bearer token)',
            tags: ['Auth'],
            security: [],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['email', 'password'],
                    properties: [
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'password', type: 'string', format: 'password'),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'OK',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'token', type: 'string', description: 'JWT (Bearer)'),
                            new OA\Property(property: 'user', type: 'object'),
                        ],
                    ),
                ),
                new OA\Response(response: 422, description: 'Validation error'),
            ],
        )
    ]
    /** Validate credentials and return the same JWT + user payload as register. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json([
            'token' => JwtToken::forUser($user),
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }
}
