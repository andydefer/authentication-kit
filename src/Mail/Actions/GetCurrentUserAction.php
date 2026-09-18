<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;

/**
 * Action to get the current authenticated user.
 *
 * This action retrieves the authenticated user from the request
 * (either from Bearer token or cookie) and returns their formatted data.
 * If no user is authenticated, returns a 401 Unauthorized response.
 */
final class GetCurrentUserAction extends AbstractAction
{
    public function __construct(
        private readonly CookieTokenStorageInterface $cookieStorage,
        private readonly NemesisInterface $nemesis,
        private readonly NemesisConfigInterface $config,
    ) {}

    /**
     * Processes the request to get the current authenticated user.
     *
     * @param  AbstractRecord  $record  The request record (empty)
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        $plainToken = $this->resolvePlainToken();

        if ($plainToken === null) {
            return $this->unauthenticated();
        }

        $tokenHash = $this->hashToken($plainToken);
        $tokenModel = $this->nemesis->findByHash($tokenHash);

        if ($tokenModel === null) {
            return $this->unauthenticated();
        }

        if ($tokenModel->isExpired()) {
            return $this->unauthenticated();
        }

        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            return $this->unauthenticated();
        }

        $authenticatable = $tokenableType::find($tokenableId);

        if ($authenticatable === null) {
            return $this->unauthenticated();
        }

        $this->nemesis->updateLastUsed($tokenModel);

        if (! $authenticatable instanceof MustNemesis) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'User data format not available',
                    status: 422,
                    errorCode: 'USER_FORMAT_ERROR',
                ),
                422
            );
        }

        return ResponseFactory::json($authenticatable->nemesisFormat(), 200);
    }

    /**
     * Resolves the plain token from the Bearer header or the cookie.
     */
    private function resolvePlainToken(): ?string
    {
        $bearerToken = request()->bearerToken();

        if ($bearerToken !== null) {
            return $bearerToken;
        }

        return $this->cookieStorage->get(request());
    }

    /**
     * Hashes the plain token using the configured hash algorithm.
     */
    private function hashToken(string $plainToken): string
    {
        $algorithm = $this->config->tokenConfig()->hash_algorithm;

        return hash($algorithm, $plainToken);
    }

    /**
     * Returns a standard 401 Unauthenticated response.
     */
    private function unauthenticated(): ResponseFactory
    {
        return ResponseFactory::json(
            new ErrorResponseData(
                message: 'Unauthenticated',
                status: 401,
                errorCode: 'UNAUTHENTICATED',
            ),
            401
        );
    }
}
