<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Handles the Google Android callback (ID-token-only flow).
 *
 * The action delegates verification and user/token resolution to the
 * service, then returns the resulting JSON payload.
 *
 * The action NEVER throws to produce a response: every failure path
 * returns a ResponseFactory.
 */
final class GoogleAndroidCallbackAction extends AbstractAction
{
    public function __construct(
        private readonly GoogleOAuthInterface $googleOAuth,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof GoogleAndroidCallbackRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof GoogleAndroidCallbackRecord) {
            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
        }

        try {
            $result = $this->googleOAuth->androidCallback($record);
        } catch (GoogleOAuthException $e) {
            $code = $e->isProviderError()
                ? ErrorCode::GOOGLE_PROVIDER_ERROR
                : ErrorCode::GOOGLE_CALLBACK_ERROR;

            return $code->toJsonResponseFactory(message: $e->getMessage());
        } catch (Exception $e) {
            $code = $this->isHttpClientFailure($e)
                ? ErrorCode::GOOGLE_CALLBACK_ERROR
                : ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR;

            return $code->toJsonResponseFactory(message: $e->getMessage());
        }

        return match (true) {
            $result instanceof ErrorResponseData => ResponseFactory::json($result, $result->status),
            $result instanceof GoogleAndroidCallbackData => ResponseFactory::json($result),
            default => ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR->toJsonResponseFactory(
                message: 'GoogleOAuthInterface::androidCallback() returned an unexpected type.',
            ),
        };
    }

    /**
     * Whether the given throwable (or one of its causes) is a Guzzle
     * HTTP failure.
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
