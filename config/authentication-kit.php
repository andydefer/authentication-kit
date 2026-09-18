<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Token Name
    |--------------------------------------------------------------------------
    |
    | The name used to identify generated authentication tokens.
    |
    */
    'token_name' => env('AUTH_KIT_TOKEN_NAME', 'authentication-kit'),

    /*
    |--------------------------------------------------------------------------
    | Password Reset Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of password reset OTPs a user can request within
    | the OTP validity window.
    |
    */
    'password_reset_rate_limit' => env('AUTH_KIT_PASSWORD_RESET_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Email Verification Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of email verification OTPs a user can request within
    | the OTP validity window.
    |
    */
    'email_verification_rate_limit' => env('AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Email Update Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of email update OTPs a user can request within
    | the OTP validity window.
    |
    */
    'email_update_rate_limit' => env('AUTH_KIT_EMAIL_UPDATE_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of two-factor authentication OTPs a user can request
    | within the OTP validity window.
    |
    */
    'two_factor_rate_limit' => env('AUTH_KIT_TWO_FACTOR_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Store Token In Cookie
    |--------------------------------------------------------------------------
    |
    | Whether authentication tokens should be stored in a secure cookie
    | after login or registration.
    |
    */
    'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),
];
