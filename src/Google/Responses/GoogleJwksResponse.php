<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Responses;

use AndyDefer\PhpClient\Abstracts\Response;

final class GoogleJwksResponse extends Response
{
    /**
     * @return array<int, array{kid: string, n: string, e: string}>
     */
    public function getKeys(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->getBody()->format();

        $keys = $payload['keys'] ?? [];

        $result = [];

        foreach ($keys as $key) {
            $result[] = [
                'kid' => (string) ($key['kid'] ?? ''),
                'n' => (string) ($key['n'] ?? ''),
                'e' => (string) ($key['e'] ?? ''),
            ];
        }

        return $result;
    }
}
