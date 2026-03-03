<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if ((int) auth()->user()->level !== 0) {
            abort(403, 'Akses admin ditolak.');
        }

        return $next($request);
    }
}