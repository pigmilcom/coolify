<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanAccessServers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            abort(401, 'Authentication required');
        }

        // Only admins/owners can access server management
        if (! auth()->user()->can('canAccessServers')) {
            abort(403, 'Access to server management is restricted to team administrators');
        }

        return $next($request);
    }
}
