<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Licenses::class)]
final class LicensesTest extends LicenseTestCase
{
    private App $app;
    private HttpClientInterface $mockHttpClient;

    protected function setUp(): void
    {
        $this->app = $this->appWithLicenseRoots();

        $mockPlugin = $this->createMock(\Kirby\Plugin\Plugin::class);
        $mockPlugin->method('version')->willReturn('1.0.0');

        $this->app->extend([
            'plugins' => [
                'test/package' => $mockPlugin
            ]
        ]);

        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
    }

    #[Test]
    public function reports_inactive_when_no_license_file_exists(): void
    {
        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);

        $this->assertInstanceOf(Licenses::class, $licenses);
        $this->assertEquals('inactive', $licenses->getStatus());
        $this->assertNull($licenses->getLicense());
    }

    #[Test]
    public function reports_active_for_a_valid_compatible_license(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.0.0'], version: '1.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '1.0.0',
            ]
        ]));

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->mockHttpClient]);
        $this->assertSame('active', $licenses->getStatus());
    }

    #[Test]
    public function reports_inactive_for_a_package_missing_from_the_license_file(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'other/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $this->assertEquals('inactive', $licenses->getStatus());
    }

    #[Test]
    public function reports_invalid_for_a_malformed_key(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $this->assertEquals('invalid', $licenses->getStatus());
    }

    #[Test]
    public function reports_incompatible_when_the_license_does_not_cover_the_plugin_version(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.0.0'], version: '1.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^2.0.0',
                'pluginVersion' => '1.0.0',
            ]
        ]));

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->mockHttpClient]);
        $this->assertSame('incompatible', $licenses->getStatus());
    }

    #[Test]
    public function reports_upgradeable_when_the_plugin_outgrew_the_license(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '2.0.0'], version: '2.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '2.0.0',
            ]
        ]));

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->mockHttpClient]);
        $this->assertSame('upgradeable', $licenses->getStatus());
    }

    #[Test]
    public function exposes_key_generation_and_compatibility_for_a_valid_license(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => null, // Use null to prevent refresh.
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $license = $licenses->getLicense();

        $this->assertIsArray($license);
        $this->assertArrayHasKey('key', $license);
        $this->assertArrayHasKey('generation', $license);
        $this->assertArrayHasKey('compatibility', $license);
    }

    #[Test]
    public function returns_no_license_data_for_a_malformed_key(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $this->assertNull($licenses->getLicense());
    }

    #[Test]
    public function reports_no_read_error_when_the_license_file_does_not_exist(): void
    {
        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);

        $this->assertNull($licenses->getReadError());
    }

    #[Test]
    public function reports_a_read_error_when_the_license_file_cannot_be_parsed(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, '{ this is not json');

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);

        $this->assertNotNull($licenses->getReadError());
        $this->assertSame('inactive', $licenses->getStatus());
    }
}
