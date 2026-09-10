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
    public function to_plugin_id_strips_the_vendor_and_the_kirby_prefix(): void
    {
        $this->assertEquals('copilot', LicenseUtils::toPluginId('johannschopplich/kirby-copilot'));
        $this->assertEquals('content-translator', LicenseUtils::toPluginId('johannschopplich/kirby-content-translator'));
        $this->assertEquals('seo-audit', LicenseUtils::toPluginId('johannschopplich/kirby-seo-audit'));
    }

    #[Test]
    public function to_api_prefix_wraps_the_plugin_id_in_double_underscores(): void
    {
        $this->assertEquals('__copilot__', LicenseUtils::toApiPrefix('johannschopplich/kirby-copilot'));
        $this->assertEquals('__content-translator__', LicenseUtils::toApiPrefix('johannschopplich/kirby-content-translator'));
        $this->assertEquals('__seo-audit__', LicenseUtils::toApiPrefix('johannschopplich/kirby-seo-audit'));
    }

    #[Test]
    public function to_package_slug_replaces_the_slash_with_a_hyphen(): void
    {
        $this->assertEquals('johannschopplich-kirby-copilot', LicenseUtils::toPackageSlug('johannschopplich/kirby-copilot'));
    }

    #[Test]
    public function to_compatible_versions_spans_the_lowest_to_the_highest_major(): void
    {
        $this->assertEquals('v1', LicenseUtils::toCompatibleVersions('^1'));

        $this->assertEquals("v1\u{2013}v2", LicenseUtils::toCompatibleVersions('^1 || ^2'));

        $this->assertEquals("v1\u{2013}v3", LicenseUtils::toCompatibleVersions('^1 || ^2 || ^3'));
    }
}
