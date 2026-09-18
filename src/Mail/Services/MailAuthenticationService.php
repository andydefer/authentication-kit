<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Services;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Records\LoginResultRecord;
use AndyDefer\AuthenticationKit\Mail\Records\NotificationMessageRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Interfaces\Transformable;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
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
class MailAuthenticationService implements MailAuthenticationInterface
{
    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

    private const PASSWORD_RESET_PURPOSE = 'password_reset';

    private const EMAIL_UPDATE_PURPOSE = 'email_update';

    private const TWO_FACTOR_PURPOSE_PREFIX = 'two_factor_';

    /**
     * @param  class-string<T>  $modelClass
     */
    protected function __construct(
        private readonly string $modelClass,
        private readonly NemesisInterface $nemesis,
        private readonly OtpService $otpService,
        private readonly LogRepositoryInterface $logRepository,
        private readonly AuthenticationKitConfigInterface $config,
        private readonly CookieTokenStorageInterface $cookieStorage,
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
     * @return static<U>
     */
    public static function for(string $modelClass): static
    {
        $nemesis = app(NemesisInterface::class);
        $otpService = app(OtpService::class);
        $logRepository = app(LogRepositoryInterface::class);
        $config = app(AuthenticationKitConfigInterface::class);
        $cookieStorage = app(CookieTokenStorageInterface::class);

        return new static($modelClass, $nemesis, $otpService, $logRepository, $config, $cookieStorage);
    }

    // ========================================================================
    // MÉTHODES PUBLIQUES FINALES
    // ========================================================================

    /**
     * {@inheritDoc}
     */
    public function register(AbstractRecord $record): array
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->beforeRegister($record);

        $data = $record->data->toArray();

        $validator = Validator::make($data, $this->getDefaultValidationRules());

        if ($validator->fails()) {
            $this->logRepository->logRegistrationFailure(
                modelClass: $this->modelClass,
                error: $validator->errors()->first(),
                errorType: ErrorType::VALIDATION_ERROR,
            );

            throw new ValidationException($validator);
        }

        $modelClass = $this->modelClass;

        $user = $modelClass::generate($data);

        $token = null;
        $plainToken = null;

        if ($record->with_token) {
            $tokenRecord = NemesisTokenRecord::from([
                'name' => 'auth-register',
                'source' => 'register',
                'metadata' => new StrictDataObject([
                    'auth_id' => $user->getKey(),
                    'email' => $user->email,
                ]),
            ]);

            [$token, $plainToken] = $this->nemesis->createWithPlainToken($tokenRecord, $user);

            if ($this->config->shouldStoreTokenInCookie()) {
                $this->cookieStorage->store($plainToken);
            }
        }

        $this->logRepository->logRegistrationSuccess(
            authId: $user->getKey(),
            modelClass: $this->modelClass,
            withToken: $record->with_token,
        );

        $this->afterRegister($user, $record);

        return [
            'user' => $user,
            'token' => $token,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function login(string $email, string $password): ?LoginResultRecord
    {
        $this->beforeLogin($email, $password);

        $modelClass = $this->modelClass;

        /** @var Model $user */
        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->loginFailure(
                modelClass: $this->modelClass,
                email: $email,
                error: 'User not found',
                errorType: ErrorType::USER_NOT_FOUND,
            );

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            $this->logRepository->loginFailure(
                modelClass: $this->modelClass,
                email: $email,
                error: 'Invalid password',
                errorType: ErrorType::INVALID_CREDENTIALS,
            );

            return null;
        }

        $this->logRepository->loginSuccess(
            authId: $user->getKey(),
            modelClass: $this->modelClass,
            email: $email,
        );

        $record = NemesisTokenRecord::from([
            'name' => 'auth-login',
            'source' => 'login',
            'metadata' => [
                'auth_id' => $user->getKey(),
                'email' => $user->getRawOriginal('email'),
            ],
        ]);

        [$token, $plainToken] = $this->nemesis->createWithPlainToken($record, $user);

        if ($this->config->shouldStoreTokenInCookie()) {
            $this->cookieStorage->store($plainToken);
        }

        $this->afterLogin($user);

        return new LoginResultRecord(
            token_record: NemesisTokenRecord::from($this->normalize($token)),
            plain_token: $plainToken,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Authenticatable&Model $authenticatable, string $plainToken): bool
    {
        $this->beforeLogout($authenticatable, $plainToken);

        $token = $this->nemesis->getTokenByPlainText($plainToken, $authenticatable);

        if ($token === null) {
            $this->logRepository->logoutFailure(
                modelClass: $this->modelClass,
                email: $authenticatable->getRawOriginal('email', 'unknown'),
                error: 'Token not found',
                errorType: ErrorType::TOKEN_NOT_FOUND,
            );

            return false;
        }

        $result = $this->nemesis->revoke($token);

        if ($result) {
            $this->logRepository->logoutSuccess(
                authId: $authenticatable->getKey(),
                modelClass: $this->modelClass,
                email: $authenticatable->getRawOriginal('email', 'unknown'),
            );

            if ($this->config->shouldStoreTokenInCookie()) {
                $this->cookieStorage->forget();
            }

            $this->afterLogout($authenticatable);
        } else {
            $this->logRepository->logoutFailure(
                modelClass: $this->modelClass,
                email: $authenticatable->getRawOriginal('email', 'unknown'),
                error: 'Failed to revoke token',
                errorType: ErrorType::TOKEN_REVOKE_FAILED,
            );
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function sendPasswordResetOtp(string $email): bool
    {
        $this->beforeSendPasswordResetOtp($email);

        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->logPasswordResetLinkSent(
                email: $email,
                success: false,
                error: 'User not found',
                errorType: ErrorType::USER_NOT_FOUND,
            );

            $this->afterSendPasswordResetOtp($email, false);

            return false;
        }

        $purpose = $this->getPasswordResetPurpose();
        $rateLimitAttempts = $this->config->getPasswordResetRateLimitAttempts();

        $otpTtl = $purpose->getTtl() ?? 600;
        $window = now()->subSeconds($otpTtl);

        if ($this->otpService->isRateLimited($user, $purpose, $rateLimitAttempts, $window)) {
            $this->logRepository->logPasswordResetLinkSent(
                email: $email,
                success: false,
                error: 'Rate limit exceeded',
                errorType: ErrorType::RATE_LIMIT_EXCEEDED,
            );

            $this->afterSendPasswordResetOtp($email, false);

            return false;
        }

        $otp = $this->otpService->create($user, $purpose);

        $result = $this->sendNotification(
            $this->buildPasswordResetNotification($this->normalize($user->email), $this->normalize($otp->code))
        );

        $this->logRepository->logPasswordResetLinkSent(
            email: $email,
            success: $result->allSuccess(),
        );

        $this->afterSendPasswordResetOtp($email, $result->allSuccess());

        return $result->allSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function resetPassword(string $email, string $code, string $password): bool
    {
        $this->beforeResetPassword($email, $code, $password);

        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->logPasswordResetFailure(
                email: $email,
                error: 'User not found',
                errorType: ErrorType::USER_NOT_FOUND,
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
                errorType: ErrorType::INVALID_OTP,
            );

            return false;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->logRepository->logPasswordResetSuccess(
            email: $email,
        );

        $this->afterResetPassword($user);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool
    {
        if ($this->isEmailVerified($authenticatable)) {
            $this->logRepository->logVerificationSuccess(
                email: $authenticatable->getRawOriginal('email', 'unknown'),
                modelClass: $this->modelClass,
                alreadyVerified: true,
            );

            return true;
        }

        $purpose = $this->getEmailVerificationPurpose();
        $rateLimitAttempts = $this->config->getEmailVerificationRateLimitAttempts();

        $otpTtl = $purpose->getTtl() ?? 300;
        $window = now()->subSeconds($otpTtl);

        if ($this->otpService->isRateLimited($authenticatable, $purpose, $rateLimitAttempts, $window)) {
            $this->logRepository->logVerificationFailure(
                email: $authenticatable->getRawOriginal('email', 'unknown'),
                modelClass: $this->modelClass,
                error: 'Rate limit exceeded',
                errorType: ErrorType::RATE_LIMIT_EXCEEDED,
            );

            return false;
        }

        $otp = $this->otpService->create(
            identifier: $authenticatable,
            purpose: $purpose,
        );

        $this->sendNotification(
            $this->buildEmailVerificationNotification($authenticatable->email, $otp->code)
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function verifyEmail(string $email, string $code): bool
    {
        $this->beforeVerifyEmail($email, $code);

        $modelClass = $this->modelClass;

        $user = $modelClass::where('email', strtolower($email))->first();

        if ($user === null) {
            $this->logRepository->logVerificationFailure(
                email: $email,
                modelClass: $this->modelClass,
                error: 'User not found',
                errorType: ErrorType::USER_NOT_FOUND,
            );

            return false;
        }

        if ($this->isEmailVerified($user)) {
            $this->logRepository->logVerificationSuccess(
                email: $email,
                modelClass: $this->modelClass,
                alreadyVerified: true,
            );

            $this->afterVerifyEmail($user);

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
                errorType: ErrorType::INVALID_OTP,
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

        $this->afterVerifyEmail($user);

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
    public function userExists(string|Transformable $email): bool
    {
        $modelClass = $this->modelClass;

        return $modelClass::where('email', strtolower($email))->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function sendEmailUpdateOtp(Authenticatable&Model $authenticatable, string $newEmail): bool
    {
        $this->beforeSendEmailUpdateOtp($authenticatable, $newEmail);

        $normalizedEmail = strtolower($newEmail);

        if ($this->userExists($normalizedEmail)) {
            $this->logRepository->logEmailUpdateFailure(
                authId: $authenticatable->getKey(),
                modelClass: $this->modelClass,
                error: 'Email already taken',
                errorType: ErrorType::EMAIL_ALREADY_TAKEN,
            );

            return false;
        }

        $purpose = $this->getEmailUpdatePurpose();
        $rateLimitAttempts = $this->config->getEmailUpdateRateLimitAttempts();

        $otpTtl = $purpose->getTtl() ?? 600;
        $window = now()->subSeconds($otpTtl);

        if ($this->otpService->isRateLimited($authenticatable, $purpose, $rateLimitAttempts, $window)) {
            $this->logRepository->logEmailUpdateFailure(
                authId: $authenticatable->getKey(),
                modelClass: $this->modelClass,
                error: 'Rate limit exceeded',
                errorType: ErrorType::RATE_LIMIT_EXCEEDED,
            );

            return false;
        }

        $otp = $this->otpService->create(
            identifier: $authenticatable,
            purpose: $purpose,
        );

        $result = $this->sendNotification(
            $this->buildEmailUpdateNotification($this->normalize($normalizedEmail), $this->normalize($otp->code))
        );

        $this->logRepository->logEmailUpdateSuccess(
            authId: $authenticatable->getKey(),
            modelClass: $this->modelClass,
            newEmail: $normalizedEmail,
        );

        $this->afterSendEmailUpdateOtp($authenticatable, $normalizedEmail);

        return $result->allSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function updateEmail(Authenticatable&Model $authenticatable, string $newEmail, string $code): bool
    {
        $this->beforeUpdateEmail($authenticatable, $newEmail, $code);

        $normalizedEmail = strtolower($newEmail);

        $purpose = $this->getEmailUpdatePurpose();

        $valid = $this->otpService->verify(
            identifier: $authenticatable,
            code: $code,
            purpose: $purpose,
        );

        if (! $valid) {
            $this->logRepository->logEmailUpdateFailure(
                authId: $authenticatable->getKey(),
                modelClass: $this->modelClass,
                error: 'Invalid or expired OTP',
                errorType: ErrorType::INVALID_OTP,
            );

            return false;
        }

        $authenticatable->email = $normalizedEmail;
        $authenticatable->email_verified_at = null;
        $authenticatable->save();

        $this->logRepository->logEmailUpdateSuccess(
            authId: $authenticatable->getKey(),
            modelClass: $this->modelClass,
            newEmail: $normalizedEmail,
        );

        $this->afterUpdateEmail($authenticatable, $normalizedEmail);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function sendTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose): bool
    {
        $this->beforeSendTwoFactorOtp($authenticatable, $purpose);

        $otpPurpose = $this->getTwoFactorPurpose($purpose);
        $rateLimitAttempts = $this->config->getTwoFactorRateLimitAttempts();

        $otpTtl = $otpPurpose->getTtl() ?? 300;
        $window = now()->subSeconds($otpTtl);

        if ($this->otpService->isRateLimited($authenticatable, $otpPurpose, $rateLimitAttempts, $window)) {
            $this->logRepository->logTwoFactorSent(
                authId: $authenticatable->getKey(),
                modelClass: $this->modelClass,
                purpose: $purpose,
                success: false,
            );

            return false;
        }

        $otp = $this->otpService->create(
            identifier: $authenticatable,
            purpose: $otpPurpose,
        );

        $result = $this->sendNotification(
            $this->buildTwoFactorNotification(
                $this->normalize($authenticatable->email),
                $this->normalize($otp->code),
                $purpose,
            )
        );

        $this->logRepository->logTwoFactorSent(
            authId: $authenticatable->getKey(),
            modelClass: $this->modelClass,
            purpose: $purpose,
            success: $result->allSuccess(),
        );

        $this->afterSendTwoFactorOtp($authenticatable, $purpose);

        return $result->allSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function verifyTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose, string $code): bool
    {
        $this->beforeVerifyTwoFactorOtp($authenticatable, $purpose, $code);

        $otpPurpose = $this->getTwoFactorPurpose($purpose);

        $valid = $this->otpService->verify(
            identifier: $authenticatable,
            code: $code,
            purpose: $otpPurpose,
        );

        $this->logRepository->logTwoFactorVerified(
            authId: $authenticatable->getKey(),
            modelClass: $this->modelClass,
            purpose: $purpose,
            success: $valid,
        );

        if (! $valid) {
            return false;
        }

        $this->afterVerifyTwoFactorOtp($authenticatable, $purpose);

        return true;
    }

    // ========================================================================
    // MÉTHODES PROTECTED - HOOKS EXTENSIBLES
    // ========================================================================

    /**
     * Hook called before registration.
     */
    protected function beforeRegister(AbstractRecord $record): void {}

    /**
     * Hook called after successful registration.
     */
    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void {}

    /**
     * Hook called before login.
     */
    protected function beforeLogin(string|Transformable $email, string|Transformable $password): void {}

    /**
     * Hook called after successful login.
     */
    protected function afterLogin(Model&Authenticatable $user): void {}

    /**
     * Hook called before logout.
     */
    protected function beforeLogout(Authenticatable&Model $authenticatable, string|Transformable $plainToken): void {}

    /**
     * Hook called after successful logout.
     */
    protected function afterLogout(Authenticatable&Model $authenticatable): void {}

    /**
     * Hook called before sending password reset OTP.
     */
    protected function beforeSendPasswordResetOtp(string|Transformable $email): void {}

    /**
     * Hook called after sending password reset OTP.
     */
    protected function afterSendPasswordResetOtp(string|Transformable $email, bool $success): void {}

    /**
     * Hook called before resetting password.
     */
    protected function beforeResetPassword(string|Transformable $email, string|Transformable $code, string|Transformable $password): void {}

    /**
     * Hook called after successful password reset.
     */
    protected function afterResetPassword(Model&Authenticatable $user): void {}

    /**
     * Hook called before email verification.
     */
    protected function beforeVerifyEmail(string|Transformable $email, string|Transformable $code): void {}

    /**
     * Hook called after successful email verification.
     */
    protected function afterVerifyEmail(Model&Authenticatable $user): void {}

    /**
     * Hook called before sending an email update OTP.
     */
    protected function beforeSendEmailUpdateOtp(Authenticatable&Model $authenticatable, string|Transformable $newEmail): void {}

    /**
     * Hook called after sending an email update OTP.
     */
    protected function afterSendEmailUpdateOtp(Authenticatable&Model $authenticatable, string|Transformable $newEmail): void {}

    /**
     * Hook called before confirming an email update.
     */
    protected function beforeUpdateEmail(Authenticatable&Model $authenticatable, string|Transformable $newEmail, string|Transformable $code): void {}

    /**
     * Hook called after confirming an email update.
     */
    protected function afterUpdateEmail(Authenticatable&Model $authenticatable, string|Transformable $newEmail): void {}

    /**
     * Hook called before sending a two-factor OTP.
     */
    protected function beforeSendTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose): void {}

    /**
     * Hook called after sending a two-factor OTP.
     */
    protected function afterSendTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose): void {}

    /**
     * Hook called before verifying a two-factor OTP.
     */
    protected function beforeVerifyTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose, string|Transformable $code): void {}

    /**
     * Hook called after verifying a two-factor OTP.
     */
    protected function afterVerifyTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose): void {}

    // ========================================================================
    // MÉTHODES DE NOTIFICATION - EXTENSIBLES
    // ========================================================================

    /**
     * Build the password reset notification message.
     */
    protected function buildPasswordResetNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord
    {
        $normalizedOtp = $this->normalize($otp);

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => 'Password Reset Code',
            'body' => "Your password reset code is: {$normalizedOtp}",
        ]);
    }

    /**
     * Build the email verification notification message.
     */
    protected function buildEmailVerificationNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord
    {
        $normalizedOtp = $this->normalize($otp);

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => 'Email Verification Code',
            'body' => "Your email verification code is: {$normalizedOtp}",
        ]);
    }

    /**
     * Build the email update notification message.
     */
    protected function buildEmailUpdateNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord
    {
        $normalizedOtp = $this->normalize($otp);

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => 'Confirm Your New Email Address',
            'body' => "Use this code to confirm your new email: {$normalizedOtp}",
        ]);
    }

    /**
     * Build the two-factor authentication notification message.
     */
    protected function buildTwoFactorNotification(string|Transformable $email, string|Transformable $otp, string|Transformable $purpose): NotificationMessageRecord
    {
        $normalizedOtp = $this->normalize($otp);
        $normalizedPurpose = $this->normalize($purpose);

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => 'Your Two-Factor Authentication Code',
            'body' => "Your two-factor code for {$normalizedPurpose} is: {$normalizedOtp}",
        ]);
    }

    /**
     * Get the password validation rules.
     */
    public static function getPasswordValidationRules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    // ========================================================================
    // MÉTHODES PRIVÉES
    // ========================================================================

    /**
     * Send a notification email.
     */
    private function sendNotification(NotificationMessageRecord $record): SendResultCollection
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO($record->body),
            subject: new MessageSubjectVO($record->subject),
        );

        return NotifiableBuilder::create()
            ->to(MailChannel::class, $record->email)
            ->subject($message->getSubjectValue())
            ->body($message->getBodyValue())
            ->type($message->getType())
            ->data($message->getData()->toArray())
            ->limit(1)
            ->sendNow();
    }

    /**
     * Get the validation rules for authentication fields only.
     */
    private function getDefaultValidationRules(): array
    {
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

    /**
     * Get the purpose for email update.
     */
    private function getEmailUpdatePurpose(): PurposeVO
    {
        return new PurposeVO(
            value: self::EMAIL_UPDATE_PURPOSE,
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3
        );
    }

    /**
     * Get the purpose for a two-factor OTP.
     *
     * The purpose value is namespaced with a prefix so that two-factor
     * OTPs for different sensitive actions (change_email, change_password,
     * delete_account, ...) remain isolated from one another.
     */
    private function getTwoFactorPurpose(string $context): PurposeVO
    {
        return new PurposeVO(
            value: self::TWO_FACTOR_PURPOSE_PREFIX.$context,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3
        );
    }

    private function normalize(mixed $value): mixed
    {
        return action_normalizer_chain(true)->normalize($value);
    }
}
