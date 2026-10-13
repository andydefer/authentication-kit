<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Contracts;

use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleRedirectData;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;

/**
 * Contract for Google OAuth 2.0 / OpenID Connect operations.
 */
interface GoogleOAuthInterface
{
    /**
     * Build the Google authorization redirect payload.
     */
    public function authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData;

    /**
     * Handle the Google OAuth callback: exchange code, verify id_token.
     */
    public function callback(GoogleCallbackRecord $record): GoogleCallbackData;
}
