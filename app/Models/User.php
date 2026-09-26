<?php

namespace App\Models;

use App\Demo\DemoWorkspace;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether the email address counts as verified. With verification switched off
     * (config auth.verify_email, env AUTH_VERIFY_EMAIL), every account does. Both the `verified`
     * middleware and the listener that emails new users ask this method, so this one override
     * turns verification off everywhere.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! config('auth.verify_email') || parent::hasVerifiedEmail();
    }

    /**
     * Whether this is one of the shared demo accounts anyone can log into (see DemoWorkspace).
     */
    public function isDemo(): bool
    {
        return str_ends_with($this->email, '@'.DemoWorkspace::EMAIL_DOMAIN);
    }

    /**
     * Every tenant this user belongs to, with their role on the pivot.
     *
     * @return BelongsToMany<Tenant, $this, Membership>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The user's role in the given tenant, or null if they don't belong to it.
     */
    public function roleIn(Tenant $tenant): ?Role
    {
        return $this->tenants()->whereKey($tenant->id)->first()?->pivot->role;
    }
}
