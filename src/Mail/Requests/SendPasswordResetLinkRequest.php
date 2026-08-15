<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\SendPasswordResetLinkRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidModelTypeRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SendPasswordResetLinkRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string', new ValidModelTypeRule],
            'email' => ['required', 'email'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return SendPasswordResetLinkRecord::from([
            'model_type' => $this->input('model_type'),
            'email' => $this->input('email'),
        ]);
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'model_type is required',
            'email.required' => 'Email is required',
        ];
    }
}
