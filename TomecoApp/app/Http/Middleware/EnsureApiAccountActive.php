<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureApiAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_if($user && in_array($user->effectiveAccountStatus(), [User::STATUS_INACTIVE, User::STATUS_SUSPENDED, User::STATUS_BANNED], true), 403, 'This account is unavailable. Contact an administrator.');

        return $next($request);
    }
}
