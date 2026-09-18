<?php

// tests/Integration/Mail/Services/MailAuthenticationServiceTest.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Mail\Services;

use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Records\LoginResultRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelOtp\Models\Otp;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use AndyDefer\Nemesis\Models\NemesisToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class MailAuthenticationServiceTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private MailAuthenticationService $service;

    private OtpService $otpService;

    private const TEST_EMAIL = 'test@example.com';

    private const TEST_PASSWORD = 'Password123!';

    private const TEST_NEW_EMAIL = 'new-email@example.com';

    private const TEST_OTP = '123456';

    private const TEST_TWO_FACTOR_PURPOSE = 'change_email';

    protected function setUp(): void
    {
        parent::setUp();

        $this->otpService = app(OtpService::class);

        $this->service = MailAuthenticationService::for(TestUserMail::class);
    }

    private function createTestUser(array $attributes = []): TestUserMail
    {
        return TestUserMail::create(array_merge([
            'name' => 'Test User',
            'email' => self::TEST_EMAIL,
            'password' => Hash::make(self::TEST_PASSWORD),
            'email_verified_at' => null,
        ], $attributes));
    }

    private function createOtp(TestUserMail $user, string $purpose = 'email_verification'): Otp
    {
        $purposeVO = new PurposeVO(
            value: $purpose,
            label: match ($purpose) {
                'email_verification' => 'Email Verification',
                'email_update' => 'Email Update',
                default => 'Password Reset',
            },
            ttl: $purpose === 'email_verification' ? 300 : 600,
            maxAttempts: 3
        );

        return $this->otpService->create($user, $purposeVO);
    }

    private function createTwoFactorOtp(TestUserMail $user, string $context = self::TEST_TWO_FACTOR_PURPOSE): Otp
    {
        $purposeVO = new PurposeVO(
            value: 'two_factor_'.$context,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        return $this->otpService->create($user, $purposeVO);
    }

    public function test_for_returns_instance_for_valid_model(): void
    {
        $service = MailAuthenticationService::for(TestUserMail::class);

        $this->assertInstanceOf(MailAuthenticationService::class, $service);
    }

    public function test_for_throws_exception_for_invalid_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model stdClass must implement');

        MailAuthenticationService::for(\stdClass::class);
    }

    public function test_register_creates_user_successfully(): void
    {
        $record = new EmailRegisterAuthRecord(
            model_type: TestUserMail::class,
            data: new StrictDataObject([
                'name' => 'Test User',
                'email' => self::TEST_EMAIL,
                'password' => self::TEST_PASSWORD,
                'password_confirmation' => self::TEST_PASSWORD,
            ]),
            with_token: false
        );

        $result = $this->service->register($record);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plain_token', $result);

        $user = $result['user'];
        $this->assertInstanceOf(TestUserMail::class, $user);
        $this->assertEquals(self::TEST_EMAIL, $user->email);
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
        $this->assertDatabaseHas('test_users', [
            'email' => self::TEST_EMAIL,
        ]);
        $this->assertNull($result['token']);
        $this->assertNull($result['plain_token']);
    }

    public function test_register_creates_user_with_token(): void
    {
        $record = new EmailRegisterAuthRecord(
            model_type: TestUserMail::class,
            data: new StrictDataObject([
                'name' => 'Test User',
                'email' => self::TEST_EMAIL,
                'password' => self::TEST_PASSWORD,
                'password_confirmation' => self::TEST_PASSWORD,
            ]),
            with_token: true
        );

        $result = $this->service->register($record);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plain_token', $result);

        $user = $result['user'];
        $this->assertInstanceOf(TestUserMail::class, $user);
        $this->assertDatabaseHas('test_users', [
            'email' => self::TEST_EMAIL,
        ]);

        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);
        $this->assertEquals('auth-register', $token->name);
        $this->assertEquals('register', $token->source);
        $this->assertNotNull($result['token']);
        $this->assertNotNull($result['plain_token']);
    }

    public function test_register_throws_validation_exception_for_invalid_data(): void
    {
        $record = new EmailRegisterAuthRecord(
            model_type: TestUserMail::class,
            data: new StrictDataObject([
                'email' => 'invalid-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ]),
            with_token: false
        );

        $this->expectException(ValidationException::class);

        $this->service->register($record);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->login(self::TEST_EMAIL, self::TEST_PASSWORD);

        $this->assertNotNull($result);
        $this->assertInstanceOf(LoginResultRecord::class, $result);

        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);
        $this->assertEquals('auth-login', $token->name);
        $this->assertEquals('login', $token->source);
    }

    public function test_login_returns_null_for_invalid_password(): void
    {
        $this->createTestUser();

        $result = $this->service->login(self::TEST_EMAIL, 'wrong-password');

        $this->assertNull($result);

        $tokenCount = NemesisToken::where('tokenable_type', TestUserMail::class)->count();
        $this->assertEquals(0, $tokenCount);
    }

    public function test_login_returns_null_for_non_existent_user(): void
    {
        $result = $this->service->login('nonexistent@example.com', self::TEST_PASSWORD);

        $this->assertNull($result);
    }

    public function test_logout_revokes_token_successfully(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->login(self::TEST_EMAIL, self::TEST_PASSWORD);
        $this->assertNotNull($result);

        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);

        $logoutResult = $this->service->logout($user, $result->plain_token ?? 'auth-login');

        $this->assertTrue($logoutResult);

        $token->refresh();
        $this->assertNotNull($token->deleted_at);
    }

    public function test_logout_returns_false_for_invalid_token(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->logout($user, 'invalid-token');

        $this->assertFalse($result);
    }

    public function test_send_password_reset_otp_sends_otp_for_existing_user(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->sendPasswordResetOtp(self::TEST_EMAIL);

        $this->assertTrue($result);

        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->first();

        $this->assertNotNull($otp);
        $this->assertEquals('password_reset', $otp->getPurpose()->getValue()->value);
        $this->assertEquals(0, $otp->attempts);
    }

    public function test_send_password_reset_otp_returns_false_for_non_existent_user(): void
    {
        $result = $this->service->sendPasswordResetOtp('nonexistent@example.com');

        $this->assertFalse($result);
    }

    public function test_send_password_reset_otp_respects_rate_limit(): void
    {
        $user = $this->createTestUser();

        $this->service->sendPasswordResetOtp(self::TEST_EMAIL);
        $this->service->sendPasswordResetOtp(self::TEST_EMAIL);
        $this->service->sendPasswordResetOtp(self::TEST_EMAIL);

        $result = $this->service->sendPasswordResetOtp(self::TEST_EMAIL);

        $this->assertFalse($result);

        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(3, $otpCount);
    }

    public function test_reset_password_with_valid_otp(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'password_reset');

        $result = $this->service->resetPassword(self::TEST_EMAIL, $otp->code, 'NewPassword123!');

        $this->assertTrue($result);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        $freshOtp = $otp->refresh();
        $this->assertGreaterThan(0, $freshOtp->attempts);
    }

    public function test_reset_password_with_invalid_otp(): void
    {
        $this->createTestUser();

        $result = $this->service->resetPassword(self::TEST_EMAIL, '000000', 'NewPassword123!');

        $this->assertFalse($result);

        $user = TestUserMail::where('email', self::TEST_EMAIL)->first();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
    }

    public function test_reset_password_with_expired_otp(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'password_reset');
        $otp->expires_at = now()->subMinutes(10);
        $otp->save();

        $result = $this->service->resetPassword(self::TEST_EMAIL, $otp->code, 'NewPassword123!');

        $this->assertFalse($result);

        $user->refresh();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
    }

    public function test_reset_password_with_exceeded_attempts(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'password_reset');

        $otp->attempts = 3;
        $otp->save();

        $result = $this->service->resetPassword(self::TEST_EMAIL, $otp->code, 'NewPassword123!');

        $this->assertFalse($result);

        $user->refresh();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
    }

    public function test_send_email_verification_otp_sends_otp(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->sendEmailVerificationOtp($user);

        $this->assertTrue($result);

        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->first();

        $this->assertNotNull($otp);
        $this->assertEquals('email_verification', $otp->getPurpose()->getValue()->value);
        $this->assertEquals(0, $otp->attempts);
    }

    public function test_send_email_verification_otp_returns_true_when_already_verified(): void
    {
        $user = $this->createTestUser(['email_verified_at' => now()]);

        $result = $this->service->sendEmailVerificationOtp($user);

        $this->assertTrue($result);

        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(0, $otpCount);
    }

    public function test_send_email_verification_otp_respects_rate_limit(): void
    {
        $user = $this->createTestUser();

        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);

        $result = $this->service->sendEmailVerificationOtp($user);

        $this->assertFalse($result);

        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(5, $otpCount);
    }

    public function test_verify_email_with_valid_otp(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_verification');

        $result = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);

        $this->assertTrue($result);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        $otp->refresh();
        $this->assertGreaterThan(0, $otp->attempts);
    }

    public function test_verify_email_with_invalid_otp(): void
    {
        $this->createTestUser();

        $result = $this->service->verifyEmail(self::TEST_EMAIL, '000000');

        $this->assertFalse($result);

        $user = TestUserMail::where('email', self::TEST_EMAIL)->first();
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_with_expired_otp(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_verification');
        $otp->expires_at = now()->subMinutes(10);
        $otp->save();

        $result = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);

        $this->assertFalse($result);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_with_exceeded_attempts(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_verification');

        $otp->attempts = 3;
        $otp->save();

        $result = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);

        $this->assertFalse($result);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_returns_true_when_already_verified(): void
    {
        $user = $this->createTestUser(['email_verified_at' => now()]);

        $result = $this->service->verifyEmail(self::TEST_EMAIL, 'any-otp');

        $this->assertTrue($result);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_resend_email_verification_otp(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->resendEmailVerificationOtp($user);

        $this->assertTrue($result);

        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(1, $otpCount);
    }

    public function test_is_email_verified(): void
    {
        $user = $this->createTestUser();

        $this->assertFalse($this->service->isEmailVerified($user));

        $user->email_verified_at = now();
        $user->save();

        $this->assertTrue($this->service->isEmailVerified($user));
    }

    public function test_user_exists(): void
    {
        $this->createTestUser();

        $this->assertTrue($this->service->userExists(self::TEST_EMAIL));
        $this->assertFalse($this->service->userExists('nonexistent@example.com'));
    }

    // ========================================================================
    // EMAIL UPDATE — TESTS
    // ========================================================================

    public function test_send_email_update_otp_creates_otp_for_new_email(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->sendEmailUpdateOtp($user, self::TEST_NEW_EMAIL);

        $this->assertTrue($result);

        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->first();

        $this->assertNotNull($otp);
        $this->assertEquals('email_update', $otp->getPurpose()->getValue()->value);
        $this->assertEquals(0, $otp->attempts);
    }

    public function test_send_email_update_otp_returns_false_when_email_already_taken(): void
    {
        $this->createTestUser();
        $otherUser = $this->createTestUser(['email' => self::TEST_NEW_EMAIL]);

        $result = $this->service->sendEmailUpdateOtp($otherUser, self::TEST_EMAIL);

        $this->assertFalse($result);

        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $otherUser->id)
            ->count();

        $this->assertEquals(0, $otpCount);
    }

    public function test_send_email_update_otp_respects_rate_limit(): void
    {
        $user = $this->createTestUser();

        $this->service->sendEmailUpdateOtp($user, 'new1@example.com');
        $this->service->sendEmailUpdateOtp($user, 'new2@example.com');
        $this->service->sendEmailUpdateOtp($user, 'new3@example.com');

        $result = $this->service->sendEmailUpdateOtp($user, 'new4@example.com');

        $this->assertFalse($result);

        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(3, $otpCount);
    }

    public function test_update_email_with_valid_otp(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_update');

        $result = $this->service->updateEmail($user, self::TEST_NEW_EMAIL, $otp->code);

        $this->assertTrue($result);

        $user->refresh();
        $this->assertEquals(self::TEST_NEW_EMAIL, $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_update_email_with_invalid_otp(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->updateEmail($user, self::TEST_NEW_EMAIL, '000000');

        $this->assertFalse($result);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    public function test_update_email_with_expired_otp(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_update');
        $otp->expires_at = now()->subMinutes(10);
        $otp->save();

        $result = $this->service->updateEmail($user, self::TEST_NEW_EMAIL, $otp->code);

        $this->assertFalse($result);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    public function test_update_email_with_exceeded_attempts(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_update');
        $otp->attempts = 3;
        $otp->save();

        $result = $this->service->updateEmail($user, self::TEST_NEW_EMAIL, $otp->code);

        $this->assertFalse($result);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    public function test_update_email_resets_email_verified_at(): void
    {
        $user = $this->createTestUser(['email_verified_at' => now()]);

        $otp = $this->createOtp($user, 'email_update');

        $result = $this->service->updateEmail($user, self::TEST_NEW_EMAIL, $otp->code);

        $this->assertTrue($result);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($this->service->isEmailVerified($user));
    }

    public function test_update_email_normalizes_email_to_lowercase(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createOtp($user, 'email_update');

        $result = $this->service->updateEmail($user, 'NEW-EMAIL@Example.COM', $otp->code);

        $this->assertTrue($result);

        $user->refresh();
        $this->assertEquals('new-email@example.com', $user->email);
    }

    // ========================================================================
    // TWO-FACTOR AUTHENTICATION — TESTS
    // ========================================================================

    public function test_send_two_factor_otp_creates_otp_for_purpose(): void
    {
        $user = $this->createTestUser();

        $result = $this->service->sendTwoFactorOtp($user, self::TEST_TWO_FACTOR_PURPOSE);

        $this->assertTrue($result);

        $purpose = new PurposeVO(
            value: 'two_factor_'.self::TEST_TWO_FACTOR_PURPOSE,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
        $this->assertEquals(0, $otps->first()->attempts);
    }

    public function test_send_two_factor_otp_isolates_purposes(): void
    {
        $user = $this->createTestUser();

        $this->service->sendTwoFactorOtp($user, 'change_email');
        $this->service->sendTwoFactorOtp($user, 'change_password');

        $emailPurpose = new PurposeVO(
            value: 'two_factor_change_email',
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $passwordPurpose = new PurposeVO(
            value: 'two_factor_change_password',
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $this->assertCount(1, $this->otpService->getAllFor($user, $emailPurpose));
        $this->assertCount(1, $this->otpService->getAllFor($user, $passwordPurpose));
    }

    public function test_send_two_factor_otp_respects_rate_limit(): void
    {
        $user = $this->createTestUser();

        $this->service->sendTwoFactorOtp($user, self::TEST_TWO_FACTOR_PURPOSE);
        $this->service->sendTwoFactorOtp($user, self::TEST_TWO_FACTOR_PURPOSE);
        $this->service->sendTwoFactorOtp($user, self::TEST_TWO_FACTOR_PURPOSE);

        $result = $this->service->sendTwoFactorOtp($user, self::TEST_TWO_FACTOR_PURPOSE);

        $this->assertFalse($result);

        $purpose = new PurposeVO(
            value: 'two_factor_'.self::TEST_TWO_FACTOR_PURPOSE,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(3, $otps);
    }

    public function test_verify_two_factor_otp_with_valid_code(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createTwoFactorOtp($user);

        $result = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );

        $this->assertTrue($result);
    }

    public function test_verify_two_factor_otp_with_invalid_code(): void
    {
        $this->createTestUser();

        $user = TestUserMail::where('email', self::TEST_EMAIL)->first();

        $result = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            '000000',
        );

        $this->assertFalse($result);
    }

    public function test_verify_two_factor_otp_with_wrong_purpose(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createTwoFactorOtp($user, 'change_email');

        $result = $this->service->verifyTwoFactorOtp(
            $user,
            'change_password',
            $otp->code,
        );

        $this->assertFalse($result);
    }

    public function test_verify_two_factor_otp_with_expired_code(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createTwoFactorOtp($user);
        $otp->expires_at = now()->subMinutes(10);
        $otp->save();

        $result = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );

        $this->assertFalse($result);
    }

    public function test_verify_two_factor_otp_with_exceeded_attempts(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createTwoFactorOtp($user);
        $otp->attempts = 3;
        $otp->save();

        $result = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );

        $this->assertFalse($result);
    }

    public function test_verify_two_factor_otp_uses_code_once(): void
    {
        $user = $this->createTestUser();

        $otp = $this->createTwoFactorOtp($user);

        $first = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );
        $this->assertTrue($first);

        $second = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );
        $this->assertFalse($second);
    }

    // ========================================================================
    // COMPLETE FLOWS
    // ========================================================================

    public function test_complete_authentication_flow(): void
    {
        // 1. Register
        $record = new EmailRegisterAuthRecord(
            model_type: TestUserMail::class,
            data: new StrictDataObject([
                'name' => 'Test User',
                'email' => self::TEST_EMAIL,
                'password' => self::TEST_PASSWORD,
                'password_confirmation' => self::TEST_PASSWORD,
            ]),
            with_token: true
        );

        $result = $this->service->register($record);
        $user = $result['user'];
        $this->assertDatabaseHas('test_users', ['email' => self::TEST_EMAIL]);

        // 2. Login
        $token = $this->service->login(self::TEST_EMAIL, self::TEST_PASSWORD);
        $this->assertNotNull($token);

        // 3. Send email verification
        $result = $this->service->sendEmailVerificationOtp($user);
        $this->assertTrue($result);

        // 4. Verify email
        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->first();

        $this->assertNotNull($otp);
        $verified = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);
        $this->assertTrue($verified);

        $freshUser = $user->refresh();
        // 5. Check email verified
        $this->assertTrue($this->service->isEmailVerified($freshUser));

        // 6. Send password reset
        $resetResult = $this->service->sendPasswordResetOtp(self::TEST_EMAIL);
        $this->assertTrue($resetResult);

        // 7. Reset password
        $resetOtp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->latest('id')->first();

        $this->assertNotNull($resetOtp);
        $resetSuccess = $this->service->resetPassword(self::TEST_EMAIL, $resetOtp->code, 'NewPassword123!');
        $this->assertTrue($resetSuccess);

        // 8. Login with new password
        $newToken = $this->service->login(self::TEST_EMAIL, 'NewPassword123!');
        $this->assertNotNull($newToken);

        // 9. Logout
        $plainToken = $newToken->plain_token ?? 'plain-token';
        $logoutResult = $this->service->logout($user, $plainToken);
        $this->assertTrue($logoutResult);
    }

    public function test_complete_email_update_flow(): void
    {
        // 1. Register + login
        $user = $this->createTestUser(['email_verified_at' => now()]);

        // 2. Send email update OTP
        $sent = $this->service->sendEmailUpdateOtp($user, self::TEST_NEW_EMAIL);
        $this->assertTrue($sent);

        // 3. Get OTP
        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($otp);

        // 4. Confirm update
        $updated = $this->service->updateEmail($user, self::TEST_NEW_EMAIL, $otp->code);
        $this->assertTrue($updated);

        // 5. Check email changed and verification reset
        $user->refresh();
        $this->assertEquals(self::TEST_NEW_EMAIL, $user->email);
        $this->assertNull($user->email_verified_at);

        // 6. Old email no longer exists
        $this->assertFalse($this->service->userExists(self::TEST_EMAIL));

        // 7. New email is registered
        $this->assertTrue($this->service->userExists(self::TEST_NEW_EMAIL));

        // 8. Re-verify the new email
        $verifySent = $this->service->sendEmailVerificationOtp($user);
        $this->assertTrue($verifySent);

        $verificationOtp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->where('purpose', 'like', '%email_verification%')
            ->latest('id')
            ->first();

        $this->assertNotNull($verificationOtp);

        $verified = $this->service->verifyEmail(self::TEST_NEW_EMAIL, $verificationOtp->code);
        $this->assertTrue($verified);

        $user->refresh();
        $this->assertTrue($this->service->isEmailVerified($user));
    }

    public function test_complete_two_factor_flow(): void
    {
        $user = $this->createTestUser(['email_verified_at' => now()]);

        // 1. Send 2FA OTP
        $sent = $this->service->sendTwoFactorOtp($user, self::TEST_TWO_FACTOR_PURPOSE);
        $this->assertTrue($sent);

        // 2. Get OTP
        $purpose = new PurposeVO(
            value: 'two_factor_'.self::TEST_TWO_FACTOR_PURPOSE,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otp = $otps->first();

        $this->assertNotNull($otp);

        // 3. Verify 2FA OTP
        $verified = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );
        $this->assertTrue($verified);

        // 4. Second attempt should fail (OTP is consumed)
        $second = $this->service->verifyTwoFactorOtp(
            $user,
            self::TEST_TWO_FACTOR_PURPOSE,
            $otp->code,
        );
        $this->assertFalse($second);
    }
}
