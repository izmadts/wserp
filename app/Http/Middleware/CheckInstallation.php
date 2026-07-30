<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckInstallation
{
    public function handle(Request $request, Closure $next)
    {
        // Allow installation routes
        if ($request->is('install*')) {
            return $next($request);
        }

        // Check if installed
        if (!file_exists(storage_path('installed'))) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }
}