<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Services;

use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;

final class GoogleStateStoreTest extends IntegrationTestCase
{
    private GoogleStateStoreInterface $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->app->make(GoogleStateStoreInterface::class);
    }

    private function makeRecord(
        string $verifier = 'verifier-xyz',
        string $nonce = 'nonce-abc',
        ?int $expiresAt = null,
        OperationType $operation = OperationType::LOGIN,
    ): GoogleStateRecord {
        return new GoogleStateRecord(
            code_verifier: $verifier,
            nonce: $nonce,
            expires_at: $expiresAt ?? time() + 600,
            operation: $operation,
        );
    }

    public function test_put_then_pull_returns_same_record(): void
    {
        $record = $this->makeRecord('verifier-1', 'nonce-1');

        $this->store->put('state-1', $record, 600);

        $pulled = $this->store->pull('state-1');

        $this->assertInstanceOf(GoogleStateRecord::class, $pulled);
        $this->assertSame('verifier-1', $pulled->code_verifier);
        $this->assertSame('nonce-1', $pulled->nonce);
        $this->assertSame(OperationType::LOGIN, $pulled->operation);
        $this->assertSame($record->expires_at, $pulled->expires_at);
    }

    public function test_pull_removes_record_from_store(): void
    {
        $this->store->put('state-1', $this->makeRecord(), 600);

        $this->assertNotNull($this->store->pull('state-1'));
        $this->assertNull($this->store->pull('state-1'));
    }

    public function test_pull_returns_null_when_state_not_found(): void
    {
        $this->assertNull($this->store->pull('missing-state'));
    }

    public function test_put_overwrites_existing_state(): void
    {
        $this->store->put('state-1', $this->makeRecord('v1', 'n1'), 600);
        $this->store->put('state-1', $this->makeRecord('v2', 'n2'), 600);

        $pulled = $this->store->pull('state-1');

        $this->assertNotNull($pulled);
        $this->assertSame('v2', $pulled->code_verifier);
        $this->assertSame('n2', $pulled->nonce);
    }

    public function test_two_states_are_isolated(): void
    {
        $this->store->put('state-a', $this->makeRecord('va', 'na', operation: OperationType::LOGIN), 600);
        $this->store->put('state-b', $this->makeRecord('vb', 'nb', operation: OperationType::REGISTER), 600);

        $a = $this->store->pull('state-a');
        $b = $this->store->pull('state-b');

        $this->assertSame('va', $a->code_verifier);
        $this->assertSame('vb', $b->code_verifier);
        $this->assertSame(OperationType::LOGIN, $a->operation);
        $this->assertSame(OperationType::REGISTER, $b->operation);
    }

    public function test_put_with_zero_ttl_clamps_to_minimum(): void
    {
        $this->store->put('state-1', $this->makeRecord(), 0);

        $this->assertNotNull($this->store->pull('state-1'));
    }
}
