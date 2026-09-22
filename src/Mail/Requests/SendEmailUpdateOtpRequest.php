<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\SendEmailUpdateOtpAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Request for sending an email update OTP.
 *
 * Validates the target model type and the new email address, then builds
 * a {@see SendEmailUpdateOtpAuthRecord}.
 *
 * Fields not declared in the validation rules are collected into the `data`
 * property of the record.
 */
final class SendEmailUpdateOtpRequest extends AbstractRequest
{
    /**
     * Fields mapped to first-class record properties and excluded from the
     * generic `data` bag.
     *
     * @var array<int, string>
     */
    private const RESERVED_FIELDS = [
        'model_type',
        'email',
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
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Return custom error messages for the request.
     *
     * @return array<string, string>
     */
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

    /**
     * Build the {@see SendEmailUpdateOtpAuthRecord} from the request payload.
     *
     * @return AbstractRecord The typed record passed to the action.
     */
    public function getRecord(): AbstractRecord
    {
        return SendEmailUpdateOtpAuthRecord::from([
            'model_type' => (string) $this->input('model_type'),
            'new_email' => strtolower((string) $this->input('email')),
            'data' => $this->buildDataBag(),
        ]);
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
