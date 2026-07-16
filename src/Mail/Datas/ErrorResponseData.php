<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

/**
 * Response data for error responses.
 *
 * Contains error message, HTTP status code, optional error code,
 * and optional additional details.
 */
final class ErrorResponseData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly ?DataObject $errors = null,
    ) {}
}
