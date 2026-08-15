<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidModelTypeRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class EmailRegisterRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string', new ValidModelTypeRule],
            'with_token' => ['sometimes', 'boolean'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return new EmailRegisterAuthRecord(
            model_type: $this->input('model_type'),
            with_token: $this->input('with_token', false),
            data: StrictDataObject::from($this->except(['model_type', 'with_token'])),
            ip: $this->ip(),
            user_agent: $this->userAgent(),
        );
    }
}
