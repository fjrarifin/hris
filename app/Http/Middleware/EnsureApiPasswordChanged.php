<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPasswordChanged
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Anda wajib mengganti password sebelum mengakses fitur HRIS.',
            ], 403);
        }

        return $next($request);
    }
}
