<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyTwoFactorOtpAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Validates the payload used to verify a two-factor authentication OTP.
 *
 * Fields not declared in the validation rules are collected into the `data`
 * property of the record.
 */
final class VerifyTwoFactorOtpRequest extends AbstractRequest
{
    /**
     * Fields mapped to first-class record properties and excluded from the
     * generic `data` bag.
     *
     * @var array<int, string>
     */
    private const RESERVED_FIELDS = [
        'model_type',
        'two_factor_purpose',
        'code',
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
            'two_factor_purpose' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'min:4', 'max:10'],
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
            'two_factor_purpose.required' => 'The two-factor purpose is required.',
            'two_factor_purpose.max' => 'The two-factor purpose is too long.',
            'code.required' => 'The verification code is required.',
            'code.min' => 'The verification code is too short.',
            'code.max' => 'The verification code is too long.',
        ];
    }

    /**
     * Build the {@see VerifyTwoFactorOtpAuthRecord} from the request payload.
     *
     * @return AbstractRecord The typed record passed to the action.
     */
    public function getRecord(): AbstractRecord
    {
        return VerifyTwoFactorOtpAuthRecord::from([
            'model_type' => (string) $this->input('model_type'),
            'two_factor_purpose' => (string) $this->input('two_factor_purpose'),
            'code' => (string) $this->input('code'),
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
