<?php

namespace App\Http\Controllers;

use App\Demo\DemoWorkspace;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DemoLoginController extends Controller
{
    /**
     * Log a visitor in as the demo tutor or the demo student with one click, so they can try the
     * app without signing up.
     */
    public function __invoke(Request $request, string $account): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 404);

        $email = $account === 'tutor' ? DemoWorkspace::TUTOR_EMAIL : DemoWorkspace::STUDENT_EMAIL;
        $user = User::query()->where('email', $email)->first();

        // Only before the demo has been built for the first time.
        if ($user === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The demo is not available right now. Please try again later.')]);

            return back();
        }

        Auth::login($user);

        // A new session ID after logging in, so an ID someone planted beforehand is useless
        // (session fixation). Fortify's own login does the same.
        $request->session()->regenerate();

        return $account === 'tutor'
            ? to_route('tenant.dashboard', DemoWorkspace::SLUG)
            : to_route('dashboard');
    }
}
