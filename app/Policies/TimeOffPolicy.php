<?php

namespace App\Policies;

use App\Models\TimeOff;
use App\Models\User;

/**
 * Found automatically by name (App\Models\TimeOff -> App\Policies\TimeOffPolicy).
 * Which workspace the entry is in is already guaranteed by the global scope; this only decides
 * whose it is.
 */
class TimeOffPolicy
{
    /**
     * Teachers delete only their own time off.
     */
    public function delete(User $user, TimeOff $timeOff): bool
    {
        return $timeOff->user_id === $user->id;
    }
}
