<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleRedirectAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleAndroidCallbackRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleRedirectRequest;
use Illuminate\Support\Facades\Route;

/*
 * Authentication Routes for Google OAuth
 *
 * This route file defines the endpoints required to complete the Google
 * OAuth 2.0 flows supported by the package:
 *
 *  - /google-redirect          : builds the authorization URL (web flow).
 *  - /google-callback          : handles Google's redirect back (web flow,
 *                                browser navigation — must NOT be under the
 *                                "api" prefix since it renders a view).
 *  - /google-android-callback  : handles the Android Credential Manager
 *                                id_token (ID-token-only flow, JSON).
 *
 * @package AndyDefer\AuthenticationKit\Google
 */

// ----------------------------------------------------------------------------
// Web flow — browser navigation, view/Inertia rendering.
// ----------------------------------------------------------------------------

Route::name('web.')->group(function (): void {
    Route::get('/google-callback', action_route(
        GoogleCallbackRequest::class,
        GoogleCallbackAction::class
    ))->name('google-callback');
});

// ----------------------------------------------------------------------------
// API flow — JSON responses.
// ----------------------------------------------------------------------------

Route::name('api.')->group(function (): void {

    // Build the Google authorization URL
    Route::post('/google-redirect', action_route(
        GoogleRedirectRequest::class,
        GoogleRedirectAction::class
    ))->name('google-redirect');

    // Handle the Android Credential Manager id_token (mobile flow)
    Route::post('/google-android-callback', action_route(
        GoogleAndroidCallbackRequest::class,
        GoogleAndroidCallbackAction::class
    ))->name('google-android-callback');

});
