<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\LicenseUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseUtils::class)]
final class LicenseUtilsTest extends TestCase
{
    #[Test]
    public function to_plugin_id(): void
    {
        $this->assertEquals('copilot', LicenseUtils::toPluginId('johannschopplich/kirby-copilot'));
        $this->assertEquals('content-translator', LicenseUtils::toPluginId('johannschopplich/kirby-content-translator'));
        $this->assertEquals('seo-audit', LicenseUtils::toPluginId('johannschopplich/kirby-seo-audit'));
    }

    #[Test]
    public function to_api_prefix(): void
    {
        $this->assertEquals('__copilot__', LicenseUtils::toApiPrefix('johannschopplich/kirby-copilot'));
        $this->assertEquals('__content-translator__', LicenseUtils::toApiPrefix('johannschopplich/kirby-content-translator'));
        $this->assertEquals('__seo-audit__', LicenseUtils::toApiPrefix('johannschopplich/kirby-seo-audit'));
    }

    #[Test]
    public function to_package_slug(): void
    {
        $this->assertEquals('johannschopplich-kirby-copilot', LicenseUtils::toPackageSlug('johannschopplich/kirby-copilot'));
    }

    #[Test]
    public function format_compatibility(): void
    {
        // Single version
        $this->assertEquals('v1', LicenseUtils::formatCompatibility('^1'));

        // Two versions
        $this->assertEquals('v1, v2', LicenseUtils::formatCompatibility('^1 || ^2'));

        // Three or more versions
        $this->assertEquals('v1, v2, v3', LicenseUtils::formatCompatibility('^1 || ^2 || ^3'));
    }
}
