<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Autenticazione richiesta.',
                    'data' => [],
                    'errors' => [],
                ], 401);
            }

            return redirect()->route('accounts.login');
        }

        if (! $request->user()->is_staff) {
            abort(403, 'Permessi insufficienti.');
        }

        return $next($request);
    }
}
