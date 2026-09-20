<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;

#[CoversClass(Licenses::class)]
final class LicensesTest extends LicenseTestCase
{
    private const LICENSE_STORED_FOR_PLUGIN_1_0_0 = [
        'licenseKey' => 'KT1-ABC123-DEF456',
        'licenseCompatibility' => '^1.0.0',
        'pluginVersion' => '1.0.0',
    ];

    private App $app;
    private HttpClientInterface&Stub $httpClient;

    protected function setUp(): void
    {
        $this->app = $this->appWithLicenseRoots();

        $plugin = $this->createStub(\Kirby\Plugin\Plugin::class);
        $plugin->method('version')->willReturn('1.0.0');

        $this->app->extend([
            'plugins' => [
                'test/package' => $plugin
            ]
        ]);

        $this->httpClient = $this->createStub(HttpClientInterface::class);
    }

    #[Test]
    public function read_drops_the_stored_license_on_a_401(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.1.0'], version: '1.1.0');

        $otherLicense = [
            'licenseKey' => 'KT1-GHI789-JKL012',
            'licenseCompatibility' => '^1.0.0',
            'pluginVersion' => '1.0.0',
        ];
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => self::LICENSE_STORED_FOR_PLUGIN_1_0_0,
            'other/package' => $otherLicense
        ]));

        $this->httpClient->method('request')->willThrowException(
            new LogicException(['fallback' => 'Unauthorized', 'key' => '401', 'translate' => false])
        );

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->httpClient]);

        $this->assertSame('inactive', $licenses->getStatus());
        $this->assertSame(
            ['other/package' => $otherLicense],
            json_decode(file_get_contents(self::LICENSE_FILE_PATH), true)
        );
    }

    public static function refreshFailuresOtherThan401(): array
    {
        return [
            'a 500' => [new LogicException(['fallback' => 'Internal Server Error', 'key' => '500', 'translate' => false])],
            'a network failure' => [new Exception('Could not resolve host: repo.kirby.tools', 6)]
        ];
    }

    #[Test]
    #[DataProvider('refreshFailuresOtherThan401')]
    public function read_keeps_the_stored_license_on_a_failure_other_than_401(Throwable $failure): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.1.0'], version: '1.1.0');

        $storedLicenses = ['test/licensed' => self::LICENSE_STORED_FOR_PLUGIN_1_0_0];
        file_put_contents(self::LICENSE_FILE_PATH, json_encode($storedLicenses));

        $this->httpClient->method('request')->willThrowException($failure);

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->httpClient]);

        $this->assertSame('active', $licenses->getStatus());
        $this->assertSame(
            $storedLicenses,
            json_decode(file_get_contents(self::LICENSE_FILE_PATH), true)
        );
    }

    #[Test]
    public function read_retries_the_refresh_after_a_failure(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.1.0'], version: '1.1.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => self::LICENSE_STORED_FOR_PLUGIN_1_0_0
        ]));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willThrowException(new Exception('Could not resolve host: repo.kirby.tools', 6));

        Licenses::read('test/licensed', ['httpClient' => $httpClient]);
        Licenses::read('test/licensed', ['httpClient' => $httpClient]);
    }

    #[Test]
    public function get_status_returns_inactive_when_no_license_file_exists(): void
    {
        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);

        $this->assertInstanceOf(Licenses::class, $licenses);
        $this->assertEquals('inactive', $licenses->getStatus());
        $this->assertNull($licenses->getLicense());
    }

    #[Test]
    public function get_status_returns_active_for_a_valid_compatible_license(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.0.0'], version: '1.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '1.0.0',
            ]
        ]));

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->httpClient]);
        $this->assertSame('active', $licenses->getStatus());
    }

    #[Test]
    public function get_status_returns_inactive_for_a_package_missing_from_the_license_file(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'other/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);
        $this->assertEquals('inactive', $licenses->getStatus());
    }

    #[Test]
    public function get_status_returns_invalid_for_a_malformed_key(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);
        $this->assertEquals('invalid', $licenses->getStatus());
    }

    #[Test]
    public function get_status_returns_incompatible_when_the_license_does_not_cover_the_plugin_version(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '1.0.0'], version: '1.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^2.0.0',
                'pluginVersion' => '1.0.0',
            ]
        ]));

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->httpClient]);
        $this->assertSame('incompatible', $licenses->getStatus());
    }

    #[Test]
    public function get_status_returns_upgradeable_when_the_plugin_outgrew_the_license(): void
    {
        App::plugin(name: 'test/licensed', extends: [], info: ['version' => '2.0.0'], version: '2.0.0');

        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/licensed' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => '2.0.0',
            ]
        ]));

        $licenses = Licenses::read('test/licensed', ['httpClient' => $this->httpClient]);
        $this->assertSame('upgradeable', $licenses->getStatus());
    }

    #[Test]
    public function get_license_returns_key_and_compatibility_constraint_for_a_valid_license(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => null, // Null prevents the refresh.
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);
        $license = $licenses->getLicense();

        $this->assertIsArray($license);
        $this->assertSame(['key', 'compatibilityConstraint'], array_keys($license));
    }

    #[Test]
    public function get_license_returns_null_for_a_malformed_key(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);
        $this->assertNull($licenses->getLicense());
    }

    #[Test]
    public function get_read_error_returns_null_when_no_license_file_exists(): void
    {
        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);

        $this->assertNull($licenses->getReadError());
    }

    #[Test]
    public function get_read_error_returns_the_failure_when_the_license_file_cannot_be_parsed(): void
    {
        file_put_contents(self::LICENSE_FILE_PATH, '{ this is not json');

        $licenses = Licenses::read('test/package', ['httpClient' => $this->httpClient]);

        $this->assertNotNull($licenses->getReadError());
        $this->assertSame('inactive', $licenses->getStatus());
    }
}
