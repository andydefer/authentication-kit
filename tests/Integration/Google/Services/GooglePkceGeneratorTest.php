<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Services;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Services\GooglePkceGenerator;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;

final class GooglePkceGeneratorTest extends IntegrationTestCase
{
    private GooglePkceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Mettre à jour la configuration AVANT tout rebind
        $this->app['config']->set('authentication-kit.google', [
            'client_id' => 'test-client-id.apps.googleusercontent.com',
            'client_secret' => 'test-client-secret',
            'redirect_uri' => 'https://app.test/auth/google/callback',

            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'jwks_endpoint' => 'https://www.googleapis.com/oauth2/v3/certs',

            'scopes' => ['openid', 'email', 'profile'],

            'state_lifetime' => 600,
            'pkce_verifier_length' => 64,
        ]);

        // 2. Reconstruire AuthenticationKitConfig avec la config mise à jour
        $this->app->singleton(
            AuthenticationKitConfig::class,
            function ($app): AuthenticationKitConfig {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        // 3. Détruire l'ancienne instance du générateur pour qu'il reçoive la nouvelle config
        $this->app->forgetInstance(GooglePkceGenerator::class);

        // 4. Construire le générateur avec la config fraîche
        $this->generator = $this->app->make(GooglePkceGenerator::class);
    }

    /**
     * Decode a base64url-encoded string, handling padding correctly.
     */
    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url value');
        }

        return $decoded;
    }

    public function test_generate_returns_array_with_verifier_and_challenge(): void
    {
        $result = $this->generator->generate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('verifier', $result);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertIsString($result['verifier']);
        $this->assertIsString($result['challenge']);
        $this->assertNotEmpty($result['verifier']);
        $this->assertNotEmpty($result['challenge']);
    }

    public function test_generate_produces_unique_values_on_each_call(): void
    {
        $first = $this->generator->generate();
        $second = $this->generator->generate();

        $this->assertNotSame($first['verifier'], $second['verifier']);
        $this->assertNotSame($first['challenge'], $second['challenge']);
    }

    public function test_challenge_is_sha256_of_verifier_base64url_encoded(): void
    {
        $result = $this->generator->generate();

        $expectedChallenge = rtrim(
            strtr(base64_encode(hash('sha256', $result['verifier'], true)), '+/', '-_'),
            '='
        );

        $this->assertSame($expectedChallenge, $result['challenge']);
    }

    public function test_generated_values_are_url_safe(): void
    {
        $result = $this->generator->generate();

        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $result['verifier']);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $result['challenge']);
    }

    public function test_generate_state_returns_url_safe_random_string(): void
    {
        $state = $this->generator->generateState();

        $this->assertIsString($state);
        $this->assertNotEmpty($state);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $state);
    }

    public function test_generate_state_produces_unique_values(): void
    {
        $this->assertNotSame(
            $this->generator->generateState(),
            $this->generator->generateState(),
        );
    }

    public function test_generate_nonce_returns_url_safe_random_string(): void
    {
        $nonce = $this->generator->generateNonce();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $nonce);
    }

    public function test_generate_nonce_produces_unique_values(): void
    {
        $this->assertNotSame(
            $this->generator->generateNonce(),
            $this->generator->generateNonce(),
        );
    }

    public function test_verifier_length_respects_configured_value(): void
    {
        // 1. Mettre à jour la clé
        config()->set('authentication-kit.google.pkce_verifier_length', 32);

        // 2. Reconstruire AuthenticationKitConfig
        $this->app->singleton(
            AuthenticationKitConfig::class,
            function ($app): AuthenticationKitConfig {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        // 3. Reconstruire le générateur
        $this->app->forgetInstance(GooglePkceGenerator::class);

        $generator = $this->app->make(GooglePkceGenerator::class);

        // Act
        $result = $generator->generate();

        $decoded = $this->base64UrlDecode($result['verifier']);

        // Assert
        $this->assertSame(32, strlen($decoded));
    }
}
