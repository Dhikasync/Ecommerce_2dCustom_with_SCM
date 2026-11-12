<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** FR-AUTH-01 #7: menolak akses lintas role, mis. `role:admin_ecommerce,admin_scm`. */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasRole(...$roles)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke halaman ini',
            ], 403);
        }

        return $next($request);
    }
}
