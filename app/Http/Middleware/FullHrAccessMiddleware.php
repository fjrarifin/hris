<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FullHrAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->user()?->username === 'hrd0002'
            && ! $request->routeIs('hr.approval.*')
        ) {
            abort(403);
        }

        return $next($request);
    }
}
