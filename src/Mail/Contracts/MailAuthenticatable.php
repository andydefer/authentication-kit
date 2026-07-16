<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Interface for authenticatable entities that support mail-based authentication.
 *
 * Extends the base Authenticatable contract with email-specific functionality
 * required for mail-based authentication flows.
 */
interface MailAuthenticatable extends Authenticatable
{
    /**
     * Returns the authentication service instance for mail-based operations.
     *
     * @return MailAuthenticationInterface The mail authentication service
     */
    public static function getMailAuthService(): MailAuthenticationInterface;

    /**
     * Gets the email verification timestamp.
     *
     * @return DateTimeVO|null The email verification timestamp, or null if not verified
     */
    public function getEmailVerifiedAt(): ?DateTimeVO;
}
