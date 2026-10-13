<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Contracts;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleRedirectData;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;

/**
 * Contract for Google OAuth 2.0 / OpenID Connect operations.
 *
 * Exposes the two server-side steps of the Authorization Code flow with
 * PKCE: building the authorization URL and handling Google's redirect back.
 */
interface GoogleOAuthInterface
{
    /**
     * Build the Google authorization redirect payload.
     *
     * @param  GoogleRedirectRecord  $record  Redirect input (state, PKCE challenge, nonce).
     * @return GoogleRedirectData Contains the full authorization URL.
     */
    public function authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData;

    /**
     * Handle the Google OAuth callback: validate state, exchange code,
     * verify the ID token, then return the final HTTP response.
     *
     * @param  GoogleCallbackRecord  $record  Callback input (code, state, error).
     * @return ResponseFactory Response produced by onCallbackSuccess().
     *
     * @throws GoogleOAuthException
     */
    public function callback(GoogleCallbackRecord $record): ResponseFactory;

    /**
     * Decide what to return once the callback succeeded.
     *
     * Default implementation returns the verified GoogleCallbackData as
     * JSON. Override this method to change the final behaviour (persist
     * the user, issue a session, redirect, ...).
     *
     * @param  GoogleCallbackData  $data  Verified identity claims + Google tokens.
     * @return ResponseFactory Final HTTP response.
     */
    public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory;
}
