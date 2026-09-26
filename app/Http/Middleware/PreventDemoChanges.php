<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops changes that would break the shared demo accounts for the next visitor: a new email or
 * password, deleting the account, or changing the demo workspace's settings. Everything else in
 * the demo may be changed freely, because it is rebuilt every night.
 */
class PreventDemoChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDemo()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This is a shared demo account, so this can\'t be changed. Sign up to try it with your own account.'),
            ]);

            return back();
        }

        return $next($request);
    }
}
