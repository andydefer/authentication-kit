<?php

// tests/Integration/Mail/Utils/AuthenticationResolverTest.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Mail\Utils;

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class AuthenticationResolverTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private const TEST_EMAIL = 'john@example.com';

    private const TEST_NAME = 'John Doe';

    private function createTestUser(array $attributes = []): TestUserMail
    {
        return TestUserMail::create(array_merge([
            'name' => self::TEST_NAME,
            'email' => self::TEST_EMAIL,
            'password' => bcrypt('Password123!'),
            'email_verified_at' => null,
        ], $attributes));
    }

    // ============================================================================
    // Tests - resolveService()
    // ============================================================================

    public function test_resolve_service_returns_service_for_valid_model(): void
    {
        $service = AuthenticationResolver::resolveService(TestUserMail::class);

        $this->assertNotNull($service);
        $this->assertInstanceOf(MailAuthenticationService::class, $service);
    }

    public function test_resolve_service_returns_null_for_invalid_model(): void
    {
        $service = AuthenticationResolver::resolveService('Invalid\\Model\\Class');

        $this->assertNull($service);
    }

    public function test_resolve_service_returns_null_for_model_not_implementing_mail_authenticatable(): void
    {
        $service = AuthenticationResolver::resolveService(\stdClass::class);

        $this->assertNull($service);
    }

    // ============================================================================
    // Tests - resolveAuthenticatable()
    // ============================================================================

    public function test_resolve_authenticatable_returns_model_for_valid_id(): void
    {
        $user = $this->createTestUser();

        $authenticatable = AuthenticationResolver::resolveAuthenticatable(
            TestUserMail::class,
            $user->id
        );

        $this->assertNotNull($authenticatable);
        $this->assertInstanceOf(TestUserMail::class, $authenticatable);
        $this->assertEquals($user->id, $authenticatable->id);
        $this->assertEquals(self::TEST_EMAIL, $authenticatable->email);
    }

    public function test_resolve_authenticatable_returns_null_for_invalid_id(): void
    {
        $authenticatable = AuthenticationResolver::resolveAuthenticatable(
            TestUserMail::class,
            99999
        );

        $this->assertNull($authenticatable);
    }

    public function test_resolve_authenticatable_returns_null_for_invalid_model_type(): void
    {
        $authenticatable = AuthenticationResolver::resolveAuthenticatable(
            'Invalid\\Model\\Class',
            1
        );

        $this->assertNull($authenticatable);
    }

    public function test_resolve_authenticatable_returns_model_with_trashed_when_include_trashed_true(): void
    {
        $user = $this->createTestUser();
        $user->delete();

        $authenticatable = AuthenticationResolver::resolveAuthenticatable(
            TestUserMail::class,
            $user->id,
            true // includeTrashed
        );

        $this->assertNull($authenticatable);
    }

    public function test_resolve_authenticatable_returns_null_for_soft_deleted_user(): void
    {
        $user = $this->createTestUser();
        $user->delete();

        $authenticatable = AuthenticationResolver::resolveAuthenticatable(
            TestUserMail::class,
            $user->id
        );

        $this->assertNull($authenticatable);
    }

    // ============================================================================
    // Tests - resolveAuthenticatableByEmail()
    // ============================================================================

    public function test_resolve_authenticatable_by_email_returns_model_for_valid_email(): void
    {
        $user = $this->createTestUser();

        $authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
            TestUserMail::class,
            self::TEST_EMAIL
        );

        $this->assertNotNull($authenticatable);
        $this->assertInstanceOf(TestUserMail::class, $authenticatable);
        $this->assertEquals($user->id, $authenticatable->id);
        $this->assertEquals(self::TEST_EMAIL, $authenticatable->email);
    }

    public function test_resolve_authenticatable_by_email_returns_model_with_case_insensitive_email(): void
    {
        $user = $this->createTestUser();

        $authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
            TestUserMail::class,
            strtoupper(self::TEST_EMAIL)
        );

        $this->assertNotNull($authenticatable);
        $this->assertEquals($user->id, $authenticatable->id);
    }

    public function test_resolve_authenticatable_by_email_returns_model_with_trimmed_email(): void
    {
        $user = $this->createTestUser();

        $authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
            TestUserMail::class,
            '  '.self::TEST_EMAIL.'  '
        );

        $this->assertNotNull($authenticatable);
        $this->assertEquals($user->id, $authenticatable->id);
    }

    public function test_resolve_authenticatable_by_email_returns_null_for_invalid_email(): void
    {
        $authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
            TestUserMail::class,
            'nonexistent@example.com'
        );

        $this->assertNull($authenticatable);
    }

    public function test_resolve_authenticatable_by_email_returns_null_for_soft_deleted_user(): void
    {
        $user = $this->createTestUser();
        $user->delete();

        $authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
            TestUserMail::class,
            self::TEST_EMAIL
        );

        $this->assertNull($authenticatable);
    }

    public function test_resolve_authenticatable_by_email_returns_model_with_trashed_when_include_trashed_true(): void
    {
        $user = $this->createTestUser();
        $user->delete();

        $authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
            TestUserMail::class,
            self::TEST_EMAIL,
            true // includeTrashed
        );

        $this->assertNull($authenticatable);
    }

    // ============================================================================
    // Tests - isValidAuthenticatable()
    // ============================================================================

    public function test_is_valid_authenticatable_returns_true_for_valid_model(): void
    {
        $result = AuthenticationResolver::isValidAuthenticatable(TestUserMail::class);

        $this->assertTrue($result);
    }

    public function test_is_valid_authenticatable_returns_false_for_invalid_model(): void
    {
        $result = AuthenticationResolver::isValidAuthenticatable('Invalid\\Model\\Class');

        $this->assertFalse($result);
    }

    public function test_is_valid_authenticatable_returns_false_for_model_not_implementing_interface(): void
    {
        $result = AuthenticationResolver::isValidAuthenticatable(\stdClass::class);

        $this->assertFalse($result);
    }

    // ============================================================================
    // Tests - usesSoftDeletes()
    // ============================================================================

    public function test_uses_soft_deletes_returns_true_for_model_with_soft_deletes(): void
    {
        $result = AuthenticationResolver::usesSoftDeletes(TestUserMail::class);

        $this->assertTrue($result);
    }

    public function test_uses_soft_deletes_returns_false_for_model_without_soft_deletes(): void
    {
        $result = AuthenticationResolver::usesSoftDeletes(\stdClass::class);

        $this->assertFalse($result);
    }

    public function test_uses_soft_deletes_returns_false_for_invalid_model(): void
    {
        $result = AuthenticationResolver::usesSoftDeletes('Invalid\\Model\\Class');

        $this->assertFalse($result);
    }

    // ============================================================================
    // Tests - resolve()
    // ============================================================================

    public function test_resolve_returns_both_service_and_authenticatable(): void
    {
        $user = $this->createTestUser();

        $result = AuthenticationResolver::resolve(TestUserMail::class, $user->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('authenticatable', $result);
        $this->assertNotNull($result['service']);
        $this->assertNotNull($result['authenticatable']);
        $this->assertInstanceOf(MailAuthenticationService::class, $result['service']);
        $this->assertInstanceOf(TestUserMail::class, $result['authenticatable']);
        $this->assertEquals($user->id, $result['authenticatable']->id);
    }

    public function test_resolve_returns_null_service_for_invalid_model(): void
    {
        $result = AuthenticationResolver::resolve('Invalid\\Model\\Class', 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('authenticatable', $result);
        $this->assertNull($result['service']);
        $this->assertNull($result['authenticatable']);
    }

    public function test_resolve_returns_null_authenticatable_for_invalid_id(): void
    {
        $result = AuthenticationResolver::resolve(TestUserMail::class, 99999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('authenticatable', $result);
        $this->assertNotNull($result['service']);
        $this->assertNull($result['authenticatable']);
    }

    // ============================================================================
    // Tests - resolveByEmail()
    // ============================================================================

    public function test_resolve_by_email_returns_both_service_and_authenticatable(): void
    {
        $user = $this->createTestUser();

        $result = AuthenticationResolver::resolveByEmail(TestUserMail::class, self::TEST_EMAIL);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('authenticatable', $result);
        $this->assertNotNull($result['service']);
        $this->assertNotNull($result['authenticatable']);
        $this->assertInstanceOf(MailAuthenticationService::class, $result['service']);
        $this->assertInstanceOf(TestUserMail::class, $result['authenticatable']);
        $this->assertEquals($user->id, $result['authenticatable']->id);
    }

    public function test_resolve_by_email_returns_null_authenticatable_for_invalid_email(): void
    {
        $result = AuthenticationResolver::resolveByEmail(
            TestUserMail::class,
            'nonexistent@example.com'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('authenticatable', $result);
        $this->assertNotNull($result['service']);
        $this->assertNull($result['authenticatable']);
    }

    public function test_resolve_by_email_returns_null_service_for_invalid_model(): void
    {
        $result = AuthenticationResolver::resolveByEmail(
            'Invalid\\Model\\Class',
            self::TEST_EMAIL
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('authenticatable', $result);
        $this->assertNull($result['service']);
        $this->assertNull($result['authenticatable']);
    }

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_resolve_with_string_id(): void
    {
        // Certains modèles utilisent des UUIDs
        $user = $this->createTestUser();

        $result = AuthenticationResolver::resolve(
            TestUserMail::class,
            (string) $user->id
        );

        $this->assertIsArray($result);
        $this->assertNotNull($result['authenticatable']);
        $this->assertEquals($user->id, $result['authenticatable']->id);
    }

    public function test_resolve_with_empty_model_type(): void
    {
        $result = AuthenticationResolver::resolve('', 1);

        $this->assertIsArray($result);
        $this->assertNull($result['service']);
        $this->assertNull($result['authenticatable']);
    }

    public function test_resolve_by_email_with_empty_email(): void
    {
        $result = AuthenticationResolver::resolveByEmail(TestUserMail::class, '');

        $this->assertIsArray($result);
        $this->assertNotNull($result['service']);
        $this->assertNull($result['authenticatable']);
    }

    public function test_resolve_service_with_model_using_soft_deletes_but_not_deleted(): void
    {
        $user = $this->createTestUser();

        $authenticatable = AuthenticationResolver::resolveAuthenticatable(
            TestUserMail::class,
            $user->id,
            false
        );

        $this->assertNotNull($authenticatable);
        $this->assertEquals($user->id, $authenticatable->id);
    }
}
