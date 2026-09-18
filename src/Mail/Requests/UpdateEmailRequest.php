<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\UpdateEmailAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Request for confirming an email update.
 *
 * Validates the target model type, the new email plus the OTP code, and
 * builds an UpdateEmailAuthRecord.
 */
final class UpdateEmailRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'min:4', 'max:10'],
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
            'code.required' => 'The verification code is required.',
            'code.min' => 'The verification code is too short.',
            'code.max' => 'The verification code is too long.',
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return UpdateEmailAuthRecord::from([
            'model_type' => (string) $this->input('model_type'),
            'new_email' => strtolower((string) $this->input('email')),
            'code' => (string) $this->input('code'),
        ]);
    }
}
