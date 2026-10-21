<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Enums\HttpResponseType;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Handles Google's browser OAuth callback.
 *
 * Renders the configured Web presentation (Blade view or Inertia page)
 * with either:
 *  - ['success' => GoogleCallbackData] on success (fully populated)
 *  - ['error'   => ErrorResponseData]  on failure
 *
 * The action NEVER throws to produce a response: every failure path
 * returns a ResponseFactory.
 */
final class GoogleCallbackAction extends AbstractAction
{
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
            return $this->renderError(ErrorCode::INVALID_RECORD_TYPE->toResponseData());
        }

        try {
            $result = $this->googleOAuth->callback($record);
        } catch (GoogleOAuthException $e) {
            $code = $e->isProviderError()
                ? ErrorCode::GOOGLE_PROVIDER_ERROR
                : ErrorCode::GOOGLE_CALLBACK_ERROR;

            return $this->renderError($code->toResponseData(message: $e->getMessage()));
        } catch (Exception $e) {
            $code = $this->isHttpClientFailure($e)
                ? ErrorCode::GOOGLE_CALLBACK_ERROR
                : ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR;

            return $this->renderError($code->toResponseData(message: $e->getMessage()));
        }

        return match (true) {
            $result instanceof ErrorResponseData => $this->renderError($result),
            $result instanceof GoogleCallbackData => $this->renderSuccess($result),
            default => $this->renderError(
                ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR->toResponseData(
                    message: 'GoogleOAuthInterface::callback() returned an unexpected type.',
                ),
            ),
        };
    }

    private function renderSuccess(GoogleCallbackData $success): ResponseFactory
    {
        return $this->renderPresentation(['success' => $success]);
    }

    private function renderError(ErrorResponseData $error): ResponseFactory
    {
        return $this->renderPresentation(['error' => $error]);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderPresentation(array $props): ResponseFactory
    {
        $type = $this->config->getGoogleCallbackResponseType();
        $target = $this->config->getGoogleCallbackTarget();

        return match ($type) {
            HttpResponseType::VIEW => ResponseFactory::view($target, $props),
            HttpResponseType::INERTIA => ResponseFactory::inertia($target, $props),
            default => ResponseFactory::text(
                "Unsupported Google callback response type: {$type->value}",
                500,
            ),
        };
    }

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
