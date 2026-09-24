<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Tutor = 'tutor';
    case Student = 'student';
}
