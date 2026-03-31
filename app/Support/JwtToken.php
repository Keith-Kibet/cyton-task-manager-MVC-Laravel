<?php

namespace App\Support;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Stateless JWT helpers: HS256 tokens with sub = user UUID, exp from config/jwt.php.
 *
 * Used by {@see \App\Http\Controllers\Api\AuthController} to issue tokens and
 * {@see \App\Http\Middleware\AuthenticateJwt} to resolve the current user from the Bearer.
 */
final class JwtToken
{
    /**
     * Build a signed JWT for the given user (login / register response).
     */
    public static function forUser(User $user): string
    {
        $secret = (string) config('jwt.secret');
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is not set in .env');
        }

        $now = time();
        $payload = [
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + (int) config('jwt.ttl', 86_400),
        ];

        return JWT::encode($payload, $secret, (string) config('jwt.algo', 'HS256'));
    }

    /**
     * Decode the Bearer token and return the subject (user id), or null if invalid/expired.
     */
    public static function userIdFromBearer(?string $jwt): ?string
    {
        if ($jwt === null || $jwt === '') {
            return null;
        }

        $secret = (string) config('jwt.secret');
        if ($secret === '') {
            return null;
        }

        try {
            $decoded = JWT::decode($jwt, new Key($secret, (string) config('jwt.algo', 'HS256')));
            $id = $decoded->sub ?? null;

            return $id !== null && $id !== '' ? (string) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
