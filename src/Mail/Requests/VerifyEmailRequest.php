<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyEmailRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidOtpRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class VerifyEmailRequest extends AbstractRequest
{
    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'email' => ['required', 'email'],
            'token' => ['required', 'string', new ValidOtpRule],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return VerifyEmailRecord::from([
            'model_type' => $this->input('model_type'),
            'email' => $this->input('email'),
            'token' => $this->input('token'),
        ]);
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'model_type is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'token.required' => 'Verification token is required',
        ];
    }
}
