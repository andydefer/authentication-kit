<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleRedirectAction;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;
use AndyDefer\AuthenticationKit\Google\Services\GooglePkceGenerator;
use AndyDefer\AuthenticationKit\Google\Services\GoogleStateStore;
use AndyDefer\PhpClient\Clients\ClientService;
use Illuminate\Support\ServiceProvider;

final class GoogleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // GoogleStateStoreInterface - bind interface to concrete
        $this->app->singleton(
            abstract: GoogleStateStoreInterface::class,
            concrete: GoogleStateStore::class,
        );

        // GooglePkceGenerator
        $this->app->singleton(
            abstract: GooglePkceGenerator::class,
            concrete: function ($app): GooglePkceGenerator {
                return new GooglePkceGenerator(
                    config: $app->make(AuthenticationKitConfig::class),
                );
            }
        );

        // GoogleIdTokenVerifier
        $this->app->singleton(
            abstract: GoogleIdTokenVerifier::class,
            concrete: function ($app): GoogleIdTokenVerifier {
                return new GoogleIdTokenVerifier(
                    client: $app->make(ClientService::class),
                    config: $app->make(AuthenticationKitConfig::class),
                );
            }
        );

        // GoogleOAuthService
        $this->app->singleton(
            abstract: GoogleOAuthService::class,
            concrete: function ($app): GoogleOAuthService {
                return new GoogleOAuthService(
                    client: $app->make(ClientService::class),
                    config: $app->make(AuthenticationKitConfig::class),
                    pkceGenerator: $app->make(GooglePkceGenerator::class),
                    stateStore: $app->make(GoogleStateStoreInterface::class),
                    idTokenVerifier: $app->make(GoogleIdTokenVerifier::class),
                );
            }
        );

        // GoogleRedirectAction
        $this->app->singleton(
            abstract: GoogleRedirectAction::class,
            concrete: function ($app): GoogleRedirectAction {
                return new GoogleRedirectAction(
                    googleOAuth: $app->make(GoogleOAuthService::class),
                );
            }
        );

        // GoogleCallbackAction
        $this->app->singleton(
            abstract: GoogleCallbackAction::class,
            concrete: function ($app): GoogleCallbackAction {
                return new GoogleCallbackAction(
                    googleOAuth: $app->make(GoogleOAuthService::class),
                );
            }
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/routes.php' => base_path('routes/authentication-google-kit.php'),
            ], 'authentication-google-kit-routes');
        }
    }
}
