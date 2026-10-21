<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Enums\HttpResponseType;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;
use UnexpectedValueException;

/**
 * Handles Google's OAuth callback: delegates to the Google OAuth service
 * and returns the HTTP response produced by the service.
 *
 * The callback is a browser round-trip: every response — success or
 * failure — is rendered through the configured Web presentation
 * (`authentication-kit.google.callback.type` = "view" | "inertia").
 * Errors are never returned as JSON: the full ErrorResponseData is
 * passed to the view / Inertia component via the `error` prop.
 */
final class GoogleCallbackAction extends AbstractAction
{
    /**
     * Response types allowed for a Google callback.
     */
    private const ALLOWED_RESPONSE_TYPES = [
        HttpResponseType::VIEW,
        HttpResponseType::INERTIA,
    ];

    private const PROP_ERROR = 'error';

    public function __construct(
        private readonly GoogleOAuthInterface $googleOAuth,
        private readonly AuthenticationKitConfigInterface $config,
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
            return $this->renderError(
                ErrorCode::INVALID_RECORD_TYPE->toResponseData(),
            );
        }

        try {
            $response = $this->googleOAuth->callback($record);

            if (! in_array($response->getType(), self::ALLOWED_RESPONSE_TYPES, true)) {
                throw new UnexpectedValueException(sprintf(
                    'GoogleOAuthInterface::callback() must return a Web response (VIEW or INERTIA), got "%s".',
                    $response->getType()->value,
                ));
            }

            return $response;
        } catch (GoogleOAuthException $e) {
            $code = $e->isProviderError()
                ? ErrorCode::GOOGLE_PROVIDER_ERROR
                : ErrorCode::GOOGLE_CALLBACK_ERROR;

            return $this->renderError(
                $code->toResponseData(message: $e->getMessage()),
            );
        } catch (UnexpectedValueException $e) {
            return $this->renderError(
                ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR->toResponseData(
                    message: $e->getMessage(),
                ),
            );
        } catch (Exception $e) {
            $code = $this->isHttpClientFailure($e)
                ? ErrorCode::GOOGLE_CALLBACK_ERROR
                : ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR;

            return $this->renderError(
                $code->toResponseData(message: $e->getMessage()),
            );
        }
    }

    /**
     * Renders a Web error response through the configured callback
     * presentation (view or inertia), injecting the normalized
     * ErrorResponseData as the `error` prop.
     */
    private function renderError(ErrorResponseData $error): ResponseFactory
    {
        $props = [self::PROP_ERROR => $error];

        $type = $this->config->getGoogleCallbackResponseType();
        $target = $this->config->getGoogleCallbackTarget();

        return match ($type) {
            HttpResponseType::VIEW => ResponseFactory::view($target, $props),
            HttpResponseType::INERTIA => ResponseFactory::inertia($target, $props),
            default => throw new \LogicException(sprintf(
                'Unsupported Google callback response type: %s.',
                $type->value,
            )),
        };
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
