<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Configs;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class AuthenticationKitConfig implements AuthenticationKitConfigInterface
{
    private const DEFAULT_TOKEN_NAME = 'authentication-kit';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getTokenName(): string
    {
        return $this->config->get(
            'authentication-kit.token_name',
            self::DEFAULT_TOKEN_NAME
        );
    }
}
