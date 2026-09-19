<?php

// src/Enums/ErrorCode.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Enums;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\ErrorDescribable;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

/**
 * Error codes for authentication API responses.
 */
enum ErrorCode: string implements ErrorDescribable
{
    case INVALID_RECORD_TYPE = 'INVALID_RECORD_TYPE';
    case PASSWORD_CONFIRMATION_MISMATCH = 'PASSWORD_CONFIRMATION_MISMATCH';
    case PASSWORD_VALIDATION_FAILED = 'PASSWORD_VALIDATION_FAILED';
    case INVALID_RESET_OTP = 'INVALID_RESET_OTP';
    case RESET_PASSWORD_ERROR = 'RESET_PASSWORD_ERROR';
    case RESET_LINK_FAILED = 'RESET_LINK_FAILED';
    case RESET_LINK_ERROR = 'RESET_LINK_ERROR';
    case USER_FETCH_ERROR = 'USER_FETCH_ERROR';
    case MISSING_CREDENTIALS = 'MISSING_CREDENTIALS';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case AUTHENTICATABLE_NOT_FOUND = 'AUTHENTICATABLE_NOT_FOUND';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case LOGIN_ERROR = 'LOGIN_ERROR';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case LOGOUT_FAILED = 'LOGOUT_FAILED';
    case LOGOUT_EXCEPTION = 'LOGOUT_EXCEPTION';
    case REGISTRATION_ERROR = 'REGISTRATION_ERROR';
    case MODEL_NOT_FOUND = 'MODEL_NOT_FOUND';
    case INVALID_MODEL = 'INVALID_MODEL';
    case MODEL_TYPE_REQUIRED = 'MODEL_TYPE_REQUIRED';
    case VERIFICATION_OTP_RESEND_FAILED = 'VERIFICATION_OTP_RESEND_FAILED';
    case VERIFICATION_EMAIL_RESEND_ERROR = 'VERIFICATION_EMAIL_RESEND_ERROR';
    case INVALID_VERIFICATION_OTP = 'INVALID_VERIFICATION_OTP';
    case VERIFY_EMAIL_ERROR = 'VERIFY_EMAIL_ERROR';
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case MODEL_TYPE_MISMATCH = 'MODEL_TYPE_MISMATCH';
    case USER_FORMAT_ERROR = 'USER_FORMAT_ERROR';
    case EMAIL_UPDATE_FAILED = 'EMAIL_UPDATE_FAILED';
    case EMAIL_UPDATE_ERROR = 'EMAIL_UPDATE_ERROR';
    case EMAIL_UPDATE_SEND_FAILED = 'EMAIL_UPDATE_SEND_FAILED';
    case EMAIL_UPDATE_SEND_ERROR = 'EMAIL_UPDATE_SEND_ERROR';
    case TWO_FACTOR_SEND_FAILED = 'TWO_FACTOR_SEND_FAILED';
    case TWO_FACTOR_SEND_ERROR = 'TWO_FACTOR_SEND_ERROR';
    case TWO_FACTOR_VERIFY_FAILED = 'TWO_FACTOR_VERIFY_FAILED';
    case TWO_FACTOR_VERIFY_ERROR = 'TWO_FACTOR_VERIFY_ERROR';

    /**
     * {@inheritDoc}
     */
    public function getHttpStatusCode(): HttpStatusCode
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
            self::RESET_LINK_ERROR => HttpStatusCode::UNPROCESSABLE_ENTITY,

            self::INVALID_RESET_OTP,
            self::MISSING_CREDENTIALS,
            self::INVALID_VERIFICATION_OTP,
            self::EMAIL_UPDATE_FAILED,
            self::TWO_FACTOR_VERIFY_FAILED,
            self::RESET_LINK_FAILED,
            self::MODEL_TYPE_REQUIRED => HttpStatusCode::BAD_REQUEST,

            self::INVALID_CREDENTIALS,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED,
            self::UNAUTHENTICATED => HttpStatusCode::UNAUTHORIZED,

            self::EMAIL_UPDATE_SEND_FAILED,
            self::TWO_FACTOR_SEND_FAILED => HttpStatusCode::TOO_MANY_REQUESTS,

            self::AUTHENTICATABLE_NOT_FOUND => HttpStatusCode::NOT_FOUND,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
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
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return $this->getMessage();
    }

    /**
     * {@inheritDoc}
     */
    public function toResponseData(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ErrorResponseData {
        return ErrorResponseData::from([
            'message' => $message ?? $this->getMessage(),
            'status' => $this->getHttpStatusCode()->value,
            'errorCode' => $this->value,
            'errors' => $errors,
        ]);
    }
}
