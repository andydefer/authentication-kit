<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Associative;

/**
 * Immutable value object representing verified Google ID-token claims.
 *
 * Produced by GoogleIdTokenVerifier and carried by GoogleCallbackVO /
 * GoogleAndroidCallbackVO. Exposes convenience helpers for common
 * comparisons and derivations.
 */
final class GoogleIdTokenClaimsVO extends AbstractValueObject
{
    private const GOOGLE_ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    public function __construct(
        private readonly string $sub,
        private readonly string $email,
        private readonly bool $emailVerified,
        private readonly string $iss,
        private readonly string $aud,
        private readonly int $exp,
        private readonly int $iat,
        private readonly ?string $nonce = null,
        private readonly ?string $name = null,
        private readonly ?string $givenName = null,
        private readonly ?string $familyName = null,
        private readonly ?string $picture = null,
        private readonly ?string $locale = null,
        private readonly ?string $hd = null,
    ) {}

    // ============================================================
    // GETTERS
    // ============================================================

    public function getSub(): string
    {
        return $this->sub;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getIss(): string
    {
        return $this->iss;
    }

    public function getAud(): string
    {
        return $this->aud;
    }

    public function getExp(): int
    {
        return $this->exp;
    }

    public function getIat(): int
    {
        return $this->iat;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getGivenName(): ?string
    {
        return $this->givenName;
    }

    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getHd(): ?string
    {
        return $this->hd;
    }

    // ============================================================
    // UTILITY METHODS
    // ============================================================

    /**
     * Whether the issuer claim matches a known Google issuer.
     */
    public function isIssuerGoogle(): bool
    {
        return in_array($this->iss, self::GOOGLE_ISSUERS, true);
    }

    /**
     * Whether the audience matches the given Google client_id.
     */
    public function isAudience(string $clientId): bool
    {
        return $this->aud === $clientId;
    }

    /**
     * Whether the token is expired relative to the given unix timestamp.
     */
    public function isExpired(?int $now = null): bool
    {
        return $this->exp <= ($now ?? time());
    }

    /**
     * Whether the token was issued in the future (iat > now).
     */
    public function isIssuedInFuture(?int $now = null): bool
    {
        return $this->iat > ($now ?? time());
    }

    /**
     * Whether the nonce claim matches the expected nonce.
     */
    public function matchesNonce(string $expectedNonce): bool
    {
        return $this->nonce !== null && hash_equals($this->nonce, $expectedNonce);
    }

    /**
     * Whether the account is hosted under a Google Workspace domain.
     */
    public function isHostedDomain(): bool
    {
        return $this->hd !== null && $this->hd !== '';
    }

    /**
     * Best-effort display name: full name, else composed from given +
     * family name, else local part of the email.
     */
    public function displayName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        $composed = trim(implode(' ', array_filter([
            $this->givenName,
            $this->familyName,
        ])));

        if ($composed !== '') {
            return $composed;
        }

        return strstr($this->email, '@', true) ?: $this->email;
    }

    // ============================================================
    // VALUE OBJECT CONTRACT
    // ============================================================

    public function getValue(): Associative
    {
        return Associative::from([
            'sub' => $this->sub,
            'email' => $this->email,
            'emailVerified' => $this->emailVerified,
            'iss' => $this->iss,
            'aud' => $this->aud,
            'exp' => $this->exp,
            'iat' => $this->iat,
            'nonce' => $this->nonce,
            'name' => $this->name,
            'givenName' => $this->givenName,
            'familyName' => $this->familyName,
            'picture' => $this->picture,
            'locale' => $this->locale,
            'hd' => $this->hd,
        ]);
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->sub === $other->sub
            && $this->email === $other->email
            && $this->emailVerified === $other->emailVerified
            && $this->iss === $other->iss
            && $this->aud === $other->aud
            && $this->exp === $other->exp
            && $this->iat === $other->iat
            && $this->nonce === $other->nonce
            && $this->name === $other->name
            && $this->givenName === $other->givenName
            && $this->familyName === $other->familyName
            && $this->picture === $other->picture
            && $this->locale === $other->locale
            && $this->hd === $other->hd;
    }
}
