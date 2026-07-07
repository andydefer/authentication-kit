<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(['validate.mail.authenticatable'])->group(function () {
    Route::post('/register', action_route(EmailRegisterRequest::class, EmailRegisterAction::class));
    Route::post('/login', action_route(EmailLoginRequest::class, EmailLoginAction::class));
});
