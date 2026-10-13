<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Responses\Concerns;

trait ReadsPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function string(array $payload, string $key, string $default = ''): string
    {
        return (string) ($payload[$key] ?? $default);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        return (string) $payload[$key];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function int(array $payload, string $key, int $default = 0): int
    {
        return (int) ($payload[$key] ?? $default);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    private function arrayValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
