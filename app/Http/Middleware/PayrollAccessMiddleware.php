<?php

namespace App\Http\Middleware;

use App\Support\PayrollAccess;
use Closure;
use Illuminate\Http\Request;

class PayrollAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!PayrollAccess::can($request->user())) {
            abort(403);
        }

        return $next($request);
    }
}
