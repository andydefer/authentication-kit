<?php

// src/Enums/ErrorType.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

/**
 * Error types for authentication events.
 */
enum ErrorType: string
{
    case USER_NOT_FOUND = 'user_not_found';
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case INVALID_OTP = 'invalid_otp';
    case RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    case TOKEN_NOT_FOUND = 'token_not_found';
    case TOKEN_REVOKE_FAILED = 'token_revoke_failed';
    case VALIDATION_ERROR = 'validation_error';
    case ACCOUNT_LOCKED = 'account_locked';
    case EMAIL_ALREADY_VERIFIED = 'email_already_verified';
    case INVALID_EMAIL = 'invalid_email';
    case PASSWORD_TOO_WEAK = 'password_too_weak';
    case INVALID_TOKEN = 'invalid_token';
    case TOKEN_EXPIRED = 'token_expired';
    case INVALID_RECORD_TYPE = 'invalid_record_type';
    case MISSING_CREDENTIALS = 'missing_credentials';

    public function message(): string
    {
        return match ($this) {
            self::USER_NOT_FOUND => 'User not found',
            self::INVALID_CREDENTIALS => 'Invalid credentials',
            self::INVALID_OTP => 'Invalid or expired OTP',
            self::RATE_LIMIT_EXCEEDED => 'Too many attempts, please try again later',
            self::TOKEN_NOT_FOUND => 'Authentication token not found',
            self::TOKEN_REVOKE_FAILED => 'Failed to revoke authentication token',
            self::VALIDATION_ERROR => 'Validation error',
            self::ACCOUNT_LOCKED => 'Account is locked',
            self::EMAIL_ALREADY_VERIFIED => 'Email already verified',
            self::INVALID_EMAIL => 'Invalid email format',
            self::PASSWORD_TOO_WEAK => 'Password is too weak',
            self::INVALID_TOKEN => 'Invalid token',
            self::TOKEN_EXPIRED => 'Token has expired',
            self::INVALID_RECORD_TYPE => 'Invalid record type',
            self::MISSING_CREDENTIALS => 'Email and password are required',
        };
    }
}
