<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Services;

use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Records\AgentPropertiesRecord;
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;
use AndyDefer\Nemesis\Services\AgentService;
use Jenssegers\Agent\Agent as JenssegersAgent;

/**
 * Service for user agent detection.
 *
 * @deprecated 1.0.0 Use {@see AgentService} instead.
 *                   The agent detection logic has been moved to the Nemesis package.
 *                   Resolve {@see AgentServiceInterface}
 *                   from the container. This class is kept for backward compatibility
 *                   and will be removed in a future major release.
 */
final class Agent implements AgentInterface
{
    /**
     * Create a new Agent instance.
     *
     * @deprecated 1.0.0 Use {@see AgentService} instead.
     *
     * @param  JenssegersAgent  $agent  The underlying agent instance
     */
    public function __construct(
        private readonly JenssegersAgent $agent,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::browser()} instead.
     */
    public function browser(): string
    {
        $browser = $this->agent->browser();

        return $browser !== false ? $browser : 'unknown';
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::platform()} instead.
     */
    public function platform(): string
    {
        $platform = $this->agent->platform();

        return $platform !== false ? $platform : 'unknown';
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::deviceType()} instead.
     */
    public function deviceType(): string
    {
        return $this->agent->deviceType();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::isMobile()} instead.
     */
    public function isMobile(): bool
    {
        return $this->agent->isMobile();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::isRobot()} instead.
     */
    public function isRobot(): bool
    {
        return $this->agent->isRobot();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::isDesktop()} instead.
     */
    public function isDesktop(): bool
    {
        return $this->agent->isDesktop();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::isTablet()} instead.
     */
    public function isTablet(): bool
    {
        return $this->agent->isTablet();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::version()} instead.
     */
    public function version(): string
    {
        $version = $this->agent->version('browser');

        return $version !== false ? (string) $version : 'unknown';
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::platformVersion()} instead.
     */
    public function platformVersion(): string
    {
        $version = $this->agent->version('platform');

        return $version !== false ? (string) $version : 'unknown';
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::getUserAgent()} instead.
     */
    public function getUserAgent(): string
    {
        return $this->agent->getUserAgent();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::setUserAgent()} instead.
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->agent->setUserAgent($userAgent);

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 1.0.0 Use {@see AgentService::getProperties()} instead.
     */
    public function getProperties(): AgentPropertiesRecord
    {
        return new AgentPropertiesRecord(
            browser: $this->browser(),
            browser_version: $this->version(),
            platform: $this->platform(),
            platform_version: $this->platformVersion(),
            device_type: $this->deviceType(),
            is_mobile: $this->isMobile(),
            is_desktop: $this->isDesktop(),
            is_tablet: $this->isTablet(),
            is_robot: $this->isRobot(),
            user_agent: $this->getUserAgent(),
        );
    }
}
