<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\JwtToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates JWT from the `Authorization: Bearer` header, loads {@see User}, and
 * registers them on both the request resolver and the default auth guard so
 * {@see \Illuminate\Foundation\Auth\Access\AuthorizesRequests} / policies work.
 */
class AuthenticateJwt
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = JwtToken::userIdFromBearer($request->bearerToken());
        if ($userId === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(static fn () => $user);

        // Gate / $this->authorize() use Auth::user(), not only $request->user().
        // Without this, policies see no user and every task action is denied.
        Auth::guard()->setUser($user);

        return $next($request);
    }
}
