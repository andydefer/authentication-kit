<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Services;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Test subclass of MailAuthenticationService bound to TestUserMail.
 *
 * Only test-specific behaviours are overridden:
 *  - the model class handled (TestUserMail)
 *  - the registration payload (name + email + password)
 *  - password hashing
 *
 * @extends MailAuthenticationService<TestUserMail>
 */
final class TestUserMailAuthenticationService extends MailAuthenticationService implements MailAuthenticationInterface
{
    /**
     * {@inheritDoc}
     */
    protected function beforeRegister(AbstractRecord $record): void
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $data = $record->data->toArray();

        if (! isset($data['name']) || trim((string) $data['name']) === '') {
            throw new \InvalidArgumentException('The "name" field is required.');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void
    {
        // Ensure the email is always stored in lowercase for the test model.
        if ($user instanceof TestUserMail) {
            $user->email = strtolower((string) $user->email);
            $user->save();
        }
    }
}
