<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit;

use AndyDefer\AuthenticationKit\Mail\MailServiceProvider;
use Illuminate\Support\ServiceProvider;

final class AuthenticationKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/authentication-kit.php',
            'authentication-kit'
        );

        $this->app->register(MailServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/authentication-kit.php' => config_path('authentication-kit.php'),
            ], 'authentication-kit-config');
        }
    }
}
