<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Mail\MailServiceProvider;
use AndyDefer\AuthenticationKit\Services\Agent;
use Illuminate\Support\ServiceProvider;
use Jenssegers\Agent\Agent as JenssegersAgent;

final class AuthenticationKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/authentication-kit.php',
            'authentication-kit'
        );

        // ✅ AuthenticationKitConfigInterface - bind interface to concrete
        $this->app->singleton(
            abstract: AuthenticationKitConfigInterface::class,
            concrete: AuthenticationKitConfig::class,
        );

        // ✅ JenssegersAgent - bind concrete to container
        $this->app->singleton(
            abstract: JenssegersAgent::class,
            concrete: function (): JenssegersAgent {
                return new JenssegersAgent;
            }
        );

        // ✅ AgentInterface - bind interface to concrete (notre wrapper)
        $this->app->singleton(
            abstract: AgentInterface::class,
            concrete: function ($app): Agent {
                return new Agent(
                    agent: $app->make(JenssegersAgent::class),
                );
            }
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
