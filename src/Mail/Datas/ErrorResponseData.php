<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

/**
 * Response data for error responses.
 *
 * Contains error message, HTTP status code, error code,
 * and optional additional details.
 */
final class ErrorResponseData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly HttpStatusCode $status,
        public readonly ErrorCode $errorCode,
        public readonly ?DataObject $errors = null,
    ) {}
}
