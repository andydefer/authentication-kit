<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Repositories;

use AndyDefer\AuthenticationKit\Enums\EventType;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Records\LogDataRecord;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

/**
 * Repository for authentication event logging.
 *
 * Handles logging of registration and login events with contextual
 * information about the request (IP, user agent, device, etc.).
 */
final class LogRepository implements LogRepositoryInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Agent $agent,
        private readonly Request $request,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function logRegistrationSuccess(
        int $authId,
        string $modelClass,
        bool $withToken,
    ): void {
        $payload = $this->buildBasePayload()
            ->merge([
                'event' => EventType::USER_REGISTRATION_SUCCESS->value,
                'auth_id' => $authId,
                'model_type' => $modelClass,
                'with_token' => $withToken,
            ]);

        $this->logger->info(new LogDataRecord(
            type: 'auth',
            payload: $payload
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function logRegistrationFailure(
        string $modelClass,
        string $error,
        string $errorClass,
    ): void {
        $payload = $this->buildBasePayload()
            ->merge([
                'event' => EventType::USER_REGISTRATION_FAILED->value,
                'model_type' => $modelClass,
                'error' => $error,
                'error_class' => $errorClass,
            ]);

        $this->logger->info(new LogDataRecord(
            type: 'auth',
            payload: $payload
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function logLoginSuccess(
        int $authId,
        string $modelClass,
        string $email,
    ): void {
        $payload = $this->buildBasePayload()
            ->merge([
                'event' => EventType::USER_LOGIN_SUCCESS->value,
                'auth_id' => $authId,
                'model_type' => $modelClass,
                'email' => $email,
            ]);

        $this->logger->info(new LogDataRecord(
            type: 'auth',
            payload: $payload
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function logLoginFailure(
        string $modelClass,
        string $email,
        string $error,
        string $errorClass,
    ): void {
        $payload = $this->buildBasePayload()
            ->merge([
                'event' => EventType::USER_LOGIN_FAILED->value,
                'model_type' => $modelClass,
                'email' => $email,
                'error' => $error,
                'error_class' => $errorClass,
            ]);

        $this->logger->info(new LogDataRecord(
            type: 'auth',
            payload: $payload
        ));
    }

    /**
     * Build the base payload with request context information.
     *
     * @return StrictDataObject The base payload with request context
     */
    private function buildBasePayload(): StrictDataObject
    {
        return new StrictDataObject([
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'platform' => $this->agent->platform(),
            'browser' => $this->agent->browser(),
            'device_type' => $this->agent->deviceType(),
            'is_mobile' => $this->agent->isMobile(),
            'is_robot' => $this->agent->isRobot(),
        ]);
    }
}
