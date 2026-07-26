<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولًا.',
            ], 401);
        }

        if (!in_array($request->user()->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لتنفيذ هذه العملية.',
            ], 403);
        }

        return $next($request);
    }
}
