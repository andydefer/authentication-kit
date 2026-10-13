<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Responses;

use AndyDefer\AuthenticationKit\Google\Responses\Concerns\ReadsPayload;
use AndyDefer\PhpClient\Abstracts\Response;
use AndyDefer\PhpClient\Utils\EmptyStruct;

final class GoogleJwksResponse extends Response
{
    use ReadsPayload;

    /**
     * @return array<int, array{kid: string, n: string, e: string}>
     */
    public function getKeys(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->getBody()->format();

        $result = [];

        foreach ($this->arrayValue($payload, 'keys') as $key) {
            if (! is_array($key)) {
                continue;
            }

            $result[] = [
                'kid' => $this->string($key, 'kid'),
                'n' => $this->string($key, 'n'),
                'e' => $this->string($key, 'e'),
            ];
        }

        return $result;
    }

    public static function getStructClass(): string
    {
        return EmptyStruct::class;
    }
}
