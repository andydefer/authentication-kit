<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Enums\HttpResponseType;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;
use UnexpectedValueException;

/**
 * Handles the Google Android callback: the client already holds an
 * id_token (Credential Manager), sends it to /google-android-callback,
 * and receives a JSON response.
 *
 * Unlike the browser callback, this action returns JSON — Android has no
 * Web view to render. The response payload mirrors AuthAndroidGoogleData
 * on the Kotlin side.
 */
final class GoogleAndroidCallbackAction extends AbstractAction
{
    /**
     * Response types allowed for an Android callback.
     */
    private const ALLOWED_RESPONSE_TYPES = [
        HttpResponseType::JSON,
    ];

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
            $response = $this->googleOAuth->androidCallback($record);

            if (! in_array($response->getType(), self::ALLOWED_RESPONSE_TYPES, true)) {
                throw new UnexpectedValueException(sprintf(
                    'GoogleOAuthInterface::androidCallback() must return a JSON response, got "%s".',
                    $response->getType()->value,
                ));
            }

            return $response;
        } catch (GoogleOAuthException $e) {
            $code = $e->isProviderError()
                ? ErrorCode::GOOGLE_PROVIDER_ERROR
                : ErrorCode::GOOGLE_CALLBACK_ERROR;

            return $code->toJsonResponseFactory(message: $e->getMessage());
        } catch (UnexpectedValueException $e) {
            return ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        } catch (Exception $e) {
            $code = $this->isHttpClientFailure($e)
                ? ErrorCode::GOOGLE_CALLBACK_ERROR
                : ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR;

            return $code->toJsonResponseFactory(message: $e->getMessage());
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
