<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicensePanel;
use JohannSchopplich\Licensing\LicenseUtils;
use JohannSchopplich\Licensing\PluginLicenseExtensions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('deprecated')]
class PluginLicenseExtensionsTest extends TestCase
{
    public function testToPluginIdDelegatesToLicenseUtils(): void
    {
        $this->assertEquals(
            LicenseUtils::toPluginId('johannschopplich/kirby-copilot'),
            PluginLicenseExtensions::toPluginId('johannschopplich/kirby-copilot')
        );
    }

    public function testToApiPrefixDelegatesToLicenseUtils(): void
    {
        $this->assertEquals(
            LicenseUtils::toApiPrefix('johannschopplich/kirby-copilot'),
            PluginLicenseExtensions::toApiPrefix('johannschopplich/kirby-copilot')
        );
    }

    public function testToPackageSlugDelegatesToLicenseUtils(): void
    {
        $this->assertEquals(
            LicenseUtils::toPackageSlug('johannschopplich/kirby-copilot'),
            PluginLicenseExtensions::toPackageSlug('johannschopplich/kirby-copilot')
        );
    }

    public function testFormatCompatibilityDelegatesToLicenseUtils(): void
    {
        $this->assertEquals(
            LicenseUtils::formatCompatibility('^1 || ^2'),
            PluginLicenseExtensions::formatCompatibility('^1 || ^2')
        );
    }

    public function testTranslationsDelegatesToLicensePanel(): void
    {
        $this->assertEquals(
            LicensePanel::translations(),
            PluginLicenseExtensions::translations()
        );
    }

    public function testActivationErrorKeysConstantExists(): void
    {
        $this->assertEquals(
            LicensePanel::ACTIVATION_ERROR_KEYS,
            PluginLicenseExtensions::ACTIVATION_ERROR_KEYS
        );
    }
}
