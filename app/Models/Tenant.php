<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A tutor's or tutoring center's workspace.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone IANA name, one of Tenant::TIMEZONES.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'timezone'])]
#[RouteKey('slug')]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The timezones a workspace can choose: Indonesia's three.
     */
    public const TIMEZONES = [
        'Asia/Jakarta' => 'WIB: Western Indonesia (Jakarta, Sumatra, Java)',
        'Asia/Makassar' => 'WITA: Central Indonesia (Makassar, Bali, Kalimantan)',
        'Asia/Jayapura' => 'WIT: Eastern Indonesia (Jayapura, Maluku)',
    ];

    /**
     * Everyone who belongs to this tenant, with their role on the pivot.
     *
     * @return BelongsToMany<User, $this, Membership>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Members who can teach here (owners and tutors), i.e. who can be given subjects.
     *
     * @return BelongsToMany<User, $this, Membership>
     */
    public function teachers(): BelongsToMany
    {
        return $this->users()->wherePivotIn('role', Role::teaching());
    }

    /**
     * Add a user to this tenant with the given role.
     */
    public function addMember(User $user, Role $role): void
    {
        $this->users()->attach($user, ['role' => $role]);
    }
}
