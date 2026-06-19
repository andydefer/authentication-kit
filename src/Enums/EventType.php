<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

enum EventType: string
{
    case USER_REGISTRATION_SUCCESS = 'user_registration_success';
    case USER_REGISTRATION_FAILED = 'user_registration_failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::USER_REGISTRATION_SUCCESS => 'User registration successful',
            self::USER_REGISTRATION_FAILED => 'User registration failed',
        };
    }
}
