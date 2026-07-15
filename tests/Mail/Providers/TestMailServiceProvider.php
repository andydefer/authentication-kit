<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Providers;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Services\TestUserMailAuthenticationService;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Illuminate\Support\ServiceProvider;

final class TestMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ Lier l'interface MailAuthenticationInterface à l'implémentation de test
        $this->app->singleton(
            abstract: MailAuthenticationInterface::class,
            concrete: function ($app) {
                return new TestUserMailAuthenticationService(
                    nemesis: $app->make(NemesisInterface::class),
                    otpService: $app->make(OtpService::class),
                );
            }
        );
    }

    public function boot(): void
    {
        // Pas de boot nécessaire pour les tests
    }
}
