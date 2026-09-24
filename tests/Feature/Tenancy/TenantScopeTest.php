<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureTenantMember;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Note;

beforeEach(function () {
    // Postgres runs DDL inside RefreshDatabase's transaction, so this table disappears after each test.
    Schema::create('notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained();
        $table->string('title');
        $table->timestamps();
    });

    $this->tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    // Created before any tenant is current, so they need an explicit tenant_id.
    $this->noteA = Note::create(['tenant_id' => $this->tenantA->id, 'title' => 'A only']);
    $this->noteB = Note::create(['tenant_id' => $this->tenantB->id, 'title' => 'B only']);
});

function actAsTenant(Tenant $tenant): void
{
    app()->instance(CurrentTenant::class, new CurrentTenant($tenant, Role::Owner));
}

test('queries only return the current tenant\'s rows', function () {
    actAsTenant($this->tenantA);

    expect(Note::pluck('title')->all())->toBe(['A only'])
        ->and(Note::find($this->noteB->id))->toBeNull();
});

test('with no current tenant, queries return nothing instead of every tenant\'s rows', function () {
    expect(Note::count())->toBe(0);
});

test('the scope can be removed explicitly', function () {
    actAsTenant($this->tenantA);

    expect(Note::withoutGlobalScope(TenantScope::class)->count())->toBe(2);
});

test('new records get the current tenant automatically', function () {
    actAsTenant($this->tenantA);

    $note = Note::create(['title' => 'New']);

    expect($note->tenant_id)->toBe($this->tenantA->id)
        ->and($note->tenant->is($this->tenantA))->toBeTrue();
});

test('records cannot be created without a tenant', function () {
    Note::create(['title' => 'Orphan']);
})->throws(LogicException::class, 'without a tenant');

test('records cannot be created for another tenant', function () {
    actAsTenant($this->tenantA);

    Note::create(['tenant_id' => $this->tenantB->id, 'title' => 'Sneaky']);
})->throws(LogicException::class, 'for another tenant');

test('records cannot be moved to another tenant', function () {
    actAsTenant($this->tenantA);

    $this->noteA->update(['tenant_id' => $this->tenantB->id]);
})->throws(LogicException::class, 'move');

test('route model binding only finds records of the tenant in the URL', function () {
    Route::middleware(['web', 'auth', EnsureTenantMember::class])
        ->get('/t/{tenant}/notes/{note}', fn (Tenant $tenant, Note $note) => $note->title);

    $user = User::factory()->create();
    $this->tenantA->addMember($user, Role::Owner);
    $this->actingAs($user);

    // Own tenant, own note: found.
    $this->get("/t/tenant-a/notes/{$this->noteA->id}")->assertOk()->assertSee('A only');

    // Own tenant in the URL, but another tenant's note id: not found, not leaked.
    $this->get("/t/tenant-a/notes/{$this->noteB->id}")->assertNotFound();

    // Another tenant's URL: forbidden before anything is looked up.
    $this->get("/t/tenant-b/notes/{$this->noteB->id}")->assertForbidden();
});
