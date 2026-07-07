<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class EmailLoginRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return new EmailLoginAuthRecord(
            model_type: $this->input('model_type'),
            data: StrictDataObject::from($this->except('model_type')),
            ip: $this->ip(),
            user_agent: $this->userAgent(),
        );
    }
}
