<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Rules;

use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

final class ValidOtpRule implements ValidationRule
{
    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

    private ?Model $authenticatable = null;

    public function __construct() {}

    public function getAuthenticatable(): ?Model
    {
        return $this->authenticatable;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $data = request()->all();

        $email = $data['email'] ?? null;
        $modelType = $data['model_type'] ?? null;

        if ($email === null) {
            $fail('Email is required for OTP validation.');

            return;
        }

        if ($modelType === null) {
            $fail('Model type is required for OTP validation.');

            return;
        }

        if (! class_exists($modelType)) {
            $fail('The specified model type does not exist.');

            return;
        }

        if (! is_subclass_of($modelType, Model::class)) {
            $fail('The specified model type must be an Eloquent model.');

            return;
        }

        /** @var Model $modelClass */
        $modelClass = $modelType;

        // ✅ Normaliser l'email pour la recherche (lowercase + trim)
        $normalizedEmail = strtolower(trim($email));

        $authenticatable = $modelClass::where('email', $normalizedEmail)->first();

        if ($authenticatable === null) {
            $fail('No user found with this email address.');

            return;
        }

        $this->authenticatable = $authenticatable;

        $otpService = app(OtpService::class);

        $purpose = new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $valid = $otpService->findValid(
            identifier: $authenticatable,
            code: $value,
            purpose: $purpose
        );

        if (! $valid) {
            $fail('Invalid or expired verification code.');
        }
    }
}
