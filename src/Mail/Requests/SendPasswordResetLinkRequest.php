<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\SendPasswordResetLinkRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SendPasswordResetLinkRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return SendPasswordResetLinkRecord::from([
            'email' => $this->input('email'),
        ]);
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
        ];
    }
}
