<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only if the signed-in user belongs to the tenant in the URL,
 * then makes that tenant available to the rest of the request as CurrentTenant.
 */
class EnsureTenantMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);

        $role = $request->user()?->roleIn($tenant);
        abort_if($role === null, 403);

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant, $role));

        return $next($request);
    }
}
