<?php

declare(strict_types = 1);

use JohannSchopplich\Licensing\Http\HttpClientInterface;
use JohannSchopplich\Licensing\Licenses;
use JohannSchopplich\Licensing\LicenseStatus;
use Kirby\Cms\App;
use Kirby\Exception\LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LicenseActivationTest extends TestCase
{
    private const LICENSE_FILE = __DIR__ . '/' . Licenses::LICENSE_FILE;

    private App $kirby;
    private HttpClientInterface&MockObject $mockHttpClient;

    protected function setUp(): void
    {
        // Initialize Kirby application with custom license file path
        $this->kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'license' => __DIR__ . '/.license'
            ]
        ]);

        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
    }

    protected function tearDown(): void
    {
        if (file_exists(static::LICENSE_FILE)) {
            unlink(static::LICENSE_FILE);
        }

        App::destroy();
    }

    public function testActivateSuccessfully(): void
    {
        // Register a test plugin that will be found by the license system
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Mock successful HTTP response
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient
        );
        $licenses->activate('test@example.com', '123456');

        $this->assertEquals('KT1-ABC123-DEF456', $licenses->getLicenseKey());
        $this->assertEquals('^1.0.0', $licenses->getLicenseCompatibility());
        $this->assertEquals(LicenseStatus::ACTIVE, $licenses->getStatus());
    }

    public function testActivateWithWrongPackageName(): void
    {
        // Register a test plugin
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Set up API response with wrong package name
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'wrong/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateWithIncompatibleVersion(): void
    {
        // Register a plugin with version 2.0.0 that's incompatible with license ^1.0.0
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '2.0.0'],
            version: '2.0.0'
        );

        // Set up API response with incompatible license version
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0', // Only supports 1.x
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin version, please upgrade your license');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateWithApiError(): void
    {
        // Register a test plugin
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Mock API error response
        $this->mockHttpClient->method('request')->willReturn([
            'error' => 'License not found'
        ]);

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('License key not valid for this plugin');

        $licenses->activate('test@example.com', '123456');
    }

    public function testActivateFromRequestWithValidData(): void
    {
        // Register a test plugin
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Mock successful HTTP response
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient
        );

        // Test activate method directly with email and orderId
        $licenses->activate('test@example.com', '123456');

        $this->assertEquals('KT1-ABC123-DEF456', $licenses->getLicenseKey());
        $this->assertEquals('^1.0.0', $licenses->getLicenseCompatibility());
        $this->assertEquals(LicenseStatus::ACTIVE, $licenses->getStatus());
    }

    public function testActivationUpdatesLicenseFile(): void
    {
        // Register a test plugin
        App::plugin(
            name: 'simple/package',
            extends: [],
            info: ['version' => '1.0.0'],
            version: '1.0.0'
        );

        // Mock successful HTTP response
        $this->mockHttpClient->method('request')->willReturn([
            'packageName' => 'simple/package',
            'licenseKey' => 'KT1-ABC123-DEF456',
            'licenseCompatibility' => '^1.0.0',
            'order' => [
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        ]);

        $licenses = new Licenses(
            licenses: [],
            packageName: 'simple/package',
            httpClient: $this->mockHttpClient
        );
        $licenses->activate('test@example.com', '123456');

        // Verify license file was created
        $this->assertFileExists(static::LICENSE_FILE);

        // Verify license data was saved correctly
        $savedData = json_decode(file_get_contents(static::LICENSE_FILE), true);
        $this->assertArrayHasKey('simple/package', $savedData);
        $this->assertEquals('KT1-ABC123-DEF456', $savedData['simple/package']['licenseKey']);
        $this->assertEquals('^1.0.0', $savedData['simple/package']['licenseCompatibility']);
    }
}
