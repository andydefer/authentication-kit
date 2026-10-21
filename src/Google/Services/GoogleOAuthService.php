<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Services;

use AndyDefer\Actions\Enums\HttpResponseType;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleAuthenticatable;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleRedirectData;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleAuthorizationRedirectRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleTokenExchangeRequest;
use AndyDefer\AuthenticationKit\Google\Responses\GoogleTokenExchangeResponse;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\PhpClient\Clients\ClientService;
use Illuminate\Database\Eloquent\Model;

class GoogleOAuthService implements GoogleOAuthInterface
{
    public function __construct(
        private readonly ClientService $client,
        private readonly AuthenticationKitConfig $config,
        private readonly GooglePkceGenerator $pkceGenerator,
        private readonly GoogleStateStoreInterface $stateStore,
        private readonly GoogleIdTokenVerifier $idTokenVerifier,
        private readonly NemesisInterface $nemesis,
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
    public function callback(GoogleCallbackRecord $record): ResponseFactory
    {
        $this->beforeCallback($record);

        if ($record->error !== null) {
            $this->afterCallbackFailed($record, ErrorCode::GOOGLE_PROVIDER_ERROR);

            throw GoogleOAuthException::providerError(
                $record->error_description ?? $record->error
            );
        }

        $stateRecord = $this->stateStore->pull($record->state);

        if ($stateRecord === null) {
            $this->afterCallbackFailed($record, ErrorCode::GOOGLE_STATE_MISMATCH);

            throw GoogleOAuthException::stateNotFound();
        }

        if ($stateRecord->expires_at < time()) {
            $this->afterCallbackFailed($record, ErrorCode::GOOGLE_STATE_EXPIRED);

            throw GoogleOAuthException::stateExpired();
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

        $data = new GoogleCallbackData(
            idToken: $tokens->idToken,
            accessToken: $tokens->accessToken,
            expiresIn: $tokens->expiresIn,
            tokenType: $tokens->tokenType,
            scope: $tokens->scope,
            claims: $claims,
            operation: $stateRecord->operation,
        );

        $this->afterCallback($record, $data);

        return $this->onCallbackSuccess($data);
    }

    /**
     * {@inheritDoc}
     */
    public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory
    {
        $type = $this->config->getGoogleCallbackResponseType();
        $target = $this->config->getGoogleCallbackTarget();

        return match ($type) {
            HttpResponseType::VIEW => ResponseFactory::view($target, ['data' => $data]),
            HttpResponseType::INERTIA => ResponseFactory::inertia($target, ['data' => $data]),
            default => throw new \LogicException(
                "Unsupported Google callback response type: {$type->value}"
            ),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function androidCallback(GoogleAndroidCallbackRecord $record): ResponseFactory
    {
        $this->beforeAndroidCallback($record);

        $claims = $this->idTokenVerifier->verify(
            idToken: $record->id_token,
            expectedNonce: null,
        );

        if ($claims->emailVerified !== true) {
            $this->afterAndroidCallbackFailed($record, ErrorCode::GOOGLE_CALLBACK_ERROR);

            throw GoogleOAuthException::emailNotVerified();
        }

        $modelClass = $record->model_type;

        if (! class_exists($modelClass)) {
            $this->afterAndroidCallbackFailed($record, ErrorCode::GOOGLE_MODEL_NOT_FOUND);

            throw GoogleOAuthException::modelNotFound($modelClass);
        }

        if (! is_subclass_of($modelClass, MailAuthenticatable::class)) {
            $this->afterAndroidCallbackFailed($record, ErrorCode::GOOGLE_INVALID_MODEL);

            throw GoogleOAuthException::invalidModel($modelClass);
        }

        if (! is_subclass_of($modelClass, GoogleAuthenticatable::class)) {
            $this->afterAndroidCallbackFailed(
                $record,
                ErrorCode::GOOGLE_MODEL_NOT_GOOGLE_AUTHENTICATABLE,
            );

            throw GoogleOAuthException::modelNotGoogleAuthenticatable($modelClass);
        }

        /** @var (Model&MailAuthenticatable&GoogleAuthenticatable)|null $auth */
        $auth = $modelClass::where('email', $claims->email)->first();
        $isNewUser = $auth === null;

        if ($isNewUser) {
            /** @var Model&MailAuthenticatable&GoogleAuthenticatable $auth */
            $auth = $modelClass::androidGenerate($claims);
        }

        $tokenRecord = NemesisTokenRecord::from([
            'name' => 'auth-google-android',
            'source' => 'google_android',
            'metadata' => new StrictAssociative([
                'auth_id' => $auth->getKey(),
                'email' => $auth->getRawOriginal('email'),
            ]),
        ]);

        [, $plainToken] = $this->nemesis->createWithPlainToken($tokenRecord, $auth);

        $data = new GoogleAndroidCallbackData(
            message: $isNewUser ? 'Registration successful' : 'Login successful',
            auth: new StrictAssociative(
                action_normalizer_chain(true)->normalize($auth->nemesisFormat())
            ),
            token: $plainToken,
            modelType: $modelClass,
            isNewUser: $isNewUser,
            claims: $claims,
        );

        $this->afterAndroidCallback($record, $data);

        return $this->onAndroidCallbackSuccess($data);
    }

    /**
     * {@inheritDoc}
     */
    public function onAndroidCallbackSuccess(GoogleAndroidCallbackData $data): ResponseFactory
    {
        return ResponseFactory::json($data);
    }

    // ========================================================================
    // HOOKS — WEB FLOW
    // ========================================================================

    /**
     * Hook called before building the authorization redirect URL.
     */
    protected function beforeAuthorizationRedirect(GoogleRedirectRecord $record): void {}

    /**
     * Hook called after the authorization redirect payload is built.
     */
    protected function afterAuthorizationRedirect(
        GoogleRedirectRecord $record,
        GoogleRedirectData $data,
    ): void {}

    /**
     * Hook called before handling the OAuth callback.
     */
    protected function beforeCallback(GoogleCallbackRecord $record): void {}

    /**
     * Hook called after the OAuth callback is successfully processed,
     * right before onCallbackSuccess() decides the final response.
     */
    protected function afterCallback(
        GoogleCallbackRecord $record,
        GoogleCallbackData $data,
    ): void {}

    /**
     * Hook called when the callback fails (Google error, invalid state, etc.).
     */
    protected function afterCallbackFailed(
        GoogleCallbackRecord $record,
        ErrorCode $error,
    ): void {}

    // ========================================================================
    // HOOKS — ANDROID FLOW
    // ========================================================================

    /**
     * Hook called before handling the Android callback.
     */
    protected function beforeAndroidCallback(GoogleAndroidCallbackRecord $record): void {}

    /**
     * Hook called after the Android callback is successfully processed,
     * right before onAndroidCallbackSuccess() decides the final response.
     */
    protected function afterAndroidCallback(
        GoogleAndroidCallbackRecord $record,
        GoogleAndroidCallbackData $data,
    ): void {}

    /**
     * Hook called when the Android callback fails.
     */
    protected function afterAndroidCallbackFailed(
        GoogleAndroidCallbackRecord $record,
        ErrorCode $error,
    ): void {}
}
