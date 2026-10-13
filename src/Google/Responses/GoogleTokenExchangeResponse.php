<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Responses;

use AndyDefer\AuthenticationKit\Google\Datas\GoogleTokensData;
use AndyDefer\PhpClient\Abstracts\Response;

final class GoogleTokenExchangeResponse extends Response
{
    public function getData(): GoogleTokensData
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->getBody()->format();

        return GoogleTokensData::from([
            'idToken' => (string) ($payload['id_token'] ?? ''),
            'accessToken' => (string) ($payload['access_token'] ?? ''),
            'refreshToken' => isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            'expiresIn' => (int) ($payload['expires_in'] ?? 0),
            'tokenType' => (string) ($payload['token_type'] ?? ''),
            'scope' => (string) ($payload['scope'] ?? ''),
        ]);
    }
}
