<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Services;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleRedirectData;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleAuthorizationRedirectRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleTokenExchangeRequest;
use AndyDefer\AuthenticationKit\Google\Responses\GoogleTokenExchangeResponse;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleAndroidCallbackVO;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleCallbackVO;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\PhpClient\Clients\ClientService;

/**
 * Default Google OAuth service.
 */
class GoogleOAuthService implements GoogleOAuthInterface
{
    public function __construct(
        private readonly ClientService $client,
        private readonly AuthenticationKitConfig $config,
        private readonly GooglePkceGenerator $pkceGenerator,
        private readonly GoogleStateStoreInterface $stateStore,
        private readonly GoogleIdTokenVerifier $idTokenVerifier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData
    {
        $this->beforeAuthorizationRedirect($record);

        $pkce = $this->pkceGenerator->generate();

        $state = $record->state !== ''
            ? $record->state
            : $this->pkceGenerator->generateState();

        $nonce = $record->nonce !== ''
            ? $record->nonce
            : $this->pkceGenerator->generateNonce();

        $ttl = $this->config->getGoogleStateLifetime();

        $stateRecord = new GoogleStateRecord(
            code_verifier: $pkce['verifier'],
            nonce: $nonce,
            expires_at: time() + $ttl,
            operation: $record->operation,
        );

        $this->stateStore->put($state, $stateRecord, $ttl);

        $request = new GoogleAuthorizationRedirectRequest(
            clientId: $this->config->getGoogleClientId(),
            redirectUri: $this->config->getGoogleRedirectUri(),
            state: $state,
            codeChallenge: $pkce['challenge'],
            nonce: $nonce,
            scopes: $this->config->getGoogleScopes(),
        );

        $data = new GoogleRedirectData(url: $request->getUrl()->getValue());

        $this->afterAuthorizationRedirect($record, $data);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function callback(GoogleCallbackRecord $record): GoogleCallbackData|ErrorResponseData
    {
        $this->beforeCallback($record);

        if ($record->error !== null) {
            $this->afterCallbackFailed($record, ErrorCode::GOOGLE_PROVIDER_ERROR);

            return ErrorCode::GOOGLE_PROVIDER_ERROR->toResponseData(
                message: $record->error_description ?? $record->error,
            );
        }

        $stateRecord = $this->stateStore->pull($record->state);

        if ($stateRecord === null) {
            $this->afterCallbackFailed($record, ErrorCode::GOOGLE_STATE_MISMATCH);

            return ErrorCode::GOOGLE_STATE_MISMATCH->toResponseData();
        }

        if ($stateRecord->expires_at < time()) {
            $this->afterCallbackFailed($record, ErrorCode::GOOGLE_STATE_EXPIRED);

            return ErrorCode::GOOGLE_STATE_EXPIRED->toResponseData();
        }

        $tokenRequest = new GoogleTokenExchangeRequest(
            code: $record->code,
            clientId: $this->config->getGoogleClientId(),
            clientSecret: $this->config->getGoogleClientSecret(),
            redirectUri: $this->config->getGoogleRedirectUri(),
            codeVerifier: $stateRecord->code_verifier,
        );

        /** @var GoogleTokenExchangeResponse $response */
        $response = $this->client->post(
            $tokenRequest->getUrl()->getValue(),
            $tokenRequest,
            GoogleTokenExchangeResponse::class,
        );

        $tokens = $response->getData();

        $claims = $this->idTokenVerifier->verify(
            idToken: $tokens->idToken,
            expectedNonce: $stateRecord->nonce,
        );

        $vo = new GoogleCallbackVO(
            idToken: $tokens->idToken,
            accessToken: $tokens->accessToken,
            expiresIn: $tokens->expiresIn,
            tokenType: $tokens->tokenType,
            scope: $tokens->scope,
            claims: $claims,
            operation: $stateRecord->operation,
        );

        $this->afterCallback($record, $vo);

        return $this->onCallbackSuccess($vo);
    }

    /**
     * {@inheritDoc}
     */
    public function androidCallback(GoogleAndroidCallbackRecord $record): GoogleAndroidCallbackData|ErrorResponseData
    {
        $this->beforeAndroidCallback($record);

        $claims = $this->idTokenVerifier->verify(
            idToken: $record->id_token,
            expectedNonce: null,
        );

        if (! $claims->isEmailVerified()) {
            $this->afterAndroidCallbackFailed($record, ErrorCode::GOOGLE_CALLBACK_ERROR);

            return ErrorCode::GOOGLE_CALLBACK_ERROR->toResponseData(
                message: 'Google email is not verified',
            );
        }

        $modelClass = $record->model_type;

        if (! class_exists($modelClass)) {
            $this->afterAndroidCallbackFailed($record, ErrorCode::GOOGLE_MODEL_NOT_FOUND);

            return ErrorCode::GOOGLE_MODEL_NOT_FOUND->toResponseData(
                message: "The specified model_type does not exist: {$modelClass}",
            );
        }

        if (! is_subclass_of($modelClass, MailAuthenticatable::class)) {
            $this->afterAndroidCallbackFailed($record, ErrorCode::GOOGLE_INVALID_MODEL);

            return ErrorCode::GOOGLE_INVALID_MODEL->toResponseData(
                message: "The specified model_type must implement MailAuthenticatable: {$modelClass}",
            );
        }

        $vo = new GoogleAndroidCallbackVO(
            claims: $claims,
            modelType: $modelClass,
        );

        $this->afterAndroidCallback($record, $vo);

        return $this->onAndroidCallbackSuccess($vo);
    }

    /**
     * {@inheritDoc}
     */
    public function onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData
    {
        return ErrorCode::GOOGLE_CALLBACK_ERROR->toResponseData(
            message: 'onCallbackSuccess() is not implemented for this flow.',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
    {
        return ErrorCode::GOOGLE_CALLBACK_ERROR->toResponseData(
            message: 'onAndroidCallbackSuccess() is not implemented for this flow.',
        );
    }

    // ========================================================================
    // HOOKS — WEB
    // ========================================================================

    protected function beforeAuthorizationRedirect(GoogleRedirectRecord $record): void {}

    protected function afterAuthorizationRedirect(GoogleRedirectRecord $record, GoogleRedirectData $data): void {}

    protected function beforeCallback(GoogleCallbackRecord $record): void {}

    protected function afterCallback(GoogleCallbackRecord $record, GoogleCallbackVO $vo): void {}

    protected function afterCallbackFailed(GoogleCallbackRecord $record, ErrorCode $error): void {}

    // ========================================================================
    // HOOKS — ANDROID
    // ========================================================================

    protected function beforeAndroidCallback(GoogleAndroidCallbackRecord $record): void {}

    protected function afterAndroidCallback(GoogleAndroidCallbackRecord $record, GoogleAndroidCallbackVO $vo): void {}

    protected function afterAndroidCallbackFailed(GoogleAndroidCallbackRecord $record, ErrorCode $error): void {}
}
