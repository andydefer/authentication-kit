<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Creates a new authenticatable entity from record data.
     *
     * The validation (email, password, etc.) is already handled by the service.
     * This method receives the complete raw data from the record.
     *
     * @param  array<string, mixed>  $data  The complete raw data from the record
     * @return Model&Authenticatable The newly created model
     */
    public static function generate(array $data): Model&Authenticatable;
}
