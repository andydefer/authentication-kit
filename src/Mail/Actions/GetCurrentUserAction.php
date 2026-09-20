<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Mail\Datas\CurrentUserResponseData;
use AndyDefer\AuthenticationKit\Mail\Enums\CurrentUserMode;
use AndyDefer\AuthenticationKit\Mail\Records\GetCurrentUserRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;

/**
 * Action to get the current authenticated user.
 */
final class GetCurrentUserAction extends AbstractAction
{
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieStorage,
        private readonly NemesisInterface $nemesis,
        private readonly NemesisConfigInterface $config,
    ) {}

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        /** @var GetCurrentUserRecord $record */
        $plainToken = $this->resolvePlainToken();

        if ($plainToken === null) {
            return ErrorCode::UNAUTHENTICATED->toJsonResponseFactory();
        }

        $tokenHash = $this->hashToken($plainToken);
        $tokenModel = $this->nemesis->findByHash($tokenHash);

        if ($tokenModel === null) {
            return ErrorCode::UNAUTHENTICATED->toJsonResponseFactory();
        }

        if ($tokenModel->isExpired()) {
            return ErrorCode::UNAUTHENTICATED->toJsonResponseFactory();
        }

        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            return ErrorCode::UNAUTHENTICATED->toJsonResponseFactory();
        }

        $authenticatable = $tokenableType::find($tokenableId);

        if ($authenticatable === null) {
            return ErrorCode::UNAUTHENTICATED->toJsonResponseFactory();
        }

        $this->nemesis->updateLastUsed($tokenModel);

        if (! $authenticatable instanceof MustNemesis) {
            return ErrorCode::USER_FORMAT_ERROR->toJsonResponseFactory();
        }

        return ResponseFactory::json(
            $record->mode === CurrentUserMode::DETAILED
                ? CurrentUserResponseData::from([
                    'user' => $authenticatable->nemesisFormat()->toArray(),
                    'modelType' => $authenticatable::class,
                ])
                : $authenticatable->nemesisFormat(),
            200,
        );
    }

    private function resolvePlainToken(): ?string
    {
        $bearerToken = request()->bearerToken();

        if ($bearerToken !== null) {
            return $bearerToken;
        }

        return $this->cookieStorage->get(request());
    }

    private function hashToken(string $plainToken): string
    {
        $algorithm = $this->config->tokenConfig()->hash_algorithm;

        return hash($algorithm, $plainToken);
    }
}
