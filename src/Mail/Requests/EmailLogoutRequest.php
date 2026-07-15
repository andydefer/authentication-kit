<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLogoutAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class EmailLogoutRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'token' => ['required', 'string'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return new EmailLogoutAuthRecord(
            model_type: $this->input('model_type'),
            token: $this->input('token'),
            ip: $this->ip(),
            user_agent: $this->userAgent(),
        );
    }
}
