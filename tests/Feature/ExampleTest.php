<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the landing page is shown to guests and signed-in users alike', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
})->with([
    'a guest' => false,
    'a signed-in user' => true,
]);
