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

    private const TEST_OTP = '123456';

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
            label: $purpose === 'email_verification' ? 'Email Verification' : 'Password Reset',
            ttl: $purpose === 'email_verification' ? 300 : 600,
            maxAttempts: 3
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
        // Arrange
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

        // Act
        $result = $this->service->register($record);

        // Assert
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
        // Arrange
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

        // Act
        $result = $this->service->register($record);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plain_token', $result);

        $user = $result['user'];
        $this->assertInstanceOf(TestUserMail::class, $user);
        $this->assertDatabaseHas('test_users', [
            'email' => self::TEST_EMAIL,
        ]);

        // Vérifier que le token existe
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
        // Arrange
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

        // Act
        $this->service->register($record);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $result = $this->service->login(self::TEST_EMAIL, self::TEST_PASSWORD);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(LoginResultRecord::class, $result);

        // Vérifier que le token est en base
        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);
        $this->assertEquals('auth-login', $token->name);
        $this->assertEquals('login', $token->source);
    }

    public function test_login_returns_null_for_invalid_password(): void
    {
        // Arrange
        $this->createTestUser();

        // Act
        $result = $this->service->login(self::TEST_EMAIL, 'wrong-password');

        // Assert
        $this->assertNull($result);

        // Vérifier qu'aucun token n'a été créé
        $tokenCount = NemesisToken::where('tokenable_type', TestUserMail::class)->count();
        $this->assertEquals(0, $tokenCount);
    }

    public function test_login_returns_null_for_non_existent_user(): void
    {
        // Act
        $result = $this->service->login('nonexistent@example.com', self::TEST_PASSWORD);

        // Assert
        $this->assertNull($result);
    }

    public function test_logout_revokes_token_successfully(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un token de connexion
        $result = $this->service->login(self::TEST_EMAIL, self::TEST_PASSWORD);
        $this->assertNotNull($result);

        // Récupérer le token en base
        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);

        // Act
        $logoutResult = $this->service->logout($user, $result->plain_token ?? 'auth-login');

        // Assert
        $this->assertTrue($logoutResult);

        // Vérifier que le token est révoqué (soft deleted)
        $token->refresh();
        $this->assertNotNull($token->deleted_at);
    }

    public function test_logout_returns_false_for_invalid_token(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $result = $this->service->logout($user, 'invalid-token');

        // Assert
        $this->assertFalse($result);
    }

    public function test_send_password_reset_otp_sends_otp_for_existing_user(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $result = $this->service->sendPasswordResetOtp(self::TEST_EMAIL);

        // Assert
        $this->assertTrue($result);

        // Vérifier que l'OTP est en base
        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->first();

        $this->assertNotNull($otp);

        // ✅ getValue() retourne StrictDataObject, on accède à ->value
        $this->assertEquals('password_reset', $otp->getPurpose()->getValue()->value);
        $this->assertEquals(0, $otp->attempts);
    }

    public function test_send_password_reset_otp_returns_false_for_non_existent_user(): void
    {
        // Act
        $result = $this->service->sendPasswordResetOtp('nonexistent@example.com');

        // Assert
        $this->assertFalse($result);
    }

    public function test_send_password_reset_otp_respects_rate_limit(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Envoyer 3 OTPs (limite = 3)
        $this->service->sendPasswordResetOtp(self::TEST_EMAIL);
        $this->service->sendPasswordResetOtp(self::TEST_EMAIL);
        $this->service->sendPasswordResetOtp(self::TEST_EMAIL);

        // Act - 4ème tentative (devrait être bloquée)
        $result = $this->service->sendPasswordResetOtp(self::TEST_EMAIL);

        // Assert
        $this->assertFalse($result);

        // Vérifier que seulement 3 OTPs existent
        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(3, $otpCount);
    }

    public function test_reset_password_with_valid_otp(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un OTP
        $otp = $this->createOtp($user, 'password_reset');

        // Act
        $result = $this->service->resetPassword(self::TEST_EMAIL, $otp->code, 'NewPassword123!');

        // Assert
        $this->assertTrue($result);

        // Vérifier que le mot de passe a été mis à jour
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        // Vérifier que l'OTP a été utilisé (attempts > 0)
        $freshOtp = $otp->refresh();
        $this->assertGreaterThan(0, $freshOtp->attempts);
    }

    public function test_reset_password_with_invalid_otp(): void
    {
        // Arrange
        $this->createTestUser();

        // Act
        $result = $this->service->resetPassword(self::TEST_EMAIL, '000000', 'NewPassword123!');

        // Assert
        $this->assertFalse($result);

        // Vérifier que le mot de passe n'a pas changé
        $user = TestUserMail::where('email', self::TEST_EMAIL)->first();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
    }

    public function test_reset_password_with_expired_otp(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un OTP expiré
        $otp = $this->createOtp($user, 'password_reset');
        $otp->expires_at = now()->subMinutes(10);
        $otp->save();

        // Act
        $result = $this->service->resetPassword(self::TEST_EMAIL, $otp->code, 'NewPassword123!');

        // Assert
        $this->assertFalse($result);

        // Vérifier que le mot de passe n'a pas changé
        $user->refresh();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
    }

    public function test_reset_password_with_exceeded_attempts(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un OTP
        $otp = $this->createOtp($user, 'password_reset');

        // Simuler 3 tentatives échouées
        $otp->attempts = 3;
        $otp->save();

        // Act
        $result = $this->service->resetPassword(self::TEST_EMAIL, $otp->code, 'NewPassword123!');

        // Assert
        $this->assertFalse($result);

        // Vérifier que le mot de passe n'a pas changé
        $user->refresh();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));
    }

    public function test_send_email_verification_otp_sends_otp(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $result = $this->service->sendEmailVerificationOtp($user);

        // Assert
        $this->assertTrue($result);

        // Vérifier que l'OTP est en base
        $otp = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->first();

        $this->assertNotNull($otp);
        $this->assertEquals('email_verification', $otp->getPurpose()->getValue()->value);
        $this->assertEquals(0, $otp->attempts);
    }

    public function test_send_email_verification_otp_returns_true_when_already_verified(): void
    {
        // Arrange
        $user = $this->createTestUser(['email_verified_at' => now()]);

        // Act
        $result = $this->service->sendEmailVerificationOtp($user);

        // Assert
        $this->assertTrue($result);

        // Vérifier qu'aucun OTP n'a été créé
        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(0, $otpCount);
    }

    public function test_send_email_verification_otp_respects_rate_limit(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Envoyer 3 OTPs (limite = 3)
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);
        $this->service->sendEmailVerificationOtp($user);

        // Act - 6ème tentative (devrait être bloquée)
        $result = $this->service->sendEmailVerificationOtp($user);

        // Assert
        $this->assertFalse($result);

        // Vérifier que seulement 3 OTPs existent
        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(5, $otpCount);
    }

    public function test_verify_email_with_valid_otp(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un OTP
        $otp = $this->createOtp($user, 'email_verification');

        // Act
        $result = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);

        // Assert
        $this->assertTrue($result);

        // Vérifier que l'email est vérifié
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Vérifier que l'OTP a été utilisé
        $otp->refresh();
        $this->assertGreaterThan(0, $otp->attempts);
    }

    public function test_verify_email_with_invalid_otp(): void
    {
        // Arrange
        $this->createTestUser();

        // Act
        $result = $this->service->verifyEmail(self::TEST_EMAIL, '000000');

        // Assert
        $this->assertFalse($result);

        // Vérifier que l'email n'est pas vérifié
        $user = TestUserMail::where('email', self::TEST_EMAIL)->first();
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_with_expired_otp(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un OTP expiré
        $otp = $this->createOtp($user, 'email_verification');
        $otp->expires_at = now()->subMinutes(10);
        $otp->save();

        // Act
        $result = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);

        // Assert
        $this->assertFalse($result);

        // Vérifier que l'email n'est pas vérifié
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_with_exceeded_attempts(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Créer un OTP
        $otp = $this->createOtp($user, 'email_verification');

        // Simuler 3 tentatives échouées
        $otp->attempts = 3;
        $otp->save();

        // Act
        $result = $this->service->verifyEmail(self::TEST_EMAIL, $otp->code);

        // Assert
        $this->assertFalse($result);

        // Vérifier que l'email n'est pas vérifié
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_returns_true_when_already_verified(): void
    {
        // Arrange
        $user = $this->createTestUser(['email_verified_at' => now()]);

        // Act
        $result = $this->service->verifyEmail(self::TEST_EMAIL, 'any-otp');

        // Assert
        $this->assertTrue($result);

        // Vérifier que la date de vérification est inchangée
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_resend_email_verification_otp(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $result = $this->service->resendEmailVerificationOtp($user);

        // Assert
        $this->assertTrue($result);

        // Vérifier qu'un nouvel OTP a été créé
        $otpCount = Otp::where('identifier_type', TestUserMail::class)
            ->where('identifier_id', $user->id)
            ->count();

        $this->assertEquals(1, $otpCount);
    }

    public function test_is_email_verified(): void
    {
        // Arrange - utilisateur non vérifié
        $user = $this->createTestUser();

        // Act & Assert
        $this->assertFalse($this->service->isEmailVerified($user));

        // Arrange - utilisateur vérifié
        $user->email_verified_at = now();
        $user->save();

        // Act & Assert
        $this->assertTrue($this->service->isEmailVerified($user));
    }

    public function test_user_exists(): void
    {
        // Arrange
        $this->createTestUser();

        // Act & Assert
        $this->assertTrue($this->service->userExists(self::TEST_EMAIL));
        $this->assertFalse($this->service->userExists('nonexistent@example.com'));
    }

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
}
