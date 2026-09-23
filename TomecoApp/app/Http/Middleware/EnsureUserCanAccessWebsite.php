<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessWebsite
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->role === User::ROLE_OFFICER, 403, 'Enforcer accounts are available only in the mobile app.');

        return $next($request);
    }
}
