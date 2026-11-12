<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_if($user?->isCashier(), 403, 'Cashier accounts have access only to the website ticket overview.');
        abort_if($user?->isAccountUnavailable(), 403, 'This account is unavailable. Contact an administrator.');

        return $next($request);
    }
}
