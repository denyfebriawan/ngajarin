<?php

namespace App\Enums;

enum BookingStatus: string
{
    // Holds the slot: only confirmed lessons count for the no-double-booking constraints.
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
