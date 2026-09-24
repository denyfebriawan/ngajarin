<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Tutor = 'tutor';
    case Student = 'student';

    /**
     * The roles whose members can teach: owners teach in their own workspace, tutors are hired to.
     *
     * @return list<self>
     */
    public static function teaching(): array
    {
        return [self::Owner, self::Tutor];
    }
}
