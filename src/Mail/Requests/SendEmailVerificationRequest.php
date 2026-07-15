<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\SendEmailVerificationRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SendEmailVerificationRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'auth_id' => ['required', 'integer'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return SendEmailVerificationRecord::from([
            'modelType' => $this->input('model_type'),
            'authId' => $this->input('auth_id'),
        ]);
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'model_type is required',
            'auth_id.required' => 'auth_id is required',
            'auth_id.integer' => 'auth_id must be an integer',
        ];
    }
}
