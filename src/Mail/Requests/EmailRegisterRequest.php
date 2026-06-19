<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterUserRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class EmailRegisterRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return new EmailRegisterUserRecord(
            model_type: $this->input('model_type'),
            data: StrictDataObject::from($this->except('model_type')),
        );
    }
}
