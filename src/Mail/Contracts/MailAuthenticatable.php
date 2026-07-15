<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

interface MailAuthenticatable extends Authenticatable
{
    public static function getMailAuthService(): MailAuthenticationInterface;

    /**
     * Get the email verification timestamp.
     *
     * @return DateTimeVO|null The email verification timestamp or null if not verified
     */
    public function getEmailVerifiedAt(): ?DateTimeVO;
}
