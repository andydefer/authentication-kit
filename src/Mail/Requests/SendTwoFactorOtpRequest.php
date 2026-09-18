<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\SendTwoFactorOtpAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SendTwoFactorOtpRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'two_factor_purpose' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'The model type is required.',
            'two_factor_purpose.required' => 'The two-factor purpose is required.',
            'two_factor_purpose.max' => 'The two-factor purpose is too long.',
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return SendTwoFactorOtpAuthRecord::from([
            'model_type' => (string) $this->input('model_type'),
            'two_factor_purpose' => (string) $this->input('two_factor_purpose'),
        ]);
    }
}
