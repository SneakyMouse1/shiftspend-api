<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Registration of a new user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Test Testovich'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@exampletest.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Successful registration',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Test Testovich'),
                                        new OA\Property(property: 'email', type: 'string', example: 'test@exampletest.com'),
                                        new OA\Property(property: 'avatar', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'settings', type: 'object', nullable: true, example: null),
                                        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true, example: null),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-07-09 07:16:00'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdefg...'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'User authentication (token retrieval)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@exampletest.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Test Testovich'),
                                        new OA\Property(property: 'email', type: 'string', example: 'test@exampletest.com'),
                                        new OA\Property(property: 'avatar', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'settings', type: 'object', nullable: true, example: null),
                                        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true, example: null),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-07-09 07:16:00'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdefg...'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error or invalid credentials'
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Revoke the current access token (logout)',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully logged out',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully.'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'data' => ['message' => 'Logged out successfully.'],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/auth/profile',
        summary: 'Get the authenticated user profile',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Test Testovich'),
                        new OA\Property(property: 'email', type: 'string', example: 'test@exampletest.com'),
                        new OA\Property(property: 'avatar', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'settings', type: 'object', nullable: true, example: null),
                        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true, example: null),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-07-09 07:00:00'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    #[OA\Patch(
        path: '/api/v1/auth/profile',
        summary: 'Update name, avatar, or settings of the authenticated user',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', example: 'New Name'),
                new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'https://example.com/avatar.jpg'),
                new OA\Property(property: 'settings', type: 'object', nullable: true, example: ['currency' => 'EUR']),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateProfile(Request $request): JsonResponse // TATI ADDED SAVING AVATAR
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar'   => ['sometimes', 'nullable', 'file', 'image', 'max:5120'], // 5 Mb
            'settings' => ['sometimes', 'array'],
        ]);

        $user = $request->user();

        // SAVING AVATAR
        if ($request->hasFile('avatar')) {
            if ($user->avatar && str_contains($user->avatar, '/storage/')) {
                $oldPath = str_replace(asset('storage/'), '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = asset('storage/' . $path);
        }

        $user->update($validated);

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/auth/account',
        summary: 'Permanently delete the authenticated user account',
        description: 'Requires current_password confirmation. All Sanctum tokens are revoked before deletion.',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account deleted successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Account deleted successfully.'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Wrong current password'),
        ]
    )]
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        // Revoke all tokens first — prevents orphaned rows in personal_access_tokens
        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'data' => ['message' => 'Account deleted successfully.'],
        ]);
    }


    // TATI ADDED TO CHANGE PASSWORD FROM SETTINGS 
    #[OA\Patch(
        path: '/api/v1/auth/password',
        summary: 'Change password for the authenticated user',
        description: 'Requires current_password confirmation. Revokes all other Sanctum tokens, keeping the current session active.',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'oldSecret123'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newSecret123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'newSecret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Wrong current password or validation error'),
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($validated['password'])]);

        $user->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'data' => ['message' => 'Password changed successfully.'],
        ]);
    }
}
