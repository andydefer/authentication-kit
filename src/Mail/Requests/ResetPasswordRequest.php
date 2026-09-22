<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\ResetPasswordRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidModelTypeRule;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidOtpRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Validates the payload used to reset a password.
 *
 * Fields not declared in the validation rules are collected into the `data`
 * property of the record.
 */
final class ResetPasswordRequest extends AbstractRequest
{
    private const PASSWORD_RESET_PURPOSE = 'password_reset';

    /**
     * Fields mapped to first-class record properties and excluded from the
     * generic `data` bag.
     *
     * @var array<int, string>
     */
    private const RESERVED_FIELDS = [
        'model_type',
        'email',
        'token',
        'password',
        'password_confirmation',
    ];

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string', new ValidModelTypeRule],
            'email' => ['required', 'email'],
            'token' => ['required', 'string', new ValidOtpRule(self::PASSWORD_RESET_PURPOSE)],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Build the {@see ResetPasswordRecord} from the request payload.
     *
     * @return AbstractRecord The typed record passed to the action.
     */
    public function getRecord(): AbstractRecord
    {
        return ResetPasswordRecord::from([
            'model_type' => $this->input('model_type'),
            'email' => $this->input('email'),
            'token' => $this->input('token'),
            'password' => $this->input('password'),
            'password_confirmation' => $this->input('password_confirmation'),
            'data' => $this->buildDataBag(),
        ]);
    }

    /**
     * Return custom error messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model_type.required' => 'model_type is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'token.required' => 'Verification token is required',
            'password.required' => 'Password is required',
            'password.confirmed' => 'Password confirmation does not match',
            'password_confirmation.required' => 'Password confirmation is required',
        ];
    }

    /**
     * Collect every request field not reserved by the record into a strict
     * associative bag.
     *
     * @return StrictAssociative|null The extra fields, or null when none are present.
     */
    private function buildDataBag(): ?StrictAssociative
    {
        $extra = array_diff_key(
            $this->all(),
            array_flip(self::RESERVED_FIELDS),
        );

        if ($extra === []) {
            return null;
        }

        return StrictAssociative::from($extra);
    }
}
