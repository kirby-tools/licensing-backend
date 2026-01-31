<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\LicenseUtils;
use JohannSchopplich\Licensing\PluginLicenseExtensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginLicenseExtensions::class)]
#[Group('deprecated')]
final class PluginLicenseExtensionsTest extends TestCase
{
    #[Test]
    public function to_plugin_id_delegates_to_license_utils(): void
    {
        $this->assertEquals(
            LicenseUtils::toPluginId('johannschopplich/kirby-copilot'),
            PluginLicenseExtensions::toPluginId('johannschopplich/kirby-copilot')
        );
    }

    #[Test]
    public function to_api_prefix_delegates_to_license_utils(): void
    {
        $this->assertEquals(
            LicenseUtils::toApiPrefix('johannschopplich/kirby-copilot'),
            PluginLicenseExtensions::toApiPrefix('johannschopplich/kirby-copilot')
        );
    }

    #[Test]
    public function to_package_slug_delegates_to_license_utils(): void
    {
        $this->assertEquals(
            LicenseUtils::toPackageSlug('johannschopplich/kirby-copilot'),
            PluginLicenseExtensions::toPackageSlug('johannschopplich/kirby-copilot')
        );
    }

    #[Test]
    public function format_compatibility_delegates_to_license_utils(): void
    {
        $this->assertEquals(
            LicenseUtils::formatCompatibility('^1 || ^2'),
            PluginLicenseExtensions::formatCompatibility('^1 || ^2')
        );
    }

    #[Test]
    public function translations_delegates_to_license_panel(): void
    {
        $this->assertEquals(
            LicensePanel::translations(),
            PluginLicenseExtensions::translations()
        );
    }

    #[Test]
    public function activation_error_keys_constant_exists(): void
    {
        $this->assertEquals(
            LicensePanel::ACTIVATION_ERROR_KEYS,
            PluginLicenseExtensions::ACTIVATION_ERROR_KEYS
        );
    }
}
