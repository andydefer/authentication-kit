<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Services;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationServiceInterface;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TestUserMailAuthenticationService implements MailAuthenticationServiceInterface
{
    public function register(AbstractRecord $record): Model&Authenticatable
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $data = $record->data->toArray();

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:test_users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $user = TestUserMail::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        return $user;
    }

    public function login(string $email, string $password): ?NemesisTokenRecord
    {
        $user = TestUserMail::where('email', $email)->first();

        if ($user === null) {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return new NemesisTokenRecord(
            name: 'test-login',
            source: 'login',
            metadata: new StrictDataObject([
                'auth_id' => $user->id,
                'email' => $user->email,
            ]),
        );
    }
}
