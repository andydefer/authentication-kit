<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

enum EventType: string
{
    case USER_REGISTRATION_SUCCESS = 'user_registration_success';
    case USER_REGISTRATION_FAILED = 'user_registration_failed';
    case USER_LOGIN_SUCCESS = 'user_login_success';
    case USER_LOGIN_FAILED = 'user_login_failed';
    case USER_LOGOUT_SUCCESS = 'user_logout_success';
    case USER_LOGOUT_FAILED = 'user_logout_failed';
    case USER_PASSWORD_RESET_SUCCESS = 'user_password_reset_success';
    case USER_PASSWORD_RESET_FAILED = 'user_password_reset_failed';
    case USER_PASSWORD_RESET_LINK_SENT = 'user_password_reset_link_sent';
    case USER_PASSWORD_RESET_LINK_FAILED = 'user_password_reset_link_failed';
    case EMAIL_VERIFICATION_SUCCESS = 'email_verification_success';
    case EMAIL_VERIFICATION_ALREADY_VERIFIED = 'email_verification_already_verified';
    case EMAIL_VERIFICATION_FAILED = 'email_verification_failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::USER_REGISTRATION_SUCCESS => 'User registration successful',
            self::USER_REGISTRATION_FAILED => 'User registration failed',
            self::USER_LOGIN_SUCCESS => 'User login successful',
            self::USER_LOGIN_FAILED => 'User login failed',
            self::USER_LOGOUT_SUCCESS => 'User logout successful',
            self::USER_LOGOUT_FAILED => 'User logout failed',
            self::USER_PASSWORD_RESET_SUCCESS => 'User password reset successful',
            self::USER_PASSWORD_RESET_FAILED => 'User password reset failed',
            self::USER_PASSWORD_RESET_LINK_SENT => 'Password reset link sent',
            self::USER_PASSWORD_RESET_LINK_FAILED => 'Password reset link failed',
            self::EMAIL_VERIFICATION_SUCCESS => 'Email verification successful',
            self::EMAIL_VERIFICATION_ALREADY_VERIFIED => 'Email already verified',
            self::EMAIL_VERIFICATION_FAILED => 'Email verification failed',
        };
    }
}
