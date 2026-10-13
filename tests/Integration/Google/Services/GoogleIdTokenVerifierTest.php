<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Services;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\PhpClient\Clients\ClientService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

final class GoogleIdTokenVerifierTest extends IntegrationTestCase
{
    private const TEST_KID = 'test-kid-1';

    private const TEST_AUDIENCE = 'test-client-id.apps.googleusercontent.com';

    private const TEST_ISSUER = 'https://accounts.google.com';

    private MockHandler $mockHandler;

    private GoogleIdTokenVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Mettre à jour la configuration AVANT tout rebind
        $this->app['config']->set('authentication-kit.google', [
            'client_id' => self::TEST_AUDIENCE,
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

        // 3. Préparer le mock HTTP Guzzle
        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $clientService = new ClientService($guzzle);
        $this->app->instance(ClientService::class, $clientService);

        // 4. Construire le verifier à la main avec les instances fraîches
        $this->verifier = new GoogleIdTokenVerifier(
            client: $clientService,
            config: $this->app->make(AuthenticationKitConfig::class),
        );
    }

    /**
     * @return array{private: \OpenSSLAsymmetricKey, jwks: array<string, mixed>}
     */
    private function generateKeyPairAndJwks(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $details = openssl_pkey_get_details($resource);

        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        return [
            'private' => $resource,
            'jwks' => [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'alg' => 'RS256',
                        'use' => 'sig',
                        'kid' => self::TEST_KID,
                        'n' => $n,
                        'e' => $e,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signJwt(\OpenSSLAsymmetricKey $privateKey, array $payload): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => self::TEST_KID,
        ];

        $encode = static function (array $data): string {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        };

        $encodedHeader = $encode($header);
        $encodedPayload = $encode($payload);

        $signingInput = $encodedHeader.'.'.$encodedPayload;

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $encodedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $signingInput.'.'.$encodedSignature;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $now = time();

        return array_merge([
            'iss' => self::TEST_ISSUER,
            'aud' => self::TEST_AUDIENCE,
            'sub' => 'google-user-123',
            'email' => 'user@example.com',
            'email_verified' => true,
            'exp' => $now + 3600,
            'iat' => $now,
            'nonce' => 'expected-nonce',
            'name' => 'John Doe',
        ], $overrides);
    }

    private function queueJwks(array $jwks): void
    {
        $this->mockHandler->append(
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($jwks))
        );
    }

    public function test_verify_returns_claims_for_valid_token(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload());

        $claims = $this->verifier->verify($token, 'expected-nonce');

        $this->assertSame('google-user-123', $claims->sub);
        $this->assertSame('user@example.com', $claims->email);
        $this->assertTrue($claims->emailVerified);
        $this->assertSame('John Doe', $claims->name);
    }

    public function test_verify_accepts_issuer_without_scheme(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['iss' => 'accounts.google.com']));

        $claims = $this->verifier->verify($token, 'expected-nonce');

        $this->assertSame('accounts.google.com', $claims->iss);
    }

    public function test_verify_throws_when_token_is_malformed(): void
    {
        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Malformed Google ID token');

        $this->verifier->verify('not-a-jwt');
    }

    public function test_verify_throws_when_algorithm_is_not_rs256(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'kid' => self::TEST_KID])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($this->validPayload())), '+/', '-_'), '=');

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Malformed Google ID token');

        $this->verifier->verify($header.'.'.$payload.'.sig');
    }

    public function test_verify_throws_when_kid_is_missing(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($this->validPayload())), '+/', '-_'), '=');

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Malformed Google ID token');

        $this->verifier->verify($header.'.'.$payload.'.sig');
    }

    public function test_verify_throws_when_kid_is_not_in_jwks(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $jwks['keys'][0]['kid'] = 'other-kid';
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload());

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('No matching public key');

        $this->verifier->verify($token);
    }

    public function test_verify_throws_when_signature_is_invalid(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        // Sign with a different key pair
        $otherKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $token = $this->signJwt($otherKey, $this->validPayload());

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('signature is invalid');

        $this->verifier->verify($token);
    }

    public function test_verify_throws_when_issuer_is_invalid(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['iss' => 'https://evil.example.com']));

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('issuer is invalid');

        $this->verifier->verify($token);
    }

    public function test_verify_throws_when_audience_is_invalid(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['aud' => 'other-client.apps.googleusercontent.com']));

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('audience is invalid');

        $this->verifier->verify($token);
    }

    public function test_verify_throws_when_token_is_expired(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['exp' => time() - 10]));

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('has expired');

        $this->verifier->verify($token);
    }

    public function test_verify_throws_when_token_is_issued_in_future(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['iat' => time() + 120]));

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('issued in the future');

        $this->verifier->verify($token);
    }

    public function test_verify_throws_when_nonce_does_not_match(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['nonce' => 'other-nonce']));

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('nonce mismatch');

        $this->verifier->verify($token, 'expected-nonce');
    }

    public function test_verify_throws_when_email_is_not_verified(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['email_verified' => false]));

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('email is not verified');

        $this->verifier->verify($token);
    }

    public function test_verify_skips_nonce_check_when_expected_nonce_is_null(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload(['nonce' => 'anything']));

        $claims = $this->verifier->verify($token, null);

        $this->assertSame('anything', $claims->nonce);
    }

    public function test_verify_hydrates_optional_claims(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $token = $this->signJwt($private, $this->validPayload([
            'given_name' => 'John',
            'family_name' => 'Doe',
            'picture' => 'https://example.com/avatar.png',
            'locale' => 'en',
            'hd' => 'example.com',
        ]));

        $claims = $this->verifier->verify($token, 'expected-nonce');

        $this->assertSame('John', $claims->givenName);
        $this->assertSame('Doe', $claims->familyName);
        $this->assertSame('https://example.com/avatar.png', $claims->picture);
        $this->assertSame('en', $claims->locale);
        $this->assertSame('example.com', $claims->hd);
    }

    public function test_verify_handles_missing_optional_claims(): void
    {
        ['private' => $private, 'jwks' => $jwks] = $this->generateKeyPairAndJwks();
        $this->queueJwks($jwks);

        $payload = $this->validPayload();
        unset($payload['name']);

        $token = $this->signJwt($private, $payload);

        $claims = $this->verifier->verify($token, 'expected-nonce');

        $this->assertNull($claims->name);
        $this->assertNull($claims->givenName);
        $this->assertNull($claims->familyName);
        $this->assertNull($claims->picture);
    }
}
