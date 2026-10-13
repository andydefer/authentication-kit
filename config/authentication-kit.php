<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Token Name
    |--------------------------------------------------------------------------
    */
    'token_name' => env('AUTH_KIT_TOKEN_NAME', 'authentication-kit'),

    /*
    |--------------------------------------------------------------------------
    | Password Reset Rate Limit
    |--------------------------------------------------------------------------
    */
    'password_reset_rate_limit' => env('AUTH_KIT_PASSWORD_RESET_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Email Verification Rate Limit
    |--------------------------------------------------------------------------
    */
    'email_verification_rate_limit' => env('AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Email Update Rate Limit
    |--------------------------------------------------------------------------
    */
    'email_update_rate_limit' => env('AUTH_KIT_EMAIL_UPDATE_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Rate Limit
    |--------------------------------------------------------------------------
    */
    'two_factor_rate_limit' => env('AUTH_KIT_TWO_FACTOR_RATE_LIMIT', 3),

    /*
    |--------------------------------------------------------------------------
    | Store Token In Cookie
    |--------------------------------------------------------------------------
    */
    'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication Cookie Duration
    |--------------------------------------------------------------------------
    */
    'cookie_duration' => env('AUTH_KIT_COOKIE_DURATION', 525600),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth
    |--------------------------------------------------------------------------
    |
    | Configuration for Google OAuth 2.0 / OpenID Connect authentication.
    | The client_id and client_secret are obtained from the Google Cloud
    | Console. The redirect_uri must match one of the authorized redirect
    | URIs registered on the OAuth client.
    |
    */
    'google' => [
        'client_id' => env('AUTH_KIT_GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('AUTH_KIT_GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => env('AUTH_KIT_GOOGLE_REDIRECT_URI', ''),

        'authorization_endpoint' => env(
            'AUTH_KIT_GOOGLE_AUTHORIZATION_ENDPOINT',
            'https://accounts.google.com/o/oauth2/v2/auth'
        ),
        'token_endpoint' => env(
            'AUTH_KIT_GOOGLE_TOKEN_ENDPOINT',
            'https://oauth2.googleapis.com/token'
        ),
        'jwks_endpoint' => env(
            'AUTH_KIT_GOOGLE_JWKS_ENDPOINT',
            'https://www.googleapis.com/oauth2/v3/certs'
        ),

        'scopes' => ['openid', 'email', 'profile'],

        'state_lifetime' => env('AUTH_KIT_GOOGLE_STATE_LIFETIME', 600),
        'pkce_verifier_length' => env('AUTH_KIT_GOOGLE_PKCE_VERIFIER_LENGTH', 64),
    ],
];
