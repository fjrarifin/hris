<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ForceChangePassword
{
    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::check() &&
            Auth::user()->must_change_password &&
            !$request->routeIs('password.change', 'password.update', 'logout')
        ) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}