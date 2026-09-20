<?php

// src/Mail/Enums/CurrentUserMode.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Enums;

enum CurrentUserMode: string
{
    case SIMPLE = 'simple';
    case DETAILED = 'detailed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
