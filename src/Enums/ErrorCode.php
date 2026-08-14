<?php

// src/Enums/ErrorCode.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

/**
 * Error codes for authentication API responses.
 */
enum ErrorCode: string
{
    /**
     * Invalid record type.
     */
    case INVALID_RECORD_TYPE = 'INVALID_RECORD_TYPE';

    /**
     * Password confirmation mismatch.
     */
    case PASSWORD_CONFIRMATION_MISMATCH = 'PASSWORD_CONFIRMATION_MISMATCH';

    /**
     * Invalid or expired reset OTP.
     */
    case INVALID_RESET_OTP = 'INVALID_RESET_OTP';

    /**
     * Reset password error.
     */
    case RESET_PASSWORD_ERROR = 'RESET_PASSWORD_ERROR';

    /**
     * User fetch error.
     */
    case USER_FETCH_ERROR = 'USER_FETCH_ERROR';

    /**
     * Missing credentials.
     */
    case MISSING_CREDENTIALS = 'MISSING_CREDENTIALS';

    /**
     * Invalid credentials.
     */
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';

    /**
     * Authenticatable not found.
     */
    case AUTHENTICATABLE_NOT_FOUND = 'AUTHENTICATABLE_NOT_FOUND';

    /**
     * Validation error.
     */
    case VALIDATION_ERROR = 'VALIDATION_ERROR';

    /**
     * Login error.
     */
    case LOGIN_ERROR = 'LOGIN_ERROR';

    /**
     * Invalid token.
     */
    case INVALID_TOKEN = 'INVALID_TOKEN';

    /**
     * Token expired.
     */
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';

    /**
     * Logout failed.
     */
    case LOGOUT_FAILED = 'LOGOUT_FAILED';

    /**
     * Logout exception.
     */
    case LOGOUT_EXCEPTION = 'LOGOUT_EXCEPTION';

    /**
     * Registration error.
     */
    case REGISTRATION_ERROR = 'REGISTRATION_ERROR';

    /**
     * Model not found.
     */
    case MODEL_NOT_FOUND = 'MODEL_NOT_FOUND';

    /**
     * Invalid model.
     */
    case INVALID_MODEL = 'INVALID_MODEL';

    /**
     * Verification OTP resend failed.
     */
    case VERIFICATION_OTP_RESEND_FAILED = 'VERIFICATION_OTP_RESEND_FAILED';

    /**
     * Verification email resend error.
     */
    case VERIFICATION_EMAIL_RESEND_ERROR = 'VERIFICATION_EMAIL_RESEND_ERROR';

    /**
     * Reset link error.
     */
    case RESET_LINK_ERROR = 'RESET_LINK_ERROR';

    /**
     * Invalid verification OTP.
     */
    case INVALID_VERIFICATION_OTP = 'INVALID_VERIFICATION_OTP';

    /**
     * Verify email error.
     */
    case VERIFY_EMAIL_ERROR = 'VERIFY_EMAIL_ERROR';

    /**
     * Get the user-friendly message for this error code.
     */
    public function message(): string
    {
        return match ($this) {
            self::INVALID_RECORD_TYPE => 'Invalid record type',
            self::PASSWORD_CONFIRMATION_MISMATCH => 'Password confirmation does not match',
            self::INVALID_RESET_OTP => 'Invalid or expired reset OTP',
            self::RESET_PASSWORD_ERROR => 'An error occurred while resetting the password',
            self::USER_FETCH_ERROR => 'An error occurred while fetching the current user',
            self::MISSING_CREDENTIALS => 'Email and password are required',
            self::INVALID_CREDENTIALS => 'Invalid credentials',
            self::AUTHENTICATABLE_NOT_FOUND => 'Authenticatable not found',
            self::VALIDATION_ERROR => 'Validation error',
            self::LOGIN_ERROR => 'An error occurred during login',
            self::INVALID_TOKEN => 'Invalid token',
            self::TOKEN_EXPIRED => 'Token has expired',
            self::LOGOUT_FAILED => 'Logout failed',
            self::LOGOUT_EXCEPTION => 'An error occurred during logout',
            self::REGISTRATION_ERROR => 'An error occurred during registration',
            self::MODEL_NOT_FOUND => 'Model does not exist',
            self::INVALID_MODEL => 'Model must implement MailAuthenticatable',
            self::VERIFICATION_OTP_RESEND_FAILED => 'Failed to resend verification OTP',
            self::VERIFICATION_EMAIL_RESEND_ERROR => 'An error occurred while resending verification OTP',
            self::RESET_LINK_ERROR => 'An error occurred while sending the reset OTP',
            self::INVALID_VERIFICATION_OTP => 'Invalid or expired verification OTP',
            self::VERIFY_EMAIL_ERROR => 'An error occurred while verifying email',
        };
    }

    /**
     * Get the HTTP status code for this error.
     */
    public function getHttpStatusCode(): int
    {
        return match ($this) {
            self::INVALID_RECORD_TYPE,
            self::RESET_PASSWORD_ERROR,
            self::USER_FETCH_ERROR,
            self::LOGIN_ERROR,
            self::LOGOUT_FAILED,
            self::LOGOUT_EXCEPTION,
            self::REGISTRATION_ERROR,
            self::MODEL_NOT_FOUND,
            self::INVALID_MODEL,
            self::VERIFICATION_OTP_RESEND_FAILED,
            self::VERIFICATION_EMAIL_RESEND_ERROR,
            self::RESET_LINK_ERROR,
            self::VERIFY_EMAIL_ERROR => 500,

            self::PASSWORD_CONFIRMATION_MISMATCH,
            self::VALIDATION_ERROR => 422,

            self::INVALID_RESET_OTP,
            self::MISSING_CREDENTIALS,
            self::INVALID_VERIFICATION_OTP => 400,

            self::INVALID_CREDENTIALS,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED => 401,

            self::AUTHENTICATABLE_NOT_FOUND => 404,
        };
    }
}
