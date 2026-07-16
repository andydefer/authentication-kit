<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Services;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Generic email authentication service.
 *
 * Works with any Eloquent model that implements MailAuthenticatable.
 *
 * @template T of Model&MailAuthenticatable
 */
final class MailAuthenticationService implements MailAuthenticationInterface
{
    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

    private const PASSWORD_RESET_PURPOSE = 'password_reset';

    /**
     * @param  class-string<T>  $modelClass
     */
    protected function __construct(
        private readonly string $modelClass,
        private readonly NemesisInterface $nemesis,
        private readonly OtpService $otpService,
        private readonly LogRepositoryInterface $logRepository,
    ) {
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist");
        }

        if (! is_subclass_of($modelClass, MailAuthenticatable::class)) {
            throw new \InvalidArgumentException(
                "Model {$modelClass} must implement ".MailAuthenticatable::class
            );
        }
    }

    /**
     * Creates a new instance of the service for a specific model class.
     *
     * @template U of Model&MailAuthenticatable
     *
     * @param  class-string<U>  $modelClass
     * @return self<U>
     */
    public static function for(string $modelClass): self
    {
        $nemesis = app(NemesisInterface::class);
        $otpService = app(OtpService::class);
        $logRepository = app(LogRepositoryInterface::class);

        return new self($modelClass, $nemesis, $otpService, $logRepository);
    }

    /**
     * {@inheritDoc}
     */
    public function register(AbstractRecord $record): Model&Authenticatable
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $data = $record->data->toArray();

        $validator = Validator::make($data, $this->getDefaultValidationRules());

        if ($validator->fails()) {
            $this->logRepository->logRegistrationFailure(
                modelClass: $this->modelClass,
                error: $validator->errors()->first(),
                errorClass: ValidationException::class,
            );

            throw new ValidationException($validator);
        }

        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        // ✅ On laisse l'erreur remonter telle quelle
        $user = $modelClass::generate($data);

        $this->logRepository->logRegistrationSuccess(
            authId: $user->getKey(),
            modelClass: $this->modelClass,
            withToken: $record->with_token,
        );

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function login(string $email, string $password): ?NemesisTokenRecord
    {
        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->loginFailure(
                modelClass: $this->modelClass,
                email: $email,
                error: 'User not found',
                errorClass: 'UserNotFoundException',
            );

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            $this->logRepository->loginFailure(
                modelClass: $this->modelClass,
                email: $email,
                error: 'Invalid password',
                errorClass: 'InvalidCredentialsException',
            );

            return null;
        }

        $this->logRepository->loginSuccess(
            authId: $user->getKey(),
            modelClass: $this->modelClass,
            email: $email,
        );

        return new NemesisTokenRecord(
            name: 'auth-login',
            source: 'login',
            metadata: new StrictDataObject([
                'auth_id' => $user->getKey(),
                'email' => $user->email,
            ]),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Authenticatable&Model $authenticatable, string $plainToken): bool
    {
        $token = $this->nemesis->getTokenByPlainText($plainToken, $authenticatable);

        if ($token === null) {
            $this->logRepository->logoutFailure(
                modelClass: $this->modelClass,
                email: $authenticatable->email ?? 'unknown',
                error: 'Token not found',
                errorClass: 'TokenNotFoundException',
            );

            return false;
        }

        $result = $this->nemesis->revoke($token);

        if ($result) {
            $this->logRepository->logoutSuccess(
                authId: $authenticatable->getKey(),
                modelClass: $this->modelClass,
                email: $authenticatable->email ?? 'unknown',
            );
        } else {
            $this->logRepository->logoutFailure(
                modelClass: $this->modelClass,
                email: $authenticatable->email ?? 'unknown',
                error: 'Failed to revoke token',
                errorClass: 'TokenRevokeException',
            );
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sendPasswordResetOtp(string $email): bool
    {
        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->logPasswordResetLinkSent(
                email: $email,
                success: false,
                error: 'User not found',
                errorClass: 'UserNotFoundException',
            );

            return false;
        }

        $purpose = $this->getPasswordResetPurpose();

        if ($this->otpService->isRateLimited($user, $purpose, 1)) {
            $this->logRepository->logPasswordResetLinkSent(
                email: $email,
                success: false,
                error: 'Rate limit exceeded',
                errorClass: 'RateLimitException',
            );

            return false;
        }

        $otp = $this->otpService->create($user, $purpose);

        $this->sendNotification(
            email: $user->email,
            subject: 'Password Reset Code',
            body: "Your password reset code is: {$otp->code}"
        );

        $this->logRepository->logPasswordResetLinkSent(
            email: $email,
            success: true,
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function resetPassword(string $email, string $code, string $password): bool
    {
        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->logPasswordResetFailure(
                email: $email,
                error: 'User not found',
                errorClass: 'UserNotFoundException',
            );

            return false;
        }

        $purpose = $this->getPasswordResetPurpose();

        $valid = $this->otpService->verify(
            identifier: $user,
            code: $code,
            purpose: $purpose
        );

        if (! $valid) {
            $this->logRepository->logPasswordResetFailure(
                email: $email,
                error: 'Invalid or expired OTP',
                errorClass: 'InvalidOtpException',
            );

            return false;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->logRepository->logPasswordResetSuccess(
            email: $email,
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool
    {
        if ($this->isEmailVerified($authenticatable)) {
            $this->logRepository->logVerificationSuccess(
                email: $authenticatable->email ?? 'unknown',
                modelClass: $this->modelClass,
                alreadyVerified: true,
            );

            return true;
        }

        $purpose = $this->getEmailVerificationPurpose();

        if ($this->otpService->isRateLimited($authenticatable, $purpose)) {
            $this->logRepository->logVerificationFailure(
                email: $authenticatable->email ?? 'unknown',
                modelClass: $this->modelClass,
                error: 'Rate limit exceeded',
                errorClass: 'RateLimitException',
            );

            return false;
        }

        $otp = $this->otpService->create(
            identifier: $authenticatable,
            purpose: $purpose,
        );

        $this->sendNotification(
            email: $authenticatable->email,
            subject: 'Email Verification Code',
            body: "Your email verification code is: {$otp->code}"
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function verifyEmail(string $email, string $code): bool
    {
        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->logVerificationFailure(
                email: $email,
                modelClass: $this->modelClass,
                error: 'User not found',
                errorClass: 'UserNotFoundException',
            );

            return false;
        }

        if ($this->isEmailVerified($user)) {
            $this->logRepository->logVerificationSuccess(
                email: $email,
                modelClass: $this->modelClass,
                alreadyVerified: true,
            );

            return true;
        }

        $purpose = $this->getEmailVerificationPurpose();

        $valid = $this->otpService->verify(
            identifier: $user,
            code: $code,
            purpose: $purpose
        );

        if (! $valid) {
            $this->logRepository->logVerificationFailure(
                email: $email,
                modelClass: $this->modelClass,
                error: 'Invalid or expired OTP',
                errorClass: 'InvalidOtpException',
            );

            return false;
        }

        $user->email_verified_at = now();
        $user->save();

        $this->logRepository->logVerificationSuccess(
            email: $email,
            modelClass: $this->modelClass,
            alreadyVerified: false,
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool
    {
        return $this->sendEmailVerificationOtp($authenticatable);
    }

    /**
     * {@inheritDoc}
     */
    public function isEmailVerified(Authenticatable&Model $authenticatable): bool
    {
        return $authenticatable->email_verified_at !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function userExists(string $email): bool
    {
        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        return $modelClass::where('email', strtolower($email))->exists();
    }

    /**
     * Send a notification email.
     */
    private function sendNotification(string $email, string $subject, string $body): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO($body),
            subject: new MessageSubjectVO($subject),
        );

        NotifiableBuilder::create()
            ->to(MailChannel::class, $email)
            ->subject($message->getSubjectValue())
            ->body($message->getBodyValue())
            ->type($message->getType())
            ->data($message->getData()->toArray())
            ->limit(1)
            ->sendNow();
    }

    /**
     * Get the validation rules for authentication fields only.
     *
     * @return array<string, array<int, mixed>>
     */
    private function getDefaultValidationRules(): array
    {
        /** @var T $modelClass */
        $modelClass = $this->modelClass;

        $table = (new $modelClass)->getTable();

        return [
            'email' => ['required', 'email', "unique:{$table}"],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Get the purpose for email verification.
     */
    private function getEmailVerificationPurpose(): PurposeVO
    {
        return new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );
    }

    /**
     * Get the purpose for password reset.
     */
    private function getPasswordResetPurpose(): PurposeVO
    {
        return new PurposeVO(
            value: self::PASSWORD_RESET_PURPOSE,
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );
    }
}
