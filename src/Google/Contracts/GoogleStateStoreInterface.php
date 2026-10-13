<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Contracts;

use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;

interface GoogleStateStoreInterface
{
    /**
     * Persist a state record.
     */
    public function put(string $state, GoogleStateRecord $record, int $ttlSeconds): void;

    /**
     * Consume and return the state record for the given state value.
     *
     * The record MUST be removed from the store so it cannot be replayed.
     */
    public function pull(string $state): ?GoogleStateRecord;
}
