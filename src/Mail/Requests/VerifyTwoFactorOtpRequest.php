<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyTwoFactorOtpAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class VerifyTwoFactorOtpRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'two_factor_purpose' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'The model type is required.',
            'two_factor_purpose.required' => 'The two-factor purpose is required.',
            'two_factor_purpose.max' => 'The two-factor purpose is too long.',
            'code.required' => 'The verification code is required.',
            'code.min' => 'The verification code is too short.',
            'code.max' => 'The verification code is too long.',
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return VerifyTwoFactorOtpAuthRecord::from([
            'model_type' => (string) $this->input('model_type'),
            'two_factor_purpose' => (string) $this->input('two_factor_purpose'),
            'code' => (string) $this->input('code'),
        ]);
    }
}
