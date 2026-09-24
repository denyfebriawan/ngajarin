<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // A closure, so it runs when the page is rendered: after EnsureTenantMember has run.
            'currentTenant' => fn () => $this->currentTenant($request),
        ];
    }

    /**
     * The tenant this request is for, or null outside tenant routes.
     *
     * `can` tells the UI which actions to show; the server still checks each action itself.
     *
     * @return array{name: string, slug: string, role: string, can: array{update: bool}}|null
     */
    private function currentTenant(Request $request): ?array
    {
        $current = CurrentTenant::resolve();

        if ($current === null) {
            return null;
        }

        return [
            'name' => $current->tenant->name,
            'slug' => $current->tenant->slug,
            'role' => $current->role->value,
            'can' => [
                'update' => $request->user()?->can('update', $current->tenant) ?? false,
            ],
        ];
    }
}
