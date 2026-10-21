<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Contracts;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleRedirectData;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;

/**
 * Contract for Google OAuth 2.0 / OpenID Connect operations.
 *
 * Exposes the server-side steps for both supported flows:
 *   - Authorization Code with PKCE (web / browser callback).
 *   - ID-token-only (Android Credential Manager, /google-android-callback).
 */
interface GoogleOAuthInterface
{
    /**
     * Build the Google authorization redirect payload (web flow).
     *
     * @param  GoogleRedirectRecord  $record  Redirect input (state, PKCE challenge, nonce).
     * @return GoogleRedirectData Contains the full authorization URL.
     */
    public function authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData;

    /**
     * Handle the Google OAuth browser callback (Authorization Code flow).
     *
     * @param  GoogleCallbackRecord  $record  Callback input (code, state, error).
     * @return ResponseFactory Response produced by onCallbackSuccess().
     *
     * @throws GoogleOAuthException
     */
    public function callback(GoogleCallbackRecord $record): ResponseFactory;

    /**
     * Handle the Google Android callback (ID-token-only flow).
     *
     * The caller already holds an id_token issued by Credential Manager.
     * The service verifies it against Google's JWKS, resolves the target
     * model class, decides login vs register, issues a Nemesis token, and
     * returns the final Android JSON payload.
     *
     * @param  GoogleAndroidCallbackRecord  $record  Callback input (model_type, id_token).
     * @return ResponseFactory Response produced by onAndroidCallbackSuccess().
     *
     * @throws GoogleOAuthException
     */
    public function androidCallback(GoogleAndroidCallbackRecord $record): ResponseFactory;

    /**
     * Decide what to return once the browser callback succeeded.
     *
     * Default implementation renders the configured Web presentation
     * (Blade view or Inertia page) with the verified GoogleCallbackData
     * under the `data` prop.
     *
     * @param  GoogleCallbackData  $data  Verified identity claims + Google tokens.
     * @return ResponseFactory Final HTTP response.
     */
    public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory;

    /**
     * Decide what to return once the Android callback succeeded.
     *
     * Default implementation returns a JSON response containing the
     * GoogleAndroidCallbackData payload.
     *
     * @param  GoogleAndroidCallbackData  $data  Android success payload.
     * @return ResponseFactory Final HTTP response.
     */
    public function onAndroidCallbackSuccess(GoogleAndroidCallbackData $data): ResponseFactory;
}
