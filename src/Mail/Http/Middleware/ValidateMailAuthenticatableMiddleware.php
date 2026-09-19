<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Http\Middleware;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that validates the 'model_type' field in incoming requests.
 *
 * Ensures that the provided model type exists and implements the
 * MailAuthenticatable interface before allowing the request to proceed.
 */
final class ValidateMailAuthenticatableMiddleware
{
    /**
     * Handles the incoming request and validates the model type.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $modelType = $request->input('model_type');

        if ($modelType === null) {
            return $this->error(ErrorCode::MODEL_TYPE_REQUIRED);
        }

        if (! class_exists($modelType)) {
            return $this->error(ErrorCode::MODEL_NOT_FOUND, "Model {$modelType} does not exist");
        }

        if (! in_array(MailAuthenticatable::class, class_implements($modelType) ?: [], true)) {
            return $this->error(
                ErrorCode::INVALID_MODEL,
                "Model {$modelType} must implement ".MailAuthenticatable::class
            );
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

    /**
     * Builds a standardized error response from an ErrorCode case.
     */
    private function error(ErrorCode $code, ?string $overrideMessage = null): JsonResponse
    {
        return new JsonResponse(
            $code->toResponseData(message: $overrideMessage)->toArray(),
            $code->getHttpStatusCode()->value,
        );
    }
}
