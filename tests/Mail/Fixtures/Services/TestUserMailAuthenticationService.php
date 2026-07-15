<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Services;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
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

final class TestUserMailAuthenticationService implements MailAuthenticationInterface
{
    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

    private const PASSWORD_RESET_PURPOSE = 'password_reset';

    private const RATE_LIMIT_ATTEMPTS = 1; // ✅ Seuil pour les tests

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly OtpService $otpService,
    ) {}

    public function register(AbstractRecord $record): Model&Authenticatable
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $data = $record->data->toArray();

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:test_users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $user = TestUserMail::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']), // ✅ Normalisation
            'password' => bcrypt($validated['password']),
        ]);

        return $user;
    }

    public function login(string $email, string $password): ?NemesisTokenRecord
    {
        $user = TestUserMail::where('email', strtolower($email))->first(); // ✅ Normalisation

        if ($user === null) {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return new NemesisTokenRecord(
            name: 'test-login',
            source: 'login',
            metadata: new StrictDataObject([
                'auth_id' => $user->id,
                'email' => $user->email,
            ]),
        );
    }

    public function logout(Authenticatable&Model $authenticatable, string $plainToken): bool
    {
        $token = $this->nemesis->getTokenByPlainText($plainToken, $authenticatable);

        if ($token === null) {
            return false;
        }

        return $this->nemesis->revoke($token);
    }

    /**
     * {@inheritDoc}
     */
    public function sendPasswordResetOtp(string $email): bool
    {
        $normalizedEmail = strtolower($email); // ✅ Normalisation
        $user = TestUserMail::where('email', $normalizedEmail)->first();

        if ($user === null) {
            return false;
        }

        $purpose = $this->getPasswordResetPurpose();

        // ✅ Vérifier le rate limit avec un seuil bas pour les tests
        if ($this->otpService->isRateLimited($user, $purpose, self::RATE_LIMIT_ATTEMPTS)) {
            return false;
        }

        // ✅ Créer l'OTP
        $otp = $this->otpService->create($user, $purpose);

        // ✅ Envoyer la notification par email
        $message = new NotificationMessageVO(
            body: new MessageBodyVO("Your password reset code is: {$otp->code}"),
            subject: new MessageSubjectVO('Password Reset Code'),
        );

        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, $user->email)
            ->subject($message->getSubjectValue())
            ->body($message->getBodyValue())
            ->type($message->getType())
            ->data($message->getData()->toArray())
            ->limit(1)
            ->sendNow();

        return $results->allSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function resetPassword(string $email, string $code, string $password): bool
    {
        $user = TestUserMail::where('email', strtolower($email))->first(); // ✅ Normalisation

        if ($user === null) {
            return false;
        }

        $purpose = $this->getPasswordResetPurpose();

        $valid = $this->otpService->verify(
            identifier: $user,
            code: $code,
            purpose: $purpose
        );

        if (! $valid) {
            return false;
        }

        $user->password = bcrypt($password);
        $user->save();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool
    {
        if (! $authenticatable instanceof TestUserMail) {
            return false;
        }

        if ($this->isEmailVerified($authenticatable)) {
            return true;
        }

        $purpose = $this->getEmailVerificationPurpose();

        if ($this->otpService->isRateLimited($authenticatable, $purpose)) {
            return false;
        }

        // ✅ Créer l'OTP
        $otp = $this->otpService->create(
            identifier: $authenticatable,
            purpose: $purpose,
        );

        // ✅ Envoyer la notification par email
        $message = new NotificationMessageVO(
            body: new MessageBodyVO("Your email verification code is: {$otp->code}"),
            subject: new MessageSubjectVO('Email Verification Code'),
        );

        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, $authenticatable->email)
            ->subject($message->getSubjectValue())
            ->body($message->getBodyValue())
            ->type($message->getType())
            ->data($message->getData()->toArray())
            ->limit(1)
            ->sendNow();

        return $results->allSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function verifyEmail(string $email, string $code): bool
    {
        $user = TestUserMail::where('email', strtolower($email))->first(); // ✅ Normalisation

        if ($user === null) {
            return false;
        }

        if ($this->isEmailVerified($user)) {
            return true;
        }

        $purpose = $this->getEmailVerificationPurpose();

        $valid = $this->otpService->verify(
            identifier: $user,
            code: $code,
            purpose: $purpose
        );

        if (! $valid) {
            return false;
        }

        $user->email_verified_at = now();
        $user->save();

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
        if (! $authenticatable instanceof TestUserMail) {
            return false;
        }

        return $authenticatable->email_verified_at !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function userExists(string $email): bool
    {
        return TestUserMail::where('email', strtolower($email))->exists(); // ✅ Normalisation
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
