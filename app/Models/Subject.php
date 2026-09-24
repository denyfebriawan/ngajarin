<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something a workspace teaches, e.g. "Math Grade 10": how long a lesson lasts and what it costs.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $price Whole rupiah.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'duration_minutes', 'price'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'integer',
        ];
    }
}
