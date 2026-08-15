<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\ResendEmailVerificationRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidModelTypeRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ResendEmailVerificationRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string', new ValidModelTypeRule],
            'auth_id' => ['required'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return ResendEmailVerificationRecord::from([
            'model_type' => $this->input('model_type'),
            'auth_id' => $this->input('auth_id'),
        ]);
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'model_type is required',
            'auth_id.required' => 'auth_id is required',
        ];
    }
}
