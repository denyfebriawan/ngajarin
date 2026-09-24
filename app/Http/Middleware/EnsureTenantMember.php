<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only if the signed-in user belongs to the tenant in the URL,
 * then makes that tenant available to the rest of the request as CurrentTenant.
 *
 * Runs before SubstituteBindings (see bootstrap/app.php), so the tenant is known before other
 * route models, which are limited to the current tenant by TenantScope, are looked up.
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
        $route = $request->route();
        abort_unless($route instanceof Route, 404);

        $slug = $route->parameter('tenant');
        $tenant = $slug instanceof Tenant ? $slug : Tenant::firstWhere('slug', $slug);
        abort_if($tenant === null, 404);

        $role = $request->user()?->roleIn($tenant);
        abort_if($role === null, 403);

        // Hand the model to the route, so controllers type-hinting Tenant receive it as-is.
        $route->setParameter('tenant', $tenant);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant, $role));

        return $next($request);
    }
}
