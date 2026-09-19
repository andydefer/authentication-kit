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
     * Password validation failed.
     */
    case PASSWORD_VALIDATION_FAILED = 'PASSWORD_VALIDATION_FAILED';

    /**
     * Invalid or expired reset OTP.
     */
    case INVALID_RESET_OTP = 'INVALID_RESET_OTP';

    /**
     * Reset password error.
     */
    case RESET_PASSWORD_ERROR = 'RESET_PASSWORD_ERROR';

    /**
     * Reset link failed.
     */
    case RESET_LINK_FAILED = 'RESET_LINK_FAILED';

    /**
     * Reset link error.
     */
    case RESET_LINK_ERROR = 'RESET_LINK_ERROR';

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
     * Model type is required.
     */
    case MODEL_TYPE_REQUIRED = 'MODEL_TYPE_REQUIRED';

    /**
     * Verification OTP resend failed.
     */
    case VERIFICATION_OTP_RESEND_FAILED = 'VERIFICATION_OTP_RESEND_FAILED';

    /**
     * Verification email resend error.
     */
    case VERIFICATION_EMAIL_RESEND_ERROR = 'VERIFICATION_EMAIL_RESEND_ERROR';

    /**
     * Invalid verification OTP.
     */
    case INVALID_VERIFICATION_OTP = 'INVALID_VERIFICATION_OTP';

    /**
     * Verify email error.
     */
    case VERIFY_EMAIL_ERROR = 'VERIFY_EMAIL_ERROR';

    /**
     * Unauthenticated.
     */
    case UNAUTHENTICATED = 'UNAUTHENTICATED';

    /**
     * Model type mismatch.
     */
    case MODEL_TYPE_MISMATCH = 'MODEL_TYPE_MISMATCH';

    /**
     * User format error.
     */
    case USER_FORMAT_ERROR = 'USER_FORMAT_ERROR';

    /**
     * Email update failed.
     */
    case EMAIL_UPDATE_FAILED = 'EMAIL_UPDATE_FAILED';

    /**
     * Email update error.
     */
    case EMAIL_UPDATE_ERROR = 'EMAIL_UPDATE_ERROR';

    /**
     * Email update send failed.
     */
    case EMAIL_UPDATE_SEND_FAILED = 'EMAIL_UPDATE_SEND_FAILED';

    /**
     * Email update send error.
     */
    case EMAIL_UPDATE_SEND_ERROR = 'EMAIL_UPDATE_SEND_ERROR';

    /**
     * Two-factor send failed.
     */
    case TWO_FACTOR_SEND_FAILED = 'TWO_FACTOR_SEND_FAILED';

    /**
     * Two-factor send error.
     */
    case TWO_FACTOR_SEND_ERROR = 'TWO_FACTOR_SEND_ERROR';

    /**
     * Two-factor verify failed.
     */
    case TWO_FACTOR_VERIFY_FAILED = 'TWO_FACTOR_VERIFY_FAILED';

    /**
     * Two-factor verify error.
     */
    case TWO_FACTOR_VERIFY_ERROR = 'TWO_FACTOR_VERIFY_ERROR';

    /**
     * Get the user-friendly message for this error code.
     */
    public function message(): string
    {
        return match ($this) {
            self::INVALID_RECORD_TYPE => 'Invalid record type',
            self::PASSWORD_CONFIRMATION_MISMATCH => 'Password confirmation does not match',
            self::PASSWORD_VALIDATION_FAILED => 'Password validation failed',
            self::INVALID_RESET_OTP => 'Invalid or expired reset OTP',
            self::RESET_PASSWORD_ERROR => 'An error occurred while resetting the password',
            self::RESET_LINK_FAILED => 'We were unable to process your request. Please try again.',
            self::RESET_LINK_ERROR => 'We were unable to send the reset link. Please try again.',
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
            self::MODEL_TYPE_REQUIRED => 'model_type is required',
            self::VERIFICATION_OTP_RESEND_FAILED => 'Failed to resend verification OTP',
            self::VERIFICATION_EMAIL_RESEND_ERROR => 'An error occurred while resending verification OTP',
            self::INVALID_VERIFICATION_OTP => 'Invalid or expired verification OTP',
            self::VERIFY_EMAIL_ERROR => 'An error occurred while verifying email',
            self::UNAUTHENTICATED => 'Unauthenticated',
            self::MODEL_TYPE_MISMATCH => 'Model type mismatch',
            self::USER_FORMAT_ERROR => 'User data format not available',
            self::EMAIL_UPDATE_FAILED => 'Invalid or expired code, or email already taken',
            self::EMAIL_UPDATE_ERROR => 'An error occurred while confirming the email update',
            self::EMAIL_UPDATE_SEND_FAILED => 'Unable to send email update code',
            self::EMAIL_UPDATE_SEND_ERROR => 'An error occurred while sending the email update code',
            self::TWO_FACTOR_SEND_FAILED => 'Unable to send two-factor code',
            self::TWO_FACTOR_SEND_ERROR => 'An error occurred while sending the two-factor code',
            self::TWO_FACTOR_VERIFY_FAILED => 'Invalid or expired two-factor code',
            self::TWO_FACTOR_VERIFY_ERROR => 'An error occurred while verifying the two-factor code',
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
            self::VERIFY_EMAIL_ERROR,
            self::MODEL_TYPE_MISMATCH,
            self::USER_FORMAT_ERROR,
            self::EMAIL_UPDATE_ERROR,
            self::EMAIL_UPDATE_SEND_ERROR,
            self::TWO_FACTOR_SEND_ERROR,
            self::TWO_FACTOR_VERIFY_ERROR,
            self::PASSWORD_CONFIRMATION_MISMATCH,
            self::PASSWORD_VALIDATION_FAILED,
            self::VALIDATION_ERROR,
            self::RESET_LINK_ERROR => 422,

            self::INVALID_RESET_OTP,
            self::MISSING_CREDENTIALS,
            self::INVALID_VERIFICATION_OTP,
            self::EMAIL_UPDATE_FAILED,
            self::TWO_FACTOR_VERIFY_FAILED,
            self::RESET_LINK_FAILED,
            self::MODEL_TYPE_REQUIRED => 400,

            self::INVALID_CREDENTIALS,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED,
            self::UNAUTHENTICATED => 401,

            self::EMAIL_UPDATE_SEND_FAILED,
            self::TWO_FACTOR_SEND_FAILED => 429,

            self::AUTHENTICATABLE_NOT_FOUND => 404,
        };
    }
}
