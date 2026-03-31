<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AdminUserController extends Controller
{
    #[
        OA\Get(
            path: '/api/admin/users',
            summary: 'List users (admin)',
            tags: ['Admin'],
            security: [['jwt' => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'OK',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'users',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                            ),
                        ],
                    ),
                ),
                new OA\Response(response: 403, description: 'Forbidden'),
            ],
        )
    ]
    public function index()
    {
        $users = User::query()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json(['users' => $users]);
    }

    #[
        OA\Patch(
            path: '/api/admin/users/{id}/role',
            summary: 'Change user role (admin)',
            tags: ['Admin'],
            security: [['jwt' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['role'],
                    properties: [
                        new OA\Property(property: 'role', type: 'string', enum: ['user', 'admin']),
                    ],
                ),
            ),
            responses: [
                new OA\Response(response: 200, description: 'OK'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 422, description: 'Validation error'),
            ],
        )
    ]
    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        $user->update(['role' => $data['role']]);

        return response()->json($user->only(['id', 'name', 'email', 'role']));
    }
}
