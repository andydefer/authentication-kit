<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts\Configs;

/**
 * Interface for authentication kit configuration.
 *
 * Provides methods to retrieve authentication configuration values
 * such as token names and other settings.
 */
interface AuthenticationKitConfigInterface
{
    /**
     * Get the token name used for authentication.
     *
     * @return string The token name
     */
    public function getTokenName(): string;
}
