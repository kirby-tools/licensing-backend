<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\LicenseRepository;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

class LicensesTest extends TestCase
{
    public const LICENSE_FILE_PATH = __DIR__ . '/' . LicenseRepository::LICENSE_FILE;

    private App $kirby;
    private HttpClientInterface $mockHttpClient;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ]
        ]);

        $mockPlugin = $this->createMock(\Kirby\Plugin\Plugin::class);
        $mockPlugin->method('version')->willReturn('1.0.0');

        $this->kirby->extend([
            'plugins' => [
                'test/package' => $mockPlugin
            ]
        ]);

        // Create a mock HTTP client that returns empty responses
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
    }

    protected function tearDown(): void
    {
        if (file_exists(static::LICENSE_FILE_PATH)) {
            unlink(static::LICENSE_FILE_PATH);
        }

        App::destroy();
    }

    public function testReadWithNoLicenseFile(): void
    {
        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);

        $this->assertInstanceOf(Licenses::class, $licenses);
        $this->assertEquals('inactive', $licenses->getStatus());
        $this->assertNull($licenses->getLicense());
    }

    public function testGetStatusInactive(): void
    {
        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $this->assertEquals('inactive', $licenses->getStatus());
    }

    public function testGetStatusInvalid(): void
    {
        file_put_contents(static::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $this->assertEquals('invalid', $licenses->getStatus());
    }

    public function testGetLicenseReturnsArrayWithValidKey(): void
    {
        file_put_contents(static::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'KT1-ABC123-DEF456',
                'licenseCompatibility' => '^1.0.0',
                'pluginVersion' => null, // Use null to prevent refresh
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

    public function testGetLicenseReturnsNullWithInvalidKey(): void
    {
        file_put_contents(static::LICENSE_FILE_PATH, json_encode([
            'test/package' => [
                'licenseKey' => 'INVALID-KEY',
                'licenseCompatibility' => '^1.0.0'
            ]
        ]));

        $licenses = Licenses::read('test/package', ['httpClient' => $this->mockHttpClient]);
        $this->assertNull($licenses->getLicense());
    }
}
