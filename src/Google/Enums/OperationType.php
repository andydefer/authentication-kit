<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Enums;

enum OperationType: string
{
    case LOGIN = 'login';
    case REGISTER = 'register';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Login',
            self::REGISTER => 'Register',
        };
    }
}
