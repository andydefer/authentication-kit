<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Exception;

final class GoogleCallbackAction extends AbstractAction
{
    public function __construct(
        private readonly GoogleOAuthInterface $googleOAuth,
    ) {}

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof GoogleCallbackRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof GoogleCallbackRecord) {
            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
        }

        try {
            return ResponseFactory::json(
                $this->googleOAuth->callback($record)
            );
        } catch (GoogleOAuthException $e) {
            if ($e->isProviderError()) {
                return ErrorCode::GOOGLE_PROVIDER_ERROR->toJsonResponseFactory(
                    message: $e->getMessage(),
                );
            }

            return ErrorCode::GOOGLE_CALLBACK_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        } catch (Exception $e) {
            return ErrorCode::GOOGLE_CALLBACK_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        }
    }
}
