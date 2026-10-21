<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Contracts;

use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleRedirectData;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleAndroidCallbackVO;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleCallbackVO;
use AndyDefer\Nemesis\Datas\ErrorResponseData;

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
     * Verify the browser callback and delegate to onCallbackSuccess().
     *
     * @throws GoogleOAuthException
     */
    public function callback(GoogleCallbackRecord $record): GoogleCallbackData|ErrorResponseData;

    /**
     * Verify the Android ID-token and delegate to onAndroidCallbackSuccess().
     *
     * @throws GoogleOAuthException
     */
    public function androidCallback(GoogleAndroidCallbackRecord $record): GoogleAndroidCallbackData|ErrorResponseData;

    /**
     * Application-defined web success handler.
     *
     * Receives a GoogleCallbackVO carrying verified Google tokens + claims.
     * The host app resolves/creates the user, issues a token, and returns
     * a populated GoogleCallbackData. On failure, returns ErrorResponseData.
     */
    public function onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData;

    /**
     * Application-defined Android success handler.
     *
     * Receives a GoogleAndroidCallbackVO carrying verified claims + model
     * type. The host app resolves/creates the user, issues a token, and
     * returns a populated GoogleAndroidCallbackData. On failure, returns
     * ErrorResponseData.
     */
    public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData;
}
