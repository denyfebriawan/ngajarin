<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // PHP tests check what the server sends, not the compiled frontend: don't look pages up in
        // Vite's build manifest, which may be missing or out of date locally.
        $this->withoutVite();

        // Nor render pages to HTML. While `composer run dev` is running, Inertia would send every
        // page to Vite's dev server for server-side rendering: about 2 seconds per test.
        config(['inertia.ssr.enabled' => false]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
