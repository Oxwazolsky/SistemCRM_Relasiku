<?php

namespace App\Http\Middleware;

use App\Services\CrmAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && app(CrmAccess::class)->role($request->user(), ...$roles), 403);
        return $next($request);
    }
}
