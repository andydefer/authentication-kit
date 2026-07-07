<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record representing agent properties detected from user agent.
 *
 * Contains browser, platform, device type, and other information
 * extracted from the user agent string.
 */
final class AgentPropertiesRecord extends AbstractRecord
{
    /**
     * Create a new AgentPropertiesRecord instance.
     *
     * @param  string  $browser  The browser name (e.g., 'Chrome', 'Firefox')
     * @param  string  $browser_version  The browser version
     * @param  string  $platform  The platform/OS name (e.g., 'macOS', 'Windows')
     * @param  string  $platform_version  The platform version
     * @param  string  $device_type  The device type (e.g., 'desktop', 'mobile', 'tablet', 'robot')
     * @param  bool  $is_mobile  Whether the device is mobile
     * @param  bool  $is_desktop  Whether the device is desktop
     * @param  bool  $is_tablet  Whether the device is tablet
     * @param  bool  $is_robot  Whether the user agent is a robot
     * @param  string  $user_agent  The raw user agent string
     */
    public function __construct(
        public readonly string $browser,
        public readonly string $browser_version,
        public readonly string $platform,
        public readonly string $platform_version,
        public readonly string $device_type,
        public readonly bool $is_mobile,
        public readonly bool $is_desktop,
        public readonly bool $is_tablet,
        public readonly bool $is_robot,
        public readonly string $user_agent,
    ) {}
}
