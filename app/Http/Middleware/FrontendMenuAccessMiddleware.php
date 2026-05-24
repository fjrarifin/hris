<?php

namespace App\Http\Middleware;

use App\Support\FrontendNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontendMenuAccessMiddleware
{
    public function __construct(private readonly FrontendNavigation $navigation) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        abort_unless($request->user() && $this->navigation->canAccess($request->user(), $key), 403);

        return $next($request);
    }
}
