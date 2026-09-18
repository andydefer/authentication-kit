<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\SendEmailUpdateOtpAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Request for sending an email update OTP.
 *
 * Validates the target model type, the new email address, and builds
 * a SendEmailUpdateOtpAuthRecord.
 */
final class SendEmailUpdateOtpRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'The model type is required.',
            'model_type.string' => 'The model type must be a string.',
            'email.required' => 'The new email address is required.',
            'email.email' => 'The new email address must be a valid email.',
            'email.max' => 'The new email address must not exceed 255 characters.',
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return SendEmailUpdateOtpAuthRecord::from([
            'model_type' => (string) $this->input('model_type'),
            'new_email' => strtolower((string) $this->input('email')),
        ]);
    }
}
