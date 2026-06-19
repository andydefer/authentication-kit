<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

enum TokenSource: string
{
    case REGISTER = 'register';
    case LOGIN = 'login';
    case PASSWORD_RESET = 'password_reset';
    case EMAIL_VERIFICATION = 'email_verification';

    public function getLabel(): string
    {
        return match ($this) {
            self::REGISTER => 'Registration',
            self::LOGIN => 'Login',
            self::PASSWORD_RESET => 'Password Reset',
            self::EMAIL_VERIFICATION => 'Email Verification',
        };
    }
}
