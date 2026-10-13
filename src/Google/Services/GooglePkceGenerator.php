<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Services;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;

final class GooglePkceGenerator
{
    public function __construct(
        private readonly AuthenticationKitConfigInterface $config,
    ) {}

    /**
     * @return array{verifier: string, challenge: string}
     */
    public function generate(): array
    {
        $length = $this->config->getGooglePkceVerifierLength();

        $verifier = $this->base64UrlEncode(random_bytes($length));

        $challenge = $this->base64UrlEncode(
            hash('sha256', $verifier, true)
        );

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
        ];
    }

    public function generateState(): string
    {
        return $this->base64UrlEncode(random_bytes(32));
    }

    public function generateNonce(): string
    {
        return $this->base64UrlEncode(random_bytes(32));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
