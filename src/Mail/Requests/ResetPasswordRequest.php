<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\ResetPasswordRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidOtpRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ResetPasswordRequest extends AbstractRequest
{
    private const PASSWORD_RESET_PURPOSE = 'password_reset';

    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'email' => ['required', 'email'],
            'token' => ['required', 'string', new ValidOtpRule(self::PASSWORD_RESET_PURPOSE)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return ResetPasswordRecord::from([
            'model_type' => $this->input('model_type'),
            'email' => $this->input('email'),
            'token' => $this->input('token'),
            'password' => $this->input('password'),
            'password_confirmation' => $this->input('password_confirmation'),
        ]);
    }

    public function messages(): array
    {
        return [
            'model_type.required' => 'model_type is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'token.required' => 'Verification token is required',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password_confirmation.required' => 'Password confirmation is required',
        ];
    }
}
