<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleRedirectAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleRedirectRequest;
use Illuminate\Support\Facades\Route;

/*
 * Authentication Routes for Google OAuth
 *
 * This route file defines the two endpoints required to complete the
 * Google OAuth 2.0 Authorization Code flow with PKCE:
 *
 *  - /google-redirect : builds the Google authorization URL and returns it.
 *  - /google-callback : handles Google's redirect back, exchanges the code,
 *                       verifies the ID token, and returns the verified
 *                       claims along with the Google tokens.
 *
 * @package AndyDefer\AuthenticationKit\Google
 */

Route::name('api.')->group(function (): void {

    // Build the Google authorization URL
    Route::post('/google-redirect', action_route(
        GoogleRedirectRequest::class,
        GoogleRedirectAction::class
    ))->name('google-redirect');

    // Handle Google's redirect back from the consent screen
    Route::get('/google-callback', action_route(
        GoogleCallbackRequest::class,
        GoogleCallbackAction::class
    ))->name('google-callback');

});
