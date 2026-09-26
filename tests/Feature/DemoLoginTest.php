<?php

use App\Demo\DemoWorkspace;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['demo.enabled' => true]);
});

test('visitors can try the demo as the tutor', function () {
    app(DemoWorkspace::class)->reset();

    $this->post(route('demo.login', 'tutor'))
        ->assertRedirect(route('tenant.dashboard', DemoWorkspace::SLUG));

    $this->assertAuthenticatedAs(User::where('email', DemoWorkspace::TUTOR_EMAIL)->firstOrFail());
});

test('visitors can try the demo as the student', function () {
    app(DemoWorkspace::class)->reset();

    $this->post(route('demo.login', 'student'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::where('email', DemoWorkspace::STUDENT_EMAIL)->firstOrFail());
});

test('the demo logins are off unless the demo is enabled', function () {
    config(['demo.enabled' => false]);
    app(DemoWorkspace::class)->reset();

    $this->post(route('demo.login', 'tutor'))->assertNotFound();

    $this->assertGuest();
});

test('only the demo tutor and student can be logged into', function () {
    app(DemoWorkspace::class)->reset();

    // Ani is a demo account too, but not one the buttons offer.
    $this->post('/demo/ani')->assertNotFound();

    $this->assertGuest();
});

test('visitors are told when the demo has not been built yet', function () {
    $this->from(route('login'))
        ->post(route('demo.login', 'tutor'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast.type', 'error');

    $this->assertGuest();
});

test('pages tell the frontend whether to offer the demo and whether the user is a demo account', function () {
    app(DemoWorkspace::class)->reset();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoEnabled', true)
            ->where('auth.isDemo', false),
        );

    $this->actingAs(User::where('email', DemoWorkspace::STUDENT_EMAIL)->firstOrFail())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isDemo', true));
});

test('demo accounts cannot change what would break the demo for the next visitor', function (string $method, Closure $url, array $data) {
    app(DemoWorkspace::class)->reset();
    $tutor = User::where('email', DemoWorkspace::TUTOR_EMAIL)->firstOrFail();
    $before = [$tutor->name, $tutor->email, $tutor->password];

    $this->actingAs($tutor)
        ->from(route('dashboard'))
        ->call($method, $url(), $data)
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.type', 'error');

    $tutor->refresh();
    $demo = Tenant::where('slug', DemoWorkspace::SLUG)->firstOrFail();

    expect([$tutor->name, $tutor->email, $tutor->password])->toBe($before)
        ->and($demo->name)->toBe('Cerdas Learning Center')
        ->and($demo->timezone)->toBe('Asia/Jakarta');
})->with([
    'profile' => ['PATCH', fn () => route('profile.update'), ['name' => 'Someone', 'email' => 'someone@example.com']],
    'password' => ['PUT', fn () => route('user-password.update'), ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']],
    'delete account' => ['DELETE', fn () => route('profile.destroy'), ['password' => 'password']],
    'workspace settings' => ['PATCH', fn () => route('tenant.settings.update', DemoWorkspace::SLUG), ['name' => 'Renamed', 'timezone' => 'Asia/Jayapura']],
]);

test('real accounts can still change their profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('New Name');
});

test('nobody can sign up with a demo email address', function () {
    $this->post(route('register.store'), [
        'name' => 'Sneaky',
        'email' => 'sneaky@'.DemoWorkspace::EMAIL_DOMAIN,
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('nobody can create a workspace at the demo address', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tenants.store'), ['name' => 'My Demo', 'slug' => DemoWorkspace::SLUG])
        ->assertSessionHasErrors(['slug' => 'This address is already taken.']);
});

test('workspaces created with demo accounts are removed when the demo is rebuilt', function () {
    $demo = app(DemoWorkspace::class);
    $demo->reset();

    // A visitor, logged in as the demo student, creates a workspace of their own.
    $student = User::where('email', DemoWorkspace::STUDENT_EMAIL)->firstOrFail();
    $created = Tenant::factory()->create(['slug' => 'visitor-made']);
    $created->addMember($student, Role::Owner);

    // A real workspace the demo student merely studies in stays.
    $real = Tenant::factory()->create();
    $real->addMember(User::factory()->create(), Role::Owner);
    $real->addMember($student, Role::Student);

    $demo->reset();

    expect(Tenant::find($created->id))->toBeNull()
        ->and(Tenant::find($real->id))->not->toBeNull();
});
