<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Services;

use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Records\AgentPropertiesRecord;
use Jenssegers\Agent\Agent as JenssegersAgent;

/**
 * Service for user agent detection.
 *
 * Wraps the Jenssegers\Agent package to provide a clean interface
 * for detecting browser, platform, device type, and other information
 * from the user agent string.
 */
final class Agent implements AgentInterface
{
    /**
     * Create a new Agent instance.
     *
     * @param  JenssegersAgent  $agent  The underlying agent instance
     */
    public function __construct(
        private readonly JenssegersAgent $agent,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function browser(): string
    {
        $browser = $this->agent->browser();

        return $browser !== false ? $browser : 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function platform(): string
    {
        $platform = $this->agent->platform();

        return $platform !== false ? $platform : 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function deviceType(): string
    {
        return $this->agent->deviceType();
    }

    /**
     * {@inheritDoc}
     */
    public function isMobile(): bool
    {
        return $this->agent->isMobile();
    }

    /**
     * {@inheritDoc}
     */
    public function isRobot(): bool
    {
        return $this->agent->isRobot();
    }

    /**
     * {@inheritDoc}
     */
    public function isDesktop(): bool
    {
        return $this->agent->isDesktop();
    }

    /**
     * {@inheritDoc}
     */
    public function isTablet(): bool
    {
        return $this->agent->isTablet();
    }

    /**
     * {@inheritDoc}
     */
    public function version(): string
    {
        $version = $this->agent->version('browser');

        return $version !== false ? (string) $version : 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function platformVersion(): string
    {
        $version = $this->agent->version('platform');

        return $version !== false ? (string) $version : 'unknown';
    }

    /**
     * {@inheritDoc}
     */
    public function getUserAgent(): string
    {
        return $this->agent->getUserAgent();
    }

    /**
     * {@inheritDoc}
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->agent->setUserAgent($userAgent);

        return $this;
    }

    /**
     * {@inheritDoc}
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
