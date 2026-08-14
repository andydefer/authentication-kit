<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Http\Middleware;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\Nemesis\Services\CookieTokenStorageService;
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
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware or controller
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next): Response
    {
        CookieTokenStorageService::class;

        $modelType = $request->input('model_type');

        if ($modelType === null) {
            return new JsonResponse(
                (new ErrorResponseData(
                    message: 'model_type is required',
                    status: 400,
                    errorCode: 'MODEL_TYPE_REQUIRED'
                ))->toArray(),
                400
            );
        }

        if (! class_exists($modelType)) {
            return new JsonResponse(
                (new ErrorResponseData(
                    message: "Model {$modelType} does not exist",
                    status: 500,
                    errorCode: 'MODEL_NOT_FOUND'
                ))->toArray(),
                500
            );
        }

        if (! in_array(MailAuthenticatable::class, class_implements($modelType) ?: [], true)) {
            return new JsonResponse(
                (new ErrorResponseData(
                    message: "Model {$modelType} must implement ".MailAuthenticatable::class,
                    status: 500,
                    errorCode: 'INVALID_MODEL'
                ))->toArray(),
                500
            );
        }

        app()->bind(MailAuthenticationInterface::class, function ($app) use ($modelType) {
            return MailAuthenticationService::for($modelType);
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
