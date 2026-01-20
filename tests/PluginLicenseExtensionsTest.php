<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\PluginLicenseExtensions;
use PHPUnit\Framework\TestCase;

class PluginLicenseExtensionsTest extends TestCase
{
    public function testToPluginId(): void
    {
        $this->assertEquals('copilot', PluginLicenseExtensions::toPluginId('johannschopplich/kirby-copilot'));
        $this->assertEquals('content-translator', PluginLicenseExtensions::toPluginId('johannschopplich/kirby-content-translator'));
        $this->assertEquals('seo-audit', PluginLicenseExtensions::toPluginId('johannschopplich/kirby-seo-audit'));
    }

    public function testToApiPrefix(): void
    {
        $this->assertEquals('__copilot__', PluginLicenseExtensions::toApiPrefix('johannschopplich/kirby-copilot'));
        $this->assertEquals('__content-translator__', PluginLicenseExtensions::toApiPrefix('johannschopplich/kirby-content-translator'));
        $this->assertEquals('__seo-audit__', PluginLicenseExtensions::toApiPrefix('johannschopplich/kirby-seo-audit'));
    }

    public function testToPackageSlug(): void
    {
        $this->assertEquals('johannschopplich-kirby-copilot', PluginLicenseExtensions::toPackageSlug('johannschopplich/kirby-copilot'));
    }

    public function testFormatCompatibility(): void
    {
        // Single version
        $this->assertEquals('v1', PluginLicenseExtensions::formatCompatibility('^1'));

        // Two versions
        $this->assertEquals('v1 & v2', PluginLicenseExtensions::formatCompatibility('^1 || ^2'));

        // Three or more versions
        $this->assertEquals('v1, v2 & v3', PluginLicenseExtensions::formatCompatibility('^1 || ^2 || ^3'));
    }
}
