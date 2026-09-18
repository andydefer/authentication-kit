<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Data transfer object for successful authentication responses.
 *
 * Provides a consistent structure for success responses across the
 * authentication package: a message, an HTTP status and optional details.
 */
final class SuccessResponseData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly int $status = 200,
        public readonly ?StrictAssociative $details = null,
    ) {}
}
