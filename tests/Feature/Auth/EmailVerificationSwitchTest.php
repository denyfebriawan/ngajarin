<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('verification is on by default', function () {
    expect(config('auth.verify_email'))->toBeTrue()
        ->and(User::factory()->unverified()->create()->hasVerifiedEmail())->toBeFalse();
});

describe('with email verification switched off', function () {
    beforeEach(function () {
        config(['auth.verify_email' => false]);
    });

    test('new users are not sent a verification email', function () {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        Notification::assertNothingSent();
    });

    test('unverified accounts can use pages that require a verified email', function () {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    });

    test('the verification prompt sends people on to the dashboard', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard', absolute: false));
    });

    test('the profile page does not ask to verify the address', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('mustVerifyEmail', false));
    });

    test('no verification email can be requested', function () {
        Notification::fake();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('verification.send'));

        Notification::assertNotSentTo(User::first(), VerifyEmail::class);
    });
});
