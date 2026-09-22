<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyEmailRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidOtpRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Validates the payload used to verify an email address.
 *
 * Fields not declared in the validation rules are collected into the `data`
 * property of the record.
 */
final class VerifyEmailRequest extends AbstractRequest
{
    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

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
    ];

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'email' => ['required', 'email'],
            'token' => ['required', 'string', new ValidOtpRule],
        ];
    }

    /**
     * Build the {@see VerifyEmailRecord} from the request payload.
     *
     * @return AbstractRecord The typed record passed to the action.
     */
    public function getRecord(): AbstractRecord
    {
        return VerifyEmailRecord::from([
            'model_type' => $this->input('model_type'),
            'email' => $this->input('email'),
            'token' => $this->input('token'),
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
