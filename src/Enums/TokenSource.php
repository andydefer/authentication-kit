<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

/**
 * Defines the possible sources or purposes for authentication tokens.
 *
 * @example
 * $source = TokenSource::PASSWORD_RESET;
 * echo $source->value;       // 'password_reset'
 * echo $source->getLabel();  // 'Password Reset'
 */
enum TokenSource: string
{
    case REGISTER = 'register';
    case LOGIN = 'login';
    case PASSWORD_RESET = 'password_reset';
    case EMAIL_VERIFICATION = 'email_verification';

    /**
     * Returns a human-readable label for the token source.
     *
     * @return string The human-readable label
     */
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
