<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Http\Middleware\ValidateMailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Repositories\LogRepository;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ LogRepositoryInterface - bind interface to concrete
        $this->app->singleton(
            abstract: LogRepositoryInterface::class,
            concrete: LogRepository::class,
        );

        // ✅ EmailRegisterAction - bind action to container
        $this->app->singleton(
            abstract: EmailRegisterAction::class,
            concrete: function ($app): EmailRegisterAction {
                return new EmailRegisterAction(
                    nemesis: $app->make(NemesisInterface::class),
                    logRepository: $app->make(LogRepositoryInterface::class),
                    agent: $app->make(AgentInterface::class),
                    config: $app->make(AuthenticationKitConfig::class),
                );
            }
        );

        // ✅ EmailLoginAction - bind action to container
        $this->app->singleton(
            abstract: EmailLoginAction::class,
            concrete: function ($app): EmailLoginAction {
                return new EmailLoginAction(
                    nemesis: $app->make(NemesisInterface::class),
                    logRepository: $app->make(LogRepositoryInterface::class),
                    agent: $app->make(AgentInterface::class),
                    config: $app->make(AuthenticationKitConfig::class),
                );
            }
        );

        // ✅ EmailLogoutAction - bind action to container
        $this->app->singleton(
            abstract: EmailLogoutAction::class,
            concrete: function ($app): EmailLogoutAction {
                return new EmailLogoutAction(
                    nemesis: $app->make(NemesisInterface::class),
                    logRepository: $app->make(LogRepositoryInterface::class),
                );
            }
        );

        // ✅ Middleware
        $this->app->make(Router::class)->aliasMiddleware(
            name: 'validate.mail.authenticatable',
            class: ValidateMailAuthenticatable::class
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
