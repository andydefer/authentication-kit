<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use Illuminate\Support\Facades\Route;

Route::post('/register', action_route(EmailRegisterRequest::class, EmailRegisterAction::class));
