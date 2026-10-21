<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Services;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Requests\EmptyRequest;
use AndyDefer\AuthenticationKit\Google\Responses\GoogleJwksResponse;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleIdTokenClaimsVO;
use AndyDefer\PhpClient\Clients\ClientService;

class GoogleIdTokenVerifier
{
    private const ALLOWED_ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com',
    ];

    public function __construct(
        private readonly ClientService $client,
        private readonly AuthenticationKitConfig $config,
    ) {}

    /**
     * @throws GoogleOAuthException
     */
    public function verify(string $idToken, ?string $expectedNonce = null): GoogleIdTokenClaimsVO
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw GoogleOAuthException::idTokenMalformed();
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        $kid = (string) ($header['kid'] ?? '');
        $alg = (string) ($header['alg'] ?? '');

        if ($alg !== 'RS256' || $kid === '') {
            throw GoogleOAuthException::idTokenMalformed();
        }

        $publicKey = $this->resolvePublicKey($kid);

        $result = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($result !== 1) {
            throw GoogleOAuthException::idTokenSignatureInvalid();
        }

        $this->verifyClaims($payload, $expectedNonce);

        return $this->hydrateClaims($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $encoded): array
    {
        $decoded = $this->base64UrlDecode($encoded);

        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            throw GoogleOAuthException::idTokenMalformed();
        }

        return $data;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw GoogleOAuthException::idTokenMalformed();
        }

        return $decoded;
    }

    private function resolvePublicKey(string $kid): \OpenSSLAsymmetricKey
    {
        $request = new EmptyRequest($this->config->getGoogleJwksEndpoint());

        /** @var GoogleJwksResponse $response */
        $response = $this->client->get(
            $request->getUrl()->getValue(),
            $request,
            GoogleJwksResponse::class,
        );

        foreach ($response->getKeys() as $key) {
            if ($key['kid'] !== $kid) {
                continue;
            }

            if ($key['n'] === '' || $key['e'] === '') {
                throw GoogleOAuthException::idTokenUnknownKey();
            }

            return $this->buildRsaPublicKey($key['n'], $key['e']);
        }

        throw GoogleOAuthException::idTokenUnknownKey();
    }

    private function buildRsaPublicKey(string $n, string $e): \OpenSSLAsymmetricKey
    {
        $modulus = $this->base64UrlDecode($n);
        $exponent = $this->base64UrlDecode($e);

        if (ord($modulus[0]) > 0x7F) {
            $modulus = "\x00".$modulus;
        }

        $modulusEncoded = "\x02".$this->encodeLength(strlen($modulus)).$modulus;
        $exponentEncoded = "\x02".$this->encodeLength(strlen($exponent)).$exponent;

        $rsaPublicKey = "\x30"
            .$this->encodeLength(strlen($modulusEncoded) + strlen($exponentEncoded))
            .$modulusEncoded
            .$exponentEncoded;

        $algorithmIdentifier = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

        $bitString = "\x03"
            .$this->encodeLength(strlen($rsaPublicKey) + 1)
            ."\x00"
            .$rsaPublicKey;

        $inner = $algorithmIdentifier.$bitString;

        $spki = "\x30".$this->encodeLength(strlen($inner)).$inner;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw GoogleOAuthException::idTokenUnknownKey();
        }

        return $key;
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $encoded = '';

        while ($length > 0) {
            $encoded = chr($length & 0xFF).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyClaims(array $payload, ?string $expectedNonce): void
    {
        $iss = (string) ($payload['iss'] ?? '');

        if (! in_array($iss, self::ALLOWED_ISSUERS, true)) {
            throw GoogleOAuthException::idTokenIssuerInvalid();
        }

        $aud = (string) ($payload['aud'] ?? '');

        if ($aud !== $this->config->getGoogleClientId()) {
            throw GoogleOAuthException::idTokenAudienceInvalid();
        }

        $now = time();

        if ((int) ($payload['exp'] ?? 0) <= $now) {
            throw GoogleOAuthException::idTokenExpired();
        }

        if ((int) ($payload['iat'] ?? 0) > $now + 60) {
            throw GoogleOAuthException::idTokenIssuedInFuture();
        }

        if ($expectedNonce !== null) {
            $nonce = (string) ($payload['nonce'] ?? '');

            if (! hash_equals($expectedNonce, $nonce)) {
                throw GoogleOAuthException::idTokenNonceMismatch();
            }
        }

        if (! (bool) ($payload['email_verified'] ?? false)) {
            throw GoogleOAuthException::emailNotVerified();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrateClaims(array $payload): GoogleIdTokenClaimsVO
    {
        return new GoogleIdTokenClaimsVO(
            sub: (string) ($payload['sub'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            emailVerified: (bool) ($payload['email_verified'] ?? false),
            iss: (string) ($payload['iss'] ?? ''),
            aud: (string) ($payload['aud'] ?? ''),
            exp: (int) ($payload['exp'] ?? 0),
            iat: (int) ($payload['iat'] ?? 0),
            nonce: isset($payload['nonce']) ? (string) $payload['nonce'] : null,
            name: isset($payload['name']) ? (string) $payload['name'] : null,
            givenName: isset($payload['given_name']) ? (string) $payload['given_name'] : null,
            familyName: isset($payload['family_name']) ? (string) $payload['family_name'] : null,
            picture: isset($payload['picture']) ? (string) $payload['picture'] : null,
            locale: isset($payload['locale']) ? (string) $payload['locale'] : null,
            hd: isset($payload['hd']) ? (string) $payload['hd'] : null,
        );
    }
}
