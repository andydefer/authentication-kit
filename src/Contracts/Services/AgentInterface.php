<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts\Services;

use AndyDefer\AuthenticationKit\Records\AgentPropertiesRecord;

/**
 * Interface for user agent detection service.
 *
 * Provides methods to detect browser, platform, device type,
 * and other information from the user agent string.
 */
interface AgentInterface
{
    /**
     * Get the browser name.
     *
     * @return string The browser name (e.g., 'Chrome', 'Firefox', 'Safari')
     */
    public function browser(): string;

    /**
     * Get the platform/OS name.
     *
     * @return string The platform name (e.g., 'macOS', 'Windows', 'Linux', 'iOS', 'Android')
     */
    public function platform(): string;

    /**
     * Get the device type.
     *
     * @return string The device type (e.g., 'Desktop', 'Mobile', 'Tablet', 'Robot')
     */
    public function deviceType(): string;

    /**
     * Check if the device is a mobile device.
     *
     * @return bool True if the device is mobile, false otherwise
     */
    public function isMobile(): bool;

    /**
     * Check if the user agent is a robot/crawler.
     *
     * @return bool True if the user agent is a robot, false otherwise
     */
    public function isRobot(): bool;

    /**
     * Check if the device is a desktop.
     *
     * @return bool True if the device is desktop, false otherwise
     */
    public function isDesktop(): bool;

    /**
     * Check if the device is a tablet.
     *
     * @return bool True if the device is tablet, false otherwise
     */
    public function isTablet(): bool;

    /**
     * Get the full version of the browser.
     *
     * @return string The browser version
     */
    public function version(): string;

    /**
     * Get the operating system version.
     *
     * @return string The OS version
     */
    public function platformVersion(): string;

    /**
     * Get the user agent string.
     *
     * @return string The user agent string
     */
    public function getUserAgent(): string;

    /**
     * Set the user agent string to analyze.
     *
     * @param  string  $userAgent  The user agent string
     * @return self The instance for method chaining
     */
    public function setUserAgent(string $userAgent): self;

    /**
     * Get all detected properties as a Record.
     *
     * @return AgentPropertiesRecord Record containing all detected properties
     */
    public function getProperties(): AgentPropertiesRecord;
}
