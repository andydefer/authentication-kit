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
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Handles Google's OAuth callback: delegates to the Google OAuth service
 * and returns the HTTP response produced by the service.
 *
 * The final presentation (JSON, redirect, session issuance, ...) is decided
 * by `GoogleOAuthInterface::onCallbackSuccess()` on the resolved service,
 * not by this Action. Override the service to change the behaviour.
 */
final class GoogleCallbackAction extends AbstractAction
{
    public function __construct(
        private readonly GoogleOAuthInterface $googleOAuth,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof GoogleCallbackRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof GoogleCallbackRecord) {
            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
        }

        try {
            return $this->googleOAuth->callback($record);
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
            // A Google HTTP failure (400/401/…) is wrapped by the HTTP client
            // into a generic RuntimeException whose `previous` is the
            // underlying Guzzle exception. That is still an expected OAuth
            // callback failure, not an unexpected application error.
            if ($this->isHttpClientFailure($e)) {
                return ErrorCode::GOOGLE_CALLBACK_ERROR->toJsonResponseFactory(
                    message: $e->getMessage(),
                );
            }

            return ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        }
    }

    /**
     * Whether the given throwable (or one of its causes) is a Guzzle
     * HTTP failure. Handles the case where the HTTP client wraps the
     * underlying Guzzle exception into a generic RuntimeException.
     */
    private function isHttpClientFailure(Throwable $exception): bool
    {
        $current = $exception;
        $depth = 0;

        while ($current !== null && $depth < 5) {
            if ($current instanceof GuzzleException) {
                return true;
            }

            $current = $current->getPrevious();
            $depth++;
        }

        return false;
    }
}
