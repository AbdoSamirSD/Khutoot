<?php

namespace App\Http\Middleware;


use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    protected function redirectTo(\Illuminate\Http\Request $request)
    {
        if (! $request->expectsJson()) {
            abort(response()->json(['message' => 'Unauthorized'], 401));
        }
    }
}
