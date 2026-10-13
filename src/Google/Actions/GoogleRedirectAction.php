<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Exception;

final class GoogleRedirectAction extends AbstractAction
{
    private ?ErrorCode $error = null;

    public function __construct(
        private readonly GoogleOAuthService $googleOAuth,
    ) {}

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof GoogleRedirectRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof GoogleRedirectRecord) {
            $this->error = ErrorCode::INVALID_RECORD_TYPE;

            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
        }

        try {
            $data = $this->googleOAuth->authorizationRedirect($record);

            return ResponseFactory::json($data);
        } catch (Exception $e) {
            $this->error = ErrorCode::GOOGLE_REDIRECT_ERROR;

            return ErrorCode::GOOGLE_REDIRECT_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        }
    }
}
