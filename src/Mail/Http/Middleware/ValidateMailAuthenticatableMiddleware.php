<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Http\Middleware;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that validates the 'model_type' field in incoming requests.
 */
final class ValidateMailAuthenticatableMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $modelType = $request->input('model_type');

        if ($modelType === null) {
            return ErrorCode::MODEL_TYPE_REQUIRED->toJsonResponseFactory()->toResponse();
        }

        if (! class_exists($modelType)) {
            return ErrorCode::MODEL_NOT_FOUND
                ->toJsonResponseFactory(message: "Model {$modelType} does not exist")
                ->toResponse();
        }

        if (! in_array(MailAuthenticatable::class, class_implements($modelType) ?: [], true)) {
            return ErrorCode::INVALID_MODEL
                ->toJsonResponseFactory(
                    message: "Model {$modelType} must implement ".MailAuthenticatable::class,
                )
                ->toResponse();
        }

        app()->bind(MailAuthenticationInterface::class, function ($app) use ($modelType) {
            /** @var MailAuthenticatable $modelType */
            return $modelType::getMailAuthService();
        });

        $response = $next($request);

        $config = app(AuthenticationKitConfigInterface::class);

        if ($config->shouldStoreTokenInCookie()) {
            $queuedCookies = Cookie::getQueuedCookies();

            foreach ($queuedCookies as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }

        return $response;
    }
}
