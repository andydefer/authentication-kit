<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Token Name
    |--------------------------------------------------------------------------
    |
    | The name used for the authentication token.
    |
    */
    'token_name' => env('AUTH_KIT_TOKEN_NAME', 'authentication-kit'),

    /*
    |--------------------------------------------------------------------------
    | Password Reset Rate Limit
    |--------------------------------------------------------------------------
    |
    | Number of password reset OTP attempts allowed per period.
    | Default: 3 attempts.
    |
    */
    'password_reset_rate_limit' => env('AUTH_KIT_PASSWORD_RESET_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Email Verification Rate Limit
    |--------------------------------------------------------------------------
    |
    | Number of email verification OTP attempts allowed per period.
    | Default: 5 attempts.
    |
    */
    'email_verification_rate_limit' => env('AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT', 5),
];
