<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Services;

use AndyDefer\Actions\Contracts\CacheServiceInterface;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;

final class GoogleStateStore implements GoogleStateStoreInterface
{
    private const CACHE_PREFIX = 'auth_kit.google.state.';

    public function __construct(
        private readonly CacheServiceInterface $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function put(string $state, GoogleStateRecord $record, int $ttlSeconds): void
    {
        $this->cache->put(
            self::CACHE_PREFIX.$state,
            $record,
            $ttlSeconds,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function pull(string $state): ?GoogleStateRecord
    {
        $key = self::CACHE_PREFIX.$state;

        if (! $this->cache->has($key)) {
            return null;
        }

        /** @var GoogleStateRecord|null $record */
        $record = $this->cache->get($key, GoogleStateRecord::class);

        $this->cache->forget($key);

        return $record;
    }
}
