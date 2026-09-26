<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts\Services;

use AndyDefer\AuthenticationKit\Records\AgentPropertiesRecord;
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;

/**
 * Interface for user agent detection service.
 *
 * @deprecated 1.0.0 Use {@see AgentServiceInterface} instead.
 *                   Resolve the interface from the container. This interface is kept
 *                   for backward compatibility and will be removed in a future major release.
 */
interface AgentInterface
{
    /**
     * Get the browser name.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::browser()} instead.
     *
     * @return string The browser name (e.g., 'Chrome', 'Firefox', 'Safari')
     */
    public function browser(): string;

    /**
     * Get the platform/OS name.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::platform()} instead.
     *
     * @return string The platform name (e.g., 'macOS', 'Windows', 'Linux', 'iOS', 'Android')
     */
    public function platform(): string;

    /**
     * Get the device type.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::deviceType()} instead.
     *
     * @return string The device type (e.g., 'Desktop', 'Mobile', 'Tablet', 'Robot')
     */
    public function deviceType(): string;

    /**
     * Check if the device is a mobile device.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::isMobile()} instead.
     *
     * @return bool True if the device is mobile, false otherwise
     */
    public function isMobile(): bool;

    /**
     * Check if the user agent is a robot/crawler.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::isRobot()} instead.
     *
     * @return bool True if the user agent is a robot, false otherwise
     */
    public function isRobot(): bool;

    /**
     * Check if the device is a desktop.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::isDesktop()} instead.
     *
     * @return bool True if the device is desktop, false otherwise
     */
    public function isDesktop(): bool;

    /**
     * Check if the device is a tablet.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::isTablet()} instead.
     *
     * @return bool True if the device is tablet, false otherwise
     */
    public function isTablet(): bool;

    /**
     * Get the full version of the browser.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::version()} instead.
     *
     * @return string The browser version
     */
    public function version(): string;

    /**
     * Get the operating system version.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::platformVersion()} instead.
     *
     * @return string The OS version
     */
    public function platformVersion(): string;

    /**
     * Get the user agent string.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::getUserAgent()} instead.
     *
     * @return string The user agent string
     */
    public function getUserAgent(): string;

    /**
     * Set the user agent string to analyze.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::setUserAgent()} instead.
     *
     * @param  string  $userAgent  The user agent string
     * @return self The instance for method chaining
     */
    public function setUserAgent(string $userAgent): self;

    /**
     * Get all detected properties as a Record.
     *
     * @deprecated 1.0.0 Use {@see AgentServiceInterface::getProperties()} instead.
     *
     * @return AgentPropertiesRecord Record containing all detected properties
     */
    public function getProperties(): AgentPropertiesRecord;
}
