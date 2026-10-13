<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Responses;

use AndyDefer\AuthenticationKit\Google\Datas\GoogleTokensData;
use AndyDefer\AuthenticationKit\Google\Responses\Concerns\ReadsPayload;
use AndyDefer\PhpClient\Abstracts\Response;
use AndyDefer\PhpClient\Utils\EmptyStruct;

final class GoogleTokenExchangeResponse extends Response
{
    use ReadsPayload;

    public function getData(): GoogleTokensData
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->getBody()->format();

        return GoogleTokensData::from([
            'idToken' => $this->string($payload, 'id_token'),
            'accessToken' => $this->string($payload, 'access_token'),
            'refreshToken' => $this->nullableString($payload, 'refresh_token'),
            'expiresIn' => $this->int($payload, 'expires_in'),
            'tokenType' => $this->string($payload, 'token_type'),
            'scope' => $this->string($payload, 'scope'),
        ]);
    }

    public static function getStructClass(): string
    {
        return EmptyStruct::class;
    }
}
