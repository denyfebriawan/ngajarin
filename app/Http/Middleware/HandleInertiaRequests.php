<?php

namespace App\Http\Middleware;

use App\Models\Membership;
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
                // Shared demo accounts see a banner explaining that everything resets nightly.
                'isDemo' => $request->user()?->isDemo() ?? false,
            ],
            // Whether to offer the one-click demo logins.
            'demoEnabled' => (bool) config('demo.enabled'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // A closure, so it runs when the page is rendered: after EnsureTenantMember has run.
            'currentTenant' => fn () => $this->currentTenant($request),
            'tenants' => fn () => $this->tenants($request),
        ];
    }

    /**
     * Every workspace the signed-in user belongs to, for the sidebar.
     *
     * @return array<int, array{name: string, slug: string, role: string}>
     */
    private function tenants(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        // Each membership is "this user's role in this workspace"; the role lets the UI separate
        // workspaces the user teaches in from those they study in.
        return Membership::query()
            ->with('tenant')
            ->where('user_id', $user->id)
            ->get()
            ->sortBy(fn (Membership $membership) => $membership->tenant->name)
            ->map(fn (Membership $membership) => [
                'name' => $membership->tenant->name,
                'slug' => $membership->tenant->slug,
                'role' => $membership->role->value,
            ])
            ->values()
            ->all();
    }

    /**
     * The tenant this request is for, or null outside tenant routes.
     *
     * `can` tells the UI which actions to show; the server still checks each action itself.
     *
     * @return array{name: string, slug: string, timezone: string, role: string, can: array{update: bool, manageSubjects: bool, teach: bool}}|null
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
            'timezone' => $current->tenant->timezone,
            'role' => $current->role->value,
            'can' => [
                'update' => $request->user()?->can('update', $current->tenant) ?? false,
                'manageSubjects' => $request->user()?->can('manageSubjects', $current->tenant) ?? false,
                'teach' => $request->user()?->can('teach', $current->tenant) ?? false,
            ],
        ];
    }
}
