<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Exception;

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
    ) {}

    /**
     * Processes the request to get the current authenticated user.
     *
     * @param  AbstractRecord  $record  The request record (empty)
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        try {
            $plainToken = null;

            // 1. Essayer de récupérer depuis le Bearer token
            $bearerToken = request()->bearerToken();
            if ($bearerToken !== null) {
                $plainToken = $bearerToken;
            }

            // 2. Si pas de Bearer token, essayer depuis le cookie
            if ($plainToken === null) {
                $plainToken = $this->cookieStorage->get(request());
            }

            // 3. Si toujours pas de token, retourner 401
            if ($plainToken === null) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unauthenticated',
                        status: 401,
                        errorCode: 'UNAUTHENTICATED'
                    ),
                    401
                );
            }

            // 4. Hasher le token et le chercher en base
            $tokenHash = hash('sha256', $plainToken);
            $tokenModel = $this->nemesis->findByHash($tokenHash);

            if ($tokenModel === null) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unauthenticated',
                        status: 401,
                        errorCode: 'UNAUTHENTICATED'
                    ),
                    401
                );
            }

            if ($tokenModel->isExpired()) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unauthenticated',
                        status: 401,
                        errorCode: 'UNAUTHENTICATED'
                    ),
                    401
                );
            }

            // 5. Récupérer le tokenable
            $tokenableType = $tokenModel->tokenable_type;
            $tokenableId = $tokenModel->tokenable_id;

            if ($tokenableType === null || $tokenableId === null) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unauthenticated',
                        status: 401,
                        errorCode: 'UNAUTHENTICATED'
                    ),
                    401
                );
            }

            $authenticatable = $tokenableType::find($tokenableId);

            if ($authenticatable === null) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unauthenticated',
                        status: 401,
                        errorCode: 'UNAUTHENTICATED'
                    ),
                    401
                );
            }

            // 6. Mettre à jour last_used_at
            $this->nemesis->updateLastUsed($tokenModel);

            // 7. Retourner les données formatées
            if (! method_exists($authenticatable, 'nemesisFormat')) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'User data format not available',
                        status: 500,
                        errorCode: 'USER_FORMAT_ERROR'
                    ),
                    500
                );
            }

            return ResponseFactory::json($authenticatable->nemesisFormat(), 200);

        } catch (Exception $e) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::USER_FETCH_ERROR->message(),
                    status: ErrorCode::USER_FETCH_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::USER_FETCH_ERROR->value
                ),
                ErrorCode::USER_FETCH_ERROR->getHttpStatusCode()
            );
        }
    }
}
