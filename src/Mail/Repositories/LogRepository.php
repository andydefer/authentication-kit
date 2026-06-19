<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Repositories;

use AndyDefer\AuthenticationKit\Enums\EventType;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Records\LogDataRecord;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

final class LogRepository
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Agent $agent,
        private readonly Request $request,
    ) {}

    public function logRegistrationSuccess(
        int $userId,
        string $modelClass,
        bool $withToken,
    ): void {
        $payload = $this->buildBasePayload()
            ->merge([
                'event' => EventType::USER_REGISTRATION_SUCCESS->value,
                'user_id' => $userId,
                'model_type' => $modelClass,
                'with_token' => $withToken,
            ]);

        $this->logger->info(new LogDataRecord(
            type: 'auth',
            payload: $payload
        ));
    }

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
