<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

/**
 * Defines all authentication events that can be logged or dispatched.
 *
 * This enum centralizes event types used throughout the authentication package,
 * providing consistent naming and human-readable labels for each event.
 *
 * @example
 * $event = EventType::USER_LOGIN_SUCCESS;
 * echo $event->value;       // 'user_login_success'
 * echo $event->getLabel();  // 'User login successful'
 */
enum EventType: string
{
    /**
     * User registration successfully completed.
     */
    case USER_REGISTRATION_SUCCESS = 'user_registration_success';

    /**
     * User registration attempt failed.
     */
    case USER_REGISTRATION_FAILED = 'user_registration_failed';

    /**
     * User successfully logged in.
     */
    case USER_LOGIN_SUCCESS = 'user_login_success';

    /**
     * User login attempt failed.
     */
    case USER_LOGIN_FAILED = 'user_login_failed';

    /**
     * User successfully logged out.
     */
    case USER_LOGOUT_SUCCESS = 'user_logout_success';

    /**
     * User logout attempt failed.
     */
    case USER_LOGOUT_FAILED = 'user_logout_failed';

    /**
     * User password successfully reset.
     */
    case USER_PASSWORD_RESET_SUCCESS = 'user_password_reset_success';

    /**
     * User password reset attempt failed.
     */
    case USER_PASSWORD_RESET_FAILED = 'user_password_reset_failed';

    /**
     * Password reset link was successfully sent to the user.
     */
    case USER_PASSWORD_RESET_LINK_SENT = 'user_password_reset_link_sent';

    /**
     * Password reset link sending failed.
     */
    case USER_PASSWORD_RESET_LINK_FAILED = 'user_password_reset_link_failed';

    /**
     * Email verification was successfully completed.
     */
    case EMAIL_VERIFICATION_SUCCESS = 'email_verification_success';

    /**
     * Email verification attempted but the email was already verified.
     */
    case EMAIL_VERIFICATION_ALREADY_VERIFIED = 'email_verification_already_verified';

    /**
     * Email verification attempt failed.
     */
    case EMAIL_VERIFICATION_FAILED = 'email_verification_failed';

    /**
     * Returns a human-readable label for the event.
     *
     * These labels are intended for logging, UI display, or API responses
     * where a descriptive string is needed instead of the raw enum value.
     *
     * @return string The human-readable event label
     *
     * @example
     * $label = EventType::USER_LOGIN_SUCCESS->getLabel(); // 'User login successful'
     */
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
