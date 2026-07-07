<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Http\Middleware;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateMailAuthenticatable
{
    public function handle(Request $request, Closure $next): Response
    {
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

        return $next($request);
    }
}
